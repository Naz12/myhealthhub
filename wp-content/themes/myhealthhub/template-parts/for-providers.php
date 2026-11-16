<?php
/**
 * Template Part: For Providers
 *
 * Renders two side-by-side cards targeting wellness professionals:
 *   1. "Not Listed? Add Free" — prompts providers to submit their practice.
 *   2. "Claim Your Profile"  — directs existing listed providers to claim their listing.
 *
 * No $args parameters required — all links are generated from home_url().
 *
 * @package MyHealthHub
 */
?>
<section style="padding: 80px 0; background: white;">
    <div class="container">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 32px; align-items: stretch;">
            
            <!-- Not Listed? Add Free Card -->
            <div style="background: linear-gradient(135deg, #0A74DA 0%, #1D4ED8 100%); border-radius: 24px; padding: 40px; color: white; position: relative; overflow: hidden;">
                <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,250,245,0.12); border-radius: 50%;"></div>
                <div style="position: relative; z-index: 1;">
                    <div style="width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                        <svg width="32" height="32" fill="none" stroke="white" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                    <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 12px;">Not Listed? Add Free</h3>
                    <p style="font-size: 1rem; opacity: 0.9; margin-bottom: 24px; line-height: 1.6;">
                        Join our growing directory of wellness professionals. Basic listings are free and take just a few minutes to submit.
                    </p>
                    <a href="<?php echo esc_url(home_url('/add-your-practice/')); ?>" class="cta-primary" style="display: inline-flex; align-items: center; gap: 8px; background: white; color: #0A74DA; padding: 14px 28px; border-radius: 12px; font-weight: 600; text-decoration: none;">
                        Submit Your Practice
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                </div>
            </div>
            
            <!-- Claim Your Profile Card -->
            <div style="background: #F8FAFC; border-radius: 24px; padding: 40px; border: 2px solid #E5E7EB; position: relative; overflow: hidden;">
                <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(16,185,129,0.05); border-radius: 50%;"></div>
                <div style="position: relative; z-index: 1;">
                    <div style="width: 64px; height: 64px; background: #DCFCE7; border-radius: 16px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                        <svg width="32" height="32" fill="none" stroke="#059669" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 12px; color: #1F2937;">Claim Your Profile</h3>
                    <p style="font-size: 1rem; color: #4B5563; margin-bottom: 24px; line-height: 1.6;">
                        Already listed? Claim your free profile to update your information, add photos, and connect with clients.
                    </p>
                    <a href="<?php echo esc_url(home_url('/listings/')); ?>" class="cta-secondary" style="display: inline-flex; align-items: center; gap: 8px; background: #059669; color: white; padding: 14px 28px; border-radius: 12px; font-weight: 600; text-decoration: none;">
                        Find Your Profile
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                </div>
            </div>
            
        </div>
    </div>
</section>
