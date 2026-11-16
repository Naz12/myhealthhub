/**
 * Mia Voice AudioWorklet Processor
 *
 * Captures microphone audio in 24kHz mono PCM chunks and
 * sends them to the main thread for realtime voice streaming.
 *
 * Replaces the deprecated ScriptProcessor approach for modern browsers.
 */
class MiaVoiceProcessor extends AudioWorkletProcessor {
    constructor(options) {
        super();
        this._active = true;
        this._packetFrames = options && options.processorOptions && options.processorOptions.packetDurationMs === 20
            ? Math.round(sampleRate * 0.02) : 0;
        this._packet = this._packetFrames ? new Float32Array(this._packetFrames) : null;
        this._filled = 0;
        this._nextFrame = null;
        this.port.onmessage = (event) => {
            if (event.data && event.data.type === 'stop') {
                this._active = false;
                this._filled = 0;
                this._packet = null;
            } else if (event.data && event.data.type === 'discard') {
                this._filled = 0;
                this._nextFrame = null;
            }
        };
    }

    process(inputs, outputs, parameters) {
        if (!this._active) {
            return false; // disconnect
        }

        const input = inputs[0];
        if (!input || !input[0]) {
            this._filled = 0;
            this._nextFrame = null;
            return true;
        }

        const channelData = input[0];
        if (this._packetFrames) {
            // Audio-clock packetization: no timer catch-up bursts or padding.
            if (this._nextFrame !== currentFrame) this._filled = 0;
            this._nextFrame = currentFrame + channelData.length;
            let offset = 0;
            while (offset < channelData.length) {
                const count = Math.min(this._packetFrames - this._filled, channelData.length - offset);
                this._packet.set(channelData.subarray(offset, offset + count), this._filled);
                this._filled += count;
                offset += count;
                if (this._filled === this._packetFrames) {
                    this.port.postMessage({ type: 'audio_chunk', audio: this._packet, sampleRate: sampleRate,
                        captureEndSeconds: (currentFrame + offset) / sampleRate });
                    this._packet = new Float32Array(this._packetFrames);
                    this._filled = 0;
                }
            }
            return true;
        }
        // Clone the buffer — the underlying buffer is reused by the browser
        const chunk = new Float32Array(channelData);

        this.port.postMessage({
            type: 'audio_chunk',
            audio: chunk,
            sampleRate: sampleRate,
            captureEndSeconds: (currentFrame + chunk.length) / sampleRate
        });

        return true;
    }
}

registerProcessor('mia-voice-processor', MiaVoiceProcessor);
