<?php
/**
 * Template Part: Hero Main
 *
 * Renders the AI-first Mia hero section: avatar identity, inline chat interface,
 * quick-start prompt chips, voice button, trust indicators, and wave divider.
 *
 * No $args parameters required.
 * All interactivity is driven by JavaScript (hero chat & voice scripts).
 * CSS animations/styles for this section are in the theme stylesheet or enqueued CSS files.
 *
 * @package MyHealthHub
 */
?>
<!-- ============================================ -->
<!-- AI-FIRST HERO: MIA CENTERPIECE -->
<!-- ============================================ -->
<section class="hero mia-hero" style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0c0a1d 100%); padding: 60px 0 80px; color: white; position: relative; overflow: hidden; min-height: 85vh; display: flex; align-items: center;">
    
    <!-- Animated Background - Deep space tech feel -->
    <div class="mia-bg-container" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; overflow: hidden;">
        <!-- Animated gradient mesh -->
        <div class="mia-gradient-1" style="position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle at 30% 30%, rgba(79, 70, 229, 0.15) 0%, transparent 50%), radial-gradient(circle at 70% 70%, rgba(10, 116, 218, 0.12) 0%, transparent 50%); animation: gradientFlow 20s ease-in-out infinite;"></div>
        
        <!-- Floating orbs -->
        <div class="mia-orb mia-orb-1" style="position: absolute; top: 10%; right: 15%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(79, 70, 229, 0.3) 0%, transparent 70%); border-radius: 50%; filter: blur(60px); animation: float 8s ease-in-out infinite;"></div>
        <div class="mia-orb mia-orb-2" style="position: absolute; bottom: 20%; left: 10%; width: 250px; height: 250px; background: radial-gradient(circle, rgba(10, 116, 218, 0.25) 0%, transparent 70%); border-radius: 50%; filter: blur(50px); animation: float 10s ease-in-out infinite reverse;"></div>
        <div class="mia-orb mia-orb-3" style="position: absolute; top: 50%; right: 30%; width: 200px; height: 200px; background: radial-gradient(circle, rgba(139, 92, 246, 0.2) 0%, transparent 70%); border-radius: 50%; filter: blur(40px); animation: float 12s ease-in-out infinite;"></div>
        
        <!-- Subtle grid pattern -->
        <div class="mia-grid" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px); background-size: 50px 50px;"></div>
        
        <!-- Sparkle particles -->
        <div class="mia-particles" id="mia-particles"></div>
    </div>
    
    <div class="container" style="position: relative; z-index: 1; width: 100%;">
        <div style="max-width: 900px; margin: 0 auto; text-align: center;">
            
            <!-- Mia Avatar & Identity -->
            <div style="margin-bottom: 32px;">
                <div class="mia-avatar-glow" style="width: 100px; height: 100px; background: linear-gradient(135deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,1) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 20px 60px rgba(79, 70, 229, 0.3), 0 0 0 4px rgba(255,255,255,0.1), inset 0 0 20px rgba(255,255,255,0.5);">
                    <div style="width: 82px; height: 82px; background: linear-gradient(135deg, #6366f1 0%, #7c7cf0 50%, #5b7cf7 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: inset 0 2px 10px rgba(255,255,255,0.3);">
                        <svg width="40" height="40" fill="none" stroke="white" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                </div>
                <div style="display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 8px;">
                    <h1 style="color: white; font-size: clamp(1.5rem, 4vw, 2.5rem); font-weight: 700; margin: 0; text-shadow: 0 2px 20px rgba(0,0,0,0.3);">Hi, I'm Mia</h1>
                    <span style="background: linear-gradient(135deg, rgba(16,185,129,0.3) 0%, rgba(5,150,105,0.3) 100%); color: #6ee7b7; padding: 5px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; border: 1px solid rgba(52, 211, 153, 0.3);">
                        <span style="width: 6px; height: 6px; background: #6ee7b7; border-radius: 50%; box-shadow: 0 0 10px #34d399; animation: pulse 2s ease-in-out infinite;"></span>
                        Online
                    </span>
                </div>
                <p style="color: rgba(255,255,255,0.8); font-size: clamp(1rem, 2.5vw, 1.25rem); margin: 0; line-height: 1.6; text-shadow: 0 1px 10px rgba(0,0,0,0.2);">
                    Calm, guided help for finding the right support
                </p>
            </div>
            
            <!-- Main Interaction Card - Inline Chat Interface -->
            <div id="mia-hero-interaction" class="mia-glass-card" style="border-radius: 28px; box-shadow: 0 25px 80px rgba(0,0,0,0.3), 0 0 40px rgba(99,102,241,0.08); max-width: 720px; margin: 0 auto 32px; overflow: hidden; border: 1px solid rgba(255,255,255,0.15); background: rgba(255,253,250,0.08); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
                
                <!-- Chat Messages Area -->
                <div id="mia-hero-messages" style="max-height: 400px; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 16px; min-height: 120px; background: transparent;">
                    <!-- Initial greeting from Mia -->
                    <div class="mia-msg mia-msg--assistant" style="display: flex; gap: 12px; align-items: flex-start;">
                        <div style="width: 38px; height: 38px; background: linear-gradient(135deg, #6366f1 0%, #7c7cf0 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 3px 14px rgba(99, 102, 241, 0.35), 0 0 0 2px rgba(255,255,255,0.1);">
                            <svg width="18" height="18" fill="none" stroke="white" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </div>
                        <div style="background: rgba(255,250,245,0.12); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.15); border-radius: 18px 18px 18px 4px; padding: 16px 20px; max-width: 85%; box-shadow: 0 4px 16px rgba(0,0,0,0.1);">
                            <p style="margin: 0; color: rgba(255,255,255,0.92); font-size: 0.95rem; line-height: 1.7; text-align: left;">
                                Hi, I'm Mia. Tell me what kind of support you're looking for, and I'll guide you to the right fit.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Start Prompts (shown initially, hidden after first message) -->
                <div id="mia-hero-quickstarts" style="padding: 0 24px 20px; border-top: 1px solid rgba(255,255,255,0.08); background: transparent;">
                    <p style="color: rgba(255,255,255,0.5); font-size: 0.75rem; margin: 16px 0 12px; font-weight: 500; letter-spacing: 0.5px; text-transform: uppercase;">Try one of these starting points</p>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <button class="mia-chip" data-prompt="I need a therapist for anxiety" style="padding: 10px 16px; background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.3); border-radius: 20px; font-size: 0.85rem; color: rgba(255,255,255,0.9); cursor: pointer; font-weight: 500; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); transition: all 0.3s ease;">
                            <span style="margin-right: 6px;">💙</span> Help me find a therapist
                        </button>
                        <button class="mia-chip" data-action="near-me" style="padding: 10px 16px; background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); border-radius: 20px; font-size: 0.85rem; color: rgba(255,255,255,0.9); cursor: pointer; font-weight: 500; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); transition: all 0.3s ease;">
                            <span style="margin-right: 6px;">📍</span> Providers near me
                        </button>
                        <button class="mia-chip" data-prompt="What wellness categories do you have?" style="padding: 10px 16px; background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3); border-radius: 20px; font-size: 0.85rem; color: rgba(255,255,255,0.9); cursor: pointer; font-weight: 500; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); transition: all 0.3s ease;">
                            <span style="margin-right: 6px;">✨</span> What services are available?
                        </button>
                        <button class="mia-chip" data-prompt="I am a provider, how do I list my practice?" style="padding: 10px 16px; background: linear-gradient(135deg, #fdf4ff 0%, #fae8ff 100%); border: 1px solid #f5d0fe; border-radius: 20px; font-size: 0.85rem; color: #a855f7; cursor: pointer; font-weight: 500;">
                            <span style="margin-right: 6px;">💼</span> I'm a provider
                        </button>
                    </div>
                </div>
                
                <!-- Input Area -->
                <div style="padding: 20px 24px 24px; border-top: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.04);">
                    <div style="display: flex; gap: 12px; align-items: flex-end;">
                        <div style="flex: 1; position: relative;">
                            <textarea id="mia-hero-input" 
                                      placeholder="Ask me anything..." 
                                      rows="1"
                                      style="width: 100%; padding: 16px 20px; border: 1px solid rgba(255,255,255,0.15); border-radius: 28px; font-size: 1rem; resize: none; font-family: inherit; line-height: 1.5; min-height: 54px; max-height: 120px; background: rgba(255,255,255,0.08); color: #fff; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);"
                                      class="mia-input-glow"></textarea>
                        </div>
                        <button id="mia-hero-voice" 
                                class="voice-btn"
                                style="width: 54px; height: 54px; background: rgba(102, 126, 234, 0.15); border: 2px solid rgba(102, 126, 234, 0.3); border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); transition: all 0.3s; color: white; font-size: 32px !important; line-height: 1; padding: 0; min-width: 54px; min-height: 54px;"
                                title="Voice conversation mode"
                                onmouseover="this.style.background='rgba(102, 126, 234, 0.25)';this.style.borderColor='rgba(102, 126, 234, 0.5)'"
                                onmouseout="this.style.background='rgba(102, 126, 234, 0.15)';this.style.borderColor='rgba(102, 126, 234, 0.3)'">
                            <span id="mic-icon" style="display: inline; line-height: 1; font-size: 32px !important;">🎤</span>
                            <span id="mic-active-icon" style="display: none; line-height: 1; font-size: 32px !important;">🔴</span>
                        </button>
                        <button id="mia-hero-send" 
                                class="mia-send-btn"
                                style="width: 54px; height: 54px; background: linear-gradient(135deg, #6366f1 0%, #7c7cf0 50%, #5b7cf7 100%); color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);">
                            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                        </button>
                    </div>
                    <div id="mia-hero-voice-permission" style="display: none; margin-top: 14px; padding: 14px 16px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.06); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;">
                            <div style="min-width: 220px; flex: 1;">
                                <div id="mia-hero-voice-permission-title" style="color: #ffffff; font-size: 0.92rem; font-weight: 600; line-height: 1.35;">Enable microphone to talk with Mia</div>
                                <div id="mia-hero-voice-permission-copy" style="color: rgba(255,255,255,0.68); font-size: 0.82rem; line-height: 1.5; margin-top: 4px;">Mia will ask for mic access before live voice starts.</div>
                            </div>
                            <div style="display: inline-flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <button type="button" id="mia-hero-permission-dismiss" style="background: transparent; border: 1px solid rgba(255,255,255,0.14); color: #cbd5e1; font-size: 0.8rem; border-radius: 999px; padding: 9px 12px; cursor: pointer;">Not now</button>
                                <button type="button" id="mia-hero-permission-confirm" style="background: linear-gradient(135deg, #6366f1 0%, #7c7cf0 100%); border: none; color: #fff; font-size: 0.8rem; font-weight: 600; border-radius: 999px; padding: 10px 14px; cursor: pointer; box-shadow: 0 8px 18px rgba(99,102,241,0.28);">Enable mic</button>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 14px;">
                        <span id="hero-typing" style="color: #6b7280; font-size: 0.85rem; display: none;">
                            <span style="display: inline-flex; align-items: center; gap: 8px;">
                                <span style="display: inline-flex; gap: 4px;">
                                    <span style="width: 7px; height: 7px; background: #6366f1; border-radius: 50%; animation: bounce 1.2s infinite cubic-bezier(0.45, 0, 0.55, 1) both;"></span>
                                    <span style="width: 7px; height: 7px; background: #6366f1; border-radius: 50%; animation: bounce 1.2s infinite cubic-bezier(0.45, 0, 0.55, 1) both; animation-delay: 0.16s;"></span>
                                    <span style="width: 7px; height: 7px; background: #6366f1; border-radius: 50%; animation: bounce 1.2s infinite cubic-bezier(0.45, 0, 0.55, 1) both; animation-delay: 0.32s;"></span>
                                </span>
                                Mia is thinking...
                            </span>
                        </span>
                        <div style="display: inline-flex; align-items: center; gap: 14px; flex-wrap: wrap; justify-content: flex-end;">
                            <button type="button" id="mia-hero-new-chat" class="mia-new-chat-btn" style="background: transparent; border: 1px solid rgba(255,255,255,0.14); color: #cbd5e1; font-size: 0.8rem; border-radius: 999px; padding: 8px 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m14.836 2A8.001 8.001 0 005.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-13.837-2m13.837 2H15"/>
                                </svg>
                                New chat
                            </button>
                            <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" class="mia-browse-link" style="color: #9ca3af; font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                Browse directory
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Trust Indicators -->
            <div class="trust-indicators" style="display: flex; align-items: center; justify-content: center; gap: 24px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 6px; color: rgba(255,255,255,0.85); font-size: 0.9rem;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Free to use
                </div>
                <div style="display: flex; align-items: center; gap: 6px; color: rgba(255,255,255,0.85); font-size: 0.9rem;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    No signup required
                </div>
                <div style="display: flex; align-items: center; gap: 6px; color: rgba(255,255,255,0.85); font-size: 0.9rem;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Raleigh/Triangle Area
                </div>
                <div style="display: flex; align-items: center; gap: 6px; color: rgba(255,255,255,0.85); font-size: 0.9rem;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <?php 
                    $post_count = wp_count_posts('gd_place');
                    echo isset($post_count->publish) ? number_format($post_count->publish) : '0';
                    ?>+ Providers
                </div>
            </div>
        </div>
    </div>
    
    <!-- Wave Divider -->
    <div style="position: absolute; bottom: -1px; left: 0; right: 0;">
        <svg viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" style="width: 100%; height: 80px; display: block;">
            <path d="M0 120L60 110C120 100 240 80 360 70C480 60 600 60 720 65C840 70 960 80 1080 85C1200 90 1320 90 1380 90L1440 90V120H1380C1320 120 1200 120 1080 120C960 120 840 120 720 120C600 120 480 120 360 120C240 120 120 120 60 120H0Z" fill="white"/>
        </svg>
    </div>
</section>
