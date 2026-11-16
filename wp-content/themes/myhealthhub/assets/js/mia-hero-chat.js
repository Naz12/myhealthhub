/* MyHealthHub - Mia Hero Chat Logic - Extracted from front-page.php */

window.MiaHero = window.MiaHero || {};

(function() {
    'use strict';

    // ── Shared state (exposed on MiaHero for cross-file access) ──
    MiaHero.conversationHistory = MiaHero.conversationHistory || [];
    MiaHero.priorContext        = MiaHero.priorContext        || {};
    MiaHero.detectedLanguage    = MiaHero.detectedLanguage    || '';

    // ── LocalStorage keys ──
    var MIA_STORAGE_KEY         = 'mia_session_context';
    var MIA_STORAGE_HISTORY_KEY = 'mia_conversation_history';

    // ── Typewriter state ──
    var heroTypewriterTarget = null;
    var heroTypewriterInterval = null;

    // ── XSS-safe HTML escape — CRITICAL SECURITY FUNCTION ──
    // Must be called BEFORE any markdown processing.
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Check if URL is safe (http/https only, no javascript: or data:)
    function isSafeUrl(url) {
        try {
            var parsed = new URL(url, window.location.origin);
            return parsed.protocol === 'http:' || parsed.protocol === 'https:';
        } catch (e) {
            return false;
        }
    }

    // Format response with XSS protection.
    // SECURITY: All HTML is escaped BEFORE markdown processing.
    function formatHeroResponse(text) {
        var escaped = window.MiaMessageLinks
            ? window.MiaMessageLinks.inline(text)
            : escapeHtml(String(text || ''));

        // STEP 3: Paragraphs
        var paragraphs = escaped.split(/\n{2,}/);
        var html = paragraphs.map(function(para) {
            para = para.trim();
            if (!para) return '';

            var lines = para.split('\n');

            // Numbered list
            var isNumberedList = lines.every(function(l) { return /^\d+[.)\s]/.test(l.trim()) || l.trim() === ''; });
            if (isNumberedList && lines.filter(function(l) { return l.trim(); }).length > 0) {
                var items = lines.filter(function(l) { return l.trim(); }).map(function(l) {
                    var content = l.replace(/^\d+[.)\s]+/, '');
                    return '<li style="margin-bottom: 6px; padding-left: 2px;">' + content + '</li>';
                }).join('');
                return '<ol style="list-style: decimal; padding-left: 20px; margin: 6px 0; text-align: left;">' + items + '</ol>';
            }

            // Bullet list
            var isBulletList = lines.every(function(l) { return /^[\u2022\-\*]\s/.test(l.trim()) || l.trim() === ''; });
            if (isBulletList && lines.filter(function(l) { return l.trim(); }).length > 0) {
                var bItems = lines.filter(function(l) { return l.trim(); }).map(function(l) {
                    var content = l.replace(/^[\u2022\-\*]\s+/, '');
                    return '<li style="margin-bottom: 4px;">' + content + '</li>';
                }).join('');
                return '<ul style="list-style: disc; padding-left: 20px; margin: 6px 0; color: #a78bfa; text-align: left;"><div style="color: rgba(255,255,255,0.92);">' + bItems + '</div></ul>';
            }

            // Bold-line list
            var isBoldList = lines.length > 1 && lines.filter(function(l) { return l.trim(); }).every(function(l) { return l.trim().startsWith('&lt;strong&gt;'); });
            if (isBoldList) {
                var blItems = lines.filter(function(l) { return l.trim(); }).map(function(l) {
                    var content = l.trim().replace(/&lt;strong&gt;/g, '<strong>').replace(/&lt;&#x2F;strong&gt;/g, '</strong>');
                    return '<li style="margin-bottom: 5px; list-style: none; padding-left: 8px; border-left: 2px solid rgba(167,139,250,0.4);">' + content + '</li>';
                }).join('');
                return '<ul style="padding: 0; margin: 8px 0; text-align: left;">' + blItems + '</ul>';
            }

            // Regular paragraph
            return '<p style="margin: 0 0 6px 0; text-align: left;">' + para.replace(/\n/g, '<br>') + '</p>';
        }).join('');

        return html;
    }

    function formatMiaResponse(text) {
        return formatHeroResponse(scrubHeroPlatformMentions(String(text || '')));
    }

    function scrubHeroPlatformMentions(text) {
        // ── Protect URLs from the scrubber ──
        // The scrubber strips "myhealthub.com" from prose so Mia doesn't say it
        // out loud, but it MUST NOT touch URLs — otherwise a markdown link like
        // [Claim](https://myhealthub.com/claim-listing/?id=1) gets mangled into
        // [Claim](https:///claim-listing/?id=1), and the browser parses that
        // bogus URL as host "claim-listing".  Mask URLs → scrub → unmask.
        var urlStash = [];
        var protectedText = String(text || '');
        // Markdown link URLs: [text](https://...)
        protectedText = protectedText.replace(/(\]\()(https?:\/\/[^)\s]+)(\))/g, function(_m, open, url, close) {
            urlStash.push(url);
            return open + 'MIAURL' + (urlStash.length - 1) + 'ENDMIAURL' + close;
        });
        // Bare URLs in text
        protectedText = protectedText.replace(/(https?:\/\/[^\s<)]+)/g, function(_m, url) {
            urlStash.push(url);
            return 'MIAURL' + (urlStash.length - 1) + 'ENDMIAURL';
        });
        var scrubbed = protectedText
            .replace(/\(?\b(?:my\s*health\s*ub|my\s*health\s*hub|myhealthub|myhealthhub)(?:\.com)?\b\)?/gi, 'the directory')
            .replace(/\bthe directory\s+the directory\b/gi, 'the directory')
            .replace(/\s+([,.;:!?])/g, '$1')
            .replace(/[^\S\r\n]{2,}/g, ' ')
            .replace(/[^\S\r\n]+\n/g, '\n')
            .replace(/\n[^\S\r\n]+/g, '\n')
            .trim();
        // Restore URLs untouched
        scrubbed = scrubbed.replace(/MIAURL(\d+)ENDMIAURL/g, function(_m, i) {
            return urlStash[parseInt(i, 10)] || '';
        });
        return scrubbed;
    }

    // ── Greeting HTML ──
    function getHeroGreetingHtml() {
        var greeting = detectHeroUiLang() === 'es'
            ? 'Hola, soy Mia, tu concierge de bienestar. Cuéntame qué tipo de apoyo estás buscando y te guiaré al proveedor adecuado.'
            : "Hi, I'm Mia. Tell me what kind of support you're looking for, and I'll guide you to the right fit.";

        return '<div class="mia-msg mia-msg--assistant" style="display: flex; gap: 12px; align-items: flex-start;">' +
            '<div style="width: 38px; height: 38px; background: linear-gradient(135deg, #6366f1 0%, #7c7cf0 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 3px 14px rgba(99, 102, 241, 0.35), 0 0 0 2px rgba(255,255,255,0.1);">' +
                '<svg width="18" height="18" fill="none" stroke="white" stroke-width="1.5" viewBox="0 0 24 24">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>' +
                '</svg>' +
            '</div>' +
            '<div style="background: rgba(255,250,245,0.12); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.15); border-radius: 18px 18px 18px 4px; padding: 16px 20px; max-width: 85%; box-shadow: 0 4px 16px rgba(0,0,0,0.1);">' +
                '<p style="margin: 0; color: rgba(255,255,255,0.92); font-size: 0.95rem; line-height: 1.7; text-align: left;">' + greeting + '</p>' +
            '</div>' +
        '</div>';
    }

    // ── Session / history persistence ──
    function loadSessionContext() {
        try {
            var stored = localStorage.getItem(MIA_STORAGE_KEY);
            if (stored) {
                var parsed = JSON.parse(stored);
                if (parsed.timestamp && (Date.now() - parsed.timestamp) < 30 * 60 * 1000) {
                    return parsed.context || {};
                }
            }
        } catch (e) {}
        return {};
    }

    function saveSessionContext() {
        try {
            var data = {
                context: MiaHero.priorContext,
                timestamp: Date.now()
            };
            localStorage.setItem(MIA_STORAGE_KEY, JSON.stringify(data));
        } catch (e) {}
    }

    function loadConversationHistory() {
        try {
            var stored = localStorage.getItem(MIA_STORAGE_HISTORY_KEY);
            if (stored) {
                var parsed = JSON.parse(stored);
                if (parsed.timestamp && (Date.now() - parsed.timestamp) < 30 * 60 * 1000) {
                    return parsed.history || [];
                }
            }
        } catch (e) {}
        return [];
    }

    function saveConversationHistory() {
        try {
            var trimmedHistory = MiaHero.conversationHistory.slice(-20);
            var data = {
                history: trimmedHistory,
                timestamp: Date.now()
            };
            localStorage.setItem(MIA_STORAGE_HISTORY_KEY, JSON.stringify(data));
        } catch (e) {}
    }

    function clearSharedMiaStorage() {
        try {
            localStorage.removeItem(MIA_STORAGE_KEY);
            localStorage.removeItem(MIA_STORAGE_HISTORY_KEY);
        } catch (e) {}
    }

    // Initialize from localStorage
    (function() {
        var savedContext = loadSessionContext();
        var savedHistory = loadConversationHistory();
        if (savedContext && Object.keys(savedContext).length > 0) {
            MiaHero.priorContext = savedContext;
            MiaDebug.info('Chat', 'session_init', { loaded_context: savedContext });
        } else {
            MiaDebug.info('Chat', 'session_init', 'No saved context — fresh session');
        }
        if (savedHistory && savedHistory.length > 0) {
            MiaHero.conversationHistory = savedHistory;
            MiaDebug.info('Chat', 'session_init', { loaded_history_turns: savedHistory.length });
        } else {
            MiaDebug.info('Chat', 'session_init', 'No saved conversation history');
        }
    })();

    // ── Language detection ──
    function detectConversationLangFromText(text) {
        var value = String(text || '').toLowerCase().trim();
        if (!value) return '';

        // Score-based detection: "hola" is only a weak Spanish indicator
        // (+1) because it's commonly used as a bilingual greeting in English
        // conversation.  English context words (provider, therapist, find,
        // etc.) score +3 and override the greeting.  Without scoring,
        // "Hola, can you find me a therapist?" was detected as Spanish,
        // causing Spanish TTS and recognition language for an English query.
        var esScore = 0;
        var enScore = 0;

        // Spanish accent characters — strong indicator
        if (/[áéíóúñü¿¡]/i.test(value)) esScore += 3;

        // Spanish content words — strong indicators of Spanish conversation
        if (/\b(?:quiero|necesito|busco|gracias|terapeuta|ansiedad|depresion|sesion|seguro|aseguranza|ciudad|cerca|espanol|hablas|puedes|ayudarme|perfil|reclamar|agregar|consejero|psic[oó]logo|psiquiatra|gestionar|categor[ií]a|opci[oó]n|muestrame|mu[eé]strame|mostrar|encuentra)\b/i.test(value)) esScore += 3;

        // "hola" and Spanish greetings — WEAK indicator only (+1).
        // Often used as a bilingual greeting in English conversation.
        if (/\b(?:hola|buenos\s+d[ií]as|buenas\s+(?:tardes|noches))\b/i.test(value)) esScore += 1;

        // Spanish overlap words that also appear in English contexts
        if (/\b(?:proveedor|terapia)\b/i.test(value)) esScore += 2;

        // English greeting/content words — strong indicators
        if (/\b(?:hello|hey|hi|how\s+are\s+you|good\s+(?:morning|afternoon|evening))\b/i.test(value)) enScore += 3;
        if (/\b(?:thanks|thank\s+you)\b/i.test(value)) enScore += 2;
        if (/\b(?:i\s+(?:need|want|have|am|'m|'d|would)|can\s+you|could\s+you|looking\s+for|find\s+me|show\s+me|help\s+me|what\s+can\s+you(?:\s+do)?)\b/i.test(value)) enScore += 3;
        if (/\b(?:therapy|therapist|counselor|provider|providers|city|near\s+me|insurance|anxiety|depression|stress|mental\s+health|session|claim|listing|upgrade|english|wellness|appointment)\b/i.test(value)) enScore += 3;

        // English context wins when it clearly dominates
        if (enScore > esScore && enScore >= 3) return 'en';
        if (esScore > enScore && esScore >= 3) return 'es';

        // Tie or low scores
        if (esScore > enScore && esScore >= 1) return 'es';
        if (enScore > esScore && enScore >= 1) return 'en';

        return '';
    }

    function rememberHeroConversationLanguage(text) {
        var detected = detectConversationLangFromText(text);
        MiaDebug.info('Chat', 'language_detect', { input: text.substring(0, 40), detected: detected || 'none' });
        if (!detected) return;

        MiaHero.detectedLanguage = detected;
        if (!MiaHero.priorContext || typeof MiaHero.priorContext !== 'object') {
            MiaHero.priorContext = {};
        }
        MiaHero.priorContext.language = detected;
    }

    function getPreferredHeroVoiceLang() {
        var detectedLang = String(MiaHero.detectedLanguage || '').toLowerCase();
        if (detectedLang.indexOf('es') === 0) return 'es-US';
        if (detectedLang.indexOf('en') === 0) return 'en-US';

        var ctxLang = (MiaHero.priorContext && MiaHero.priorContext.language ? MiaHero.priorContext.language : '').toLowerCase();
        if (ctxLang.indexOf('es') === 0) return 'es-US';
        if (ctxLang.indexOf('en') === 0) return 'en-US';

        var browserLang = (navigator.language || navigator.userLanguage || 'en-US').toLowerCase();
        if (browserLang.indexOf('es') === 0) return 'es-US';

        return 'en-US';
    }

    function detectHeroUiLang() {
        return getPreferredHeroVoiceLang().indexOf('es') === 0 ? 'es' : 'en';
    }

    function detectSpeechLangFromText(text) {
        var conversationLang = detectConversationLangFromText(text);
        if (conversationLang === 'es') return 'es-US';
        if (conversationLang === 'en') return 'en-US';

        var value = String(text || '').toLowerCase();
        // Use the same scoring logic — only return es-US when Spanish
        // clearly dominates.  "hola" alone is not enough to switch the
        // speech recognition language to Spanish.
        var esScore = 0;
        var enScore = 0;
        if (/[áéíóúñü¿¡]/i.test(value)) esScore += 3;
        if (/\b(?:quiero|necesito|busco|seguro|ciudad|gracias|sesion|especialidad|consejero|psic[oó]logo|psiquiatra|reclamar|gestionar|ayudarme|puedes|hablas|espanol)\b/i.test(value)) esScore += 3;
        if (/\b(?:hola|buenos\s+d[ií]as|buenas\s+(?:tardes|noches))\b/i.test(value)) esScore += 1;
        if (/\b(?:proveedor|terapia)\b/i.test(value)) esScore += 2;
        if (/\b(?:i\s+(?:need|want|have)|can\s+you|could\s+you|looking\s+for|find\s+me|show\s+me|help\s+me|therapy|therapist|counselor|provider|providers|city|near\s+me|insurance|anxiety|depression|stress|mental\s+health|session|claim|listing)\b/i.test(value)) enScore += 3;
        if (enScore > esScore && enScore >= 3) return 'en-US';
        if (esScore > enScore && esScore >= 3) return 'es-US';
        return getPreferredHeroVoiceLang();
    }

    function getTtsLanguageCode(text) {
        var lang = detectSpeechLangFromText(text).toLowerCase();
        return lang.indexOf('es') === 0 ? 'es-MX' : 'en';
    }

    // ── Voice UI copy ──
    function getVoiceUiText(key) {
        var lang = detectHeroUiLang();
        var copy = {
            en: {
                listening: "I'm listening…",
                got_it: 'Got it…',
                checking_speech: 'Checking what you said…',
                preparing_voice: 'Mia is getting your answer ready…',
                speaking: 'Mia is talking…',
                interrupted: 'Stopped. I am listening…',
                didnt_catch: "I didn't catch that — I'm listening…",
                mic_denied: 'Mic access denied. Allow in browser settings.',
                unsupported: 'Voice input is not supported in this browser. You can still type below.',
                thinking: 'Mia is thinking — please wait…',
                connection: 'Connection issue — tap mic to retry',
                audio_failed: 'Voice reply unavailable — continuing in text',
                tap_to_enable_audio: 'Tap once to enable Mia voice in this browser',
                enable_mic_title: 'Enable microphone to talk with Mia',
                enable_mic_copy: 'Mia will ask for mic access before live voice starts.',
                enable_mic_button: 'Enable mic',
                enable_mic_cancel: 'Not now',
                voice_greeting: "Hi, I'm Mia. Tell me what kind of support you're looking for, and I'll guide you to the right fit."
            },
            es: {
                listening: 'Te escucho…',
                got_it: 'Entendido…',
                checking_speech: 'Estoy comprobando lo que dijiste…',
                preparing_voice: 'Mia esta preparando tu respuesta…',
                speaking: 'Mia esta hablando…',
                interrupted: 'Detenido. Te escucho…',
                didnt_catch: 'No lo capte bien — te escucho…',
                mic_denied: 'El microfono fue bloqueado. Activalo en el navegador.',
                unsupported: 'La entrada de voz no es compatible en este navegador. Aun puedes escribir abajo.',
                thinking: 'Mia esta pensando — espera un momento…',
                connection: 'Problema de conexion — toca el microfono para intentar otra vez',
                audio_failed: 'La respuesta por voz no estuvo disponible — seguimos en texto',
                tap_to_enable_audio: 'Toca una vez para activar la voz de Mia en este navegador',
                enable_mic_title: 'Activa el microfono para hablar con Mia',
                enable_mic_copy: 'Mia pedira acceso al microfono antes de iniciar la voz en vivo.',
                enable_mic_button: 'Activar microfono',
                enable_mic_cancel: 'Ahora no',
                voice_greeting: 'Hola, soy Mia. Cuentame que tipo de apoyo buscas y te guio hacia la mejor opcion.'
            }
        };
        return copy[lang][key] || copy.en[key] || '';
    }

    function getSpeechTextFromResponse(rawText) {
        return scrubHeroPlatformMentions(String(rawText || '')
            .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '$1')
            .replace(/\*\*([^*]+)\*\*/g, '$1')
            .replace(/\*([^*]+)\*/g, '$1')
            .replace(/`([^`]+)`/g, '$1')
            .replace(/\n+/g, '. ')
            .replace(/\s+/g, ' ')
            .trim());
    }

    // ── Provider helper functions ──
    function isHeroPlatformProviderName(name) {
        var value = String(name || '').replace(/<[^>]*>/g, '').trim().toLowerCase();
        if (!value) return true;
        value = value.replace(/^https?:\/\//, '').replace(/^www\./, '');
        return /^(?:myhealthub|myhealthhub)\.com\/?$/.test(value) ||
            /\b(?:my\s*health\s*ub|my\s*health\s*hub|myhealthub|myhealthhub)(?:\.com)?\b/i.test(value) ||
            /^[a-z0-9.-]+\.(?:com|org|net|health|care)(?:\/.*)?$/i.test(value);
    }

    function getSafeHeroProviderName(provider, index) {
        var item = provider || {};
        var raw = String(item.name || item.title || '').trim();
        if (raw && !isHeroPlatformProviderName(raw)) return raw;
        var city = String(item.city || '').trim();
        if (city) return 'Provider in ' + city;
        return index ? 'Provider ' + index : 'Provider';
    }

    function getHeroProviderBadgeLabel(provider) {
        var status = String((provider && provider.verification_status) || '').toLowerCase();
        if (status === 'board_confirmed') return 'Board Verified';
        if (status === 'core_verified') return 'Verified';
        if (status === 'source_only') return 'Verified Source';
        if (provider && provider.claimed) return 'Claimed';
        return '';
    }

    function getHeroProviderCardsHtml(providers) {
        if (!Array.isArray(providers) || !providers.length) {
            MiaDebug.warn('Chat', 'provider_cards', 'No providers passed — cards not rendered');
            return '';
        }
        MiaDebug.info('Chat', 'provider_cards', { count: providers.length, names: providers.slice(0,4).map(function(p, i){ return getSafeHeroProviderName(p, i + 1) || '?'; }) });

        var cards = providers.slice(0, 4).map(function(provider, index) {
            var name = escapeHtml(getSafeHeroProviderName(provider, index + 1));
            var url  = escapeHtml(String(provider.url || '#'));
            var providerId = Math.max(0, parseInt(provider.id, 10) || 0);
            var city = escapeHtml(String(provider.city || ''));
            var phone = escapeHtml(String(provider.phone || ''));
            var address = escapeHtml(String(provider.address || ''));
            var street = escapeHtml(String(provider.street || ''));
            var license = escapeHtml(String(provider.license_type || ''));
            var specialties = Array.isArray(provider.specialties) ? provider.specialties.slice(0, 2).map(function(item) {
                return escapeHtml(String(item || ''));
            }).filter(Boolean) : [];
            var badge = getHeroProviderBadgeLabel(provider);
            var recommendation = index === 0 ? 'Best first fit' : (index === 1 ? 'Strong alternative' : '');
            var rating = (provider.rating_average && provider.rating_count)
                ? '<span style="color: rgba(255,255,255,0.66); font-size:0.68rem;">' + escapeHtml(String(provider.rating_average)) + '/5' + ' • ' + escapeHtml(String(provider.rating_count)) + ' reviews</span>'
                : '';
            var meta = [city, license].filter(Boolean).join(' • ');
            var contactHtml = '';
            if (phone || address) {
                contactHtml = '<div style="display:grid; gap:2px; margin-top:6px; color:rgba(255,255,255,0.68); font-size:0.66rem; line-height:1.25;">' +
                    (phone ? '<div>Phone: ' + phone + '</div>' : '') +
                    (address ? '<div>' + (street ? 'Address: ' : 'Location: ') + address + '</div>' : '') +
                '</div>';
            }
            var chips = specialties.map(function(item) {
                return '<span style="display:inline-flex; align-items:center; padding:3px 7px; border-radius:999px; background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.08); color:rgba(255,255,255,0.78); font-size:0.66rem; line-height:1.1;">' + item + '</span>';
            }).join('');
            var recommendationHtml = recommendation
                ? '<span style="display:inline-flex; align-items:center; padding:3px 7px; border-radius:999px; background:' + (index === 0 ? 'rgba(52,211,153,0.15)' : 'rgba(96,165,250,0.13)') + '; border:1px solid ' + (index === 0 ? 'rgba(52,211,153,0.24)' : 'rgba(96,165,250,0.20)') + '; color:#effff8; font-size:0.65rem; font-weight:700; letter-spacing:0.015em; line-height:1.1; white-space:nowrap;">' + recommendation + '</span>'
                : '';
            var badgeHtml = badge
                ? '<span style="display:inline-flex; align-items:center; padding:3px 7px; border-radius:999px; background:rgba(99,102,241,0.14); border:1px solid rgba(99,102,241,0.22); color:#dbe4ff; font-size:0.65rem; font-weight:600; line-height:1.1; white-space:nowrap;">' + escapeHtml(badge) + '</span>'
                : '';
            var topRowBadges = [recommendationHtml, badgeHtml].filter(Boolean).join('');

            return '<div style="padding:9px 10px 8px; border-radius:12px; background:' + (index === 0 ? 'linear-gradient(180deg, rgba(52,211,153,0.08), rgba(255,255,255,0.045))' : 'rgba(255,255,255,0.045)') + '; border:1px solid ' + (index === 0 ? 'rgba(52,211,153,0.16)' : 'rgba(255,255,255,0.085)') + '; box-shadow:0 4px 10px rgba(0,0,0,0.06);">' +
                '<div style="display:flex; align-items:flex-start; justify-content:space-between; gap:7px;">' +
                    '<div style="min-width:0;">' +
	                        '<div style="font-weight:700; color:#ffffff; line-height:1.18; font-size:0.86rem;">' + name + '</div>' +
	                        (meta ? '<div style="margin-top:3px; color:rgba(255,255,255,0.62); font-size:0.66rem; line-height:1.25; letter-spacing:0.04em; text-transform:uppercase;">' + meta + '</div>' : '') +
	                    '</div>' +
	                    (topRowBadges || '') +
	                '</div>' +
	                ((chips || rating) ? '<div style="display:flex; align-items:center; gap:5px; flex-wrap:wrap; margin-top:6px;">' + chips + rating + '</div>' : '') +
	                contactHtml +
                '<div style="margin-top:7px;"><a class="mia-provider-profile-link" data-mia-provider-id="' + providerId + '" data-mia-provider-rank="' + (index + 1) + '" href="' + url + '" style="display:inline-flex; align-items:center; gap:6px; padding:6px 9px; border-radius:999px; background:linear-gradient(135deg,#6366f1 0%,#60a5fa 100%); color:#ffffff; text-decoration:none; font-size:0.68rem; font-weight:700; line-height:1;">View profile</a></div>' +
	            '</div>';
        }).join('');

        return '<div class="mia-provider-results" style="margin-top:10px;">' +
            '<div style="color:rgba(255,255,255,0.58); font-size:0.64rem; font-weight:800; letter-spacing:0.09em; text-transform:uppercase; margin-bottom:7px;">Recommended providers</div>' +
            '<div style="display:grid; gap:6px;">' + cards + '</div>' +
        '</div>';
    }

    function appendHeroProviderCards(targetEl, providers) {
        if (!targetEl || !Array.isArray(providers) || !providers.length) return;

        var host = targetEl.classList && targetEl.classList.contains('mia-response-content')
            ? targetEl
            : (targetEl.querySelector ? targetEl.querySelector('.mia-response-content') : null) || (targetEl.closest ? targetEl.closest('.mia-response-content') : null) || targetEl;
        if (!host || host.querySelector('.mia-provider-results')) return;

        host.insertAdjacentHTML('beforeend', getHeroProviderCardsHtml(providers));
    }

    // ── Add message to chat UI ──
    function addHeroMessage(content, isUser) {
        if (typeof isUser === 'undefined') isUser = true;
        var messagesContainer = document.getElementById('mia-hero-messages');
        MiaDebug.info('Chat', 'add_message', { role: isUser ? 'user' : 'assistant', preview: String(content).substring(0, 60) });
        if (!messagesContainer) {
            MiaDebug.error('Chat', 'add_message', 'FAILED: #mia-hero-messages container not found in DOM');
            return;
        }

        var msgDiv = document.createElement('div');
        msgDiv.className = isUser ? 'mia-msg mia-msg--user' : 'mia-msg mia-msg--assistant';
        msgDiv.style.display = 'flex';
        msgDiv.style.gap = '12px';
        msgDiv.style.alignItems = 'flex-start';

        if (isUser) {
            msgDiv.style.flexDirection = 'row-reverse';
            msgDiv.innerHTML =
                '<div style="width: 38px; height: 38px; background: linear-gradient(135deg, #6366f1 0%, #7c7cf0 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 3px 14px rgba(99, 102, 241, 0.35), 0 0 0 2px rgba(255,255,255,0.1);">' +
                    '<svg width="18" height="18" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>' +
                    '</svg>' +
                '</div>' +
                '<div style="background: linear-gradient(135deg, rgba(99,102,241,0.4) 0%, rgba(139,92,246,0.4) 100%); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(139,92,246,0.3); color: white; border-radius: 18px 18px 4px 18px; padding: 12px 18px; max-width: 85%; box-shadow: 0 4px 16px rgba(99, 102, 241, 0.15);">' +
                    '<p style="margin: 0; font-size: 0.95rem; line-height: 1.7;">' + escapeHtml(content) + '</p>' +
                '</div>';
        } else {
            msgDiv.innerHTML =
                '<div style="width: 38px; height: 38px; background: linear-gradient(135deg, #6366f1 0%, #7c7cf0 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 3px 14px rgba(99, 102, 241, 0.35), 0 0 0 2px rgba(255,255,255,0.1);">' +
                    '<svg width="18" height="18" fill="none" stroke="white" stroke-width="1.5" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>' +
                    '</svg>' +
                '</div>' +
                '<div style="background: rgba(255,250,245,0.12); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.15); border-radius: 18px 18px 18px 4px; padding: 16px 20px; max-width: 85%; box-shadow: 0 4px 16px rgba(0,0,0,0.1);">' +
                    '<div class="mia-response-content" style="color: rgba(255,255,255,0.92); font-size: 0.95rem; line-height: 1.7;">' + content + '</div>' +
                '</div>';
        }

        messagesContainer.appendChild(msgDiv);
        msgDiv.scrollIntoView({ behavior: 'smooth', block: 'end' });
        // Sync voice overlay conversation panel if voice module is loaded
        if (MiaHero.Voice && typeof MiaHero.Voice.syncLiveConversationPanel === 'function') {
            MiaHero.Voice.syncLiveConversationPanel();
        }
    }

    // ── Typewriter effect ──
    function addHeroMessageTypewriter(rawContent, onComplete) {
        MiaDebug.info('Chat', 'typewriter_start', { preview: String(rawContent).substring(0, 60) });
        var messagesContainer = document.getElementById('mia-hero-messages');
        if (!messagesContainer) {
            MiaDebug.error('Chat', 'typewriter_start', 'FAILED: #mia-hero-messages not found');
            if (typeof onComplete === 'function') onComplete();
            return { element: null, done: Promise.resolve() };
        }

        var msgDiv = document.createElement('div');
        msgDiv.className = 'mia-msg mia-msg--assistant';
        msgDiv.style.display = 'flex';
        msgDiv.style.gap = '12px';
        msgDiv.style.alignItems = 'flex-start';

        var avatarHtml =
            '<div style="width: 38px; height: 38px; background: linear-gradient(135deg, #6366f1 0%, #7c7cf0 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 3px 14px rgba(99, 102, 241, 0.35), 0 0 0 2px rgba(255,255,255,0.1);">' +
                '<svg width="18" height="18" fill="none" stroke="white" stroke-width="1.5" viewBox="0 0 24 24">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>' +
                '</svg>' +
            '</div>';

        msgDiv.innerHTML = avatarHtml +
            '<div style="background: rgba(255,250,245,0.12); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.15); border-radius: 18px 18px 18px 4px; padding: 16px 20px; max-width: 85%; box-shadow: 0 4px 16px rgba(0,0,0,0.1);">' +
                '<div class="mia-response-content" id="typewriter-content" style="color: rgba(255,255,255,0.92); font-size: 0.95rem; line-height: 1.7;"></div>' +
            '</div>';

        messagesContainer.appendChild(msgDiv);
        msgDiv.scrollIntoView({ behavior: 'smooth', block: 'end' });

        var formattedContent = formatHeroResponse(rawContent);

        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = formattedContent;
        var textContent = tempDiv.textContent || tempDiv.innerText || '';
        var words = textContent.split(/\s+/).filter(function(w) { return w.length > 0; });

        var voiceMode = MiaHero.Voice && MiaHero.Voice.isVoiceMode ? MiaHero.Voice.isVoiceMode() : false;
        var targetDuration = voiceMode ? 380 : 1400;
        var msPerWord = words.length ? Math.max(12, Math.min(28, Math.round(targetDuration / words.length))) : 20;
        var wordIndex = 0;
        var contentEl = document.getElementById('typewriter-content');
        var completionResolve;
        var completionPromise = new Promise(function(resolve) { completionResolve = resolve; });

        MiaHero.Chat.typewriterTarget = {
            contentEl: contentEl,
            formattedContent: formattedContent,
            resolve: completionResolve
        };

        if (heroTypewriterInterval) {
            clearInterval(heroTypewriterInterval);
        }

        var revealedText = '';

        heroTypewriterInterval = setInterval(function() {
            if (wordIndex >= words.length) {
                clearInterval(heroTypewriterInterval);
                heroTypewriterInterval = null;
                contentEl.removeAttribute('id');
                contentEl.innerHTML = formattedContent;
                MiaHero.Chat.typewriterTarget = null;
                msgDiv.scrollIntoView({ behavior: 'smooth', block: 'end' });
                MiaDebug.info('Chat', 'typewriter_complete', { words: words.length });
                if (typeof onComplete === 'function') onComplete();
                completionResolve();
                return;
            }

            revealedText += (wordIndex > 0 ? ' ' : '') + words[wordIndex];
            wordIndex++;
            contentEl.innerHTML = escapeHtml(revealedText) + '<span style="opacity:0.4">▌</span>';
            if (wordIndex % 5 === 0) {
                msgDiv.scrollIntoView({ behavior: 'smooth', block: 'end' });
            }
        }, msPerWord);

        if (!words.length) {
            if (typeof onComplete === 'function') onComplete();
            completionResolve();
        }

        return { element: msgDiv, done: completionPromise };
    }

    function stopHeroTypewriter(completeMessage) {
        if (!MiaHero.Chat.typewriterTarget) return;

        if (heroTypewriterInterval) {
            clearInterval(heroTypewriterInterval);
            heroTypewriterInterval = null;
        }

        var target = MiaHero.Chat.typewriterTarget;
        MiaHero.Chat.typewriterTarget = null;

        if (!target.contentEl) return;

        target.contentEl.removeAttribute('id');
        if (completeMessage && target.formattedContent) {
            target.contentEl.innerHTML = target.formattedContent;
        } else {
            target.contentEl.textContent = String(target.contentEl.textContent || '').replace(/▌$/, '').trim();
        }

        if (typeof target.resolve === 'function') target.resolve();
    }

    // ── Typing indicator ──
    function setHeroTyping(isTyping) {
        MiaDebug.info('Chat', 'typing_indicator', { isTyping: isTyping });
        var typingEl = document.getElementById('hero-typing');
        if (typingEl) {
            typingEl.style.display = isTyping ? 'inline-flex' : 'none';
        }
    }

    // ── Context helpers ──
    function rememberHeroAssistantContext(rawText) {
        var clean = getSpeechTextFromResponse(rawText).replace(/\s+/g, ' ').trim();
        if (!clean) {
            MiaDebug.warn('Chat', 'remember_assistant_context', 'Empty context — not saved');
            return;
        }
        MiaHero.Chat.lastAssistantContext = clean.length > 280 ? clean.slice(0, 280).trim() : clean;
        MiaDebug.info('Chat', 'remember_assistant_context', { preview: MiaHero.Chat.lastAssistantContext.substring(0, 60) });
    }

    function getRecentHeroProviders() {
        if (!MiaHero.priorContext || !Array.isArray(MiaHero.priorContext.recent_providers)) return [];
        return MiaHero.priorContext.recent_providers;
    }

    function syncHeroSpokenProviderFocus(rawText) {
        var providers = getRecentHeroProviders();
        MiaHero.Chat.speechProviderSequence = [];
        if (!providers.length || !rawText) return;

        var text = String(rawText).toLowerCase();
        var matches = providers
            .map(function(provider, index) {
                var name = getSafeHeroProviderName(provider, index + 1).toLowerCase();
                return { provider: provider, index: name ? text.indexOf(name) : -1 };
            })
            .filter(function(match) { return match.index >= 0; })
            .sort(function(a, b) { return a.index - b.index; });

        if (!matches.length) return;

        MiaHero.Chat.speechProviderSequence = matches.map(function(match) { return match.provider.id; });

        if (!MiaHero.priorContext || typeof MiaHero.priorContext !== 'object') MiaHero.priorContext = {};
        MiaHero.priorContext.last_provider_focus = matches[0].provider.id;
    }

    // ── Bounce animation style injection ──
    (function() {
        var bounceStyle = document.createElement('style');
        bounceStyle.textContent = '@keyframes bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }';
        document.head.appendChild(bounceStyle);
    })();

    // ── Public API ──
    window.MiaHero.Chat = {
        // State
        typewriterTarget:       null,
        lastAssistantContext:   '',
        speechProviderSequence: [],

        // Formatting
        escapeHtml:             escapeHtml,
        isSafeUrl:              isSafeUrl,
        formatHeroResponse:     formatHeroResponse,
        formatMiaResponse:      formatMiaResponse,
        getSpeechTextFromResponse: getSpeechTextFromResponse,

        // Greeting
        getHeroGreetingHtml:    getHeroGreetingHtml,

        // Persistence
        loadSessionContext:        loadSessionContext,
        saveSessionContext:        saveSessionContext,
        loadConversationHistory:   loadConversationHistory,
        saveConversationHistory:   saveConversationHistory,
        clearSharedMiaStorage:     clearSharedMiaStorage,

        // Language
        detectConversationLangFromText: detectConversationLangFromText,
        rememberHeroConversationLanguage: rememberHeroConversationLanguage,
        getPreferredHeroVoiceLang:  getPreferredHeroVoiceLang,
        detectHeroUiLang:           detectHeroUiLang,
        detectSpeechLangFromText:   detectSpeechLangFromText,
        getTtsLanguageCode:         getTtsLanguageCode,
        getVoiceUiText:             getVoiceUiText,

        // Provider cards
        isHeroPlatformProviderName: isHeroPlatformProviderName,
        getSafeHeroProviderName:   getSafeHeroProviderName,
        getHeroProviderBadgeLabel:  getHeroProviderBadgeLabel,
        getHeroProviderCardsHtml:   getHeroProviderCardsHtml,
        appendHeroProviderCards:    appendHeroProviderCards,

        // Message display
        addHeroMessage:             addHeroMessage,
        addHeroMessageTypewriter:   addHeroMessageTypewriter,
        stopHeroTypewriter:         stopHeroTypewriter,
        setHeroTyping:              setHeroTyping,

        // Context tracking
        rememberHeroAssistantContext: rememberHeroAssistantContext,
        syncHeroSpokenProviderFocus:  syncHeroSpokenProviderFocus,
        getRecentHeroProviders:       getRecentHeroProviders
    };

})();
