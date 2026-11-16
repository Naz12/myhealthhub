/* MyHealthHub - Mia Voice Audio Utilities - Extracted from front-page.php */

window.MiaHero = window.MiaHero || {};

// ── [DEBUG] MiaDebug namespace — enable with localStorage.setItem('mia_debug','1') ──
// Full millisecond tracing: MiaDebug.enable() in console, then reload.
window.MiaDebug = window.MiaDebug || (function() {
    var enabled = (localStorage.getItem('mia_debug') === '1');
    var logs    = [];
    var MAX     = 4000;
    var origin  = performance.now(); // session-relative origin (ms)
    var lastMs  = origin;            // for delta computation
    var timers  = {};                // named timers for MiaDebug.time / timeEnd
    var metrics = {};                // accumulated perf metrics

    // Wall-clock ISO string
    function ts() { return new Date().toISOString().replace('T', ' ').replace('Z', ''); }
    // Milliseconds since MiaDebug was loaded
    function msNow() { return Math.round((performance.now() - origin) * 10) / 10; }

    function log(task, stage, detail, level) {
        if (!enabled) return;
        level = level || 'info';
        var now   = msNow();
        var delta = Math.round((now - lastMs) * 10) / 10;
        lastMs    = now;
        var entry = { ts: ts(), ms: now, delta_ms: delta, task: task, stage: stage, detail: detail, level: level };
        logs.push(entry);
        if (logs.length > MAX) logs.shift();
        var prefix = '[Mia:' + task + ':' + stage + ']';
        if (level === 'error')       console.error(prefix, detail);
        else if (level === 'warn')   console.warn(prefix, detail);
        else                         console.log(prefix, detail);
        if (window.MiaDebugPanel && typeof window.MiaDebugPanel.push === 'function') {
            window.MiaDebugPanel.push(entry);
        }
    }

    // ── Named timers ──────────────────────────────────────────────────────
    // MiaDebug.time('tts_request')  → start clock
    // MiaDebug.timeEnd('tts_request', 'TTS', 'request_duration')  → log result
    function startTimer(label) {
        timers[label] = performance.now();
    }
    function endTimer(label, task, stage, extra) {
        var start = timers[label];
        if (start == null) return;
        var elapsed = Math.round((performance.now() - start) * 10) / 10;
        delete timers[label];
        var detail = Object.assign({ label: label, elapsed_ms: elapsed }, extra || {});
        log(task || 'Perf', stage || label, detail, 'info');
        // Accumulate into metrics
        if (!metrics[label]) metrics[label] = { count: 0, sum: 0, min: Infinity, max: -Infinity };
        metrics[label].count++;
        metrics[label].sum  += elapsed;
        metrics[label].min   = Math.min(metrics[label].min, elapsed);
        metrics[label].max   = Math.max(metrics[label].max, elapsed);
        metrics[label].last  = elapsed;
        return elapsed;
    }

    // ── Direct metric record ───────────────────────────────────────────────
    function metric(name, value, unit) {
        if (!enabled) return;
        if (!metrics[name]) metrics[name] = { count: 0, sum: 0, min: Infinity, max: -Infinity };
        var m = metrics[name];
        m.count++; m.sum += value; m.min = Math.min(m.min, value); m.max = Math.max(m.max, value); m.last = value;
        if (unit) m.unit = unit;
        if (window.MiaDebugPanel && typeof window.MiaDebugPanel.metric === 'function') {
            window.MiaDebugPanel.metric(name, m);
        }
    }

    // ── Export ─────────────────────────────────────────────────────────────
    function exportLogs() {
        var rows = ['ms,delta_ms,level,task,stage,detail'];
        logs.forEach(function(e) {
            var d = typeof e.detail === 'object' ? JSON.stringify(e.detail) : String(e.detail || '');
            rows.push([e.ms, e.delta_ms, e.level, e.task, e.stage, '"' + d.replace(/"/g, '""') + '"'].join(','));
        });
        var blob = new Blob([rows.join('\n')], { type: 'text/csv' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'mia-debug-' + Date.now() + '.csv';
        a.click();
    }

    return {
        log:        log,
        info:       function(task, stage, detail)        { log(task, stage, detail, 'info');  },
        warn:       function(task, stage, detail)        { log(task, stage, detail, 'warn');  },
        error:      function(task, stage, detail)        { log(task, stage, detail, 'error'); },
        time:       startTimer,
        timeEnd:    endTimer,
        metric:     metric,
        getLogs:    function() { return logs.slice(); },
        getMetrics: function() { return JSON.parse(JSON.stringify(metrics)); },
        clear:      function() { logs = []; metrics = {}; origin = performance.now(); lastMs = origin; },
        export:     exportLogs,
        enable:     function() { enabled = true; localStorage.setItem('mia_debug', '1'); console.log('[MiaDebug] Debug logging ENABLED'); },
        disable:    function() { enabled = false; localStorage.removeItem('mia_debug'); },
        isEnabled:  function() { return enabled; },
        msNow:      msNow
    };
})();

(function() {
    'use strict';

    // ── PCM / Float32 conversion utilities ──

    function floatTo16BitPCM(float32Array) {
        const buffer = new ArrayBuffer(float32Array.length * 2);
        const view = new DataView(buffer);
        for (let i = 0; i < float32Array.length; i++) {
            let sample = Math.max(-1, Math.min(1, float32Array[i]));
            view.setInt16(i * 2, sample < 0 ? sample * 0x8000 : sample * 0x7fff, true);
        }
        return new Int16Array(buffer);
    }

    function pcm16ToFloat32(buffer) {
        const pcm = new Int16Array(buffer);
        const float32 = new Float32Array(pcm.length);
        for (let i = 0; i < pcm.length; i++) {
            float32[i] = pcm[i] / 0x8000;
        }
        return float32;
    }

    function downsampleTo24k(float32Array, inputRate) {
        if (!float32Array || !float32Array.length) {
            return new Float32Array(0);
        }

        if (!inputRate || inputRate === 24000) {
            return new Float32Array(float32Array);
        }

        const ratio = inputRate / 24000;
        const newLength = Math.max(1, Math.round(float32Array.length / ratio));
        const result = new Float32Array(newLength);
        let offsetResult = 0;
        let offsetBuffer = 0;

        while (offsetResult < result.length) {
            const nextOffsetBuffer = Math.min(float32Array.length, Math.round((offsetResult + 1) * ratio));
            let accum = 0;
            let count = 0;
            for (let i = offsetBuffer; i < nextOffsetBuffer; i++) {
                accum += float32Array[i];
                count++;
            }
            result[offsetResult] = count ? (accum / count) : float32Array[Math.min(offsetBuffer, float32Array.length - 1)];
            offsetResult++;
            offsetBuffer = nextOffsetBuffer;
        }

        return result;
    }

    function arrayBufferToBase64(buffer) {
        let binary = '';
        const bytes = new Uint8Array(buffer);
        const chunkSize = 0x8000;
        for (let i = 0; i < bytes.length; i += chunkSize) {
            binary += String.fromCharCode.apply(null, bytes.subarray(i, i + chunkSize));
        }
        return btoa(binary);
    }

    function base64ToArrayBuffer(base64) {
        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    }

    // ── RMS energy calculation (used by noise gate) ──

    function getChunkRms(float32Chunk) {
        if (!float32Chunk || !float32Chunk.length) {
            return 0;
        }

        var sum = 0;
        for (var i = 0; i < float32Chunk.length; i++) {
            sum += float32Chunk[i] * float32Chunk[i];
        }
        return Math.sqrt(sum / Math.max(1, float32Chunk.length));
    }

    function getChunkPeak(float32Chunk) {
        if (!float32Chunk || !float32Chunk.length) {
            return 0;
        }

        var peak = 0;
        for (var i = 0; i < float32Chunk.length; i++) {
            var abs = Math.abs(float32Chunk[i]);
            if (abs > peak) peak = abs;
        }
        return peak;
    }

    function shapeRealtimeInputChunk(float32Chunk) {
        if (!float32Chunk || !float32Chunk.length) {
            return {
                audio: new Float32Array(0),
                rms: 0,
                peak: 0,
                gainApplied: 1,
                nearMic: false
            };
        }

        var rms = getChunkRms(float32Chunk);
        var peak = getChunkPeak(float32Chunk);
        var gain = 1;

        // Close-mic speech often arrives with a strong RMS envelope and high peaks.
        // Pull it down before PCM conversion so the upstream STT model gets cleaner audio.
        if (peak > 0.86) gain = Math.min(gain, 0.58 / peak);
        else if (peak > 0.72) gain = Math.min(gain, 0.68 / peak);
        if (rms > 0.22) gain = Math.min(gain, 0.16 / rms);
        else if (rms > 0.16) gain = Math.min(gain, 0.18 / rms);

        var nearMic = gain < 0.985 || peak > 0.78 || rms > 0.18;
        if (!nearMic) {
            return {
                audio: float32Chunk,
                rms: rms,
                peak: peak,
                gainApplied: 1,
                nearMic: false
            };
        }

        var shaped = new Float32Array(float32Chunk.length);
        var drive = 1.25;
        var norm = Math.tanh(drive);
        for (var j = 0; j < float32Chunk.length; j++) {
            var sample = float32Chunk[j] * gain;
            shaped[j] = Math.tanh(sample * drive) / norm;
        }

        return {
            audio: shaped,
            rms: rms,
            peak: peak,
            gainApplied: gain,
            nearMic: true
        };
    }

    // ── AudioContext helpers ──

    function createAudioContext(sampleRate) {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) {
            return null;
        }
        return new AudioCtx({ sampleRate: sampleRate || 24000 });
    }

    // ── Capability detection ──

    const audioSupported = typeof Audio !== 'undefined'
        && typeof Blob !== 'undefined'
        && typeof URL !== 'undefined';

    const realtimeSupported = audioSupported
        && typeof WebSocket !== 'undefined'
        && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)
        && !!(window.AudioContext || window.webkitAudioContext);

    const audioWorkletSupported = realtimeSupported
        && typeof AudioWorkletNode !== 'undefined';

    const voiceInputSupported = 'webkitSpeechRecognition' in window
        || 'SpeechRecognition' in window;

    const voiceSupported = realtimeSupported || voiceInputSupported;

    // ── [DEBUG] Log audio capability detection results ──
    (function() {
        MiaDebug.info('AudioUtils', 'capability_detection', {
            audioSupported:        audioSupported,
            realtimeSupported:     realtimeSupported,
            audioWorkletSupported: audioWorkletSupported,
            voiceInputSupported:   voiceInputSupported,
            voiceSupported:        voiceSupported,
            userAgent:             navigator.userAgent.substring(0, 80)
        });
        if (!voiceSupported) {
            MiaDebug.warn('AudioUtils', 'capability_detection', 'VOICE NOT SUPPORTED in this browser — voice features will be disabled');
        }
        if (!realtimeSupported) {
            MiaDebug.warn('AudioUtils', 'capability_detection', 'REALTIME (WebSocket+getUserMedia+AudioContext) not available — will fall back to push-to-talk');
        }
    })();

    // ── Public API ──

    window.MiaHero.AudioUtils = {
        floatTo16BitPCM:    floatTo16BitPCM,
        pcm16ToFloat32:     pcm16ToFloat32,
        downsampleTo24k:    downsampleTo24k,
        arrayBufferToBase64: arrayBufferToBase64,
        base64ToArrayBuffer: base64ToArrayBuffer,
        getChunkRms:        getChunkRms,
        getChunkPeak:       getChunkPeak,
        shapeRealtimeInputChunk: shapeRealtimeInputChunk,
        createAudioContext: createAudioContext,
        // Capability flags
        audioSupported:     audioSupported,
        realtimeSupported:  realtimeSupported,
        audioWorkletSupported: audioWorkletSupported,
        voiceInputSupported: voiceInputSupported,
        voiceSupported:     voiceSupported
    };

})();
