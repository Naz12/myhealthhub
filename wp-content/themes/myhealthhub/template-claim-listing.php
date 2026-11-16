<?php
/**
 * Template Name: Claim Listing
 *
 * @package MyHealthHub
 * @version 2.0.0
 */

get_header();

$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;
$listing = $listing_id ? get_post($listing_id) : null;

// Get listing meta for display
$listing_city = $listing_id ? get_the_terms($listing_id, 'myhh_city') : null;
$listing_specialties = $listing_id ? get_the_terms($listing_id, 'myhh_specialty') : null;
?>

<!-- Hero Section -->
<section class="page-hero" style="background: linear-gradient(135deg, #0A74DA 0%, #0858a8 100%); padding: 80px 0 60px; color: white; text-align: center; position: relative; overflow: hidden;">
    <div style="position: absolute; top: -30px; right: -30px; width: 200px; height: 200px; background: rgba(255,255,255,0.05); border-radius: 50%;"></div>
    <div style="position: absolute; bottom: -50px; left: 10%; width: 150px; height: 150px; background: rgba(255,255,255,0.03); border-radius: 50%;"></div>
    <div class="container" style="position: relative; z-index: 1;">
        <div style="display: inline-flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.15); padding: 8px 18px; border-radius: 30px; margin-bottom: 20px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 12l2 2 4-4"></path>
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
            <span style="font-weight: 500;">Verify Your Identity</span>
        </div>
        <h1 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 12px; letter-spacing: -0.02em;">Claim Your Listing</h1>
        <p style="font-size: 1.125rem; opacity: 0.9; max-width: 480px; margin: 0 auto; line-height: 1.6;">Take control of your profile to manage information, respond to testimonials, and connect with clients.</p>
    </div>
</section>

<!-- Form Section -->
<section class="section-standard" style="padding: 60px 0; background: #F8FAFC;">
    <div class="container">
        <div style="max-width: 600px; margin: 0 auto;">
            
            <?php if (isset($_GET['success']) && $_GET['success'] == '1') : ?>
                <!-- Success State -->
                <div style="background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%); border-radius: 20px; padding: 48px; text-align: center;">
                    <div style="width: 80px; height: 80px; background: #059669; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                        <svg width="40" height="40" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 12px; color: #065F46;">Claim Received!</h2>
                    <p style="color: #047857; font-size: 1.05rem; margin-bottom: 24px; line-height: 1.6;">Your claim has been received and the profile has been marked as claimed. Please check your email for confirmation and next steps.</p>
                    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                        <a href="<?php echo esc_url(home_url('/listings/')); ?>" style="display: inline-flex; align-items: center; gap: 8px; background: #059669; color: white; padding: 14px 24px; border-radius: 10px; font-weight: 600; text-decoration: none;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                            </svg>
                            Back to Providers
                        </a>
                        <a href="<?php echo esc_url(home_url('/')); ?>" style="display: inline-flex; align-items: center; gap: 8px; background: white; color: #059669; padding: 14px 24px; border-radius: 10px; font-weight: 600; text-decoration: none; border: 2px solid #059669;">
                            Go Home
                        </a>
                    </div>
                </div>
            <?php else : ?>
            
                <?php if ($listing) : ?>
                    <!-- Listing Preview Card -->
                    <div style="background: white; border-radius: 16px; padding: 24px; margin-bottom: 24px; border: 1px solid #E5E7EB; box-shadow: 0 2px 12px rgba(0,0,0,0.04); display: flex; align-items: center; gap: 20px;">
                        <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #EEF2FF 0%, #DBEAFE 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0A74DA" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 0.75rem; color: #6B7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">You are claiming</div>
                            <h3 style="font-size: 1.125rem; font-weight: 600; margin: 0 0 4px; color: #1F2937; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo esc_html($listing->post_title); ?></h3>
                            <?php if ($listing_city && !is_wp_error($listing_city)) : ?>
                                <div style="display: flex; align-items: center; gap: 4px; color: #6B7280; font-size: 0.875rem;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                    <?php echo esc_html($listing_city[0]->name); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo esc_url(get_permalink($listing_id)); ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 6px; color: #0A74DA; font-weight: 500; text-decoration: none; font-size: 0.875rem; padding: 8px 12px; background: #EFF6FF; border-radius: 8px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                            View
                        </a>
                    </div>
                <?php endif; ?>
                
                <!-- Form Card -->
                <div style="background: white; border-radius: 20px; padding: 40px; border: 1px solid #E5E7EB; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <div style="text-align: center; margin-bottom: 32px;">
                        <div style="width: 56px; height: 56px; background: #EEF2FF; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <h2 style="font-size: 1.375rem; font-weight: 700; margin-bottom: 8px; color: #1F2937;">Claim Your Free Profile</h2>
                        <p style="color: #6B7280; margin: 0; font-size: 0.9375rem;">Verify that you own or represent this listing so you can keep it updated.</p>
                    </div>
                    
                    <form method="post" action="" class="claim-listing-form">
                        <?php wp_nonce_field('myhh_claim_listing', 'myhh_nonce'); ?>
                        <input type="hidden" name="myhh_form_action" value="submit_claim">
                        <input type="hidden" name="listing_id" value="<?php echo esc_attr($listing_id); ?>">
                        
                        <!-- Name Field -->
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                Full Name <span style="color: #EF4444;">*</span>
                            </label>
                            <input type="text" id="claim_name" name="claim_name" required 
                                   style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s, box-shadow 0.2s;"
                                   onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                   onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';">
                        </div>
                        
                        <!-- Email Field -->
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                Email Address <span style="color: #EF4444;">*</span>
                            </label>
                            <input type="email" id="claim_email" name="claim_email" required 
                                   style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s, box-shadow 0.2s;"
                                   onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                   onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';">
                        </div>
                        
                        <!-- Phone Field -->
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                Phone Number
                            </label>
                            <input type="tel" id="claim_phone" name="claim_phone" 
                                   style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s, box-shadow 0.2s;"
                                   onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                   onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';">
                        </div>
                        
                        <!-- Proof Link Field -->
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                Proof Link <span style="color: #EF4444;">*</span>
                            </label>
                            <input type="url" id="claim_proof" name="claim_proof" required 
                                   placeholder="https://your-website.com or https://psychologytoday.com/..."
                                   style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s, box-shadow 0.2s;"
                                   onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                   onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';">
                            <p style="font-size: 0.8125rem; color: #6B7280; margin-top: 8px; line-height: 1.5;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                                Use your professional website, Psychology Today profile, license verification page, or another public profile that helps confirm ownership.
                            </p>
                        </div>
                        
                        <!-- Notes Field -->
                        <div style="margin-bottom: 28px;">
                            <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 0.9375rem;">
                                Additional Notes
                            </label>
                            <textarea id="claim_notes" name="claim_notes" rows="4" 
                                      placeholder="Any additional information to help us verify your identity..."
                                      style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; resize: vertical; transition: border-color 0.2s, box-shadow 0.2s;"
                                      onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';" 
                                      onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';"></textarea>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" 
                                style="width: 100%; padding: 16px; background: linear-gradient(135deg, #0A74DA 0%, #1D4ED8 100%); color: white; border: none; border-radius: 12px; font-size: 1.05rem; font-weight: 600; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;"
                                onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 20px rgba(10, 116, 218, 0.3)';"
                                onmouseout="this.style.transform='none';this.style.boxShadow='none';">
                            Claim Your Free Profile
                        </button>
                        
                        <p style="font-size: 0.8125rem; color: #6B7280; text-align: center; margin: 16px 0 0; line-height: 1.5;">
                            By submitting this form, you confirm that you are the owner or authorized representative of this listing.
                        </p>
                    </form>
                </div>
            
            <?php endif; ?>
            
        </div>
    </div>
</section>

<!-- Trust Section -->
<section style="padding: 60px 0; background: white;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 40px;">
            <span style="display: inline-block; background: #EEF2FF; color: #4F46E5; padding: 6px 16px; border-radius: 20px; font-size: 0.875rem; font-weight: 600; margin-bottom: 12px;">Why Claim?</span>
            <h2 style="font-size: 1.75rem; font-weight: 700; color: #1F2937; margin-bottom: 8px;">Benefits of Claiming Your Listing</h2>
            <p style="color: #6B7280; max-width: 480px; margin: 0 auto;">Take full control of your professional profile on MyHealthHub.</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; max-width: 900px; margin: 0 auto;">
            <div style="text-align: center; padding: 24px; background: #F8FAFC; border-radius: 16px;">
                <div style="width: 56px; height: 56px; background: #EEF2FF; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                </div>
                <h3 style="font-size: 1.0625rem; font-weight: 600; margin-bottom: 8px; color: #1F2937;">Update Information</h3>
                <p style="color: #6B7280; font-size: 0.875rem; margin: 0; line-height: 1.5;">Keep your profile accurate with current services, hours, and contact info.</p>
            </div>
            <div style="text-align: center; padding: 24px; background: #F8FAFC; border-radius: 16px;">
                <div style="width: 56px; height: 56px; background: #D1FAE5; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                </div>
                <h3 style="font-size: 1.0625rem; font-weight: 600; margin-bottom: 8px; color: #1F2937;">Respond to Testimonials</h3>
                <p style="color: #6B7280; font-size: 0.875rem; margin: 0; line-height: 1.5;">Engage with clients and build trust through professional responses.</p>
            </div>
            <div style="text-align: center; padding: 24px; background: #F8FAFC; border-radius: 16px;">
                <div style="width: 56px; height: 56px; background: #FEF3C7; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                    </svg>
                </div>
                <h3 style="font-size: 1.0625rem; font-weight: 600; margin-bottom: 8px; color: #1F2937;">Gain Insights</h3>
                <p style="color: #6B7280; font-size: 0.875rem; margin: 0; line-height: 1.5;">See how many people view your profile and connect with potential clients.</p>
            </div>
        </div>
    </div>
</section>

<?php
get_footer();
