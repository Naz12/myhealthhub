/* Mia's OpenAI voice adapter: WebRTC audio, Live events, website-owned tools. */
(function () {
    'use strict';
    if (!window.miaConfig || window.miaConfig.aiProvider !== 'openai') return;
    const Mia = window.MiaHero, Chat = Mia.Chat, config = window.miaConfig;
    const el = id => document.getElementById(id);
    let live = null, epoch = 0, textController = null, textVersion = 0;
    let outputMuted = false, micMuted = false, initialized = false;
    const trace = [];
    function record(type, detail = {}) {
        trace.push({ type, at: performance.now(), ...detail });
        if (trace.length > 300) trace.shift();
    }
    function persist() { Chat.saveSessionContext(); Chat.saveConversationHistory(); }
    function panel() {
        const source = el('mia-hero-messages'), target = el('mia-live-conversation');
        if (!source || !target) return;
        target.replaceChildren(...Array.from(source.querySelectorAll('.mia-msg')).slice(-6).map(node => {
            const clone = node.cloneNode(true);
            clone.querySelectorAll('[id]').forEach(child => child.removeAttribute('id'));
            return clone;
        }));
        target.classList.remove('empty'); target.scrollTop = target.scrollHeight;
    }
    function overlay(show) {
        const node = el('mia-live-voice-overlay');
        if (node) { node.classList.toggle('active', show); node.setAttribute('aria-hidden', String(!show)); }
        document.body.style.overflow = show ? 'hidden' : '';
        panel();
    }
    function state(kind, label, detail = '') {
        for (const id of ['mia-live-main', 'mia-live-visual']) {
            const node = el(id); if (!node) continue;
            ['idle','listening','processing','speaking','ready','error'].forEach(value => node.classList.remove('voice-' + value));
            node.classList.add('voice-' + kind);
        }
        if (el('mia-live-status')) el('mia-live-status').textContent = label;
        if (el('mia-live-substatus')) el('mia-live-substatus').textContent = detail;
        const button = el('mia-hero-voice');
        if (button) { button.dataset.state = live ? kind : 'idle'; button.setAttribute('aria-pressed', String(!!live)); }
    }
    function buttonLabel(id, text, pressed) {
        const button = el(id); if (!button) return;
        const label = button.querySelector('[data-mia-control-label]'); if (label) label.textContent = text;
        button.setAttribute('aria-pressed', String(pressed)); button.classList.toggle('is-active', pressed);
    }
    function controls() {
        buttonLabel('mia-live-mute', outputMuted ? 'Unmute voice' : 'Mute voice', outputMuted);
        buttonLabel('mia-live-mic-mute', micMuted ? 'Unmute mic' : 'Mute mic', micMuted);
    }
    function send(session, type, fields = {}) {
        if (!session || session.channel?.readyState !== 'open') return false;
        session.channel.send(JSON.stringify({ type, event_id: 'mia_' + (++session.sequence), ...fields }));
        record('sent', { eventType: type, delegationId: fields.delegation_id || null });
        return true;
    }
    async function post(action, fields, signal, timeout = 25000) {
        const controller = new AbortController();
        const abort = () => controller.abort();
        if (signal?.aborted) abort(); else signal?.addEventListener('abort', abort, { once: true });
        const timer = setTimeout(abort, timeout);
        try {
            const response = await fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin',
                body: new URLSearchParams({ action, nonce: config.nonce, ...fields }), signal: controller.signal });
            const result = await response.json();
            if (!response.ok || !result.success || !result.data) throw new Error(result.data?.message || 'Mia could not connect. Please try again.');
            return result.data;
        } finally { clearTimeout(timer); signal?.removeEventListener('abort', abort); }
    }
    function applyResult(data, reply, showMessage = true) {
        if (data.prior_context) Mia.priorContext = Object.assign({}, Mia.priorContext, data.prior_context);
        if (data.prior_context?.language) Mia.detectedLanguage = data.prior_context.language;
        if (data.provider_exposure) Mia.latestProviderExposure = data.provider_exposure;
        const providers = Array.isArray(data.providers) ? data.providers : [];
        let answer = null;
        if (showMessage && reply) { Chat.addHeroMessage(Chat.formatMiaResponse ? Chat.formatMiaResponse(reply) : reply, false); answer = { role: 'assistant', content: reply }; Mia.conversationHistory.push(answer); }
        if (providers.length) {
            // Cards and backend text stay together; Live captions are separate.
            const messages = el('mia-hero-messages');
            const bubble = messages?.querySelector('.mia-msg--assistant:last-child');
            Chat.appendHeroProviderCards(bubble, providers);
        }
        persist(); panel();
        return answer;
    }
    function fragmentsText(session, from, through = Infinity) {
        return session.fragments.filter(item => item.role === 'user' && item.end_ms > from && item.start_ms <= through)
            .sort((a, b) => a.start_ms - b.start_ms || a.order - b.order).map(item => item.delta).join('').trim();
    }
    function transcript(session, event, role) {
        if (typeof event.delta !== 'string' || !Number.isFinite(event.start_ms) || !Number.isFinite(event.end_ms)) return;
        if (event.event_id && session.seen.has(event.event_id)) return;
        if (event.event_id) session.seen.add(event.event_id);
        const fragment = { role, delta: event.delta, start_ms: event.start_ms, end_ms: event.end_ms, order: session.fragments.length };
        session.fragments.push(fragment);
        if (session.fragments.length > 1200) session.fragments.splice(0, 200);
        const answer = role === 'assistant' && session.answers?.filter(item => fragment.start_ms >= item.inputEnd
            && !session.fragments.some(other => other.role === 'user' && other.end_ms > item.inputEnd && other.start_ms <= fragment.start_ms)).at(-1);
        let group = session.groups.filter(item => (!answer || (item.linkedAnswer && item.history === answer.history)) && item.role === role && fragment.start_ms <= item.end + 1100 && fragment.end_ms >= item.start - 1100
            && !session.fragments.some(other => other.role !== role && ((other.start_ms >= item.end && other.end_ms <= fragment.start_ms) || (other.start_ms >= fragment.end_ms && other.end_ms <= item.start))))
            .sort((a,b) => Math.max(0, a.start-fragment.end_ms, fragment.start_ms-a.end)
                - Math.max(0, b.start-fragment.end_ms, fragment.start_ms-b.end))[0];
        if (!group) {
            let history, node;
            if (answer) { history = answer.history; node = answer.node; }
            else {
                Chat.addHeroMessage('', role === 'user');
                history = { role, content: '' }; node = el('mia-hero-messages')?.lastElementChild;
                const later = session.groups.find(item => item.start > fragment.end_ms);
                if (later) { Mia.conversationHistory.splice(Mia.conversationHistory.indexOf(later.history), 0, history); later.node?.before(node); }
                else Mia.conversationHistory.push(history);
            }
            group = { role, start: fragment.start_ms, end: fragment.end_ms, parts: [], history, node, linkedAnswer: !!answer };
            session.groups.push(group); session.groups.sort((a,b) => a.start-b.start);
        }
        group.parts.push(fragment); group.start = Math.min(group.start, fragment.start_ms); group.end = Math.max(group.end, fragment.end_ms);
        const text = group.parts.sort((a,b) => a.start_ms - b.start_ms || a.order - b.order).map(item => item.delta).join('');
        if (group.linkedAnswer) group.history.spoken_content = session.groups
            .filter(item => item.linkedAnswer && item.history === group.history).flatMap(item => item.parts)
            .sort((a,b) => a.start_ms - b.start_ms || a.order - b.order).map(item => item.delta).join('');
        else group.history.content = text;
        const content = group.node?.querySelector(role === 'user' ? 'p' : '.mia-response-content');
        if (content && !group.linkedAnswer) {
            if (role === 'assistant' && Chat.formatMiaResponse) content.innerHTML = Chat.formatMiaResponse(text);
            else content.textContent = text;
        }
        if (role === 'user') {
            session.lastInputAt = performance.now();
            clearTimeout(session.lookupWatchdog);
            session.lookupWatchdog = setTimeout(() => {
                if (live !== session || session.closing || !session.ready || (session.task && !session.task.done)) return;
                const pending = fragmentsText(session, session.committedInput);
                // Recover an acknowledged website request if Live omits its delegation event.
                if (!pending || (/^(?:hi|hello|hey|thanks|thank you|hola|gracias)[.! ]*$/iu.test(pending) && !Mia.priorContext?.pending_question?.type)) return;
                record('delegation_recovered');
                delegate(session, { delegation: { target: 'client' }, offset_ms: fragment.end_ms }, true)
                    .catch(() => state('error', 'Could not check this request', 'Please try again.'));
            }, 3500);
            Chat.rememberHeroConversationLanguage(text);
            if (session.task?.controller && fragment.end_ms > session.task.inputEnd + 250) session.task.controller.abort();
            if (el('mia-live-transcript')) el('mia-live-transcript').textContent = text;
            state('listening', micMuted ? 'Mic muted' : 'Listening', 'You can interrupt Mia naturally.');
        } else {
            if (el('mia-live-caption')) el('mia-live-caption').textContent = text;
            state('speaking', outputMuted ? 'Voice muted' : 'Mia is speaking', 'You can interrupt anytime.');
        }
        record('transcript', { role, delta: event.delta, startMs: event.start_ms, endMs: event.end_ms });
        persist(); panel();
    }
    function appendComplete(session, type, id, content) {
        const encoder = new TextEncoder(); let chunk = '';
        const flush = () => { if (chunk.trim()) send(session, type, { delegation_id: id, content: chunk }); chunk = ''; };
        for (const sentence of String(content).match(/[^.!?\n]+[.!?\n]*|[.!?\n]+/gu) || []) {
            if (encoder.encode(chunk + sentence).length > 480) flush();
            for (const char of sentence) { if (encoder.encode(chunk + char).length > 480) flush(); chunk += char; }
        }
        flush();
    }
    function providerFollowupHint(message) {
        return Mia.priorContext?.recent_providers?.length
            && /\b(?:the\s+(?:first|second|third|fourth)(?:['’]s)?\s+(?:provider|one|option)|their\s+(?:profile|insurance|address)|this\s+provider|that\s+one)\b/iu.test(message)
            ? 'provider_followup' : '';
    }
    async function delegate(session, event, recovered = false) {
        const id = recovered ? null : event.delegation?.id;
        if (!recovered && (!id || event.delegation.target !== 'client' || session.delegations.has(id))) return;
        clearTimeout(session.lookupWatchdog);
        if (id) session.delegations.add(id);
        session.task?.controller?.abort();
        const task = { id, controller: null, inputEnd: Number(event.offset_ms) || 0 };
        session.task = task;
        record('delegation', { delegationId: id, offsetMs: event.offset_ms });
        for (let attempt = 0; attempt < 3 && live === session && !session.closing && session.task === task; attempt++) {
            // Delegation has no utterance. Wait briefly for late transcript fragments.
            const deadline = performance.now() + 1600;
            while (performance.now() < deadline && (!fragmentsText(session, session.committedInput) || performance.now() - session.lastInputAt < 220)) {
                await new Promise(resolve => setTimeout(resolve, 80));
                if (live !== session || session.closing || session.task !== task) return;
            }
            const message = fragmentsText(session, session.committedInput);
            if (!message) { task.done = true; send(session, 'session.commentary.append', { delegation_id: id, content: 'I could not reliably read that request. Could you repeat what you need?' }); return; }
            task.inputEnd = Math.max(...session.fragments.filter(item => item.role === 'user').map(item => item.end_ms));
            task.controller = new AbortController();
            state('processing', 'Checking your request', 'Mia is checking the website’s provider information.');
            try {
                const data = await post('mia_realtime_tool', { message, history: JSON.stringify(Mia.conversationHistory.slice(-20)),
                    prior_context: JSON.stringify(Mia.priorContext || {}), route_hint: providerFollowupHint(message), transcript_candidates: JSON.stringify({ canonical_text: message, realtime_transcript: message }) }, task.controller.signal);
                if (live !== session || session.closing || session.task !== task || task.controller.signal.aborted
                    || message !== fragmentsText(session, session.committedInput)) { record('stale_result_discarded', { delegationId: id }); continue; }
                const reply = String(data.conversational_speech || data.assistant_reply || '').trim();
                if (!reply) throw new Error('The website returned no answer. Please try again.');
                const answer = applyResult(data, String(data.assistant_reply || reply));
                if (answer) session.answers.push({ history: answer, node: el('mia-hero-messages')?.lastElementChild, inputEnd: task.inputEnd });
                session.committedInput = task.inputEnd;
                // Keep each Live context update comfortably below its 500-token limit.
                const names = (data.providers || []).slice(0,4).map(item => item.name);
                const facts = JSON.stringify({ verified_provider_names: names, displayed_count: names.length,
                    required_facts: data.required_facts || [], selected_provider_id: data.prior_context?.last_provider_focus || null,
                    pending_question: data.prior_context?.pending_question || null });
                appendComplete(session, 'session.thinking.append', id, facts);
                appendComplete(session, 'session.commentary.append', id, reply);
                record('lookup_complete', { delegationId: id, intent: data.intent, source: data.understanding?.source,
                    providerNames: names, reply, performance: data.performance });
                task.done = true;
                state('ready', 'Ready', 'Keep talking whenever you’re ready.'); return;
            } catch (error) {
                if (live !== session || session.closing || session.task !== task) return;
                if (task.controller.signal.aborted) { record('lookup_interrupted', { delegationId: id }); continue; }
                task.done = true; session.committedInput = Math.max(session.committedInput, task.inputEnd);
                record('lookup_failed', { message: error.message });
                send(session, 'session.commentary.append', { delegation_id: id, content: 'The website lookup failed. Tell the user the facts could not be verified and offer to retry or use the directory.' });
                state('error', 'Could not check the directory', 'You can retry or continue in text.'); return;
            }
        }
        task.done = true;
    }
    function cleanup(session) {
        clearTimeout(session.startTimer); clearTimeout(session.closeTimer); clearTimeout(session.lookupWatchdog);
        clearInterval(session.visualTimer); session.visualContext?.close().catch(() => {});
        session.startController.abort(); session.task?.controller?.abort();
        session.stream?.getTracks().forEach(track => track.stop());
        session.audio.pause(); session.audio.srcObject = null; session.audio.remove();
        session.channel?.close(); session.peer?.close();
        if (live === session) live = null;
        session.resolveClosed?.(); controls();
    }
    function handle(session, event) {
        record('received', { eventType: event.type });
        if (event.type === 'session.closed') {
            record('session_closed', { confirmed: true }); session.closed = true; cleanup(session); return;
        }
        if (session.closing || live !== session) return;
        if (event.type === 'session.started') {
            session.ready = true; clearTimeout(session.startTimer);
            state('listening', 'Listening', 'Mia is an AI voice. Speak naturally and interrupt anytime.');
            const returning = Mia.conversationHistory.some(item => item.role === 'user');
            if (returning) {
                appendComplete(session, 'session.thinking.append', null, JSON.stringify({ current_goal: Mia.priorContext?.navigation_state?.goal,
                    city: Mia.priorContext?.city, selected_provider_id: Mia.priorContext?.last_provider_focus,
                    pending_question: Mia.priorContext?.pending_question }));
                appendComplete(session, 'session.commentary.append', null, Mia.priorContext?.pending_question?.text ||
                    (Mia.priorContext?.language === 'es' ? 'Seguimos donde lo dejamos. ¿Qué te gustaría hacer ahora?' : 'We can continue where we left off. What would you like to do next?'));
            } else send(session, 'session.commentary.append', { delegation_id: null,
                content: 'I am Mia, your AI wellness concierge. What kind of support are you looking for?' });
        } else if (event.type === 'session.input_transcript.delta') transcript(session, event, 'user');
        else if (event.type === 'session.output_transcript.delta') transcript(session, event, 'assistant');
        else if (event.type === 'session.delegation.created') delegate(session, event).catch(() => state('error', 'Could not check this request', 'Please try again.'));
        else if (event.type === 'error') {
            record('live_error', { code: event.error?.code || '' });
            state('error', 'Voice needs attention', 'You can end voice and continue in text, or retry.');
        }
    }
    async function start() {
        if (live) return;
        if (!navigator.mediaDevices?.getUserMedia || !window.RTCPeerConnection) {
            Chat.addHeroMessage('Voice is unavailable in this browser. You can still type to Mia.', false); return;
        }
        textController?.abort(); textVersion++; Chat.setHeroTyping(false);
        const session = { epoch: ++epoch, sequence: 0, ready: false, closing: false, fragments: [], groups: [],
            seen: new Set(), delegations: new Set(), answers: [], committedInput: -1, lastInputAt: 0, task: null,
            audio: document.createElement('audio'), startController: new AbortController() };
        live = session; session.audio.autoplay = true; session.audio.setAttribute('playsinline', '');
        session.audio.muted = outputMuted; document.body.appendChild(session.audio);
        overlay(true); state('processing', 'Connecting Mia', 'Allow microphone access to start talking.');
        session.startTimer = setTimeout(() => { if (!session.ready && live === session) { state('error', 'Voice connection took too long', 'Please retry or type your message.'); end(false); } }, 30000);
        try {
            // Audio permission is requested only after the visitor presses Talk.
            const stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } });
            if (live !== session || session.closing) { stream.getTracks().forEach(track => track.stop()); return; }
            session.stream = stream; session.peer = new RTCPeerConnection();
            stream.getAudioTracks().forEach(track => { track.enabled = !micMuted; session.peer.addTrack(track, stream); });
            session.peer.ontrack = event => {
                const remoteStream = event.streams[0] || new MediaStream([event.track]);
                session.audio.srcObject = remoteStream;
                session.audio.play().catch(() => {
                    outputMuted = true; session.audio.muted = true; controls();
                    state('ready', 'Tap Unmute voice to hear Mia', 'Your browser paused audio playback.');
                });
                // Use actual received audio for the speaking indicator; captions can arrive late.
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (AudioCtx) {
                    session.visualContext = new AudioCtx(); session.visualContext.resume().catch(() => {});
                    const source = session.visualContext.createMediaStreamSource(remoteStream);
                    const analyser = session.visualContext.createAnalyser(); analyser.fftSize = 512; source.connect(analyser);
                    const samples = new Float32Array(analyser.fftSize); let lastSpeech = performance.now(), active = false;
                    session.visualTimer = setInterval(() => {
                        if (live !== session || session.closing || !session.ready) return;
                        analyser.getFloatTimeDomainData(samples);
                        const level = Math.sqrt(samples.reduce((sum, value) => sum + value * value, 0) / samples.length);
                        document.querySelector('.mia-live-shell')?.style.setProperty('--mia-live-energy', String(Math.min(1, level * 8)));
                        if (level > .012) { lastSpeech = performance.now(); active = true; }
                        else if (active && performance.now() - lastSpeech > 1200 && (!session.task || session.task.done)) {
                            active = false;
                            state('listening', micMuted ? 'Mic muted' : 'Listening', 'Keep talking whenever you’re ready.');
                        }
                    }, 80);
                }
            };
            session.peer.onconnectionstatechange = () => {
                if (session.peer.connectionState === 'failed' && live === session && !session.closing) {
                    state('error', 'Voice connection interrupted', 'Please retry or continue in text.'); end(false);
                }
            };
            session.channel = session.peer.createDataChannel('oai-events');
            session.channel.onmessage = ({ data }) => { try { handle(session, JSON.parse(data)); } catch (_) { record('invalid_event'); } };
            session.channel.onclose = () => { if (!session.closed && !session.closing && live === session) {
                record('connection_lost'); cleanup(session); state('error', 'Voice disconnected', 'Press Talk to reconnect.');
            } };
            const offer = await session.peer.createOffer(); await session.peer.setLocalDescription(offer);
            const answer = await post('mia_realtime_session', { sdp: offer.sdp, history: JSON.stringify(Mia.conversationHistory.slice(-16)) }, session.startController.signal);
            if (live !== session || session.closing) return;
            if (answer.provider !== 'openai' || answer.transport?.type !== 'webrtc') throw new Error('Voice settings changed. Refresh the page and try again.');
            session.id = answer.session.id;
            await session.peer.setRemoteDescription({ type: 'answer', sdp: answer.transport.sdp });
            record('connected', { model: answer.model });
        } catch (error) {
            if (live !== session || session.closing) return;
            state('error', error.name === 'NotAllowedError' ? 'Microphone access is needed' : 'Could not start voice', 'You can type your message or try voice again.');
            Chat.addHeroMessage(error.name === 'NotAllowedError' ? 'Microphone access was declined. You can still type to Mia.' : error.message, false);
            cleanup(session);
        }
    }
    async function end(hide = true) {
        const session = live;
        if (hide) overlay(false);
        if (!session || session.closing) return session?.closingPromise;
        session.closing = true; session.task?.controller?.abort();
        session.stream?.getTracks().forEach(track => track.stop()); session.audio.muted = true;
        session.closingPromise = new Promise(resolve => { session.resolveClosed = resolve; });
        if (session.channel?.readyState === 'open') {
            send(session, 'session.close');
            session.closeTimer = setTimeout(() => { record('session_closed', { confirmed: false }); cleanup(session); }, 15000);
        } else cleanup(session);
        state('idle', 'Mia is ready', 'Press Talk to start a new voice session.');
        return session.closingPromise;
    }
    async function chat(message) {
        message = String(message ?? el('mia-hero-input')?.value ?? '').trim(); if (!message) return;
        // Avoid competing website text and live voice turns. Restore text history on the next voice session.
        if (live) await end();
        textController?.abort(); const version = ++textVersion; const controller = new AbortController(); textController = controller;
        if (el('mia-hero-input')) el('mia-hero-input').value = '';
        Chat.addHeroMessage(message, true); Chat.rememberHeroConversationLanguage(message);
        Mia.conversationHistory.push({ role: 'user', content: message }); Chat.setHeroTyping(true);
        try {
            const data = await post('mia_chat', { message, history: JSON.stringify(Mia.conversationHistory.slice(-20)),
                prior_context: JSON.stringify(Mia.priorContext || {}) }, controller.signal, 45000);
            if (version !== textVersion || controller.signal.aborted) return;
            applyResult(data, String(data.message || 'Please try your request again.'));
        } catch (error) {
            if (version === textVersion && !controller.signal.aborted) Chat.addHeroMessage(error.message, false);
        } finally { if (version === textVersion) { Chat.setHeroTyping(false); textController = null; } }
    }
    var triangleCities = [
        { name: 'Raleigh',        lat: 35.7796, lng: -78.6382 },
        { name: 'Durham',         lat: 35.9940, lng: -78.8986 },
        { name: 'Chapel Hill',    lat: 35.9132, lng: -79.0558 },
        { name: 'Cary',           lat: 35.7915, lng: -78.7811 },
        { name: 'Apex',           lat: 35.7327, lng: -78.8503 },
        { name: 'Morrisville',    lat: 35.8235, lng: -78.8256 },
        { name: 'Holly Springs',  lat: 35.6513, lng: -78.8336 },
        { name: 'Fuquay-Varina',  lat: 35.5843, lng: -78.8000 },
        { name: 'Wake Forest',    lat: 35.9799, lng: -78.5097 },
        { name: 'Garner',         lat: 35.7113, lng: -78.6142 },
        { name: 'Knightdale',     lat: 35.7968, lng: -78.4897 },
        { name: 'Carrboro',       lat: 35.9101, lng: -79.0753 }
    ];

    function haversineDistance(lat1, lng1, lat2, lng2) {
        var R = 3959;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLng/2) * Math.sin(dLng/2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function nearestCity(lat, lng) {
        var MAX_DISTANCE = 250;
        var best = null, bestDist = Infinity;
        triangleCities.forEach(function(c) {
            var d = haversineDistance(lat, lng, c.lat, c.lng);
            if (d < bestDist) { bestDist = d; best = c.name; }
        });
        if (bestDist > MAX_DISTANCE) return null;
        return { city: best, miles: Math.round(bestDist) };
    }

    async function nearMe() {
        if (live) await end();
        Chat.addHeroMessage('Choose “Share my location” to find a nearby city, or type your city below.', false);
        const bubble = el('mia-hero-messages')?.querySelector('.mia-msg--assistant:last-child');
        if (!bubble) return;
        const actions = document.createElement('div');
        actions.style.cssText = 'display:flex;gap:8px;flex-wrap:wrap;margin-top:10px';
        const allow = document.createElement('button'), deny = document.createElement('button');
        allow.type = deny.type = 'button'; allow.className = deny.className = 'hero-geo-btn';
        allow.textContent = 'Share my location'; deny.textContent = "I’ll type my city";
        const typeCity = () => { actions.remove(); el('mia-hero-input')?.focus(); };
        deny.addEventListener('click', typeCity);
        allow.addEventListener('click', () => {
            allow.disabled = true; deny.disabled = true; allow.textContent = 'Finding your city…';
            if (!navigator.geolocation) { typeCity(); return; }
            navigator.geolocation.getCurrentPosition(position => {
                // Resolve the closest supported city locally; send only the city to the website.
                const result = nearestCity(position.coords.latitude, position.coords.longitude);
                actions.remove();
                if (result) chat('Show me providers near me in ' + result.city);
                else { Chat.addHeroMessage('Please type a city in the Raleigh-Durham Triangle area.', false); el('mia-hero-input')?.focus(); }
            }, () => {
                actions.remove(); Chat.addHeroMessage('Please type your city so I can continue the search.', false); el('mia-hero-input')?.focus();
            }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 });
        });
        actions.append(allow, deny); bubble.appendChild(actions);
    }
    async function newChat() {
        textController?.abort(); textVersion++;
        await end();
        Mia.conversationHistory = []; Mia.priorContext = {}; Mia.detectedLanguage = ''; Chat.clearSharedMiaStorage();
        if (el('mia-hero-messages')) el('mia-hero-messages').innerHTML = Chat.getHeroGreetingHtml();
        Chat.setHeroTyping(false); panel(); el('mia-hero-input')?.focus();
    }
    function muteMic() {
        micMuted = !micMuted; live?.stream?.getAudioTracks().forEach(track => { track.enabled = !micMuted; });
        if (live?.ready) send(live, micMuted ? 'session.input_audio.mute' : 'session.input_audio.unmute');
        controls(); state('listening', micMuted ? 'Mic muted' : 'Listening', micMuted ? 'Mia will not hear you until you unmute.' : 'You can interrupt anytime.');
    }
    function muteOutput() { outputMuted = !outputMuted; if (live) { live.audio.muted = outputMuted; if (!outputMuted) live.audio.play().catch(() => {}); } controls(); }
    function init() {
        if (initialized) return;
        initialized = true;
        record('initialized');
        if (Mia.conversationHistory.length && el('mia-hero-messages')) {
            el('mia-hero-messages').replaceChildren();
            Mia.conversationHistory.slice(-20).forEach(item => {
                if (!['user','assistant'].includes(item.role) || typeof item.content !== 'string') return;
                Chat.addHeroMessage(item.role === 'assistant' && Chat.formatMiaResponse ? Chat.formatMiaResponse(item.content) : item.content, item.role === 'user');
            });
            panel();
        }
        const on = (id, fn) => el(id)?.addEventListener('click', fn);
        on('mia-hero-voice', () => live ? end() : start()); on('mia-hero-send', () => chat());
        on('mia-live-close-btn', () => end()); on('mia-live-end', () => end());
        on('mia-live-mute', muteOutput); on('mia-live-mic-mute', muteMic);
        on('mia-hero-new-chat', newChat); on('mia-live-new-chat', newChat);
        el('mia-hero-input')?.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); chat(); } });
        document.querySelectorAll('.mia-chip[data-prompt]').forEach(chip => chip.addEventListener('click', () => chat(chip.dataset.prompt)));
        document.querySelectorAll('.mia-chip[data-action="near-me"]').forEach(chip => chip.addEventListener('click', nearMe));
        document.addEventListener('keydown', event => { if (event.key === 'Escape' && live) end(); });
        document.addEventListener('visibilitychange', () => { if (document.hidden && live) end(); });
        window.addEventListener('pagehide', () => { if (live) { send(live, 'session.close'); cleanup(live); } });
        document.querySelectorAll('.mia-live-debug').forEach(node => { node.style.display = 'none'; });
        controls();
    }
    Mia.Voice = {
        toggleHeroVoice: () => live ? end() : start(), startHeroVoiceSession: start, exitHeroVoiceMode: end,
        sendHeroChatMessage: chat, heroQuickStart: chat, heroNearMe: nearMe, startNewHeroChat: newChat,
        isVoiceMode: () => !!live && !live.closing, syncLiveConversationPanel: panel,
        getRuntimeFailureLog: () => trace.slice(), getOpenAILiveState: () => ({ ready: !!live?.ready,
            closing: !!live?.closing, model: 'gpt-live-1', micMuted, outputMuted }),
    };
    // Cache optimizers can replay scripts with a synthetic loading readyState.
    // Footer controls already exist in that case, so bind immediately.
    if (el('mia-hero-voice') || document.readyState !== 'loading') init();
    else document.addEventListener('DOMContentLoaded', init);
})();
