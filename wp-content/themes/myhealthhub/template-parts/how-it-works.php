<?php
/**
 * Template Part: How It Works
 *
 * Renders the 3-step "Get the Help You Deserve" journey section.
 * Steps: Tell Us What You Need → Find Your Match → Start Your Journey.
 * Includes a connector line decoration (desktop) and a CTA button below the cards.
 *
 * No $args parameters required — all content is static.
 *
 * @package MyHealthHub
 */
?>
<!-- How It Works -->
<section class="section-standard" style="padding: 100px 0; background: linear-gradient(180deg, #ffffff 0%, #F8FAFC 100%);">
    <div class="container">
        <div style="text-align: center; margin-bottom: 72px;">
            <span style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%); color: #0A74DA; padding: 8px 20px; border-radius: 50px; font-size: 0.875rem; font-weight: 600; margin-bottom: 20px;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                We Make It Simple
            </span>
            <h2 style="font-size: clamp(2rem, 5vw, 3rem); font-weight: 800; margin-bottom: 20px; color: #1F2937; letter-spacing: -0.025em;">Get the Help You Deserve</h2>
            <p style="font-size: 1.25rem; color: #6B7280; max-width: 600px; margin: 0 auto; line-height: 1.7;">We know reaching out takes courage. Here's how we make finding the right professional as easy as possible.</p>
        </div>
        
        <div class="how-it-works-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; max-width: 1100px; margin: 0 auto; position: relative;">
            <!-- Connector Line (Desktop) -->
            <div class="hide-mobile" style="position: absolute; top: 60px; left: 18%; right: 18%; height: 3px; background: linear-gradient(90deg, #0A74DA 0%, #6366F1 50%, #0A74DA 100%); z-index: 0; border-radius: 2px;"></div>
            
            <!-- Step 1 -->
            <div class="step-card" style="position: relative; z-index: 1; text-align: center; background: white; border-radius: 24px; padding: 40px 32px; border: 1px solid #E5E7EB; box-shadow: 0 4px 20px rgba(0,0,0,0.04); transition: all 0.3s;">
                <div style="width: 120px; height: 120px; background: linear-gradient(135deg, #0A74DA 0%, #1D4ED8 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 28px; box-shadow: 0 15px 40px rgba(10, 116, 218, 0.35); position: relative;">
                    <svg width="48" height="48" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <div style="position: absolute; top: -8px; right: -8px; width: 36px; height: 36px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #0A74DA; font-size: 1.1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">1</div>
                </div>
                <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 12px; color: #1F2937;">Tell Us What You Need</h3>
                <p style="color: #6B7280; line-height: 1.7; font-size: 1rem; margin-bottom: 20px;">Browse our verified wellness professionals or search by what you're going through—anxiety, depression, relationship issues, and more.</p>
                <div style="display: flex; flex-wrap: wrap; gap: 8px; justify-content: center;">
                    <span style="background: #F3F4F6; color: #4B5563; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 500;">By Location</span>
                    <span style="background: #F3F4F6; color: #4B5563; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 500;">By Specialty</span>
                    <span style="background: #F3F4F6; color: #4B5563; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 500;">By Insurance</span>
                </div>
            </div>
            
            <!-- Step 2 -->
            <div class="step-card" style="position: relative; z-index: 1; text-align: center; background: white; border-radius: 24px; padding: 40px 32px; border: 1px solid #E5E7EB; box-shadow: 0 4px 20px rgba(0,0,0,0.04); transition: all 0.3s;">
                <div style="width: 120px; height: 120px; background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 28px; box-shadow: 0 15px 40px rgba(99, 102, 241, 0.35); position: relative;">
                    <svg width="48" height="48" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    <div style="position: absolute; top: -8px; right: -8px; width: 36px; height: 36px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #6366F1; font-size: 1.1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">2</div>
                </div>
                <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 12px; color: #1F2937;">Find Your Match</h3>
                <p style="color: #6B7280; line-height: 1.7; font-size: 1rem; margin-bottom: 20px;">Review detailed profiles with credentials, specialties, and client testimonials. Take your time—this is about finding the right fit for YOU.</p>
                <div style="display: flex; flex-wrap: wrap; gap: 8px; justify-content: center;">
                    <span style="background: #F3F4F6; color: #4B5563; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 500;">Profiles</span>
                    <span style="background: #F3F4F6; color: #4B5563; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 500;">Testimonials</span>
                    <span style="background: #F3F4F6; color: #4B5563; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 500;">No Rush</span>
                </div>
            </div>
            
            <!-- Step 3 -->
            <div class="step-card" style="position: relative; z-index: 1; text-align: center; background: white; border-radius: 24px; padding: 40px 32px; border: 1px solid #E5E7EB; box-shadow: 0 4px 20px rgba(0,0,0,0.04); transition: all 0.3s;">
                <div style="width: 120px; height: 120px; background: linear-gradient(135deg, #059669 0%, #047857 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 28px; box-shadow: 0 15px 40px rgba(5, 150, 105, 0.35); position: relative;">
                    <svg width="48" height="48" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                    <div style="position: absolute; top: -8px; right: -8px; width: 36px; height: 36px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #059669; font-size: 1.1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">3</div>
                </div>
                <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 12px; color: #1F2937;">Start Your Journey</h3>
                <p style="color: #6B7280; line-height: 1.7; font-size: 1rem; margin-bottom: 20px;">Book directly with your chosen professional or use our matching service for personalized help. You've got this—and we're here for you.</p>
                <div style="display: flex; flex-wrap: wrap; gap: 8px; justify-content: center;">
                    <span style="background: #D1FAE5; color: #065F46; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 500;">Direct Book</span>
                    <span style="background: #D1FAE5; color: #065F46; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 500;">Free Match</span>
                </div>
            </div>
        </div>
        
        <!-- CTA under steps -->
        <div style="text-align: center; margin-top: 56px;">
            <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" style="display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(135deg, #0A74DA 0%, #1D4ED8 100%); color: white; padding: 16px 36px; border-radius: 12px; font-weight: 600; font-size: 1.1rem; text-decoration: none; box-shadow: 0 8px 24px rgba(10, 116, 218, 0.3); transition: all 0.3s;">
                Start Your Search
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
        </div>
    </div>
</section>
