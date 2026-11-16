<?php
/**
 * Template Part: Insurance Section
 *
 * Renders the insurance logos grid and "We Work With Your Insurance" section.
 * Displays 5 major insurance carriers (Blue Cross, Aetna, United, Cigna, Humana)
 * plus 3 benefit points below.
 *
 * No $args parameters required — all data is static.
 *
 * @package MyHealthHub
 */
?>
<!-- Insurance Section -->
<section style="padding: 80px 0; background: linear-gradient(135deg, #0A74DA 0%, #1e40af 50%, #0A74DA 100%); color: white; position: relative; overflow: hidden;">
    <!-- Background Pattern -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; opacity: 0.05;">
        <svg width="100%" height="100%" style="position: absolute;">
            <defs>
                <pattern id="insurance-grid" width="40" height="40" patternUnits="userSpaceOnUse">
                    <circle cx="20" cy="20" r="1" fill="white"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#insurance-grid)" />
        </svg>
    </div>
    
    <div class="container" style="position: relative; z-index: 1;">
        <div style="text-align: center; margin-bottom: 48px;">
            <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 8px 20px; border-radius: 50px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.2);">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span style="font-size: 0.9rem; font-weight: 500;">Insurance Accepted</span>
            </div>
            <h2 style="font-size: clamp(1.75rem, 4vw, 2.75rem); font-weight: 800; margin-bottom: 16px; letter-spacing: -0.025em;">We Work With Your Insurance</h2>
            <p style="font-size: 1.15rem; opacity: 0.9; max-width: 600px; margin: 0 auto; line-height: 1.7;">Most of our providers accept major insurance plans. Focus on your wellness, not paperwork.</p>
        </div>
        
        <!-- Insurance Cards -->
        <div class="insurance-logos" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; max-width: 900px; margin: 0 auto 40px;">
            <div style="background: rgba(255,250,245,0.12); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); border-radius: 16px; padding: 24px 16px; text-align: center; transition: all 0.3s;">
                <div style="font-weight: 700; font-size: 1rem; margin-bottom: 4px;">Blue Cross</div>
                <div style="font-size: 0.8rem; opacity: 0.8;">Blue Shield</div>
            </div>
            <div style="background: rgba(255,250,245,0.12); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); border-radius: 16px; padding: 24px 16px; text-align: center; transition: all 0.3s;">
                <div style="font-weight: 700; font-size: 1rem; margin-bottom: 4px;">Aetna</div>
                <div style="font-size: 0.8rem; opacity: 0.8;">All Plans</div>
            </div>
            <div style="background: rgba(255,250,245,0.12); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); border-radius: 16px; padding: 24px 16px; text-align: center; transition: all 0.3s;">
                <div style="font-weight: 700; font-size: 1rem; margin-bottom: 4px;">United</div>
                <div style="font-size: 0.8rem; opacity: 0.8;">Healthcare</div>
            </div>
            <div style="background: rgba(255,250,245,0.12); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); border-radius: 16px; padding: 24px 16px; text-align: center; transition: all 0.3s;">
                <div style="font-weight: 700; font-size: 1rem; margin-bottom: 4px;">Cigna</div>
                <div style="font-size: 0.8rem; opacity: 0.8;">Network</div>
            </div>
            <div style="background: rgba(255,250,245,0.12); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); border-radius: 16px; padding: 24px 16px; text-align: center; transition: all 0.3s;">
                <div style="font-weight: 700; font-size: 1rem; margin-bottom: 4px;">Humana</div>
                <div style="font-size: 0.8rem; opacity: 0.8;">Accepted</div>
            </div>
        </div>
        
        <!-- Additional Info -->
        <div style="display: flex; justify-content: center; gap: 32px; flex-wrap: wrap; padding-top: 32px; border-top: 1px solid rgba(255,255,255,0.15);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <span style="font-size: 0.95rem;">Most providers accept insurance</span>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <span style="font-size: 0.95rem;">Sliding scale options available</span>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <span style="font-size: 0.95rem;">Out-of-network benefits</span>
            </div>
        </div>
        
        <p style="text-align: center; margin-top: 32px; font-size: 0.9rem; opacity: 0.7;">Don't see your insurance? <a href="<?php echo esc_url(home_url('/contact/')); ?>" style="color: white; text-decoration: underline; font-weight: 500;">Contact us</a> to verify your coverage.</p>
    </div>
</section>
