<?php
/**
 * Template Part: Final CTA
 *
 * Renders the final call-to-action section with two primary buttons:
 *   - "Get Matched Free" (links to /get-matched/)
 *   - "Browse Directory" (links to the provider directory)
 *
 * No $args parameters required.
 *
 * @package MyHealthHub
 */
?>
<!-- CTA Section -->
<section class="cta-section" style="padding: 100px 0; background: white;">
    <div class="container" style="max-width: 900px;">
        <div style="background: linear-gradient(135deg, #0A74DA 0%, #1e40af 100%); border-radius: 32px; padding: clamp(40px, 8vw, 80px); text-align: center; position: relative; overflow: hidden; box-shadow: 0 30px 60px rgba(10, 116, 218, 0.25);">
            <!-- Background decoration -->
            <div style="position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: rgba(255,250,245,0.12); border-radius: 50%;"></div>
            <div style="position: absolute; bottom: -30px; left: -30px; width: 150px; height: 150px; background: rgba(255,250,245,0.12); border-radius: 50%;"></div>
            
            <div style="position: relative; z-index: 1;">
                <div style="width: 80px; height: 80px; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 28px; border: 2px solid rgba(255,255,255,0.3);">
                    <svg width="40" height="40" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </div>
                
                <h2 style="font-size: clamp(1.75rem, 5vw, 2.75rem); font-weight: 800; margin-bottom: 20px; color: white; letter-spacing: -0.025em;">Ready to Start Your Journey?</h2>
                <p style="font-size: 1.2rem; color: rgba(255,255,255,0.9); margin-bottom: 36px; line-height: 1.7; max-width: 600px; margin-left: auto; margin-right: auto;">Take the first step toward better wellness. Our team is here to help you find the perfect provider match.</p>
                
                <div class="cta-buttons" style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
                    <a href="<?php echo esc_url(home_url('/get-matched/')); ?>" style="display: inline-flex; align-items: center; gap: 10px; background: white; color: #0A74DA; padding: 18px 36px; border-radius: 14px; font-weight: 700; font-size: 1.1rem; text-decoration: none; box-shadow: 0 8px 24px rgba(0,0,0,0.15); transition: all 0.3s;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        Get Matched Free
                    </a>
                    <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" style="display: inline-flex; align-items: center; gap: 10px; background: transparent; color: white; padding: 18px 36px; border-radius: 14px; font-weight: 600; font-size: 1.1rem; text-decoration: none; border: 2px solid rgba(255,255,255,0.5); transition: all 0.3s;">
                        Browse Directory
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                </div>
                
                <div style="display: flex; justify-content: center; gap: 32px; margin-top: 40px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.85); font-size: 0.9rem;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        100% Free Service
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.85); font-size: 0.9rem;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Growing Network
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.85); font-size: 0.9rem;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        24hr Response
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
