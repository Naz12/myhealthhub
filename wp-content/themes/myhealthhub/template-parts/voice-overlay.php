<?php
/**
 * Template Part: Voice Overlay
 *
 * Renders the full-screen live voice conversation panel for Mia.
 * Includes the avatar presence card, live conversation thread, debug console,
 * and footer controls (Mute voice, Mute mic, New chat, End live).
 *
 * No $args parameters required — all state is driven by JavaScript.
 *
 * @package MyHealthHub
 */
?>
<div id="mia-live-voice-overlay" aria-hidden="true">
    <div class="mia-live-shell">
        <div class="mia-live-header">
            <div class="mia-live-identity">
                <div class="mia-live-pill">
                    <span class="mia-live-dot"></span>
                    <span>Mia Live</span>
                </div>
                <div class="mia-live-signature">MyHealthHub wellness concierge</div>
            </div>
            <button class="mia-live-close" type="button" id="mia-live-close-btn" aria-label="Exit live voice mode">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="mia-live-body">
            <div id="mia-live-main" class="mia-live-main voice-idle">
                <div class="mia-live-presence-card">
                    <div id="mia-live-visual" class="mia-live-visual">
                        <div class="mia-live-avatar">
                            <svg width="42" height="42" fill="none" stroke="white" stroke-width="1.6" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </div>
                        <div class="mia-live-wave" aria-hidden="true">
                            <span></span><span></span><span></span><span></span><span></span>
                        </div>
                    </div>
                    <div class="mia-live-status-stack">
                        <div id="mia-live-status" class="mia-live-status">Mia is ready</div>
                        <div id="mia-live-substatus" class="mia-live-substatus">A calmer, guided conversation space for finding the right support.</div>
                    </div>
                </div>
            </div>
            <div class="mia-live-side">
                <div class="mia-live-thread-shell">
                    <div class="mia-live-thread-head">
                        <div id="mia-live-thread-intro">
                            <div class="mia-live-thread-kicker">Live conversation</div>
                            <div class="mia-live-thread-title">Talk naturally, interrupt anytime, and keep every recommendation on screen.</div>
                        </div>
                        <div class="mia-live-thread-chip">
                            <span class="mia-live-thread-pulse"></span>
                            <span>Voice active</span>
                        </div>
                    </div>
                    <div class="mia-live-thread-stream">
                        <div id="mia-live-conversation" class="mia-live-conversation empty">The live conversation will stay visible here while Mia talks.</div>
                    </div>
                    <div class="mia-live-debug">
                        <div class="mia-live-panel-label" style="margin-bottom: 0;">
                            <span class="mia-live-cc-badge">DBG</span>
                            <span>Voice debug</span>
                        </div>
                        <div class="mia-live-debug-row">
                            <div class="mia-live-debug-key">Heard</div>
                            <div id="mia-live-debug-heard" class="mia-live-debug-value">Waiting for speech…</div>
                        </div>
                        <div class="mia-live-debug-row">
                            <div class="mia-live-debug-key">Final Turn</div>
                            <div id="mia-live-debug-final" class="mia-live-debug-value">Waiting for final turn…</div>
                        </div>
                        <div class="mia-live-debug-row">
                            <div class="mia-live-debug-key">Decision</div>
                            <div id="mia-live-debug-decision" class="mia-live-debug-value">Idle</div>
                        </div>
                        <div class="mia-live-debug-row">
                            <div class="mia-live-debug-key">Sent To Mia</div>
                            <div id="mia-live-debug-sent" class="mia-live-debug-value">Nothing sent yet.</div>
                        </div>
                        <div class="mia-live-debug-row">
                            <div class="mia-live-debug-key">Timing</div>
                            <div id="mia-live-debug-timing" class="mia-live-debug-value">Wait: — / Reply: —</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mia-live-footer">
            <div class="mia-live-controls">
                <button id="mia-live-mute" class="mia-live-control" type="button">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5L6 9H2v6h4l5 4V5z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M23 9l-6 6M17 9l6 6"/>
                    </svg>
                    <span data-mia-control-label>Mute voice</span>
                </button>
                <button id="mia-live-mic-mute" class="mia-live-control" type="button">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2a3 3 0 00-3 3v6a3 3 0 006 0V5a3 3 0 00-3-3z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 10v1a7 7 0 01-14 0v-1M12 18v4M8 22h8"/>
                    </svg>
                    <span data-mia-control-label>Mute mic</span>
                </button>
                <button id="mia-live-new-chat" class="mia-live-control" type="button">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                    </svg>
                    <span data-mia-control-label>New chat</span>
                </button>
                <button id="mia-live-end" class="mia-live-control warn" type="button">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
                    </svg>
                    <span data-mia-control-label>End live</span>
                </button>
            </div>
            <div id="mia-live-footer-note" class="mia-live-footer-note">Mia stays warm, grounded, and easy to interrupt while she guides the search.</div>
        </div>
    </div>
</div>
