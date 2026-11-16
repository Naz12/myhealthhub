<?php
/**
 * Form Handler Class
 *
 * Handles all form submissions (leads, claims, testimonials)
 *
 * @package MyHealthHub
 * @subpackage Core
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Handler Class
 */
class MyHH_Form_Handler {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('template_redirect', array($this, 'handle_form_submissions'));
        add_action('geodir_after_single_content', array($this, 'add_claim_button'));
        add_action('geodir_after_single_content', array($this, 'add_testimonial_form'));
        
        // Hook for submission approval propagation (Milestone 3)
        add_action('save_post_myhh_submission', array($this, 'propagate_category_on_approval'), 20, 3);
    }
    
    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        if (!isset($_POST['myhh_form_action'])) {
            return;
        }
        
        $action = sanitize_text_field($_POST['myhh_form_action']);
        
        switch ($action) {
            case 'submit_lead':
                $this->handle_lead_submission();
                break;
            case 'submit_claim':
                $this->handle_claim_submission();
                break;
            case 'submit_testimonial':
                $this->handle_testimonial_submission();
                break;
            case 'submit_practice':
                $this->handle_practice_submission();
                break;
        }
    }
    
    /**
     * Handle lead submission
     */
    private function handle_lead_submission() {
        // Verify nonce
        if (!isset($_POST['myhh_nonce']) || !wp_verify_nonce($_POST['myhh_nonce'], 'myhh_get_matched')) {
            return;
        }
        
        // Sanitize input
        $name = sanitize_text_field($_POST['lead_name'] ?? '');
        $email = sanitize_email($_POST['lead_email'] ?? '');
        $city = sanitize_text_field($_POST['lead_city'] ?? '');
        $preference = sanitize_text_field($_POST['lead_preference'] ?? '');
        $message = sanitize_textarea_field($_POST['lead_message'] ?? '');
        
        if (empty($name) || empty($email)) {
            return;
        }
        
        // Always store lead locally
        $lead_id = wp_insert_post(array(
            'post_title'   => $name,
            'post_type'    => 'myhh_lead',
            'post_status'  => 'publish',
            'post_content' => $message,
        ));
        
        if ($lead_id) {
            update_post_meta($lead_id, '_myhh_email', $email);
            update_post_meta($lead_id, '_myhh_city', $city);
            update_post_meta($lead_id, '_myhh_preference', $preference);
            update_post_meta($lead_id, '_myhh_status', 'new');
        }
        
        // Send to GHL webhook if configured
        $webhook_url = get_option('myhh_ghl_webhook_url');
        if ($webhook_url) {
            $data = array(
                'name'       => $name,
                'email'      => $email,
                'city'       => $city,
                'preference' => $preference,
                'message'    => $message,
                'source'     => 'My Health Hub - Get Matched',
                'timestamp'  => current_time('mysql'),
            );
            
            wp_remote_post($webhook_url, array(
                'body'    => json_encode($data),
                'headers' => array('Content-Type' => 'application/json'),
                'timeout' => 30,
            ));
        }
        
        // Email admin
        $this->send_admin_notification('New Lead Submission', array(
            'Name'       => $name,
            'Email'      => $email,
            'City'       => $city,
            'Preference' => $preference,
            'Message'    => $message,
        ));
        
        // Redirect with success
        wp_redirect(add_query_arg('success', '1', wp_get_referer()));
        exit;
    }
    
    /**
     * Handle claim submission
     */
    private function handle_claim_submission() {
        // Verify nonce
        if (!isset($_POST['myhh_nonce']) || !wp_verify_nonce($_POST['myhh_nonce'], 'myhh_claim_listing')) {
            return;
        }
        
        // Sanitize input
        $listing_id = intval($_POST['listing_id'] ?? 0);
        $name = sanitize_text_field($_POST['claim_name'] ?? '');
        $email = sanitize_email($_POST['claim_email'] ?? '');
        $phone = sanitize_text_field($_POST['claim_phone'] ?? '');
        $proof = esc_url_raw($_POST['claim_proof'] ?? '');
        $notes = sanitize_textarea_field($_POST['claim_notes'] ?? '');
        
        if (empty($name) || empty($email) || empty($proof)) {
            return;
        }
        
        $listing_title = $listing_id ? get_the_title($listing_id) : 'Unknown Listing';
        
        // Create claim post
        $claim_id = wp_insert_post(array(
            'post_title'   => sprintf('%s - %s', $listing_title, $name),
            'post_type'    => 'myhh_claim',
            'post_status'  => 'publish',
            'post_content' => $notes,
        ));
        
        if ($claim_id) {
            update_post_meta($claim_id, '_myhh_listing_id', $listing_id);
            update_post_meta($claim_id, '_myhh_claimant_name', $name);
            update_post_meta($claim_id, '_myhh_claimant_email', $email);
            update_post_meta($claim_id, '_myhh_claimant_phone', $phone);
            update_post_meta($claim_id, '_myhh_proof_link', $proof);
            update_post_meta($claim_id, '_myhh_status', 'approved');
            
            // Auto-approve: Mark listing as claimed immediately
            if ($listing_id) {
                update_post_meta($listing_id, '_myhh_claimed', true);
                update_post_meta($listing_id, '_myhh_claimed_by', $email);
                update_post_meta($listing_id, '_myhh_claimed_date', current_time('mysql'));
            }
            
            // Send confirmation email to claimant
            $this->send_claim_confirmation_email($claim_id, $email, $name, $listing_title, $listing_id);
        }
        
        // Email admin about the auto-approved claim
        $this->send_admin_notification('Listing Claim Auto-Approved', array(
            'Listing'     => $listing_title,
            'Claimant'    => $name,
            'Email'       => $email,
            'Phone'       => $phone,
            'Proof Link'  => $proof,
            'Notes'       => $notes,
            'Status'      => 'Auto-Approved',
        ));
        
        // Redirect with success
        wp_redirect(add_query_arg('success', '1', wp_get_referer()));
        exit;
    }
    
    /**
     * Handle testimonial submission
     */
    private function handle_testimonial_submission() {
        // Verify nonce
        if (!isset($_POST['myhh_nonce']) || !wp_verify_nonce($_POST['myhh_nonce'], 'myhh_testimonial')) {
            return;
        }
        
        // Sanitize input
        $listing_id = intval($_POST['listing_id'] ?? 0);
        $author_name = sanitize_text_field($_POST['testimonial_name'] ?? '');
        $rating = intval($_POST['testimonial_rating'] ?? 5);
        $content = sanitize_textarea_field($_POST['testimonial_content'] ?? '');
        
        if (empty($author_name) || empty($content)) {
            return;
        }
        
        $listing_title = $listing_id ? get_the_title($listing_id) : 'Unknown Listing';
        
        // Create testimonial post
        $testimonial_id = wp_insert_post(array(
            'post_title'   => sprintf('Testimonial for %s by %s', $listing_title, $author_name),
            'post_type'    => 'myhh_testimonial',
            'post_status'  => 'publish',
            'post_content' => $content,
        ));
        
        if ($testimonial_id) {
            update_post_meta($testimonial_id, '_myhh_listing_id', $listing_id);
            update_post_meta($testimonial_id, '_myhh_author_name', $author_name);
            update_post_meta($testimonial_id, '_myhh_rating', $rating);
            
            // Set status to pending
            wp_set_object_terms($testimonial_id, 'pending', 'myhh_testimonial_status');
        }
        
        // Redirect with success
        wp_redirect(add_query_arg('testimonial_submitted', '1', wp_get_referer()));
        exit;
    }
    
    /**
     * Send admin notification email
     */
    private function send_admin_notification($subject, $data) {
        $admin_email = get_option('myhh_notification_email') ?: get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $message = sprintf("New submission on %s:\n\n", $site_name);
        foreach ($data as $key => $value) {
            $message .= sprintf("%s: %s\n", $key, $value);
        }
        $message .= sprintf("\n---\nView in admin: %s", admin_url('admin.php?page=myhh-dashboard'));
        
        wp_mail($admin_email, sprintf('[%s] %s', $site_name, $subject), $message);
    }
    
    /**
     * Send claim confirmation email to claimant
     */
    private function send_claim_confirmation_email($claim_id, $email, $name, $listing_title, $listing_id) {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $listing_url = $listing_id ? get_permalink($listing_id) : $site_url;
        
        $subject = sprintf('[%s] Your Listing Claim Has Been Approved', $site_name);
        
        $message = "Dear {$name},\n\n";
        $message .= "Congratulations! Your claim for \"{$listing_title}\" has been approved.\n\n";
        $message .= "You now have ownership of this listing on {$site_name}.\n\n";
        $message .= "Listing URL: {$listing_url}\n\n";
        $message .= "If you have any questions, please don't hesitate to contact us.\n\n";
        $message .= "Best regards,\n{$site_name} Team\n{$site_url}";
        
        wp_mail($email, $subject, $message);
    }
    
    /**
     * Add claim button to listing
     */
    public function add_claim_button() {
        if (!is_singular('gd_place')) {
            return;
        }
        
        $listing_id = get_the_ID();
        ?>
        <div class="claim-listing">
            <h3 class="claim-listing__title"><?php _e('Is this your listing?', 'myhealthhub'); ?></h3>
            <p class="claim-listing__desc"><?php _e('Claim this listing to manage your information and respond to reviews.', 'myhealthhub'); ?></p>
            <a href="<?php echo esc_url(add_query_arg('listing_id', $listing_id, home_url('/claim-listing/'))); ?>" class="btn btn--outline">
                <?php _e('Claim Your Free Profile', 'myhealthhub'); ?>
            </a>
        </div>
        <?php
    }
    
    /**
     * Add testimonial form to listing
     */
    public function add_testimonial_form() {
        if (!is_singular('gd_place')) {
            return;
        }
        
        $listing_id = get_the_ID();
        
        // Display existing testimonials
        $this->display_testimonials($listing_id);
        
        // Show form
        if (isset($_GET['testimonial_submitted'])) {
            ?>
            <div class="form-success" style="margin: 20px 0;">
                <p><?php _e('Thank you! Your testimonial has been submitted for review.', 'myhealthhub'); ?></p>
            </div>
            <?php
        }
        ?>
        <div class="testimonial-form-wrapper" style="margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 8px;">
            <h3><?php _e('Leave a Testimonial', 'myhealthhub'); ?></h3>
            <form method="post" action="">
                <?php wp_nonce_field('myhh_testimonial', 'myhh_nonce'); ?>
                <input type="hidden" name="myhh_form_action" value="submit_testimonial">
                <input type="hidden" name="listing_id" value="<?php echo esc_attr($listing_id); ?>">
                
                <div class="form-group">
                    <label for="testimonial_name" class="form-label"><?php _e('Your Name', 'myhealthhub'); ?> *</label>
                    <input type="text" id="testimonial_name" name="testimonial_name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label for="testimonial_rating" class="form-label"><?php _e('Rating', 'myhealthhub'); ?></label>
                    <select id="testimonial_rating" name="testimonial_rating" class="form-select">
                        <option value="5">5 - Excellent</option>
                        <option value="4">4 - Very Good</option>
                        <option value="3">3 - Good</option>
                        <option value="2">2 - Fair</option>
                        <option value="1">1 - Poor</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="testimonial_content" class="form-label"><?php _e('Your Testimonial', 'myhealthhub'); ?> *</label>
                    <textarea id="testimonial_content" name="testimonial_content" class="form-textarea" rows="4" required></textarea>
                </div>
                
                <button type="submit" class="btn btn--primary"><?php _e('Submit Testimonial', 'myhealthhub'); ?></button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Display testimonials for a listing
     */
    private function display_testimonials($listing_id) {
        $testimonials = get_posts(array(
            'post_type'      => 'myhh_testimonial',
            'posts_per_page' => 10,
            'tax_query'      => array(
                array(
                    'taxonomy' => 'myhh_testimonial_status',
                    'field'    => 'slug',
                    'terms'    => 'approved',
                ),
            ),
            'meta_query'     => array(
                array(
                    'key'   => '_myhh_listing_id',
                    'value' => $listing_id,
                ),
            ),
        ));
        
        if (empty($testimonials)) {
            return;
        }
        ?>
        <div class="testimonials-section" style="margin-top: 30px;">
            <h3><?php _e('Client Testimonials', 'myhealthhub'); ?></h3>
            <?php foreach ($testimonials as $testimonial) : 
                $rating = get_post_meta($testimonial->ID, '_myhh_rating', true);
                $author = get_post_meta($testimonial->ID, '_myhh_author_name', true);
            ?>
                <div class="testimonial" style="padding: 15px; background: #f8fafc; border-radius: 8px; margin-bottom: 15px;">
                    <?php if ($rating) : ?>
                        <div class="testimonial-rating" style="color: #f59e0b; margin-bottom: 10px;">
                            <?php echo str_repeat('★', intval($rating)) . str_repeat('☆', 5 - intval($rating)); ?>
                        </div>
                    <?php endif; ?>
                    <div class="testimonial-content" style="font-style: italic; margin-bottom: 10px;">
                        "<?php echo esc_html($testimonial->post_content); ?>"
                    </div>
                    <div class="testimonial-author" style="font-weight: 600;">
                        — <?php echo esc_html($author); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Handle practice submission
     */
    private function handle_practice_submission() {
        // Verify nonce
        if (!isset($_POST['myhh_nonce']) || !wp_verify_nonce($_POST['myhh_nonce'], 'myhh_add_practice')) {
            return;
        }
        
        // Honeypot spam check
        if (!empty($_POST['myhh_website_hp'])) {
            return;
        }
        
        // Sanitize input
        $name = sanitize_text_field($_POST['provider_name'] ?? '');
        $practice_name = sanitize_text_field($_POST['practice_name'] ?? '');
        $email = sanitize_email($_POST['provider_email'] ?? '');
        $phone = sanitize_text_field($_POST['provider_phone'] ?? '');
        $city = sanitize_text_field($_POST['provider_city'] ?? '');
        $primary_category = sanitize_text_field($_POST['provider_primary_category'] ?? ''); // Milestone 3
        $specialty = sanitize_text_field($_POST['provider_specialty'] ?? '');
        $credentials = sanitize_text_field($_POST['provider_credentials'] ?? '');
        $website = esc_url_raw($_POST['provider_website'] ?? '');
        $booking = esc_url_raw($_POST['provider_booking'] ?? '');
        $session_type = sanitize_text_field($_POST['session_type'] ?? '');
        $insurance = sanitize_text_field($_POST['provider_insurance'] ?? '');
        $bio = sanitize_textarea_field($_POST['provider_bio'] ?? '');
        $notes = sanitize_textarea_field($_POST['provider_notes'] ?? '');
        
        if (empty($name) || empty($practice_name) || empty($email) || empty($website) || empty($bio)) {
            return;
        }
        
        // Create submission post
        $submission_id = wp_insert_post(array(
            'post_title'   => sprintf('%s - %s', $practice_name, $name),
            'post_type'    => 'myhh_submission',
            'post_status'  => 'publish',
            'post_content' => $bio,
        ));
        
        if ($submission_id) {
            update_post_meta($submission_id, '_myhh_provider_name', $name);
            update_post_meta($submission_id, '_myhh_practice_name', $practice_name);
            update_post_meta($submission_id, '_myhh_email', $email);
            update_post_meta($submission_id, '_myhh_phone', $phone);
            update_post_meta($submission_id, '_myhh_city', $city);
            update_post_meta($submission_id, '_myhh_primary_category', $primary_category); // Milestone 3
            update_post_meta($submission_id, '_myhh_specialty', $specialty);
            update_post_meta($submission_id, '_myhh_credentials', $credentials);
            update_post_meta($submission_id, '_myhh_website', $website);
            update_post_meta($submission_id, '_myhh_booking_link', $booking);
            update_post_meta($submission_id, '_myhh_session_type', $session_type);
            update_post_meta($submission_id, '_myhh_insurance', $insurance);
            update_post_meta($submission_id, '_myhh_notes', $notes);
            update_post_meta($submission_id, '_myhh_status', 'pending');
            update_post_meta($submission_id, '_myhh_submitted', current_time('mysql'));
        }
        
        // Email admin
        $this->send_admin_notification('New Practice Submission', array(
            'Practice Name'   => $practice_name,
            'Provider Name'   => $name,
            'Email'           => $email,
            'Phone'           => $phone,
            'City'            => $city,
            'Primary Category' => $primary_category, // Milestone 3
            'Specialty'       => $specialty,
            'Credentials'     => $credentials,
            'Website'         => $website,
            'Booking Link'    => $booking,
            'Session Type'    => $session_type,
            'Insurance'       => $insurance,
            'Bio'             => $bio,
            'Notes'           => $notes,
        ));
        
        // Redirect with success
        wp_redirect(add_query_arg('success', '1', wp_get_referer()));
        exit;
    }
    
    /**
     * Propagate primary category to live provider when submission is approved (Milestone 3)
     * 
     * This method hooks into save_post_myhh_submission to detect when a submission's
     * status changes to 'approved' and propagate the primary category to the associated
     * live gd_place provider listing.
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public function propagate_category_on_approval($post_id, $post, $update) {
        // Don't run on autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Don't run on revisions
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // Only run on updates (not initial creation)
        if (!$update) {
            return;
        }
        
        // Check if status is being changed to approved
        $status = get_post_meta($post_id, '_myhh_status', true);
        if ($status !== 'approved') {
            return;
        }
        
        // Check if we've already propagated (prevent duplicate processing)
        if (get_post_meta($post_id, '_myhh_category_propagated', true)) {
            return;
        }
        
        // Get the associated provider listing ID (if any)
        $provider_id = get_post_meta($post_id, '_myhh_provider_id', true);
        if (!$provider_id) {
            return;
        }
        
        // Get the primary category from submission
        $primary_category = get_post_meta($post_id, '_myhh_primary_category', true);
        
        // If no category was selected, default to Mental Health & Therapy
        if (empty($primary_category)) {
            $primary_category = 'mental-health-therapy';
        }
        
        // Verify the category exists
        $term = get_term_by('slug', $primary_category, 'myhh_primary_category');
        if (!$term) {
            // Fallback to default if term doesn't exist
            $term = get_term_by('slug', 'mental-health-therapy', 'myhh_primary_category');
        }
        
        if ($term) {
            // Assign the category to the live provider
            wp_set_object_terms($provider_id, $term->term_id, 'myhh_primary_category', true);
            
            // Mark as propagated
            update_post_meta($post_id, '_myhh_category_propagated', current_time('mysql'));
        }
    }
}
