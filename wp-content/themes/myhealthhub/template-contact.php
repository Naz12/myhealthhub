<?php
/**
 * Template Name: Contact Page
 *
 * @package MyHealthHub
 */

get_header();

// Detect upgrade parameter safely
$upgrade_tier = '';
$upgrade_tier_label = '';
$upgrade_tier_price = '';
if ( isset( $_GET['upgrade'] ) ) {
    $raw_upgrade = sanitize_text_field( wp_unslash( $_GET['upgrade'] ) );
    if ( $raw_upgrade === 'pulse-lite' ) {
        $upgrade_tier       = 'pulse-lite';
        $upgrade_tier_label = 'Pulse Lite';
        $upgrade_tier_price = 'only $97/month';
    } elseif ( $raw_upgrade === 'pulse-pro' ) {
        $upgrade_tier       = 'pulse-pro';
        $upgrade_tier_label = 'Pulse Pro';
        $upgrade_tier_price = 'only $197/month';
    }
}
?>

<!-- Hero Section -->
<section class="page-hero" style="background: linear-gradient(135deg, #0A74DA 0%, #1e40af 100%); padding: 80px 0; color: white; text-align: center;">
    <div class="container">
        <?php if ( $upgrade_tier ) : ?>
            <h1 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 16px;">Upgrade to <?php echo esc_html( $upgrade_tier_label ); ?></h1>
            <p style="font-size: 1.25rem; opacity: 0.9; max-width: 600px; margin: 0 auto;">You're one step away from upgrading your listing. Let us know you're interested and we'll get you started.</p>
        <?php else : ?>
            <h1 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 16px;">Contact Us</h1>
            <p style="font-size: 1.25rem; opacity: 0.9; max-width: 600px; margin: 0 auto;">We'd love to hear from you. Get in touch with our team.</p>
        <?php endif; ?>
    </div>
</section>

<!-- Contact Section -->
<section class="section-standard" style="padding: 80px 0; background: white;">
    <div class="container">
        <div class="contact-layout" style="display: grid; grid-template-columns: 1fr 1fr; gap: 64px; max-width: 1000px; margin: 0 auto;">
            
            <!-- Contact Form -->
            <div class="form-card" style="background: white; border-radius: 20px; padding: 40px; border: 1px solid #E5E7EB; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                <?php if ( $upgrade_tier ) : ?>
                    <!-- Upgrade context banner -->
                    <div style="background: linear-gradient(135deg, #EEF2FF 0%, #DBEAFE 100%); border: 1px solid #BFDBFE; border-radius: 12px; padding: 20px 24px; margin-bottom: 24px;">
                        <p style="font-weight: 600; color: #1E40AF; margin: 0 0 6px 0; font-size: 1.05rem;">
                            <?php echo esc_html( $upgrade_tier_label ); ?> — <?php echo esc_html( $upgrade_tier_price ); ?>
                        </p>
                        <p style="color: #374151; margin: 0; font-size: 0.95rem;">
                            Complete the form below and our team will follow up with everything you need to get started.
                        </p>
                    </div>
                    <input type="hidden" name="upgrade_tier" value="<?php echo esc_attr( $upgrade_tier ); ?>">
                <?php endif; ?>

                <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 8px; color: #1F2937;">
                    <?php echo $upgrade_tier ? 'Tell Us About Your Practice' : 'Send us a Message'; ?>
                </h2>
                <p style="color: #6B7280; margin-bottom: 32px;">Fill out the form below and we'll get back to you within 24 hours.</p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('myhh_contact', 'myhh_nonce'); ?>
                    <input type="hidden" name="myhh_form_action" value="contact">
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151;">Your Name <span style="color: #EF4444;">*</span></label>
                        <input type="text" name="contact_name" required style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s;" 
                            onfocus="this.style.borderColor='#0A74DA'" onblur="this.style.borderColor='#E5E7EB'">
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151;">Email Address <span style="color: #EF4444;">*</span></label>
                        <input type="email" name="contact_email" required style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s;"
                            onfocus="this.style.borderColor='#0A74DA'" onblur="this.style.borderColor='#E5E7EB'">
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151;">Subject</label>
                        <select name="contact_subject" style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; background: white; transition: border-color 0.2s;"
                            onfocus="this.style.borderColor='#0A74DA'" onblur="this.style.borderColor='#E5E7EB'">
                            <option value="">Select a topic</option>
                            <option value="general"<?php echo ( ! $upgrade_tier && false ) ? ' selected' : ''; ?>>General Inquiry</option>
                            <option value="provider">I'm a Provider</option>
                            <option value="upgrade"<?php echo $upgrade_tier ? ' selected' : ''; ?>>Pulse Upgrade Inquiry</option>
                            <option value="support">Technical Support</option>
                            <option value="feedback">Feedback</option>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 24px;">
                        <label style="display: block; font-weight: 500; margin-bottom: 8px; color: #374151;">Message <span style="color: #EF4444;">*</span></label>
                        <textarea name="contact_message" rows="5" required style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; resize: vertical; transition: border-color 0.2s;"
                            onfocus="this.style.borderColor='#0A74DA'" onblur="this.style.borderColor='#E5E7EB'"></textarea>
                    </div>
                    
                    <button type="submit" style="width: 100%; padding: 16px; background: linear-gradient(135deg, #0A74DA 0%, #1D4ED8 100%); color: white; border: none; border-radius: 10px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;"
                        onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 20px rgba(10, 116, 218, 0.3)';"
                        onmouseout="this.style.transform='none';this.style.boxShadow='none';">
                        Send Message
                    </button>
                </form>
            </div>
            
            <!-- Contact Info -->
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 8px; color: #1F2937;">Get in Touch</h2>
                <p style="color: #6B7280; margin-bottom: 32px;">We're here to help with any questions about finding a provider or using our directory.</p>
                
                <!-- Contact Cards -->
                <div class="contact-cards" style="display: flex; flex-direction: column; gap: 20px;">
                    <div style="display: flex; gap: 16px; padding: 24px; background: #F8FAFC; border-radius: 16px;">
                        <div class="icon-box" style="width: 56px; height: 56px; background: linear-gradient(135deg, #0A74DA 0%, #1D4ED8 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg width="24" height="24" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 4px; color: #1F2937;">Email</h4>
                            <p style="color: #6B7280; margin: 0; font-size: 0.95rem;">hello@myhealthub.com</p>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 16px; padding: 24px; background: #F8FAFC; border-radius: 16px;">
                        <div class="icon-box" style="width: 56px; height: 56px; background: linear-gradient(135deg, #059669 0%, #047857 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg width="24" height="24" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 4px; color: #1F2937;">Business Hours</h4>
                            <p style="color: #6B7280; margin: 0; font-size: 0.95rem;">Mon - Fri: 9:00 AM - 5:00 PM EST</p>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 16px; padding: 24px; background: #F8FAFC; border-radius: 16px;">
                        <div class="icon-box" style="width: 56px; height: 56px; background: linear-gradient(135deg, #D97706 0%, #B45309 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg width="24" height="24" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 4px; color: #1F2937;">Location</h4>
                            <p style="color: #6B7280; margin: 0; font-size: 0.95rem;">Raleigh/Triangle Area, NC</p>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="quick-links-card" style="margin-top: 40px; padding: 32px; background: linear-gradient(135deg, #EEF2FF 0%, #DBEAFE 100%); border-radius: 16px;">
                    <h4 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 16px; color: #1F2937;">Quick Links</h4>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <a href="<?php echo esc_url(home_url('/providers/')); ?>" style="display: flex; align-items: center; gap: 8px; color: #0A74DA; font-weight: 500; text-decoration: none;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            Browse Providers
                        </a>
                        <a href="<?php echo esc_url(home_url('/get-matched/')); ?>" style="display: flex; align-items: center; gap: 8px; color: #0A74DA; font-weight: 500; text-decoration: none;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            Get Matched
                        </a>
                        <a href="<?php echo esc_url(home_url('/about/')); ?>" style="display: flex; align-items: center; gap: 8px; color: #0A74DA; font-weight: 500; text-decoration: none;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            About Us
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
get_footer();
