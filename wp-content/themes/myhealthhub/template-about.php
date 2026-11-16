<?php
/**
 * Template Name: About Page
 *
 * @package MyHealthHub
 */

get_header();
?>

<!-- Hero Section -->
<section class="page-hero" style="background: linear-gradient(135deg, #0A74DA 0%, #1e40af 50%, #0A74DA 100%); padding: 100px 0 120px; color: white; text-align: center; position: relative; overflow: hidden;">
    <!-- Background Pattern -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; opacity: 0.08;">
        <svg width="100%" height="100%" style="position: absolute;">
            <pattern id="hero-pattern" x="0" y="0" width="60" height="60" patternUnits="userSpaceOnUse">
                <circle cx="30" cy="30" r="1.5" fill="white"/>
            </pattern>
            <rect width="100%" height="100%" fill="url(#hero-pattern)"/>
        </svg>
    </div>
    <div class="container" style="position: relative; z-index: 1;">
        <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); padding: 8px 16px; border-radius: 30px; margin-bottom: 24px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
            </svg>
            <span style="font-size: 0.875rem; font-weight: 500;">Serving the Triangle Area</span>
        </div>
        <h1 style="font-size: clamp(2.5rem, 5vw, 3.5rem); font-weight: 800; margin-bottom: 20px; letter-spacing: -0.02em; line-height: 1.1;">Your Journey to Wellness<br><span style="opacity: 0.9;">Starts with the Right Guide</span></h1>
        <p style="font-size: 1.25rem; opacity: 0.9; max-width: 650px; margin: 0 auto; line-height: 1.7;">We built My Health Hub because we believe finding a wellness professional shouldn't feel overwhelming. We're here to guide you to the right care, not just list providers.</p>
    </div>
</section>

<!-- Our Story Section -->
<section style="padding: 100px 0; background: white;">
    <div class="container">
        <div style="max-width: 800px; margin: 0 auto; text-align: center;">
            <span style="display: inline-block; background: #EEF2FF; color: #0A74DA; padding: 8px 20px; border-radius: 30px; font-size: 0.875rem; font-weight: 600; margin-bottom: 20px;">Our Story</span>
            <h2 style="font-size: clamp(1.75rem, 4vw, 2.5rem); font-weight: 700; margin-bottom: 24px; color: #1F2937; line-height: 1.2;">We Know How Hard It Is to Find Help</h2>
            <p style="color: #6B7280; line-height: 1.9; font-size: 1.125rem; margin-bottom: 24px;">When you're struggling—whether with anxiety, depression, relationship issues, or just feeling stuck—the last thing you need is to spend hours scrolling through endless directories, unsure who to trust.</p>
            <p style="color: #6B7280; line-height: 1.9; font-size: 1.125rem; margin-bottom: 24px;">We've been there. That's why we created My Health Hub: a different kind of directory designed around <strong style="color: #1F2937;">your</strong> journey, not just provider listings.</p>
            <p style="color: #6B7280; line-height: 1.9; font-size: 1.125rem;">We serve the Raleigh, Durham, Chapel Hill, and greater Triangle area—connecting neighbors with local wellness professionals who understand our community.</p>
        </div>
    </div>
</section>

<!-- How We Help Section -->
<section style="padding: 100px 0; background: linear-gradient(180deg, #F8FAFC 0%, #EEF2FF 100%);">
    <div class="container">
        <div style="text-align: center; margin-bottom: 64px;">
            <span style="display: inline-block; background: white; color: #0A74DA; padding: 8px 20px; border-radius: 30px; font-size: 0.875rem; font-weight: 600; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">How We Help</span>
            <h2 style="font-size: clamp(1.75rem, 4vw, 2.5rem); font-weight: 700; color: #1F2937; margin-bottom: 16px;">Your Guide to Finding Care</h2>
            <p style="color: #6B7280; max-width: 600px; margin: 0 auto; font-size: 1.125rem;">Think of us as a knowledgeable friend who happens to know every wellness professional in town.</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; max-width: 1100px; margin: 0 auto;">
            <!-- Step 1 -->
            <div style="background: white; border-radius: 24px; padding: 40px 32px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #E5E7EB; position: relative;">
                <div style="position: absolute; top: -16px; left: 50%; transform: translateX(-50%); width: 40px; height: 40px; background: linear-gradient(135deg, #0A74DA 0%, #1D4ED8 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.1rem; box-shadow: 0 4px 12px rgba(10, 116, 218, 0.3);">1</div>
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #EEF2FF 0%, #DBEAFE 100%); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 16px auto 24px;">
                    <svg width="36" height="36" fill="none" stroke="#0A74DA" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="M21 21l-4.35-4.35"/>
                    </svg>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 12px; color: #1F2937;">Search Your Way</h3>
                <p style="color: #6B7280; line-height: 1.7; margin: 0;">Browse by specialty, location, insurance, or session type. Filter until you find exactly what fits your life.</p>
            </div>
            
            <!-- Step 2 -->
            <div style="background: white; border-radius: 24px; padding: 40px 32px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #E5E7EB; position: relative;">
                <div style="position: absolute; top: -16px; left: 50%; transform: translateX(-50%); width: 40px; height: 40px; background: linear-gradient(135deg, #059669 0%, #047857 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.1rem; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);">2</div>
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 16px auto 24px;">
                    <svg width="36" height="36" fill="none" stroke="#059669" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 12px; color: #1F2937;">Compare Profiles</h3>
                <p style="color: #6B7280; line-height: 1.7; margin: 0;">Read detailed bios, specialties, and client testimonials. No guessing—just clear, helpful information.</p>
            </div>
            
            <!-- Step 3 -->
            <div style="background: white; border-radius: 24px; padding: 40px 32px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #E5E7EB; position: relative;">
                <div style="position: absolute; top: -16px; left: 50%; transform: translateX(-50%); width: 40px; height: 40px; background: linear-gradient(135deg, #D97706 0%, #B45309 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.1rem; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);">3</div>
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 16px auto 24px;">
                    <svg width="36" height="36" fill="none" stroke="#D97706" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 12px; color: #1F2937;">Connect with Care</h3>
                <p style="color: #6B7280; line-height: 1.7; margin: 0;">Reach out directly or use our matching service. We'll help you take that first step when you're ready.</p>
            </div>
        </div>
    </div>
</section>

<!-- Values Section -->
<section style="padding: 100px 0; background: white;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 64px;">
            <span style="display: inline-block; background: #EEF2FF; color: #0A74DA; padding: 8px 20px; border-radius: 30px; font-size: 0.875rem; font-weight: 600; margin-bottom: 20px;">Our Values</span>
            <h2 style="font-size: clamp(1.75rem, 4vw, 2.5rem); font-weight: 700; color: #1F2937; margin-bottom: 16px;">What We Stand For</h2>
            <p style="color: #6B7280; max-width: 600px; margin: 0 auto; font-size: 1.125rem;">These aren't just words on a page—they guide every decision we make.</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 32px; max-width: 900px; margin: 0 auto;">
            <div style="padding: 32px; background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%); border-radius: 20px; border-left: 4px solid #0A74DA;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <svg width="24" height="24" fill="none" stroke="#0A74DA" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: #1F2937; margin: 0;">You First, Always</h3>
                </div>
                <p style="color: #6B7280; line-height: 1.7; margin: 0;">Every feature we build starts with a question: "Does this help someone find care faster?" If the answer is no, we don't build it.</p>
            </div>
            
            <div style="padding: 32px; background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%); border-radius: 20px; border-left: 4px solid #059669;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <svg width="24" height="24" fill="none" stroke="#059669" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: #1F2937; margin: 0;">Privacy Matters</h3>
                </div>
                <p style="color: #6B7280; line-height: 1.7; margin: 0;">Your search for help is personal. We don't sell your data or share your information with third parties. Period.</p>
            </div>
            
            <div style="padding: 32px; background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%); border-radius: 20px; border-left: 4px solid #D97706;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <svg width="24" height="24" fill="none" stroke="#D97706" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                    </svg>
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: #1F2937; margin: 0;">Honest Information</h3>
                </div>
                <p style="color: #6B7280; line-height: 1.7; margin: 0;">We don't pay providers to rank higher. Profiles are listed based on relevance to your search—not who paid the most.</p>
            </div>
            
            <div style="padding: 32px; background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%); border-radius: 20px; border-left: 4px solid #7C3AED;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <svg width="24" height="24" fill="none" stroke="#7C3AED" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: #1F2937; margin: 0;">Community Roots</h3>
                </div>
                <p style="color: #6B7280; line-height: 1.7; margin: 0;">We're built in and for the Triangle. This is our home, and we're invested in connecting our neighbors with quality care.</p>
            </div>
        </div>
    </div>
</section>

<!-- Trust Icons Section -->
<section style="padding: 80px 0; background: #F8FAFC;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 48px;">
            <h2 style="font-size: clamp(1.5rem, 4vw, 2rem); font-weight: 700; color: #1F2937;">Built on Trust</h2>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; max-width: 1000px; margin: 0 auto;">
            <div style="text-align: center; padding: 24px;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #0A74DA 0%, #1D4ED8 100%); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <svg width="28" height="28" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="M21 21l-4.35-4.35"/>
                    </svg>
                </div>
                <h4 style="font-size: 1rem; font-weight: 600; color: #1F2937; margin-bottom: 4px;">Search-First</h4>
                <p style="font-size: 0.875rem; color: #6B7280;">Find providers fast</p>
            </div>
            
            <div style="text-align: center; padding: 24px;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #059669 0%, #047857 100%); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <svg width="28" height="28" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <h4 style="font-size: 1rem; font-weight: 600; color: #1F2937; margin-bottom: 4px;">Claimable Profiles</h4>
                <p style="font-size: 0.875rem; color: #6B7280;">Providers manage listings</p>
            </div>
            
            <div style="text-align: center; padding: 24px;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <svg width="28" height="28" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <h4 style="font-size: 1rem; font-weight: 600; color: #1F2937; margin-bottom: 4px;">Privacy-First</h4>
                <p style="font-size: 0.875rem; color: #6B7280;">Your data stays yours</p>
            </div>
            
            <div style="text-align: center; padding: 24px;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #D97706 0%, #B45309 100%); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <svg width="28" height="28" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <h4 style="font-size: 1rem; font-weight: 600; color: #1F2937; margin-bottom: 4px;">Triangle Focused</h4>
                <p style="font-size: 0.875rem; color: #6B7280;">Raleigh, Durham & more</p>
            </div>
        </div>
    </div>
</section>

<!-- For Providers Section -->
<section style="padding: 100px 0; background: white;">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 64px; align-items: center; max-width: 1100px; margin: 0 auto;">
            <div>
                <span style="display: inline-block; background: #D1FAE5; color: #059669; padding: 8px 20px; border-radius: 30px; font-size: 0.875rem; font-weight: 600; margin-bottom: 20px;">For Providers</span>
                <h2 style="font-size: clamp(1.75rem, 4vw, 2.25rem); font-weight: 700; margin-bottom: 20px; color: #1F2937; line-height: 1.2;">Are You a Wellness Professional?</h2>
                <p style="color: #6B7280; line-height: 1.8; font-size: 1.1rem; margin-bottom: 20px;">Join our growing community of providers serving the Triangle area. Claim your profile, update your information, and connect with clients who are actively searching for your expertise.</p>
                <div style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 24px;">
                    <span style="display: inline-flex; align-items: center; gap: 6px; background: #F3F4F6; padding: 8px 16px; border-radius: 8px; font-size: 0.9rem; color: #374151;">
                        <svg width="16" height="16" fill="none" stroke="#059669" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        Free to list
                    </span>
                    <span style="display: inline-flex; align-items: center; gap: 6px; background: #F3F4F6; padding: 8px 16px; border-radius: 8px; font-size: 0.9rem; color: #374151;">
                        <svg width="16" height="16" fill="none" stroke="#059669" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        Manage your profile
                    </span>
                    <span style="display: inline-flex; align-items: center; gap: 6px; background: #F3F4F6; padding: 8px 16px; border-radius: 8px; font-size: 0.9rem; color: #374151;">
                        <svg width="16" height="16" fill="none" stroke="#059669" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        Connect with clients
                    </span>
                </div>
                <a href="<?php echo esc_url(home_url('/claim-listing/')); ?>" style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; font-weight: 600; padding: 14px 28px; border-radius: 12px; text-decoration: none; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);">
                    Claim Your Profile
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>
            <div style="background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%); border-radius: 24px; padding: 48px; text-align: center;">
                <div style="width: 100px; height: 100px; background: white; border-radius: 24px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; box-shadow: 0 8px 24px rgba(0,0,0,0.08);">
                    <svg width="48" height="48" fill="none" stroke="#059669" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <h3 style="font-size: 1.5rem; font-weight: 700; color: #1F2937; margin-bottom: 12px;">Growing Community</h3>
                <p style="color: #4B5563; line-height: 1.7;">Join fellow wellness professionals across Raleigh, Durham, Cary, Chapel Hill, and beyond who are connecting with clients through My Health Hub.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section style="padding: 100px 0; background: linear-gradient(135deg, #0A74DA 0%, #1e40af 100%); color: white; text-align: center; position: relative; overflow: hidden;">
    <!-- Background Pattern -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; opacity: 0.08;">
        <svg width="100%" height="100%" style="position: absolute;">
            <pattern id="cta-pattern" x="0" y="0" width="40" height="40" patternUnits="userSpaceOnUse">
                <circle cx="20" cy="20" r="1" fill="white"/>
            </pattern>
            <rect width="100%" height="100%" fill="url(#cta-pattern)"/>
        </svg>
    </div>
    <div class="container" style="position: relative; z-index: 1;">
        <h2 style="font-size: clamp(2rem, 4vw, 2.75rem); font-weight: 700; margin-bottom: 16px;">Ready to Start Your Journey?</h2>
        <p style="font-size: 1.25rem; opacity: 0.9; margin-bottom: 40px; max-width: 550px; margin-left: auto; margin-right: auto; line-height: 1.6;">You've already taken the first step by being here. Let us help you find the right wellness professional for your needs.</p>
        <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
            <a href="<?php echo esc_url(home_url('/providers/')); ?>" style="display: inline-flex; align-items: center; gap: 8px; background: white; color: #0A74DA; font-weight: 600; padding: 16px 32px; border-radius: 12px; text-decoration: none; box-shadow: 0 4px 16px rgba(0,0,0,0.15);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="M21 21l-4.35-4.35"/>
                </svg>
                Browse Providers
            </a>
            <a href="<?php echo esc_url(home_url('/get-matched/')); ?>" style="display: inline-flex; align-items: center; gap: 8px; background: transparent; color: white; border: 2px solid white; font-weight: 600; padding: 14px 30px; border-radius: 12px; text-decoration: none;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
                Get Matched Free
            </a>
        </div>
    </div>
</section>

<?php
get_footer();
