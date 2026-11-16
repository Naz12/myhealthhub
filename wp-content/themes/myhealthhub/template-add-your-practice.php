<?php
/**
 * Template Name: Add Your Practice
 * Description: Provider submission form for new practices
 *
 * @package MyHealthHub
 * @version 1.0.0
 */

get_header();

// Get cities and specialties for dropdowns
$cities = get_terms(array(
    'taxonomy'   => 'myhh_city',
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC',
));

$specialties = get_terms(array(
    'taxonomy'   => 'myhh_specialty',
    'hide_empty' => false,
    'parent'     => 0,
    'orderby'    => 'name',
    'order'      => 'ASC',
));

// Get primary categories for dropdown (Milestone 3)
$primary_categories = get_terms(array(
    'taxonomy'   => 'myhh_primary_category',
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC',
));
?>

<!-- Hero Section -->
<section style="background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%); padding: 100px 0 120px; color: white; text-align: center; position: relative; overflow: hidden;">
    <!-- Animated background -->
    <div style="position: absolute; top: -30px; right: -30px; width: 300px; height: 300px; background: rgba(240, 147, 251, 0.1); border-radius: 50%; animation: float 30s infinite linear;"></div>
    <div style="position: absolute; bottom: -50px; left: 10%; width: 200px; height: 200px; background: rgba(102, 126, 234, 0.08); border-radius: 50%;"></div>
    
    <div class="container" style="position: relative; z-index: 1;">
        <div style="display: inline-flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.15); padding: 10px 24px; border-radius: 30px; margin-bottom: 24px; backdrop-filter: blur(10px);">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>
            <span style="font-weight: 600; font-size: 0.95rem;">Join Our Professional Network</span>
        </div>
        <h1 style="font-size: clamp(2.5rem, 5vw, 3.5rem); font-weight: 800; margin-bottom: 20px; letter-spacing: -0.02em; line-height: 1.1; text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);">Add Your Practice</h1>
        <p style="font-size: 1.2rem; opacity: 0.95; max-width: 600px; margin: 0 auto; line-height: 1.8; text-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">Get discovered by clients in the Raleigh/Triangle area. Join our network of verified wellness professionals.</p>
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
<section style="padding: 80px 0; background: linear-gradient(135deg, #f8f9fb 0%, #f1f5f9 100%);">
    <div class="container">
        <div style="max-width: 700px; margin: 0 auto;">
            
            <?php if (isset($_GET['success']) && $_GET['success'] == '1') : ?>
                <!-- Success State -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 24px; padding: 64px 48px; text-align: center; box-shadow: 0 16px 48px rgba(102, 126, 234, 0.25);">
                    <div style="width: 100px; height: 100px; background: rgba(255,255,255,0.25); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 28px;">
                        <svg width="48" height="48" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 16px; color: white; letter-spacing: -0.01em;">✨ Submission Received!</h2>
                    <p style="color: rgba(255,255,255,0.95); font-size: 1.1rem; margin-bottom: 28px; line-height: 1.7;">Thank you for submitting your practice! Our team will review your information and get back to you within 2-3 business days to confirm your listing.</p>
                    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                        <a href="<?php echo esc_url(home_url('/listings/')); ?>" style="display: inline-flex; align-items: center; gap: 8px; background: white; color: #667eea; padding: 14px 28px; border-radius: 12px; font-weight: 700; text-decoration: none; transition: all 0.3s;"
                           onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 20px rgba(0,0,0,0.2)'"
                           onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                            Browse Network
                        </a>
                        <a href="<?php echo esc_url(home_url('/')); ?>" style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); color: white; padding: 14px 28px; border-radius: 12px; font-weight: 700; text-decoration: none; border: 2px solid rgba(255,255,255,0.3); transition: all 0.3s;"
                           onmouseover="this.style.background='rgba(255,255,255,0.25)'"
                           onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                            Go Home
                        </a>
                    </div>
                </div>
            <?php else : ?>
            
                <!-- Helper Note -->
                <div style="background: linear-gradient(135deg, #E5E7FF 0%, #D8D9FF 100%); border: 2px solid #667eea; border-radius: 16px; padding: 20px 24px; margin-bottom: 28px; display: flex; align-items: start; gap: 12px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <div>
                        <p style="margin: 0; color: #3730A3; font-size: 0.9375rem; line-height: 1.5;">
                            <strong>Already listed on MyHealthHub?</strong> 
                            <a href="<?php echo esc_url(home_url('/listings/')); ?>" style="color: #4F46E5; text-decoration: underline;">Claim your profile</a> instead.
                        </p>
                    </div>
                </div>
                
                <!-- Form Card -->
                <div style="background: white; border-radius: 20px; padding: 40px; border: 1px solid #E5E7EB; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <div style="text-align: center; margin-bottom: 32px;">
                        <div style="width: 56px; height: 56px; background: #EEF2FF; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0A74DA" stroke-width="2">
                                <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <h2 style="font-size: 1.375rem; font-weight: 700; margin-bottom: 8px; color: #1F2937;">Practice Information</h2>
                        <p style="color: #6B7280; margin: 0; font-size: 0.9375rem;">Tell us about your practice and services.</p>
                    </div>
                    
                    <form method="post" action="" class="add-practice-form">
                        <?php wp_nonce_field('myhh_add_practice', 'myhh_nonce'); ?>
                        <input type="hidden" name="myhh_form_action" value="submit_practice">
                        
                        <!-- Honeypot for spam -->
                        <div style="position: absolute; left: -5000px;" aria-hidden="true">
                            <input type="text" name="myhh_website_hp" tabindex="-1" autocomplete="off">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <!-- Full Name -->
                            <div>
                                <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                    Your Full Name <span style="color: #EF4444;">*</span>
                                </label>
                                <input type="text" name="provider_name" required 
                                       style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;"
                                       onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                       onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';">
                            </div>
                            
                            <!-- Practice Name -->
                            <div>
                                <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                    Practice/Provider Name <span style="color: #EF4444;">*</span>
                                </label>
                                <input type="text" name="practice_name" required 
                                       style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;"
                                       onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                       onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                            <!-- Email -->
                            <div>
                                <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                    Email Address <span style="color: #EF4444;">*</span>
                                </label>
                                <input type="email" name="provider_email" required 
                                       style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;"
                                       onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                       onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';">
                            </div>
                            
                            <!-- Phone -->
                            <div>
                                <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                    Phone Number
                                </label>
                                <input type="tel" name="provider_phone" 
                                       style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;"
                                       onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                       onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                            <!-- City -->
                            <div>
                                <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                    City/Area <span style="color: #EF4444;">*</span>
                                </label>
                                <select name="provider_city" required 
                                        style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; background: white; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;"
                                        onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                        onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';">
                                    <option value="">Select a city...</option>
                                    <?php if ($cities && !is_wp_error($cities)) : ?>
                                        <?php foreach ($cities as $city) : ?>
                                            <option value="<?php echo esc_attr($city->name); ?>"><?php echo esc_html($city->name); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <option value="Other">Other (specify in notes)</option>
                                </select>
                            </div>
                            
                            <!-- Primary Category (Milestone 3) -->
                            <div>
                                <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                    Primary Category <span style="color: #EF4444;">*</span>
                                </label>
                                <select name="provider_primary_category" required 
                                        style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; background: white; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;"
                                        onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                        onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';">
                                    <option value="">Select a category...</option>
                                    <?php if ($primary_categories && !is_wp_error($primary_categories)) : ?>
                                        <?php foreach ($primary_categories as $category) : ?>
                                            <option value="<?php echo esc_attr($category->slug); ?>"><?php echo esc_html($category->name); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <!-- Specialty -->
                            <div>
                                <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                    Primary Specialty <span style="color: #EF4444;">*</span>
                                </label>
                                <select name="provider_specialty" required 
                                        style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; background: white; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;"
                                        onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                        onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';">
                                    <option value="">Select a specialty...</option>
                                    <?php if ($specialties && !is_wp_error($specialties)) : ?>
                                        <?php foreach ($specialties as $specialty) : ?>
                                            <option value="<?php echo esc_attr($specialty->name); ?>"><?php echo esc_html($specialty->name); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <option value="Other">Other (specify in notes)</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Credentials -->
                        <div style="margin-top: 20px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                Credentials / Licenses
                            </label>
                            <input type="text" name="provider_credentials" placeholder="e.g., LCSW, LPC, LMFT, PhD" 
                                   style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;"
                                   onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                   onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                            <!-- Website -->
                            <div>
                                <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                    Website <span style="color: #EF4444;">*</span>
                                </label>
                                <input type="url" name="provider_website" required placeholder="https://your-website.com" 
                                       style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;"
                                       onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                       onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';">
                            </div>
                            
                            <!-- Booking Link -->
                            <div>
                                <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                    Booking Link
                                </label>
                                <input type="url" name="provider_booking" placeholder="https://..." 
                                       style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;"
                                       onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                       onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';">
                            </div>
                        </div>
                        
                        <!-- Session Type -->
                        <div style="margin-top: 20px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                Session Type <span style="color: #EF4444;">*</span>
                            </label>
                            <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="radio" name="session_type" value="In-Person" required style="width: 18px; height: 18px;">
                                    <span>In-Person</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="radio" name="session_type" value="Virtual" style="width: 18px; height: 18px;">
                                    <span>Virtual</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="radio" name="session_type" value="Hybrid" style="width: 18px; height: 18px;">
                                    <span>Hybrid (Both)</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Insurance -->
                        <div style="margin-top: 20px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                Insurance Accepted
                            </label>
                            <input type="text" name="provider_insurance" placeholder="e.g., Blue Cross, Aetna, Cigna, Self-pay" 
                                   style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;"
                                   onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                   onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';">
                        </div>
                        
                        <!-- Bio -->
                        <div style="margin-top: 20px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                Short Bio / Description <span style="color: #EF4444;">*</span>
                            </label>
                            <textarea name="provider_bio" rows="4" required placeholder="Tell us about your practice, approach, and what clients can expect..." 
                                      style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; resize: vertical; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;"
                                      onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                      onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';"></textarea>
                        </div>
                        
                        <!-- Additional Notes -->
                        <div style="margin-top: 20px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                Additional Notes
                            </label>
                            <textarea name="provider_notes" rows="3" placeholder="Any additional information, specialties, or questions..." 
                                      style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; resize: vertical; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;"
                                      onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                      onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';"></textarea>
                        </div>
                        
                        <!-- Submit Button -->
                        <div style="margin-top: 28px;">
                            <button type="submit" 
                                    style="width: 100%; padding: 16px; background: linear-gradient(135deg, #0A74DA 0%, #1D4ED8 100%); color: white; border: none; border-radius: 12px; font-size: 1.05rem; font-weight: 600; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;"
                                    onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 20px rgba(10, 116, 218, 0.3)';"
                                    onmouseout="this.style.transform='none';this.style.boxShadow='none';">
                                Submit Practice for Review
                            </button>
                        </div>
                        
                        <p style="font-size: 0.8125rem; color: #6B7280; text-align: center; margin: 16px 0 0; line-height: 1.5;">
                            By submitting this form, you confirm that you are authorized to represent this practice. Submissions are reviewed within 2-3 business days.
                        </p>
                    </form>
                </div>
            
            <?php endif; ?>
            
        </div>
    </div>
</section>

<!-- Benefits Section -->
<section style="padding: 60px 0; background: white;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 40px;">
            <span style="display: inline-block; background: #EEF2FF; color: #4F46E5; padding: 6px 16px; border-radius: 20px; font-size: 0.875rem; font-weight: 600; margin-bottom: 12px;">Why Join?</span>
            <h2 style="font-size: 1.75rem; font-weight: 700; color: #1F2937; margin-bottom: 8px;">Benefits of Being Listed</h2>
            <p style="color: #6B7280; max-width: 480px; margin: 0 auto;">Connect with clients searching for wellness services in the Triangle area.</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; max-width: 1000px; margin: 0 auto;">
            <div style="text-align: center; padding: 24px; background: #F8FAFC; border-radius: 16px;">
                <div style="width: 56px; height: 56px; background: #EEF2FF; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0A74DA" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="M21 21l-4.35-4.35"></path>
                    </svg>
                </div>
                <h3 style="font-size: 1.0625rem; font-weight: 600; margin-bottom: 8px; color: #1F2937;">Get Found</h3>
                <p style="color: #6B7280; font-size: 0.875rem; margin: 0; line-height: 1.5;">Appear in local searches for your specialty in the Triangle area.</p>
            </div>
            <div style="text-align: center; padding: 24px; background: #F8FAFC; border-radius: 16px;">
                <div style="width: 56px; height: 56px; background: #D1FAE5; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2">
                        <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
                <h3 style="font-size: 1.0625rem; font-weight: 600; margin-bottom: 8px; color: #1F2937;">Build Trust</h3>
                <p style="color: #6B7280; font-size: 0.875rem; margin: 0; line-height: 1.5;">Claim your profile to manage your information and respond to testimonials.</p>
            </div>
            <div style="text-align: center; padding: 24px; background: #F8FAFC; border-radius: 16px;">
                <div style="width: 56px; height: 56px; background: #FEF3C7; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <h3 style="font-size: 1.0625rem; font-weight: 600; margin-bottom: 8px; color: #1F2937;">Connect</h3>
                <p style="color: #6B7280; font-size: 0.875rem; margin: 0; line-height: 1.5;">Reach clients actively searching for your specific services.</p>
            </div>
            <div style="text-align: center; padding: 24px; background: #F8FAFC; border-radius: 16px;">
                <div style="width: 56px; height: 56px; background: #FCE7F3; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#DB2777" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="3" y1="9" x2="21" y2="9"></line>
                        <line x1="9" y1="21" x2="9" y2="9"></line>
                    </svg>
                </div>
                <h3 style="font-size: 1.0625rem; font-weight: 600; margin-bottom: 8px; color: #1F2937;">Free Listing</h3>
                <p style="color: #6B7280; font-size: 0.875rem; margin: 0; line-height: 1.5;">Basic listings are free. No hidden fees or complicated pricing.</p>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section style="padding: 60px 0; background: #F8FAFC;">
    <div class="container">
        <div style="max-width: 700px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 32px;">
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1F2937;">Frequently Asked Questions</h2>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div style="background: white; border-radius: 12px; padding: 24px; border: 1px solid #E5E7EB;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #1F2937; margin: 0 0 8px;">How long does it take to get listed?</h3>
                    <p style="color: #6B7280; font-size: 0.9375rem; line-height: 1.7; margin: 0;">We review submissions within 2-3 business days. Once approved, your listing will appear in our directory.</p>
                </div>
                <div style="background: white; border-radius: 12px; padding: 24px; border: 1px solid #E5E7EB;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #1F2937; margin: 0 0 8px;">Is there a cost to be listed?</h3>
                    <p style="color: #6B7280; font-size: 0.9375rem; line-height: 1.7; margin: 0;">Basic listings are completely free. There are no hidden fees or charges for being included in our directory.</p>
                </div>
                <div style="background: white; border-radius: 12px; padding: 24px; border: 1px solid #E5E7EB;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #1F2937; margin: 0 0 8px;">What's the difference between Add Your Practice and Claim Profile?</h3>
                    <p style="color: #6B7280; font-size: 0.9375rem; line-height: 1.7; margin: 0;">"Add Your Practice" is for providers not yet listed on MyHealthHub. "Claim Profile" is for providers who already have a listing and want to manage it.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
