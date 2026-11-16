<?php
/**
 * Template Name: Get Matched
 *
 * @package MyHealthHub
 * @version 1.1.0
 */

get_header();

// Get specialties for dropdown
$specialties = get_terms(array('taxonomy' => 'myhh_specialty', 'hide_empty' => false, 'parent' => 0));
?>

<!-- Hero Section -->
<section class="page-hero" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%); padding: 100px 0 120px; color: white; text-align: center; position: relative; overflow: hidden;">
    <!-- Animated background -->
    <div style="position: absolute; top: 20px; left: 20px; width: 150px; height: 150px; background: rgba(240, 147, 251, 0.1); border-radius: 50%; animation: float 30s infinite linear;"></div>
    <div style="position: absolute; bottom: 20px; right: 40px; width: 200px; height: 200px; background: rgba(102, 126, 234, 0.08); border-radius: 50%;"></div>
    
    <div class="container" style="position: relative; z-index: 1;">
        <div style="display: inline-flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.15); padding: 10px 24px; border-radius: 30px; margin-bottom: 24px; backdrop-filter: blur(10px);">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span style="font-weight: 600; font-size: 0.95rem;">✨ Free AI-Powered Matching Service</span>
        </div>
        <h1 style="font-size: clamp(2.5rem, 5vw, 3.5rem); font-weight: 800; margin-bottom: 20px; letter-spacing: -0.02em; line-height: 1.1; text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);">Get Matched with Your Ideal Provider</h1>
        <p style="font-size: 1.2rem; opacity: 0.95; max-width: 600px; margin: 0 auto; line-height: 1.8; text-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">Tell us what you're looking for and our AI will connect you with the perfect wellness professional in minutes.</p>
    </div>
    <style>
        @keyframes float {
            0% { transform: translate(0, 0); }
            50% { transform: translate(25px, 25px); }
            100% { transform: translate(0, 0); }
        }
    </style>
</section>

<!-- Form Section -->
<section class="section-standard" style="padding: 80px 0; background: linear-gradient(135deg, #f8f9fb 0%, #f1f5f9 100%);">
    <div class="container">
        <div style="max-width: 640px; margin: 0 auto;">
            
            <?php if (isset($_GET['success']) && $_GET['success'] == '1') : ?>
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 24px; padding: 64px 48px; text-align: center; box-shadow: 0 16px 48px rgba(102, 126, 234, 0.25);">
                    <div style="width: 100px; height: 100px; background: rgba(255,255,255,0.25); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 28px;">
                        <svg width="48" height="48" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 16px; color: white; letter-spacing: -0.01em;">🎉 Thank You!</h2>
                    <p style="color: rgba(255,255,255,0.95); font-size: 1.1rem; margin-bottom: 28px; line-height: 1.7;">We've received your request! Our team will analyze your needs and connect you with the perfect provider within 24 hours.</p>
                    <a href="<?php echo esc_url(home_url('/providers/')); ?>" style="display: inline-block; background: white; color: #667eea; padding: 14px 32px; border-radius: 12px; font-weight: 700; text-decoration: none; transition: all 0.3s; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"
                       onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 20px rgba(0,0,0,0.2)'"
                       onmouseout="this.style.transform='none';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'">Browse Providers Now</a>
                </div>
            <?php else : ?>
            
                <div style="background: white; border-radius: 24px; padding: 48px; border: 2px solid #E5E7EB; box-shadow: 0 10px 40px rgba(102, 126, 234, 0.12);" class="form-card">
                    <div style="text-align: center; margin-bottom: 36px;">
                        <span style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 20px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; margin-bottom: 16px;">💡 AI-Powered Matching</span>
                        <h2 style="font-size: 1.75rem; font-weight: 800; margin-bottom: 12px; color: #1F2937; letter-spacing: -0.01em;">Find Your Perfect Match</h2>
                        <p style="color: #6B7280; margin: 0; font-size: 1rem;">Complete the form and our intelligent system will pair you with providers who meet your exact needs.</p>
                    </div>
                    
                    <form method="post" action="" class="get-matched-form">
                        <?php wp_nonce_field('myhh_get_matched', 'myhh_nonce'); ?>
                        <input type="hidden" name="myhh_form_action" value="submit_lead">
                        
                        <div style="margin-bottom: 24px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #374151;">Your Name <span style="color: #f093fb;">*</span></label>
                            <input type="text" id="lead_name" name="lead_name" required style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 12px; font-size: 1rem; transition: all 0.3s;" 
                                onfocus="this.style.borderColor='#667eea';this.style.boxShadow='0 0 0 3px rgba(102, 126, 234, 0.1)'" 
                                onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none'">
                        </div>
                        
                        <div style="margin-bottom: 24px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #374151;">Email Address <span style="color: #f093fb;">*</span></label>
                            <input type="email" id="lead_email" name="lead_email" required style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 12px; font-size: 1rem; transition: all 0.3s;"
                                onfocus="this.style.borderColor='#667eea';this.style.boxShadow='0 0 0 3px rgba(102, 126, 234, 0.1)'" 
                                onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none'">
                        </div>
                        
                        <div style="margin-bottom: 24px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #374151;">Phone Number</label>
                            <input type="tel" id="lead_phone" name="lead_phone" style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 12px; font-size: 1rem; transition: all 0.3s;"
                                onfocus="this.style.borderColor='#667eea';this.style.boxShadow='0 0 0 3px rgba(102, 126, 234, 0.1)'" 
                                onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none'">
                        </div>
                        
                        <div style="margin-bottom: 24px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #374151;">City/Area</label>
                            <select id="lead_city" name="lead_city" style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 12px; font-size: 1rem; background: white; transition: all 0.3s; cursor: pointer;"
                                onfocus="this.style.borderColor='#667eea';this.style.boxShadow='0 0 0 3px rgba(102, 126, 234, 0.1)'" 
                                onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none'">
                                <option value="">Select an area</option>
                                <option value="raleigh">Raleigh</option>
                                <option value="durham">Durham</option>
                                <option value="cary">Cary</option>
                                <option value="chapel-hill">Chapel Hill</option>
                                <option value="apex">Apex</option>
                                <option value="other">Other Triangle Area</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 24px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 12px; color: #374151;">Preference</label>
                            <div class="preference-options" style="display: flex; gap: 12px; flex-wrap: wrap;">
                                <label style="display: flex; align-items: center; gap: 8px; padding: 12px 16px; border: 2px solid #E5E7EB; border-radius: 12px; cursor: pointer; transition: all 0.3s; background: white;" 
                                       onmouseover="this.style.borderColor='#667eea';this.style.background='rgba(102, 126, 234, 0.05)'" 
                                       onmouseout="this.style.borderColor='#E5E7EB';this.style.background='white'">
                                    <input type="radio" name="lead_preference" value="in-person" checked style="accent-color: #667eea; cursor: pointer;">
                                    <span style="font-weight: 500;">In-Person</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; padding: 12px 16px; border: 2px solid #E5E7EB; border-radius: 10px; cursor: pointer; transition: border-color 0.2s;"
                                       onmouseover="this.style.borderColor='#0A74DA'" onmouseout="this.style.borderColor='#E5E7EB'">
                                    <input type="radio" name="lead_preference" value="virtual" style="accent-color: #0A74DA;">
                                    <span style="font-weight: 500;">Virtual/Telehealth</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; padding: 12px 16px; border: 2px solid #E5E7EB; border-radius: 10px; cursor: pointer; transition: border-color 0.2s;"
                                       onmouseover="this.style.borderColor='#0A74DA'" onmouseout="this.style.borderColor='#E5E7EB'">
                                    <input type="radio" name="lead_preference" value="either" style="accent-color: #0A74DA;">
                                    <span style="font-weight: 500;">Either</span>
                                </label>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 24px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151;">What type of provider are you looking for?</label>
                            <select id="lead_type" name="lead_type" style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; background: white; transition: border-color 0.2s;"
                                onfocus="this.style.borderColor='#0A74DA'" onblur="this.style.borderColor='#E5E7EB'">
                                <option value="">Select provider type</option>
                                <option value="therapist">Therapist/Counselor</option>
                                <option value="psychologist">Psychologist</option>
                                <option value="psychiatrist">Psychiatrist</option>
                                <option value="life-coach">Life Coach</option>
                                <option value="unsure">Not Sure</option>
                            </select>
                        </div>
                        
                        <?php if ($specialties && !is_wp_error($specialties)) : ?>
                        <div style="margin-bottom: 24px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151;">What areas would you like help with?</label>
                            <select id="lead_specialty" name="lead_specialty" style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; background: white; transition: border-color 0.2s;"
                                onfocus="this.style.borderColor='#0A74DA'" onblur="this.style.borderColor='#E5E7EB'">
                                <option value="">Select a specialty area</option>
                                <?php foreach ($specialties as $specialty) : ?>
                                    <option value="<?php echo esc_attr($specialty->slug); ?>"><?php echo esc_html($specialty->name); ?></option>
                                <?php endforeach; ?>
                                <option value="other">Other / Not Sure</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div style="margin-bottom: 24px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151;">Tell us about what you are looking for</label>
                            <textarea id="lead_message" name="lead_message" rows="4" placeholder="e.g., I am looking for a therapist who specializes in anxiety and accepts Blue Cross insurance..." 
                                      style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; resize: vertical; transition: border-color 0.2s;"
                                      onfocus="this.style.borderColor='#0A74DA'" onblur="this.style.borderColor='#E5E7EB'"></textarea>
                        </div>
                        
                        <button type="submit" style="width: 100%; padding: 18px; background: linear-gradient(135deg, #0A74DA 0%, #1D4ED8 100%); color: white; border: none; border-radius: 12px; font-size: 1.15rem; font-weight: 600; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; margin-bottom: 16px;"
                            onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 20px rgba(10, 116, 218, 0.3)';"
                            onmouseout="this.style.transform='none';this.style.boxShadow='none';">
                            Submit Request
                        </button>
                        
                        <p style="font-size: 0.875rem; color: #6B7280; text-align: center; margin: 0;">
                            By submitting this form, you agree to our 
                            <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>" style="color: #0A74DA;">Privacy Policy</a>.
                        </p>
                    </form>
                </div>
            
            <?php endif; ?>
            
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="section-standard" style="padding: 80px 0; background: white;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 56px;">
            <span style="display: inline-block; background: #EEF2FF; color: #4F46E5; padding: 6px 16px; border-radius: 20px; font-size: 0.875rem; font-weight: 600; margin-bottom: 16px;">Simple Process</span>
            <h2 style="font-size: 2.25rem; font-weight: 700; color: #1F2937; margin-bottom: 12px;">How It Works</h2>
            <p style="color: #6B7280; max-width: 500px; margin: 0 auto;">Three simple steps to find the right wellness provider for your needs.</p>
        </div>
        
        <div class="how-it-works-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px; max-width: 900px; margin: 0 auto;">
            <div style="text-align: center;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; position: relative;">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    <div style="position: absolute; top: -4px; right: -4px; width: 28px; height: 28px; background: #4F46E5; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 0.875rem;">1</div>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 8px; color: #1F2937;">Submit Request</h3>
                <p style="color: #6B7280; font-size: 0.95rem; line-height: 1.6;">Fill out the form with your preferences, needs, and location.</p>
            </div>
            <div style="text-align: center;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #EEFDF3 0%, #D1FAE5 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; position: relative;">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="M21 21l-4.35-4.35"></path>
                    </svg>
                    <div style="position: absolute; top: -4px; right: -4px; width: 28px; height: 28px; background: #059669; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 0.875rem;">2</div>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 8px; color: #1F2937;">We Match You</h3>
                <p style="color: #6B7280; font-size: 0.95rem; line-height: 1.6;">Our team reviews your request and finds providers who fit.</p>
            </div>
            <div style="text-align: center;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; position: relative;">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"></path>
                    </svg>
                    <div style="position: absolute; top: -4px; right: -4px; width: 28px; height: 28px; background: #D97706; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 0.875rem;">3</div>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 8px; color: #1F2937;">Connect</h3>
                <p style="color: #6B7280; font-size: 0.95rem; line-height: 1.6;">We contact you within 24 hours with recommendations.</p>
            </div>
        </div>
    </div>
</section>

<!-- Trust Section -->
<section style="padding: 60px 0; background: #F8FAFC;">
    <div class="container">
        <div style="display: flex; justify-content: center; gap: 48px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 48px; height: 48px; background: #EEFDF3; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                </div>
                <div>
                    <p style="font-weight: 600; color: #1F2937; margin: 0;">100% Confidential</p>
                    <p style="font-size: 0.875rem; color: #6B7280; margin: 0;">Your information is secure</p>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 48px; height: 48px; background: #EEF2FF; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <div>
                    <p style="font-weight: 600; color: #1F2937; margin: 0;">Quick Response</p>
                    <p style="font-size: 0.875rem; color: #6B7280; margin: 0;">Within 24 hours</p>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 48px; height: 48px; background: #FEF3C7; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2">
                        <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
                <div>
                    <p style="font-weight: 600; color: #1F2937; margin: 0;">Vetted Providers</p>
                    <p style="font-size: 0.875rem; color: #6B7280; margin: 0;">All professionals vetted</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
get_footer();
