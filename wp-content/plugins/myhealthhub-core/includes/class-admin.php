<?php
/**
 * Admin Class
 *
 * Handles admin interface, menus, and dashboard
 *
 * @package MyHealthHub
 * @subpackage Core
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Class
 */
class MyHH_Admin {
    
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        
        // Practice submission columns
        add_filter('manage_myhh_submission_posts_columns', array($this, 'practice_submission_columns'));
        add_action('manage_myhh_submission_posts_custom_column', array($this, 'practice_submission_column_content'), 10, 2);
        
        // Handle tab redirect after settings save
        add_filter('wp_redirect', array($this, 'settings_tab_redirect'), 10, 2);
        
        // AJAX handler for SMTP test email
        add_action('wp_ajax_myhh_test_smtp_email', array($this, 'ajax_test_smtp_email'));
        
        // AJAX handler for Google Maps key test
        add_action('wp_ajax_myhh_test_maps_key', array($this, 'ajax_test_maps_key'));
        add_action('wp_ajax_myhh_test_openai_key', array($this, 'ajax_test_openai_key'));
        
        // AJAX handler for Hive/DeepSeek API test
        add_action('wp_ajax_myhh_test_hive_key', array($this, 'ajax_test_hive_key'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main dashboard
        add_menu_page(
            __('My Health Hub', 'myhealthhub'),
            __('My Health Hub', 'myhealthhub'),
            'manage_options',
            'myhh-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-heart',
            30
        );
        
        // Dashboard submenu (first item - redirects to main menu)
        add_submenu_page(
            'myhh-dashboard',
            __('Dashboard', 'myhealthhub'),
            __('Dashboard', 'myhealthhub'),
            'manage_options',
            'myhh-dashboard',
            array($this, 'render_dashboard')
        );
        
        // Settings (last item)
        add_submenu_page(
            'myhh-dashboard',
            __('Settings', 'myhealthhub'),
            __('Settings', 'myhealthhub'),
            'manage_options',
            'myhh-settings',
            array($this, 'render_settings')
        );
        
        // Note: CPTs (Leads, Claims, Testimonials, Practice Submissions) are automatically
        // added as submenu items via 'show_in_menu' => 'myhh-dashboard' in class-post-types.php
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // General Settings (main option group)
        register_setting('myhh_settings', 'myhh_ghl_webhook_url', 'esc_url_raw');
        register_setting('myhh_settings', 'myhh_booking_link', 'esc_url_raw');
        register_setting('myhh_settings', 'myhh_notification_email', 'sanitize_email');
        
        // SMTP Settings (separate option group)
        register_setting('myhh_smtp_settings', 'myhh_smtp_host', 'sanitize_text_field');
        register_setting('myhh_smtp_settings', 'myhh_smtp_port', 'intval');
        register_setting('myhh_smtp_settings', 'myhh_smtp_secure', array($this, 'sanitize_smtp_secure'));
        register_setting('myhh_smtp_settings', 'myhh_smtp_user', 'sanitize_email');
        register_setting('myhh_smtp_settings', 'myhh_smtp_pass', 'sanitize_text_field');
        register_setting('myhh_smtp_settings', 'myhh_smtp_from', 'sanitize_email');
        register_setting('myhh_smtp_settings', 'myhh_smtp_from_name', 'sanitize_text_field');
        register_setting('myhh_smtp_settings', 'myhh_smtp_enabled', 'intval');
        
        // Mia Chatbot Settings (separate option group)
        register_setting('myhh_mia_settings', 'myhh_grok_api_key', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_api_key'),
        ));
        register_setting('myhh_mia_settings', 'myhh_mia_enabled', 'intval');
        register_setting('myhh_mia_settings', 'myhh_openai_api_key', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_openai_api_key'),
            'show_in_rest' => false,
        ));
        register_setting('myhh_mia_settings', 'myhh_deepseek_api_key', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_api_key'),
            'show_in_rest' => false,
        ));
        register_setting('myhh_mia_settings', 'myhh_mia_ai_provider', array(
            'type' => 'string', 'default' => 'grok',
            'sanitize_callback' => array($this, 'sanitize_ai_provider'),
        ));
        register_setting('myhh_mia_settings', 'myhh_mia_decision_provider', array(
            'type' => 'string',
            'default' => 'openai',
            'sanitize_callback' => array($this, 'sanitize_decision_provider'),
        ));
        
        // Google Maps Settings (separate option group)
        register_setting('myhh_maps_settings', 'myhh_google_maps_key', array(
            'type'              => 'string',
            'sanitize_callback' => array($this, 'sanitize_maps_api_key'),
        ));
    }
    
    /**
     * Sanitize API key — only trim whitespace, preserve all characters.
     * sanitize_text_field strips too aggressively for API keys.
     */
    public function sanitize_api_key($value) {
        // Trim whitespace/newlines that might be pasted
        $value = trim($value);
        // Remove any invisible characters
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        return $value;
    }

    public function sanitize_openai_api_key($value) {
        if (!empty($_POST['myhh_remove_openai_key'])) {
            return '';
        }
        $value = is_string($value) ? $this->sanitize_api_key($value) : '';
        return $value !== '' ? $value : get_option('myhh_openai_api_key', '');
    }

    public function sanitize_decision_provider($value) {
        return in_array($value, array('grok', 'openai'), true) ? $value : 'openai';
    }

    public function sanitize_ai_provider($value) {
        return in_array($value, array('grok', 'openai', 'deepseek'), true) ? $value : 'grok';
    }

    public function ajax_test_openai_key() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }
        check_ajax_referer('myhh_test_openai_key', 'nonce');
        require_once __DIR__ . '/class-mia-decisions.php';
        $key = isset($_POST['api_key']) && is_string($_POST['api_key'])
            ? $this->sanitize_api_key(wp_unslash($_POST['api_key'])) : '';
        $result = MyHH_Mia_Decisions::select_action('What services does MyHealthHub offer?', array(), $key !== '' ? $key : null);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        wp_send_json_success(array('message' => 'OpenAI Decisions API connection succeeded. Save Mia Settings to keep any new key.'));
    }

    /**
     * Test Hive/DeepSeek API connection (server-side, avoids CORS)
     */
    public function ajax_test_hive_key() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }
        
        check_ajax_referer('myhh_test_hive_key', 'nonce');
        
        $key = isset($_POST['api_key']) && is_string($_POST['api_key'])
            ? trim(wp_unslash($_POST['api_key'])) : '';
        
        if (empty($key)) {
            wp_send_json_error(array('message' => 'Hive API key is required.'));
        }
        
        // Test connection to Deploy.com DeepSeek API (correct endpoint)
        $response = wp_remote_post('https://api.deploy.com/api/v1/chat/completions', array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $key,
            ),
            'body' => wp_json_encode(array(
                'model' => 'deepseek-ai/deepseek-v4.1-flash-1017',
                'messages' => array(array('role' => 'user', 'content' => 'Say OK')),
            )),
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Connection failed: ' . $response->get_error_message()));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!is_array($data)) {
            wp_send_json_error(array('message' => 'Invalid response from Hive API.'));
        }
        
        if (isset($data['error'])) {
            $error_msg = is_array($data['error']) ? ($data['error']['message'] ?? 'Unknown error') : $data['error'];
            wp_send_json_error(array('message' => 'Hive API error: ' . $error_msg));
        }
        
        if (isset($data['choices'][0]['message']['content'])) {
            wp_send_json_success(array('message' => 'DeepSeek API connection succeeded! Ready to use.'));
        }
        
        wp_send_json_error(array('message' => 'Unexpected response from API.'));
    }
    
    /**
     * Sanitize Google Maps API key and sync to GeoDirectory options.
     */
    public function sanitize_maps_api_key($value) {
        $value = trim($value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        
        if (!empty($value)) {
            // Sync to GeoDirectory settings array (correct method)
            // GeoDirectory stores all settings in a single 'geodir_settings' option
            $gd_settings = get_option('geodir_settings', array());
            $gd_settings['google_maps_api_key'] = $value;
            $gd_settings['maps_api'] = 'google'; // Force Google Maps provider
            update_option('geodir_settings', $gd_settings);
            
            // Also update global for immediate use
            global $geodir_options;
            if (is_array($geodir_options)) {
                $geodir_options['google_maps_api_key'] = $value;
                $geodir_options['maps_api'] = 'google';
            }
        }
        
        return $value;
    }
    
    /**
     * Sanitize SMTP secure value
     * Ensures only valid values are stored: 'ssl', 'tls', or ''
     */
    public function sanitize_smtp_secure($value) {
        $valid_values = array('ssl', 'tls', '');
        if (in_array($value, $valid_values, true)) {
            return $value;
        }
        // Default to SSL if invalid
        return 'ssl';
    }
    
    /**
     * Handle redirect after settings save to preserve tab
     */
    public function settings_tab_redirect($location, $status) {
        // Only process settings page redirects
        if (strpos($location, 'page=myhh-settings') !== false && strpos($location, 'tab=') === false) {
            // Determine which tab to redirect to based on which settings group was saved
            if (isset($_POST['option_page'])) {
                $option_page = sanitize_text_field($_POST['option_page']);
                $tab_map = array(
                    'myhh_settings'      => 'general',
                    'myhh_mia_settings'  => 'mia',
                    'myhh_smtp_settings' => 'smtp',
                    'myhh_maps_settings' => 'maps',
                );
                if (isset($tab_map[$option_page])) {
                    $location = add_query_arg('tab', $tab_map[$option_page], $location);
                }
            }
        }
        return $location;
    }
    
    /**
     * AJAX handler for testing SMTP email
     */
    public function ajax_test_smtp_email() {
        // Verify nonce
        check_ajax_referer('myhh_test_smtp', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'myhealthhub')));
        }
        
        // Get test email address
        $test_email = sanitize_email($_POST['email']);
        if (!is_email($test_email)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'myhealthhub')));
        }
        
        // Check if SMTP is enabled
        $smtp_enabled = get_option('myhh_smtp_enabled');
        if (!$smtp_enabled) {
            wp_send_json_error(array('message' => __('SMTP is not enabled. Enable it above and save settings first.', 'myhealthhub')));
        }
        
        // Check required fields
        $host = get_option('myhh_smtp_host');
        $user = get_option('myhh_smtp_user');
        $pass = get_option('myhh_smtp_pass');
        
        if (empty($host) || empty($user) || empty($pass)) {
            wp_send_json_error(array('message' => __('Please configure SMTP host, username, and password first.', 'myhealthhub')));
        }
        
        // Send test email
        $subject = __('MyHealthHub SMTP Test', 'myhealthhub') . ' - ' . date('Y-m-d H:i:s');
        $message = sprintf(
            __("This is a test email from MyHealthHub SMTP configuration.\n\nSent to: %s\nTime: %s\n\nIf you received this email, your SMTP settings are working correctly!", 'myhealthhub'),
            $test_email,
            date('Y-m-d H:i:s')
        );
        
        $from_email = get_option('myhh_smtp_from', get_option('admin_email'));
        $from_name = get_option('myhh_smtp_from_name', get_bloginfo('name'));
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        );
        
        $sent = wp_mail($test_email, $subject, $message, $headers);
        
        if ($sent) {
            wp_send_json_success(array(
                'message' => sprintf(__('Test email sent successfully to %s! Check your inbox.', 'myhealthhub'), $test_email)
            ));
        } else {
            // Get last error
            $last_error = error_get_last();
            $error_msg = $last_error ? $last_error['message'] : 'Unknown error';
            wp_send_json_error(array(
                'message' => sprintf(__('Failed to send test email. Please check your SMTP settings. Error: %s', 'myhealthhub'), $error_msg)
            ));
        }
    }
    
    /**
     * AJAX handler for testing Google Maps API key
     */
    public function ajax_test_maps_key() {
        // Verify nonce
        check_ajax_referer('myhh_test_maps_key', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'myhealthhub')));
        }
        
        // Get the saved key (test the stored key, not a submitted one)
        $key = get_option('myhh_google_maps_key');
        if (empty($key)) {
            wp_send_json_error(array('message' => __('No API key saved. Please enter and save a key first.', 'myhealthhub')));
        }
        
        // Call Google Geocoding API with a known address
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode('Raleigh,NC') . '&key=' . urlencode($key);
        $response = wp_remote_get($url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => sprintf(__('Connection failed: %s', 'myhealthhub'), $response->get_error_message())));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data) {
            wp_send_json_error(array('message' => __('Invalid response from Google Maps API.', 'myhealthhub')));
        }
        
        $status = isset($data['status']) ? $data['status'] : 'UNKNOWN';
        
        if ($status === 'OK') {
            wp_send_json_success(array(
                'message' => __('Google Maps API key is valid and working correctly.', 'myhealthhub'),
            ));
        } elseif ($status === 'REQUEST_DENIED') {
            $error_msg = isset($data['error_message']) ? $data['error_message'] : 'Request denied.';
            wp_send_json_error(array('message' => sprintf(__('API key rejected: %s', 'myhealthhub'), $error_msg)));
        } elseif ($status === 'INVALID_REQUEST') {
            wp_send_json_error(array('message' => __('Invalid request — check that Geocoding API is enabled in Google Cloud Console.', 'myhealthhub')));
        } else {
            wp_send_json_error(array('message' => sprintf(__('Unexpected API status: %s', 'myhealthhub'), $status)));
        }
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        // Lead meta box
        add_meta_box(
            'myhh_lead_details',
            __('Lead Details', 'myhealthhub'),
            array($this, 'render_lead_meta_box'),
            'myhh_lead',
            'normal',
            'high'
        );
        
        // Claim meta box
        add_meta_box(
            'myhh_claim_details',
            __('Claim Details', 'myhealthhub'),
            array($this, 'render_claim_meta_box'),
            'myhh_claim',
            'normal',
            'high'
        );
        
        // Testimonial meta box
        add_meta_box(
            'myhh_testimonial_details',
            __('Testimonial Details', 'myhealthhub'),
            array($this, 'render_testimonial_meta_box'),
            'myhh_testimonial',
            'normal',
            'high'
        );
        
        // Practice Submission meta box
        add_meta_box(
            'myhh_submission_details',
            __('Submission Details', 'myhealthhub'),
            array($this, 'render_practice_submission_meta_box'),
            'myhh_submission',
            'normal',
            'high'
        );
    }
    
    /**
     * Render lead meta box
     */
    public function render_lead_meta_box($post) {
        wp_nonce_field('myhh_save_lead_meta', 'myhh_lead_meta_nonce');
        
        $email = get_post_meta($post->ID, '_myhh_email', true);
        $city = get_post_meta($post->ID, '_myhh_city', true);
        $preference = get_post_meta($post->ID, '_myhh_preference', true);
        $status = get_post_meta($post->ID, '_myhh_status', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="myhh_email"><?php _e('Email', 'myhealthhub'); ?></label></th>
                <td><input type="email" id="myhh_email" name="myhh_email" value="<?php echo esc_attr($email); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="myhh_city"><?php _e('City', 'myhealthhub'); ?></label></th>
                <td><input type="text" id="myhh_city" name="myhh_city" value="<?php echo esc_attr($city); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="myhh_preference"><?php _e('Preference', 'myhealthhub'); ?></label></th>
                <td>
                    <select id="myhh_preference" name="myhh_preference">
                        <option value=""><?php _e('Select...', 'myhealthhub'); ?></option>
                        <option value="in-person" <?php selected($preference, 'in-person'); ?>><?php _e('In-Person', 'myhealthhub'); ?></option>
                        <option value="virtual" <?php selected($preference, 'virtual'); ?>><?php _e('Virtual', 'myhealthhub'); ?></option>
                        <option value="either" <?php selected($preference, 'either'); ?>><?php _e('Either', 'myhealthhub'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="myhh_status"><?php _e('Status', 'myhealthhub'); ?></label></th>
                <td>
                    <select id="myhh_status" name="myhh_status">
                        <option value="new" <?php selected($status, 'new'); ?>><?php _e('New', 'myhealthhub'); ?></option>
                        <option value="contacted" <?php selected($status, 'contacted'); ?>><?php _e('Contacted', 'myhealthhub'); ?></option>
                        <option value="closed" <?php selected($status, 'closed'); ?>><?php _e('Closed', 'myhealthhub'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render claim meta box
     */
    public function render_claim_meta_box($post) {
        wp_nonce_field('myhh_save_claim_meta', 'myhh_claim_meta_nonce');
        
        $listing_id = get_post_meta($post->ID, '_myhh_listing_id', true);
        $claimant_name = get_post_meta($post->ID, '_myhh_claimant_name', true);
        $claimant_email = get_post_meta($post->ID, '_myhh_claimant_email', true);
        $claimant_phone = get_post_meta($post->ID, '_myhh_claimant_phone', true);
        $proof_link = get_post_meta($post->ID, '_myhh_proof_link', true);
        $status = get_post_meta($post->ID, '_myhh_status', true) ?: 'pending';
        ?>
        <table class="form-table">
            <tr>
                <th><label><?php _e('Listing', 'myhealthhub'); ?></label></th>
                <td>
                    <?php if ($listing_id) : ?>
                        <a href="<?php echo esc_url(get_edit_post_link($listing_id)); ?>" target="_blank">
                            <?php echo esc_html(get_the_title($listing_id)); ?>
                        </a>
                        <br><a href="<?php echo esc_url(get_permalink($listing_id)); ?>" target="_blank" style="font-size: 12px;">View on site</a>
                    <?php else : ?>
                        <?php _e('Unknown Listing', 'myhealthhub'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="myhh_claimant_name"><?php _e('Claimant Name', 'myhealthhub'); ?></label></th>
                <td><input type="text" id="myhh_claimant_name" name="myhh_claimant_name" value="<?php echo esc_attr($claimant_name); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="myhh_claimant_email"><?php _e('Claimant Email', 'myhealthhub'); ?></label></th>
                <td>
                    <input type="email" id="myhh_claimant_email" name="myhh_claimant_email" value="<?php echo esc_attr($claimant_email); ?>" class="regular-text">
                    <?php if ($claimant_email) : ?>
                        <br><a href="mailto:<?php echo esc_attr($claimant_email); ?>" style="font-size: 12px;">Send email</a>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="myhh_claimant_phone"><?php _e('Claimant Phone', 'myhealthhub'); ?></label></th>
                <td><input type="tel" id="myhh_claimant_phone" name="myhh_claimant_phone" value="<?php echo esc_attr($claimant_phone); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="myhh_proof_link"><?php _e('Proof Link', 'myhealthhub'); ?></label></th>
                <td>
                    <input type="url" id="myhh_proof_link" name="myhh_proof_link" value="<?php echo esc_attr($proof_link); ?>" class="regular-text">
                    <?php if ($proof_link) : ?>
                        <br><a href="<?php echo esc_url($proof_link); ?>" target="_blank" class="button button-small" style="margin-top: 5px;"><?php _e('View Proof', 'myhealthhub'); ?></a>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="myhh_claim_status"><?php _e('Status', 'myhealthhub'); ?></label></th>
                <td>
                    <select id="myhh_claim_status" name="myhh_claim_status">
                        <option value="pending" <?php selected($status, 'pending'); ?>><?php _e('Pending', 'myhealthhub'); ?></option>
                        <option value="approved" <?php selected($status, 'approved'); ?>><?php _e('Approved', 'myhealthhub'); ?></option>
                        <option value="rejected" <?php selected($status, 'rejected'); ?>><?php _e('Rejected', 'myhealthhub'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        
        <?php if ($status === 'pending') : ?>
        <div style="margin-top: 15px; padding: 15px; background: #f0f0f1; border-radius: 5px;">
            <strong><?php _e('Quick Actions:', 'myhealthhub'); ?></strong><br><br>
            <button type="button" class="button button-primary" onclick="document.getElementById('myhh_claim_status').value='approved'; this.form.submit();" style="margin-right: 5px;">
                <?php _e('Approve & Send Email', 'myhealthhub'); ?>
            </button>
            <button type="button" class="button" onclick="document.getElementById('myhh_claim_status').value='rejected'; this.form.submit();">
                <?php _e('Reject & Send Email', 'myhealthhub'); ?>
            </button>
        </div>
        <?php elseif ($status === 'approved') : ?>
        <div style="margin-top: 15px; padding: 15px; background: #d1fae5; border-radius: 5px;">
            <span style="color: #059669;">✓ <?php _e('This claim has been approved. The claimant has been notified.', 'myhealthhub'); ?></span>
        </div>
        <?php elseif ($status === 'rejected') : ?>
        <div style="margin-top: 15px; padding: 15px; background: #fee2e2; border-radius: 5px;">
            <span style="color: #dc2626;">✗ <?php _e('This claim has been rejected. The claimant has been notified.', 'myhealthhub'); ?></span>
        </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render testimonial meta box
     */
    public function render_testimonial_meta_box($post) {
        wp_nonce_field('myhh_save_testimonial_meta', 'myhh_testimonial_meta_nonce');
        
        $listing_id = get_post_meta($post->ID, '_myhh_listing_id', true);
        $author_name = get_post_meta($post->ID, '_myhh_author_name', true);
        $rating = get_post_meta($post->ID, '_myhh_rating', true);
        $status_terms = wp_get_object_terms($post->ID, 'myhh_testimonial_status', array('fields' => 'slugs'));
        $status = !empty($status_terms) ? $status_terms[0] : 'pending';
        ?>
        <table class="form-table">
            <tr>
                <th><label><?php _e('Listing', 'myhealthhub'); ?></label></th>
                <td>
                    <?php if ($listing_id) : ?>
                        <a href="<?php echo esc_url(get_edit_post_link($listing_id)); ?>" target="_blank">
                            <?php echo esc_html(get_the_title($listing_id)); ?>
                        </a>
                    <?php else : ?>
                        <?php _e('Unknown Listing', 'myhealthhub'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="myhh_author_name"><?php _e('Author Name', 'myhealthhub'); ?></label></th>
                <td><input type="text" id="myhh_author_name" name="myhh_author_name" value="<?php echo esc_attr($author_name); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="myhh_rating"><?php _e('Rating', 'myhealthhub'); ?></label></th>
                <td>
                    <select id="myhh_rating" name="myhh_rating">
                        <?php for ($i = 5; $i >= 1; $i--) : ?>
                            <option value="<?php echo $i; ?>" <?php selected($rating, $i); ?>><?php echo $i; ?> - <?php echo str_repeat('★', $i); ?></option>
                        <?php endfor; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="myhh_testimonial_status"><?php _e('Status', 'myhealthhub'); ?></label></th>
                <td>
                    <select id="myhh_testimonial_status" name="myhh_testimonial_status">
                        <option value="pending" <?php selected($status, 'pending'); ?>><?php _e('Pending', 'myhealthhub'); ?></option>
                        <option value="approved" <?php selected($status, 'approved'); ?>><?php _e('Approved', 'myhealthhub'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render practice submission meta box
     */
    public function render_practice_submission_meta_box($post) {
        wp_nonce_field('myhh_save_practice_submission_meta', 'myhh_submission_meta_nonce');
        
        $provider_name = get_post_meta($post->ID, '_myhh_provider_name', true);
        $practice_name = get_post_meta($post->ID, '_myhh_practice_name', true);
        $email = get_post_meta($post->ID, '_myhh_email', true);
        $phone = get_post_meta($post->ID, '_myhh_phone', true);
        $city = get_post_meta($post->ID, '_myhh_city', true);
        $specialty = get_post_meta($post->ID, '_myhh_specialty', true);
        $credentials = get_post_meta($post->ID, '_myhh_credentials', true);
        $website = get_post_meta($post->ID, '_myhh_website', true);
        $booking_link = get_post_meta($post->ID, '_myhh_booking_link', true);
        $session_type = get_post_meta($post->ID, '_myhh_session_type', true);
        $insurance = get_post_meta($post->ID, '_myhh_insurance', true);
        $bio = $post->post_content;
        $notes = get_post_meta($post->ID, '_myhh_notes', true);
        $status = get_post_meta($post->ID, '_myhh_status', true) ?: 'pending';
        $submitted = get_post_meta($post->ID, '_myhh_submitted', true);
        ?>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Left Column -->
            <div>
                <h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #E5E7EB; color: #374151;">Contact Information</h3>
                <table class="form-table">
                    <tr>
                        <th><label><?php _e('Provider Name', 'myhealthhub'); ?></label></th>
                        <td><strong><?php echo esc_html($provider_name); ?></strong></td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Practice Name', 'myhealthhub'); ?></label></th>
                        <td><strong><?php echo esc_html($practice_name); ?></strong></td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Email', 'myhealthhub'); ?></label></th>
                        <td>
                            <a href="mailto:<?php echo esc_attr($email); ?>" style="color: #0A74DA;"><?php echo esc_html($email); ?></a>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Phone', 'myhealthhub'); ?></label></th>
                        <td><?php echo esc_html($phone ?: 'Not provided'); ?></td>
                    </tr>
                    <tr>
                        <th><label><?php _e('City', 'myhealthhub'); ?></label></th>
                        <td><?php echo esc_html($city); ?></td>
                    </tr>
                </table>
                
                <h3 style="margin-top: 20px; padding-bottom: 10px; border-bottom: 1px solid #E5E7EB; color: #374151;">Professional Details</h3>
                <table class="form-table">
                    <tr>
                        <th><label><?php _e('Specialty', 'myhealthhub'); ?></label></th>
                        <td><?php echo esc_html($specialty); ?></td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Credentials', 'myhealthhub'); ?></label></th>
                        <td><?php echo esc_html($credentials ?: 'Not provided'); ?></td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Session Type', 'myhealthhub'); ?></label></th>
                        <td><?php echo esc_html($session_type); ?></td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Insurance', 'myhealthhub'); ?></label></th>
                        <td><?php echo esc_html($insurance ?: 'Not provided'); ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Right Column -->
            <div>
                <h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #E5E7EB; color: #374151;">Links</h3>
                <table class="form-table">
                    <tr>
                        <th><label><?php _e('Website', 'myhealthhub'); ?></label></th>
                        <td>
                            <?php if ($website) : ?>
                                <a href="<?php echo esc_url($website); ?>" target="_blank" style="color: #0A74DA;"><?php echo esc_html($website); ?></a>
                            <?php else : ?>
                                Not provided
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Booking Link', 'myhealthhub'); ?></label></th>
                        <td>
                            <?php if ($booking_link) : ?>
                                <a href="<?php echo esc_url($booking_link); ?>" target="_blank" style="color: #0A74DA;"><?php echo esc_html($booking_link); ?></a>
                            <?php else : ?>
                                Not provided
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <h3 style="margin-top: 20px; padding-bottom: 10px; border-bottom: 1px solid #E5E7EB; color: #374151;">Status</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="myhh_submission_status"><?php _e('Status', 'myhealthhub'); ?></label></th>
                        <td>
                            <select id="myhh_submission_status" name="myhh_submission_status">
                                <option value="pending" <?php selected($status, 'pending'); ?>><?php _e('Pending Review', 'myhealthhub'); ?></option>
                                <option value="reviewed" <?php selected($status, 'reviewed'); ?>><?php _e('Reviewed', 'myhealthhub'); ?></option>
                                <option value="approved" <?php selected($status, 'approved'); ?>><?php _e('Approved', 'myhealthhub'); ?></option>
                                <option value="rejected" <?php selected($status, 'rejected'); ?>><?php _e('Rejected', 'myhealthhub'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Submitted', 'myhealthhub'); ?></label></th>
                        <td><?php echo esc_html($submitted ?: get_the_date('', $post)); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Bio Section (Full Width) -->
        <div style="margin-top: 20px;">
            <h3 style="padding-bottom: 10px; border-bottom: 1px solid #E5E7EB; color: #374151;">Bio / Description</h3>
            <div style="background: #F8FAFC; padding: 15px; border-radius: 8px; margin-top: 10px;">
                <?php echo wpautop(esc_html($bio)); ?>
            </div>
        </div>
        
        <?php if ($notes) : ?>
        <!-- Notes Section -->
        <div style="margin-top: 20px;">
            <h3 style="padding-bottom: 10px; border-bottom: 1px solid #E5E7EB; color: #374151;">Additional Notes</h3>
            <div style="background: #FEF3C7; padding: 15px; border-radius: 8px; margin-top: 10px;">
                <?php echo wpautop(esc_html($notes)); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($status === 'pending') : ?>
        <div style="margin-top: 20px; padding: 20px; background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%); border-radius: 12px; border: 2px solid #C7D2FE;">
            <strong style="color: #3730A3; font-size: 14px;"><?php _e('Quick Actions:', 'myhealthhub'); ?></strong><br><br>
            <button type="button" class="button button-primary" onclick="document.getElementById('myhh_submission_status').value='approved'; this.form.submit();" style="margin-right: 8px; background: #059669; border-color: #059669;">
                <?php _e('Approve Submission', 'myhealthhub'); ?>
            </button>
            <button type="button" class="button" onclick="document.getElementById('myhh_submission_status').value='reviewed'; this.form.submit();" style="margin-right: 8px;">
                <?php _e('Mark as Reviewed', 'myhealthhub'); ?>
            </button>
            <button type="button" class="button" onclick="document.getElementById('myhh_submission_status').value='rejected'; this.form.submit();" style="background: #DC2626; color: #fff; border-color: #DC2626;">
                <?php _e('Reject', 'myhealthhub'); ?>
            </button>
        </div>
        <?php elseif ($status === 'approved') : ?>
        <div style="margin-top: 20px; padding: 15px; background: #D1FAE5; border-radius: 8px;">
            <span style="color: #059669;">✓ <?php _e('This submission has been approved. You can now create a provider listing.', 'myhealthhub'); ?></span>
        </div>
        <?php elseif ($status === 'rejected') : ?>
        <div style="margin-top: 20px; padding: 15px; background: #FEE2E2; border-radius: 8px;">
            <span style="color: #DC2626;">✗ <?php _e('This submission has been rejected.', 'myhealthhub'); ?></span>
        </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Save meta boxes
     */
    public function save_meta_boxes($post_id) {
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save lead meta
        if (isset($_POST['myhh_lead_meta_nonce']) && wp_verify_nonce($_POST['myhh_lead_meta_nonce'], 'myhh_save_lead_meta')) {
            update_post_meta($post_id, '_myhh_email', sanitize_email($_POST['myhh_email'] ?? ''));
            update_post_meta($post_id, '_myhh_city', sanitize_text_field($_POST['myhh_city'] ?? ''));
            update_post_meta($post_id, '_myhh_preference', sanitize_text_field($_POST['myhh_preference'] ?? ''));
            update_post_meta($post_id, '_myhh_status', sanitize_text_field($_POST['myhh_status'] ?? 'new'));
        }
        
        // Save claim meta
        if (isset($_POST['myhh_claim_meta_nonce']) && wp_verify_nonce($_POST['myhh_claim_meta_nonce'], 'myhh_save_claim_meta')) {
            $old_status = get_post_meta($post_id, '_myhh_status', true);
            $new_status = sanitize_text_field($_POST['myhh_claim_status'] ?? 'pending');
            
            update_post_meta($post_id, '_myhh_claimant_name', sanitize_text_field($_POST['myhh_claimant_name'] ?? ''));
            update_post_meta($post_id, '_myhh_claimant_email', sanitize_email($_POST['myhh_claimant_email'] ?? ''));
            update_post_meta($post_id, '_myhh_claimant_phone', sanitize_text_field($_POST['myhh_claimant_phone'] ?? ''));
            update_post_meta($post_id, '_myhh_proof_link', esc_url_raw($_POST['myhh_proof_link'] ?? ''));
            update_post_meta($post_id, '_myhh_status', $new_status);
            
            // Handle status change
            if ($old_status !== $new_status && $old_status === 'pending') {
                $listing_id = get_post_meta($post_id, '_myhh_listing_id', true);
                $claimant_email = sanitize_email($_POST['myhh_claimant_email'] ?? '');
                $claimant_name = sanitize_text_field($_POST['myhh_claimant_name'] ?? '');
                
                if ($new_status === 'approved' && $listing_id) {
                    // Mark listing as claimed
                    update_post_meta($listing_id, '_myhh_claimed', true);
                    update_post_meta($listing_id, '_myhh_claimed_by', $claimant_email);
                    update_post_meta($listing_id, '_myhh_claimed_date', current_time('mysql'));
                    
                    // Send approval email
                    $this->send_claim_notification($post_id, 'approved');
                } elseif ($new_status === 'rejected') {
                    // Send rejection email
                    $this->send_claim_notification($post_id, 'rejected');
                }
            }
        }
        
        // Save testimonial meta
        if (isset($_POST['myhh_testimonial_meta_nonce']) && wp_verify_nonce($_POST['myhh_testimonial_meta_nonce'], 'myhh_save_testimonial_meta')) {
            update_post_meta($post_id, '_myhh_author_name', sanitize_text_field($_POST['myhh_author_name'] ?? ''));
            update_post_meta($post_id, '_myhh_rating', intval($_POST['myhh_rating'] ?? 5));
            
            $status = sanitize_text_field($_POST['myhh_testimonial_status'] ?? 'pending');
            wp_set_object_terms($post_id, $status, 'myhh_testimonial_status');
        }
        
        // Save practice submission meta
        if (isset($_POST['myhh_submission_meta_nonce']) && wp_verify_nonce($_POST['myhh_submission_meta_nonce'], 'myhh_save_practice_submission_meta')) {
            $old_status = get_post_meta($post_id, '_myhh_status', true);
            $new_status = sanitize_text_field($_POST['myhh_submission_status'] ?? 'pending');
            
            update_post_meta($post_id, '_myhh_status', $new_status);
            
            // Send notification on status change
            if ($old_status !== $new_status && $old_status === 'pending') {
                $this->send_practice_submission_notification($post_id, $new_status);
            }
        }
    }
    
    /**
     * Send claim notification email
     */
    private function send_claim_notification($claim_id, $status) {
        $claimant_email = get_post_meta($claim_id, '_myhh_claimant_email', true);
        $claimant_name = get_post_meta($claim_id, '_myhh_claimant_name', true);
        $listing_id = get_post_meta($claim_id, '_myhh_listing_id', true);
        $listing_title = $listing_id ? get_the_title($listing_id) : 'Unknown Listing';
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        if (empty($claimant_email)) {
            return false;
        }
        
        if ($status === 'approved') {
            $subject = sprintf('[%s] Your Listing Claim Has Been Approved', $site_name);
            $message = "Dear {$claimant_name},\n\n";
            $message .= "Congratulations! Your claim for \"{$listing_title}\" has been approved.\n\n";
            $message .= "You now have ownership of this listing on {$site_name}. You can manage your listing by logging into your account.\n\n";
            $message .= "Listing URL: " . get_permalink($listing_id) . "\n\n";
            $message .= "If you have any questions, please don't hesitate to contact us.\n\n";
            $message .= "Best regards,\n{$site_name} Team\n{$site_url}";
        } else {
            $subject = sprintf('[%s] Your Listing Claim Status', $site_name);
            $message = "Dear {$claimant_name},\n\n";
            $message .= "Thank you for your interest in claiming \"{$listing_title}\" on {$site_name}.\n\n";
            $message .= "After reviewing your submission, we were unable to verify your ownership at this time.\n\n";
            $message .= "If you believe this is an error or would like to submit additional verification, please contact us.\n\n";
            $message .= "Best regards,\n{$site_name} Team\n{$site_url}";
        }
        
        return wp_mail($claimant_email, $subject, $message);
    }
    
    /**
     * Send practice submission notification email
     */
    private function send_practice_submission_notification($submission_id, $status) {
        $provider_email = get_post_meta($submission_id, '_myhh_email', true);
        $provider_name = get_post_meta($submission_id, '_myhh_provider_name', true);
        $practice_name = get_post_meta($submission_id, '_myhh_practice_name', true);
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        if (empty($provider_email)) {
            return false;
        }
        
        if ($status === 'approved') {
            $subject = sprintf('[%s] Your Practice Has Been Approved', $site_name);
            $message = "Dear {$provider_name},\n\n";
            $message .= "Great news! Your practice \"{$practice_name}\" has been approved and added to our directory.\n\n";
            $message .= "You can view your listing on our website.\n\n";
            $message .= "If you have any questions, please don't hesitate to contact us.\n\n";
            $message .= "Best regards,\n{$site_name} Team\n{$site_url}";
        } elseif ($status === 'rejected') {
            $subject = sprintf('[%s] Your Practice Submission Status', $site_name);
            $message = "Dear {$provider_name},\n\n";
            $message = "Dear {$provider_name},\n\n";
            $message .= "Thank you for your interest in listing \"{$practice_name}\" on {$site_name}.\n\n";
            $message .= "After reviewing your submission, we were unable to approve it at this time.\n\n";
            $message .= "If you believe this is an error or would like to submit additional information, please contact us.\n\n";
            $message .= "Best regards,\n{$site_name} Team\n{$site_url}";
        } else {
            return false;
        }
        
        return wp_mail($provider_email, $subject, $message);
    }
    
    /**
     * Render dashboard
     */
    public function render_dashboard() {
        // Handle manual page creation
        if (isset($_POST['myhh_create_pages'])) {
            if (check_admin_referer('myhh_create_pages')) {
                $result = $this->create_missing_pages();
                if ($result > 0) {
                    echo '<div class="notice notice-success"><p>' . sprintf(__('Pages updated successfully! (%d changes)', 'myhealthhub'), $result) . '</p></div>';
                } else {
                    echo '<div class="notice notice-info"><p>' . __('No changes needed - all pages exist with content. Permalinks flushed.', 'myhealthhub') . '</p></div>';
                }
                // Always flush rewrite rules when button is clicked
                flush_rewrite_rules();
            } else {
                echo '<div class="notice notice-error"><p>' . __('Security check failed. Please refresh the page and try again.', 'myhealthhub') . '</p></div>';
            }
        }
        
        // Handle sample provider seeding
        if (isset($_POST['myhh_seed_providers'])) {
            if (check_admin_referer('myhh_seed_providers')) {
                // Reset the seeded flag to allow re-seeding
                delete_option('myhh_sample_providers_seeded');
                $count = $this->seed_sample_providers();
                if ($count > 0) {
                    echo '<div class="notice notice-success"><p>' . sprintf(__('Sample providers created successfully! (%d listings)', 'myhealthhub'), $count) . '</p></div>';
                } else {
                    echo '<div class="notice notice-info"><p>' . __('No new providers created. They may already exist.', 'myhealthhub') . '</p></div>';
                }
            }
        }
        
        // Show message after seeding
        if (isset($_GET['message']) && $_GET['message'] === 'seeded') {
            echo '<div class="notice notice-success"><p>' . __('Pages created successfully! Permalinks have been updated.', 'myhealthhub') . '</p></div>';
        }
        
        // Handle quick claim actions
        if (isset($_GET['action']) && in_array($_GET['action'], array('approve_claim', 'reject_claim'))) {
            $claim_id = intval($_GET['claim_id'] ?? 0);
            $action_type = sanitize_text_field($_GET['action']);
            
            if ($claim_id && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'myhh_claim_action_' . $claim_id)) {
                $new_status = ($action_type === 'approve_claim') ? 'approved' : 'rejected';
                $old_status = get_post_meta($claim_id, '_myhh_status', true);
                
                update_post_meta($claim_id, '_myhh_status', $new_status);
                
                // If approved, mark listing as claimed
                if ($new_status === 'approved') {
                    $listing_id = get_post_meta($claim_id, '_myhh_listing_id', true);
                    $claimant_email = get_post_meta($claim_id, '_myhh_claimant_email', true);
                    
                    if ($listing_id) {
                        update_post_meta($listing_id, '_myhh_claimed', true);
                        update_post_meta($listing_id, '_myhh_claimed_by', $claimant_email);
                        update_post_meta($listing_id, '_myhh_claimed_date', current_time('mysql'));
                    }
                }
                
                // Send notification email
                $this->send_claim_notification($claim_id, $new_status);
                
                echo '<div class="notice notice-success"><p>' . sprintf(__('Claim %s successfully!', 'myhealthhub'), $new_status) . '</p></div>';
            }
        }
        
        // Handle quick testimonial approve action
        if (isset($_GET['action']) && $_GET['action'] === 'approve_testimonial') {
            $testimonial_id = intval($_GET['testimonial_id'] ?? 0);
            
            if ($testimonial_id && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'myhh_testimonial_action_' . $testimonial_id)) {
                wp_set_object_terms($testimonial_id, 'approved', 'myhh_testimonial_status');
                echo '<div class="notice notice-success"><p>' . __('Testimonial approved successfully!', 'myhealthhub') . '</p></div>';
            }
        }
        
        $leads_count = wp_count_posts('myhh_lead')->publish;
        $claims_count = wp_count_posts('myhh_claim')->publish;
        $testimonials_count = wp_count_posts('myhh_testimonial')->publish;
        $submissions_count = wp_count_posts('myhh_submission')->publish;
        
        // Get pending practice submissions
        $pending_submissions = get_posts(array(
            'post_type'      => 'myhh_submission',
            'posts_per_page' => 5,
            'meta_query'     => array(
                array(
                    'key'   => '_myhh_status',
                    'value' => 'pending',
                ),
            ),
        ));
        
        // Get pending claims
        $pending_claims = get_posts(array(
            'post_type'      => 'myhh_claim',
            'posts_per_page' => 5,
            'meta_query'     => array(
                array(
                    'key'   => '_myhh_status',
                    'value' => 'pending',
                ),
            ),
        ));
        
        // Get pending testimonials
        $pending_testimonials = get_posts(array(
            'post_type'      => 'myhh_testimonial',
            'posts_per_page' => 5,
            'tax_query'      => array(
                array(
                    'taxonomy' => 'myhh_testimonial_status',
                    'field'    => 'slug',
                    'terms'    => 'pending',
                ),
            ),
        ));
        ?>
        <div class="wrap myhh-admin">
            <h1><?php _e('Dashboard', 'myhealthhub'); ?></h1>
            
            <!-- Setup Card -->
            <div class="myhh-setup-card">
                <h3><span class="dashicons dashicons-admin-tools" style="margin-right: 8px;"></span><?php _e('Quick Setup', 'myhealthhub'); ?></h3>
                <p><?php _e('Create required pages and sample data for testing.', 'myhealthhub'); ?></p>
                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <form method="post" style="display: inline-block;">
                        <?php wp_nonce_field('myhh_create_pages'); ?>
                        <input type="submit" name="myhh_create_pages" class="button button-primary" value="<?php esc_attr_e('Create Missing Pages', 'myhealthhub'); ?>">
                    </form>
                    <form method="post" style="display: inline-block;">
                        <?php wp_nonce_field('myhh_seed_providers'); ?>
                        <input type="submit" name="myhh_seed_providers" class="button button-secondary" value="<?php esc_attr_e('Add Sample Providers', 'myhealthhub'); ?>">
                    </form>
                </div>
                <details style="margin-top: 16px;">
                    <summary style="cursor: pointer; color: #3730A3; font-weight: 500;"><?php _e('View Page Status', 'myhealthhub'); ?></summary>
                    <ul style="list-style: disc; margin-left: 20px; margin-top: 12px;">
                        <?php
                        $required_slugs = array('about', 'contact', 'providers', 'get-matched', 'claim-listing', 'add-your-practice', 'privacy-policy', 'terms', 'disclaimer');
                        foreach ($required_slugs as $slug) {
                            $page = get_page_by_path($slug);
                            if ($page) {
                                $status = get_post_status($page->ID);
                                $url = get_permalink($page->ID);
                                echo '<li><a href="' . esc_url($url) . '" target="_blank" style="color: #3730A3;">' . esc_html($slug) . '</a> <span style="color: #059669;">✓</span></li>';
                            } else {
                                echo '<li>' . esc_html($slug) . ' <span style="color: #EF4444;">✗ missing</span></li>';
                            }
                        }
                        ?>
                    </ul>
                </details>
            </div>
            
            <!-- Stats Grid -->
            <div class="myhh-stats">
                <div class="myhh-stat-box">
                    <h3><?php echo intval($leads_count); ?></h3>
                    <p><?php _e('Leads', 'myhealthhub'); ?></p>
                </div>
                <div class="myhh-stat-box">
                    <h3><?php echo intval($submissions_count); ?></h3>
                    <p><?php _e('Submissions', 'myhealthhub'); ?></p>
                </div>
                <div class="myhh-stat-box">
                    <h3><?php echo intval($claims_count); ?></h3>
                    <p><?php _e('Claims', 'myhealthhub'); ?></p>
                </div>
                <div class="myhh-stat-box">
                    <h3><?php echo intval($testimonials_count); ?></h3>
                    <p><?php _e('Testimonials', 'myhealthhub'); ?></p>
                </div>
            </div>
            
            <?php
            // Get recent leads
            $recent_leads = get_posts(array(
                'post_type'      => 'myhh_lead',
                'posts_per_page' => 5,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ));
            ?>
            
            <?php if ($recent_leads) : ?>
            <div class="myhh-card">
                <div class="myhh-card-header">
                    <h2><span class="dashicons dashicons-groups"></span><?php _e('Recent Leads', 'myhealthhub'); ?></h2>
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'myhealthhub'); ?></th>
                            <th><?php _e('Email', 'myhealthhub'); ?></th>
                            <th><?php _e('City', 'myhealthhub'); ?></th>
                            <th><?php _e('Preference', 'myhealthhub'); ?></th>
                            <th><?php _e('Status', 'myhealthhub'); ?></th>
                            <th><?php _e('Date', 'myhealthhub'); ?></th>
                            <th><?php _e('Actions', 'myhealthhub'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_leads as $lead) : 
                            $lead_email = get_post_meta($lead->ID, '_myhh_email', true);
                            $lead_city = get_post_meta($lead->ID, '_myhh_city', true);
                            $lead_preference = get_post_meta($lead->ID, '_myhh_preference', true);
                            $lead_status = get_post_meta($lead->ID, '_myhh_status', true) ?: 'new';
                            $status_class = 'myhh-status myhh-status-' . $lead_status;
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($lead->post_title); ?></strong></td>
                                <td><a href="mailto:<?php echo esc_attr($lead_email); ?>"><?php echo esc_html($lead_email); ?></a></td>
                                <td><?php echo esc_html($lead_city ?: '-'); ?></td>
                                <td><?php echo esc_html($lead_preference ?: '-'); ?></td>
                                <td><span class="<?php echo esc_attr($status_class); ?>"><?php echo esc_html(ucfirst($lead_status)); ?></span></td>
                                <td><?php echo esc_html(get_the_date('', $lead)); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_post_link($lead->ID)); ?>" class="button button-small myhh-btn-review"><?php _e('View', 'myhealthhub'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="myhh-card-footer">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=myhh_lead')); ?>">View All Leads →</a>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="myhh-dashboard-columns">
                <div class="myhh-dashboard-column">
                    <h2><span class="dashicons dashicons-lock"></span><?php _e('Pending Claims', 'myhealthhub'); ?></h2>
                    <?php if ($pending_claims) : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Listing', 'myhealthhub'); ?></th>
                                    <th><?php _e('Claimant', 'myhealthhub'); ?></th>
                                    <th><?php _e('Email', 'myhealthhub'); ?></th>
                                    <th><?php _e('Actions', 'myhealthhub'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_claims as $claim) : 
                                    $listing_id = get_post_meta($claim->ID, '_myhh_listing_id', true);
                                    $claimant_email = get_post_meta($claim->ID, '_myhh_claimant_email', true);
                                ?>
                                    <tr>
                                        <td><?php echo $listing_id ? esc_html(get_the_title($listing_id)) : '-'; ?></td>
                                        <td><?php echo esc_html($claim->post_title); ?></td>
                                        <td><a href="mailto:<?php echo esc_attr($claimant_email); ?>"><?php echo esc_html($claimant_email); ?></a></td>
                                        <td class="myhh-quick-actions">
                                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'approve_claim', 'claim_id' => $claim->ID), admin_url('admin.php?page=myhh-dashboard')), 'myhh_claim_action_' . $claim->ID)); ?>" class="button button-small myhh-btn-approve" onclick="return confirm('Approve this claim?');">
                                                <?php _e('Approve', 'myhealthhub'); ?>
                                            </a>
                                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'reject_claim', 'claim_id' => $claim->ID), admin_url('admin.php?page=myhh-dashboard')), 'myhh_claim_action_' . $claim->ID)); ?>" class="button button-small myhh-btn-reject" onclick="return confirm('Reject this claim?');">
                                                <?php _e('Reject', 'myhealthhub'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <div class="myhh-empty-state">
                            <span class="dashicons dashicons-lock"></span>
                            <p><?php _e('No pending claims', 'myhealthhub'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="myhh-dashboard-column">
                    <h2><span class="dashicons dashicons-format-quote"></span><?php _e('Pending Testimonials', 'myhealthhub'); ?></h2>
                    <?php if ($pending_testimonials) : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Listing', 'myhealthhub'); ?></th>
                                    <th><?php _e('Author', 'myhealthhub'); ?></th>
                                    <th><?php _e('Rating', 'myhealthhub'); ?></th>
                                    <th><?php _e('Actions', 'myhealthhub'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_testimonials as $testimonial) : 
                                    $listing_id = get_post_meta($testimonial->ID, '_myhh_listing_id', true);
                                    $author = get_post_meta($testimonial->ID, '_myhh_author_name', true);
                                    $rating = get_post_meta($testimonial->ID, '_myhh_rating', true);
                                ?>
                                    <tr>
                                        <td><?php echo $listing_id ? esc_html(get_the_title($listing_id)) : '-'; ?></td>
                                        <td><?php echo esc_html($author); ?></td>
                                        <td><span style="color: #F59E0B;"><?php echo str_repeat('★', intval($rating)); ?></span></td>
                                        <td class="myhh-quick-actions">
                                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'approve_testimonial', 'testimonial_id' => $testimonial->ID), admin_url('admin.php?page=myhh-dashboard')), 'myhh_testimonial_action_' . $testimonial->ID)); ?>" class="button button-small myhh-btn-approve">
                                                <?php _e('Approve', 'myhealthhub'); ?>
                                            </a>
                                            <a href="<?php echo esc_url(get_edit_post_link($testimonial->ID)); ?>" class="button button-small myhh-btn-review">
                                                <?php _e('Review', 'myhealthhub'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <div class="myhh-empty-state">
                            <span class="dashicons dashicons-format-quote"></span>
                            <p><?php _e('No pending testimonials', 'myhealthhub'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Pending Practice Submissions -->
            <?php if ($pending_submissions) : ?>
            <div class="myhh-card">
                <div class="myhh-card-header">
                    <h2><span class="dashicons dashicons-plus-alt2"></span><?php _e('Pending Practice Submissions', 'myhealthhub'); ?></h2>
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Practice Name', 'myhealthhub'); ?></th>
                            <th><?php _e('Provider', 'myhealthhub'); ?></th>
                            <th><?php _e('Email', 'myhealthhub'); ?></th>
                            <th><?php _e('City', 'myhealthhub'); ?></th>
                            <th><?php _e('Specialty', 'myhealthhub'); ?></th>
                            <th><?php _e('Date', 'myhealthhub'); ?></th>
                            <th><?php _e('Actions', 'myhealthhub'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_submissions as $submission) : 
                            $provider_name = get_post_meta($submission->ID, '_myhh_provider_name', true);
                            $email = get_post_meta($submission->ID, '_myhh_email', true);
                            $city = get_post_meta($submission->ID, '_myhh_city', true);
                            $specialty = get_post_meta($submission->ID, '_myhh_specialty', true);
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($submission->post_title); ?></strong></td>
                                <td><?php echo esc_html($provider_name); ?></td>
                                <td><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></td>
                                <td><?php echo esc_html($city ?: '-'); ?></td>
                                <td><?php echo esc_html($specialty ?: '-'); ?></td>
                                <td><?php echo esc_html(get_the_date('', $submission)); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_post_link($submission->ID)); ?>" class="button button-small myhh-btn-review"><?php _e('Review', 'myhealthhub'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="myhh-card-footer">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=myhh_submission')); ?>">View All Submissions →</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings page with tabs
     */
    public function render_settings() {
        // Get active tab (default to 'general')
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        $tabs = array(
            'general' => __('General', 'myhealthhub'),
            'mia'     => __('Mia Chatbot', 'myhealthhub'),
            'smtp'    => __('SMTP Email', 'myhealthhub'),
            'maps'    => __('Google Maps', 'myhealthhub'),
        );
        ?>
        <div class="wrap myhh-admin">
            <h1><?php _e('Settings', 'myhealthhub'); ?></h1>
            
            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
                <?php foreach ($tabs as $tab_key => $tab_name) : ?>
                <a href="<?php echo esc_url(add_query_arg('tab', $tab_key, admin_url('admin.php?page=myhh-settings'))); ?>" 
                   class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($tab_name); ?>
                </a>
                <?php endforeach; ?>
            </nav>
            
            <?php if ($active_tab === 'general') : ?>
            <!-- General Settings Tab -->
            <div class="myhh-card">
                <div class="myhh-card-header">
                    <h2><span class="dashicons dashicons-admin-settings"></span><?php _e('General Settings', 'myhealthhub'); ?></h2>
                </div>
                
                <form method="post" action="options.php">
                    <?php settings_fields('myhh_settings'); ?>
                    <?php do_settings_sections('myhh_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="myhh_notification_email">
                                    <?php _e('Notification Email', 'myhealthhub'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="email" name="myhh_notification_email" id="myhh_notification_email"
                                       value="<?php echo esc_attr(get_option('myhh_notification_email')); ?>"
                                       class="regular-text"
                                       placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                                <p class="description">
                                    <?php _e('Email address for new submission notifications. Defaults to admin email if not set.', 'myhealthhub'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="myhh_ghl_webhook_url">
                                    <?php _e('GoHighLevel Webhook', 'myhealthhub'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="url" name="myhh_ghl_webhook_url" id="myhh_ghl_webhook_url"
                                       value="<?php echo esc_attr(get_option('myhh_ghl_webhook_url')); ?>"
                                       class="regular-text"
                                       placeholder="https://">
                                <p class="description">
                                    <?php _e('If set, lead submissions will also be sent to this webhook for CRM integration.', 'myhealthhub'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="myhh_booking_link">
                                    <?php _e('Default Booking Link', 'myhealthhub'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="url" name="myhh_booking_link" id="myhh_booking_link"
                                       value="<?php echo esc_attr(get_option('myhh_booking_link')); ?>"
                                       class="regular-text"
                                       placeholder="https://">
                                <p class="description">
                                    <?php _e('Optional default booking link for providers without their own booking page.', 'myhealthhub'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Save General Settings', 'myhealthhub')); ?>
                </form>
            </div>
            
            <?php elseif ($active_tab === 'mia') : ?>
            <!-- Mia Chatbot Settings Tab -->
            <div class="myhh-card">
                <div class="myhh-card-header">
                    <h2><span class="dashicons dashicons-format-chat"></span><?php _e('Mia Chatbot Settings', 'myhealthhub'); ?></h2>
                </div>
                
                <form method="post" action="options.php">
                    <?php settings_fields('myhh_mia_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="myhh_mia_enabled">
                                    <?php _e('Enable Mia', 'myhealthhub'); ?>
                                </label>
                            </th>
                            <td>
                                <label class="myhh-toggle">
                                    <input type="checkbox" name="myhh_mia_enabled" id="myhh_mia_enabled" value="1" <?php checked(get_option('myhh_mia_enabled'), 1); ?>>
                                    <span class="myhh-toggle-slider"></span>
                                </label>
                                <p class="description">
                                    <?php _e('Enable the Mia wellness concierge chatbot on your site.', 'myhealthhub'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="myhh_grok_api_key">
                                    <?php _e('Grok API Key', 'myhealthhub'); ?>
                                </label>
                            </th>
                            <td>
                                <?php 
                                $api_key = get_option('myhh_grok_api_key');
                                $key_length = $api_key ? strlen($api_key) : 0;
                                $masked_key = $api_key ? substr($api_key, 0, 8) . '...' . substr($api_key, -4) . ' (' . $key_length . ' chars)' : '';
                                ?>
                                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                    <input type="password" 
                                           name="myhh_grok_api_key" 
                                           id="myhh_grok_api_key"
                                           value="<?php echo esc_attr($api_key); ?>"
                                           class="regular-text"
                                           placeholder="xai-..."
                                           autocomplete="off"
                                           data-lpignore="true"
                                           style="font-family: monospace;">
                                    <button type="button" id="myhh-toggle-key" class="button" onclick="var f=document.getElementById('myhh_grok_api_key');if(f.type==='password'){f.type='text';this.textContent='Hide';}else{f.type='password';this.textContent='Show';}" style="min-width: 60px;">Show</button>
                                    <button type="button" id="myhh-test-api" class="button button-secondary" onclick="myhh_test_grok_api()" style="min-width: 120px;">Test Connection</button>
                                </div>
                                <div id="myhh-api-test-result" style="margin-top: 8px;"></div>
                                <?php if ($api_key) : ?>
                                <p class="description" style="color: #059669; margin-top: 8px;">
                                    <span class="dashicons dashicons-yes-alt" style="vertical-align: middle;"></span>
                                    API key saved (<?php echo esc_html($masked_key); ?>)
                                </p>
                                <?php else : ?>
                                <p class="description" style="margin-top: 8px;">
                                    <span class="dashicons dashicons-warning" style="color: #D97706; vertical-align: middle;"></span>
                                    Required for Mia's AI features. <a href="https://console.x.ai" target="_blank">Get your API key</a>
                                </p>
                                <?php endif; ?>
                                <script>
                                function myhh_test_grok_api() {
                                    var key = document.getElementById('myhh_grok_api_key').value.trim();
                                    var result = document.getElementById('myhh-api-test-result');
                                    var btn = document.getElementById('myhh-test-api');
                                    
                                    if (!key) {
                                        result.innerHTML = '<p style="color:#D97706;"><span class="dashicons dashicons-warning"></span> Please enter an API key first.</p>';
                                        return;
                                    }
                                    
                                    btn.disabled = true;
                                    btn.textContent = 'Testing...';
                                    result.innerHTML = '<p style="color:#6B7280;"><span class="dashicons dashicons-update spin"></span> Testing connection...</p>';
                                    
                                    fetch('https://api.x.ai/v1/chat/completions', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Authorization': 'Bearer ' + key
                                        },
                                        body: JSON.stringify({
                                            model: 'grok-3-mini',
                                            messages: [{role: 'user', content: 'Say OK'}],
                                            max_tokens: 5
                                        })
                                    })
                                    .then(function(r) { return r.json(); })
                                    .then(function(data) {
                                        btn.disabled = false;
                                        btn.textContent = 'Test Connection';
                                        if (data.choices && data.choices[0]) {
                                            result.innerHTML = '<p style="color:#059669;"><span class="dashicons dashicons-yes-alt"></span> <strong>Success!</strong> Grok API is working. Key length: ' + key.length + ' chars.</p>';
                                        } else if (data.error) {
                                            result.innerHTML = '<p style="color:#DC2626;"><span class="dashicons dashicons-dismiss"></span> <strong>Error:</strong> ' + data.error + '</p>';
                                        } else {
                                            result.innerHTML = '<p style="color:#DC2626;"><span class="dashicons dashicons-dismiss"></span> Unexpected response from API.</p>';
                                        }
                                    })
                                    .catch(function(err) {
                                        btn.disabled = false;
                                        btn.textContent = 'Test Connection';
                                        result.innerHTML = '<p style="color:#DC2626;"><span class="dashicons dashicons-dismiss"></span> Connection failed: ' + err.message + '</p>';
                                    });
                                }
                                </script>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="myhh_openai_api_key">OpenAI API Key</label></th>
                            <td>
                                <input type="password" name="myhh_openai_api_key" id="myhh_openai_api_key" value="" class="regular-text" placeholder="sk-..." autocomplete="new-password" data-lpignore="true">
                                <button type="button" id="myhh-test-openai" class="button button-secondary">Test Decisions API</button>
                                <p class="description">
                                    <?php echo get_option('myhh_openai_api_key', '') ? 'OpenAI key saved. Leave blank to keep it, or enter a replacement.' : 'Enter your OpenAI API key to use Decisions.'; ?>
                                    <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">Get an API key</a>
                                </p>
                                <?php if (get_option('myhh_openai_api_key', '')) : ?>
                                <label><input type="checkbox" name="myhh_remove_openai_key" value="1"> Remove saved OpenAI key when saving</label>
                                <?php endif; ?>
                                <p class="description">The test sends a sample question to OpenAI and uses a small amount of API usage.</p>
                                <div id="myhh-openai-test-result" role="status" aria-live="polite"></div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="myhh_deepseek_api_key">Hive AI (DeepSeek) API Key</label></th>
                            <td>
                                <?php 
                                $hive_key = get_option('myhh_deepseek_api_key');
                                $hive_key_masked = $hive_key ? substr($hive_key, 0, 6) . '...' . substr($hive_key, -4) . ' (' . strlen($hive_key) . ' chars)' : '';
                                ?>
                                <input type="password" name="myhh_deepseek_api_key" id="myhh_deepseek_api_key" value="" class="regular-text" placeholder="Enter Hive API key" autocomplete="new-password" data-lpignore="true">
                                <button type="button" id="myhh-test-hive" class="button button-secondary">Test Connection</button>
                                <p class="description">
                                    <?php echo $hive_key ? 'Hive key saved (' . esc_html($hive_key_masked) . '). Leave blank to keep it.' : 'Enter your Hive AI API key for DeepSeek v4.1 Flash support.'; ?>
                                    <a href="https://dashboard.thehive.ai" target="_blank" rel="noopener noreferrer">Get your API key</a>
                                </p>
                                <div id="myhh-hive-test-result" role="status" aria-live="polite"></div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="myhh_mia_ai_provider">Mia Chat Provider</label></th>
                            <td>
                                <select name="myhh_mia_ai_provider" id="myhh_mia_ai_provider">
                                    <option value="grok" <?php selected(get_option('myhh_mia_ai_provider', 'grok'), 'grok'); ?>>Grok (xAI) — Fast, real-time voice</option>
                                    <option value="openai" <?php selected(get_option('myhh_mia_ai_provider', 'grok'), 'openai'); ?>>OpenAI — GPT-4, text chat</option>
                                    <option value="deepseek" <?php selected(get_option('myhh_mia_ai_provider', 'grok'), 'deepseek'); ?>>DeepSeek (via Hive) — Fast, cost-efficient chat</option>
                                </select>
                                <p class="description">
                                    Select which AI provider Mia uses for chat responses. <strong>Voice always uses Grok</strong> for best real-time performance.
                                    Switching takes effect on new page loads and new voice sessions.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="myhh_mia_decision_provider">Action Selection</label></th>
                            <td>
                                <select name="myhh_mia_decision_provider" id="myhh_mia_decision_provider">
                                    <option value="openai" <?php selected(get_option('myhh_mia_decision_provider', 'openai'), 'openai'); ?>>OpenAI Decisions</option>
                                    <option value="grok" <?php selected(get_option('myhh_mia_decision_provider', 'openai'), 'grok'); ?>>Grok Classification</option>
                                </select>
                                <p class="description">In Grok chat mode, this selects whether to use OpenAI Decisions (more accurate) or Grok classification (faster).</p>
                            </td>
                        </tr>
                    </table>
                    
                    <script>
                    (function () {
                        // OpenAI test
                        var buttonOpenAI = document.getElementById('myhh-test-openai');
                        buttonOpenAI.addEventListener('click', function () {
                            var result = document.getElementById('myhh-openai-test-result');
                            var body = new URLSearchParams();
                            body.set('action', 'myhh_test_openai_key');
                            body.set('nonce', <?php echo wp_json_encode(wp_create_nonce('myhh_test_openai_key')); ?>);
                            body.set('api_key', document.getElementById('myhh_openai_api_key').value.trim());
                            buttonOpenAI.disabled = true;
                            result.textContent = 'Testing OpenAI Decisions...';
                            fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
                                method: 'POST', credentials: 'same-origin', body: body
                            }).then(function (response) {
                                return response.json();
                            }).then(function (data) {
                                result.textContent = data && data.data && data.data.message ? data.data.message : 'Unexpected response. Refresh the page and try again.';
                                result.style.color = data.success ? '#059669' : '#DC2626';
                            }).catch(function () {
                                result.textContent = 'Connection failed. Refresh the page and try again.';
                                result.style.color = '#DC2626';
                            }).finally(function () {
                                buttonOpenAI.disabled = false;
                            });
                        });
                        
                        // Hive API test (server-side, avoids CORS issues)
                        var buttonHive = document.getElementById('myhh-test-hive');
                        if (buttonHive) {
                            buttonHive.addEventListener('click', function () {
                                var result = document.getElementById('myhh-hive-test-result');
                                var key = document.getElementById('myhh_deepseek_api_key').value.trim();
                                
                                if (!key) {
                                    result.innerHTML = '<p style="color:#D97706;"><span class="dashicons dashicons-warning"></span> Please enter a Hive API key first.</p>';
                                    return;
                                }
                                
                                buttonHive.disabled = true;
                                buttonHive.textContent = 'Testing...';
                                result.innerHTML = '<p style="color:#6B7280;"><span class="dashicons dashicons-update spin"></span> Testing Hive DeepSeek API...</p>';
                                
                                var body = new URLSearchParams();
                                body.set('action', 'myhh_test_hive_key');
                                body.set('nonce', <?php echo wp_json_encode(wp_create_nonce('myhh_test_hive_key')); ?>);
                                body.set('api_key', key);
                                
                                fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    body: body
                                })
                                .then(function(response) { return response.json(); })
                                .then(function(data) {
                                    buttonHive.disabled = false;
                                    buttonHive.textContent = 'Test Connection';
                                    if (data.success && data.data && data.data.message) {
                                        result.innerHTML = '<p style="color:#059669;"><span class="dashicons dashicons-yes-alt"></span> <strong>Success!</strong> ' + data.data.message + '</p>';
                                    } else if (data.data && data.data.message) {
                                        result.innerHTML = '<p style="color:#DC2626;"><span class="dashicons dashicons-dismiss"></span> <strong>Error:</strong> ' + data.data.message + '</p>';
                                    } else {
                                        result.innerHTML = '<p style="color:#DC2626;"><span class="dashicons dashicons-dismiss"></span> Unexpected response from server.</p>';
                                    }
                                })
                                .catch(function(err) {
                                    buttonHive.disabled = false;
                                    buttonHive.textContent = 'Test Connection';
                                    result.innerHTML = '<p style="color:#DC2626;"><span class="dashicons dashicons-dismiss"></span> Connection failed: ' + err.message + '</p>';
                                });
                            });
                        }
                    }());
                    </script>
                    
                    <p class="description" style="margin-bottom: 16px; padding: 12px; background: #FEF3C7; border-radius: 8px;">
                        <strong>Chat Providers:</strong>
                        <ul style="margin: 8px 0; padding-left: 20px;">
                            <li><strong>Grok:</strong> Fast, real-time voice support. Best for conversational experience.</li>
                            <li><strong>OpenAI:</strong> High accuracy, supports Decisions API. No real-time voice in this mode.</li>
                            <li><strong>DeepSeek (Hive):</strong> Cost-efficient, fast chat. Voice always uses Grok for real-time performance.</li>
                        </ul>
                    </p>
                    
                    <?php submit_button(__('Save Mia Settings', 'myhealthhub')); ?>
                </form>
            </div>
            
            <?php elseif ($active_tab === 'smtp') : ?>
            <!-- SMTP Settings Tab -->
            <div class="myhh-card">
                <div class="myhh-card-header">
                    <h2><span class="dashicons dashicons-email-alt"></span><?php _e('SMTP Email Settings', 'myhealthhub'); ?></h2>
                </div>
                
                <form method="post" action="options.php">
                    <?php settings_fields('myhh_smtp_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="myhh_smtp_enabled">
                                    <?php _e('Enable SMTP', 'myhealthhub'); ?>
                                </label>
                            </th>
                            <td>
                                <label class="myhh-toggle">
                                    <input type="checkbox" name="myhh_smtp_enabled" id="myhh_smtp_enabled" value="1" <?php checked(get_option('myhh_smtp_enabled'), 1); ?>>
                                    <span class="myhh-toggle-slider"></span>
                                </label>
                                <p class="description">
                                    <?php _e('Enable authenticated SMTP for all outgoing emails. Disable to use default PHP mail.', 'myhealthhub'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="myhh_smtp_host">
                                    <?php _e('SMTP Host', 'myhealthhub'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" name="myhh_smtp_host" id="myhh_smtp_host"
                                       value="<?php echo esc_attr(get_option('myhh_smtp_host', 'smtp.hostinger.com')); ?>"
                                       class="regular-text"
                                       placeholder="smtp.example.com">
                                <p class="description">
                                    <?php _e('SMTP server hostname (e.g., smtp.hostinger.com, smtp.gmail.com).', 'myhealthhub'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="myhh_smtp_port">
                                    <?php _e('SMTP Port', 'myhealthhub'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number" name="myhh_smtp_port" id="myhh_smtp_port"
                                       value="<?php echo esc_attr(get_option('myhh_smtp_port', 465)); ?>"
                                       class="small-text"
                                       min="1" max="65535">
                                <p class="description">
                                    <?php _e('Common ports: 465 (SSL), 587 (TLS), 25 (unencrypted).', 'myhealthhub'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="myhh_smtp_secure">
                                    <?php _e('Encryption', 'myhealthhub'); ?>
                                </label>
                            </th>
                            <td>
                                <select name="myhh_smtp_secure" id="myhh_smtp_secure">
                                    <option value="ssl" <?php selected(get_option('myhh_smtp_secure', 'ssl'), 'ssl'); ?>><?php _e('SSL', 'myhealthhub'); ?></option>
                                    <option value="tls" <?php selected(get_option('myhh_smtp_secure'), 'tls'); ?>><?php _e('TLS', 'myhealthhub'); ?></option>
                                    <option value="" <?php selected(get_option('myhh_smtp_secure'), ''); ?>><?php _e('None', 'myhealthhub'); ?></option>
                                </select>
                                <p class="description">
                                    <?php _e('SSL for port 465, TLS for port 587.', 'myhealthhub'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="myhh_smtp_user">
                                    <?php _e('SMTP Username', 'myhealthhub'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="email" name="myhh_smtp_user" id="myhh_smtp_user"
                                       value="<?php echo esc_attr(get_option('myhh_smtp_user')); ?>"
                                       class="regular-text"
                                       placeholder="hello@myhealthub.com">
                                <p class="description">
                                    <?php _e('Usually your full email address.', 'myhealthhub'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="myhh_smtp_pass">
                                    <?php _e('SMTP Password', 'myhealthhub'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="password" name="myhh_smtp_pass" id="myhh_smtp_pass"
                                       value="<?php echo esc_attr(get_option('myhh_smtp_pass')); ?>"
                                       class="regular-text"
                                       placeholder="••••••••••••">
                                <?php if (get_option('myhh_smtp_pass')) : ?>
                                <p class="description" style="color: #059669;">
                                    <span class="dashicons dashicons-yes-alt" style="vertical-align: middle;"></span>
                                    Password configured
                                </p>
                                <?php else : ?>
                                <p class="description">
                                    <span class="dashicons dashicons-warning" style="color: #D97706; vertical-align: middle;"></span>
                                    Enter the email account password from your hosting provider.
                                </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="myhh_smtp_from">
                                    <?php _e('From Email', 'myhealthhub'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="email" name="myhh_smtp_from" id="myhh_smtp_from"
                                       value="<?php echo esc_attr(get_option('myhh_smtp_from', get_option('admin_email'))); ?>"
                                       class="regular-text"
                                       placeholder="hello@myhealthub.com">
                                <p class="description">
                                    <?php _e('Email address shown as the sender.', 'myhealthhub'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="myhh_smtp_from_name">
                                    <?php _e('From Name', 'myhealthhub'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" name="myhh_smtp_from_name" id="myhh_smtp_from_name"
                                       value="<?php echo esc_attr(get_option('myhh_smtp_from_name', get_bloginfo('name'))); ?>"
                                       class="regular-text"
                                       placeholder="My Health Hub">
                                <p class="description">
                                    <?php _e('Name shown as the sender.', 'myhealthhub'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="description" style="margin-bottom: 16px; padding: 12px; background: #EEF2FF; border-radius: 8px;">
                        <strong>Tip:</strong> For Hostinger, use <code>smtp.hostinger.com</code> port <code>465</code> with SSL. The username is your email address and password is the email account password from Hostinger control panel.
                    </p>
                    
                    <?php submit_button(__('Save SMTP Settings', 'myhealthhub')); ?>
                </form>
                
                <!-- Test Email Section -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dcdcde;">
                    <h3 style="margin-bottom: 15px;"><span class="dashicons dashicons-email" style="vertical-align: middle; margin-right: 5px;"></span><?php _e('Test Email Configuration', 'myhealthhub'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="myhh_test_email"><?php _e('Test Email Address', 'myhealthhub'); ?></label>
                            </th>
                            <td>
                                <input type="email" id="myhh_test_email" 
                                       value="<?php echo esc_attr(get_option('admin_email')); ?>"
                                       class="regular-text"
                                       placeholder="test@example.com">
                                <input type="button" id="myhh_send_test_email" 
                                       class="button button-secondary" 
                                       value="<?php esc_attr_e('Send Test Email', 'myhealthhub'); ?>">
                                <span id="myhh_test_email_spinner" class="spinner" style="float: none; margin-left: 5px;"></span>
                                <p class="description">
                                    <?php _e('Send a test email to verify your SMTP configuration is working correctly.', 'myhealthhub'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <div id="myhh_test_email_result" style="display: none; margin-top: 15px; padding: 12px; border-radius: 8px;"></div>
                </div>
                
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#myhh_send_test_email').on('click', function() {
                        var btn = $(this);
                        var email = $('#myhh_test_email').val();
                        var spinner = $('#myhh_test_email_spinner');
                        var result = $('#myhh_test_email_result');
                        
                        if (!email) {
                            result.removeClass('notice-success').addClass('notice notice-error').html('<p>Please enter an email address.</p>').show();
                            return;
                        }
                        
                        btn.prop('disabled', true);
                        spinner.addClass('is-active');
                        result.hide();
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'myhh_test_smtp_email',
                                email: email,
                                nonce: '<?php echo wp_create_nonce('myhh_test_smtp'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    result.removeClass('notice-error').addClass('notice notice-success').html('<p>' + response.data.message + '</p>').show();
                                } else {
                                    result.removeClass('notice-success').addClass('notice notice-error').html('<p>' + response.data.message + '</p>').show();
                                }
                            },
                            error: function() {
                                result.removeClass('notice-success').addClass('notice notice-error').html('<p>Request failed. Please try again.</p>').show();
                            },
                            complete: function() {
                                btn.prop('disabled', false);
                                spinner.removeClass('is-active');
                            }
                        });
                    });
                });
                </script>
            </div>
            <?php endif; ?>
            
            <?php if ($active_tab === 'maps') : ?>
            <!-- Google Maps Settings Tab -->
            <div class="myhh-card">
                <div class="myhh-card-header">
                    <h2><span class="dashicons dashicons-location-alt"></span><?php _e('Google Maps Settings', 'myhealthhub'); ?></h2>
                </div>
                
                <form method="post" action="options.php">
                    <?php settings_fields('myhh_maps_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="myhh_google_maps_key">
                                    <?php _e('Google Maps API Key', 'myhealthhub'); ?>
                                </label>
                            </th>
                            <td>
                                <?php
                                $maps_key    = get_option('myhh_google_maps_key');
                                $maps_length = $maps_key ? strlen($maps_key) : 0;
                                $maps_masked = $maps_key ? substr($maps_key, 0, 8) . '...' . substr($maps_key, -4) . ' (' . $maps_length . ' chars)' : '';
                                ?>
                                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                    <input type="password"
                                           name="myhh_google_maps_key"
                                           id="myhh_google_maps_key"
                                           value="<?php echo esc_attr($maps_key); ?>"
                                           class="regular-text"
                                           placeholder="AIza..."
                                           autocomplete="off"
                                           data-lpignore="true"
                                           style="font-family: monospace;">
                                    <button type="button" id="myhh-toggle-maps-key" class="button"
                                            onclick="var f=document.getElementById('myhh_google_maps_key');if(f.type==='password'){f.type='text';this.textContent='Hide';}else{f.type='password';this.textContent='Show';}"
                                            style="min-width: 60px;">Show</button>
                                    <button type="button" id="myhh-test-maps-key" class="button button-secondary"
                                            onclick="myhh_test_maps_key()"
                                            style="min-width: 140px;">Test Connection</button>
                                </div>
                                <div id="myhh-maps-test-result" style="margin-top: 8px;"></div>
                                <?php if ($maps_key) : ?>
                                <p class="description" style="color: #059669; margin-top: 8px;">
                                    <span class="dashicons dashicons-yes-alt" style="vertical-align: middle;"></span>
                                    API key saved (<?php echo esc_html($maps_masked); ?>)
                                </p>
                                <?php else : ?>
                                <p class="description" style="margin-top: 8px;">
                                    <span class="dashicons dashicons-warning" style="color: #D97706; vertical-align: middle;"></span>
                                    Required for provider map display. <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Get your API key</a>
                                </p>
                                <?php endif; ?>
                                <script>
                                function myhh_test_maps_key() {
                                    var btn    = document.getElementById('myhh-test-maps-key');
                                    var result = document.getElementById('myhh-maps-test-result');
                                    var nonce  = '<?php echo wp_create_nonce('myhh_test_maps_key'); ?>';
                                    
                                    btn.disabled    = true;
                                    btn.textContent = 'Testing...';
                                    result.innerHTML = '<p style="color:#6B7280;"><span class="dashicons dashicons-update spin"></span> Testing connection...</p>';
                                    
                                    var xhr = new XMLHttpRequest();
                                    xhr.open('POST', ajaxurl, true);
                                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                    xhr.onload = function() {
                                        btn.disabled    = false;
                                        btn.textContent = 'Test Connection';
                                        try {
                                            var data = JSON.parse(xhr.responseText);
                                            if (data.success) {
                                                result.innerHTML = '<p style="color:#059669;"><span class="dashicons dashicons-yes-alt"></span> <strong>Success!</strong> ' + data.data.message + '</p>';
                                            } else {
                                                result.innerHTML = '<p style="color:#DC2626;"><span class="dashicons dashicons-dismiss"></span> <strong>Error:</strong> ' + data.data.message + '</p>';
                                            }
                                        } catch(e) {
                                            result.innerHTML = '<p style="color:#DC2626;"><span class="dashicons dashicons-dismiss"></span> Unexpected response.</p>';
                                        }
                                    };
                                    xhr.onerror = function() {
                                        btn.disabled    = false;
                                        btn.textContent = 'Test Connection';
                                        result.innerHTML = '<p style="color:#DC2626;"><span class="dashicons dashicons-dismiss"></span> Request failed.</p>';
                                    };
                                    xhr.send('action=myhh_test_maps_key&nonce=' + encodeURIComponent(nonce));
                                }
                                </script>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="description" style="margin-bottom: 16px; padding: 12px; background: #EEF2FF; border-radius: 8px;">
                        <strong>Note:</strong> Saving this key automatically syncs it to GeoDirectory so provider listings display Google Maps. Enable the <em>Maps JavaScript API</em> and <em>Geocoding API</em> in your <a href="https://console.cloud.google.com/apis/library" target="_blank">Google Cloud Console</a>.
                    </p>
                    
                    <?php submit_button(__('Save Maps Settings', 'myhealthhub')); ?>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Create missing required pages
     * @return int Number of pages created/updated
     */
    private function create_missing_pages() {
        $created = 0;
        $updated = 0;
        
        // Update existing pages with content if empty
        $updated += $this->maybe_update_page_content('about', $this->get_about_content());
        $updated += $this->maybe_update_page_content('contact', $this->get_contact_content());
        $updated += $this->maybe_update_page_content('providers', $this->get_providers_content());
        
        $pages = array(
            array(
                'title'    => 'Get Matched',
                'slug'     => 'get-matched',
                'template' => 'template-get-matched.php',
            ),
            array(
                'title'    => 'Claim Listing',
                'slug'     => 'claim-listing',
                'template' => 'template-claim-listing.php',
            ),
            array(
                'title'    => 'Submit Testimonial',
                'slug'     => 'submit-testimonial',
                'template' => 'template-testimonial.php',
            ),
            array(
                'title'    => 'About',
                'slug'     => 'about',
                'template' => '',
                'content'  => $this->get_about_content(),
            ),
            array(
                'title'    => 'Contact',
                'slug'     => 'contact',
                'template' => '',
                'content'  => $this->get_contact_content(),
            ),
            array(
                'title'    => 'Providers',
                'slug'     => 'providers',
                'template' => '',
                'content'  => '<div class="providers-directory-placeholder" style="text-align: center; padding: 80px 20px;">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#0A74DA" stroke-width="1.5" style="margin-bottom: 24px;">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <h2 style="margin-bottom: 16px;">Provider Directory Coming Soon</h2>
                    <p style="color: #6B7280; max-width: 500px; margin: 0 auto 32px;">We are currently building our network of verified mental health professionals in the Raleigh/Triangle area. Check back soon!</p>
                    <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
                        <a href="' . home_url('/get-matched/') . '" class="btn btn--primary btn--lg">Get Matched</a>
                        <a href="' . home_url('/contact/') . '" class="btn btn--outline btn--lg">Contact Us</a>
                    </div>
                </div>',
            ),
            array(
                'title'    => 'Privacy Policy',
                'slug'     => 'privacy-policy',
                'template' => '',
            ),
            array(
                'title'    => 'Terms of Service',
                'slug'     => 'terms',
                'template' => '',
            ),
            array(
                'title'    => 'Disclaimer',
                'slug'     => 'disclaimer',
                'template' => '',
            ),
            array(
                'title'    => 'How We Verify Listings',
                'slug'     => 'how-we-verify',
                'template' => '',
            ),
        );
        
        foreach ($pages as $page_data) {
            $existing = get_page_by_path($page_data['slug']);
            if ($existing) {
                continue;
            }
            
            $page_id = wp_insert_post(array(
                'post_title'   => $page_data['title'],
                'post_name'    => $page_data['slug'],
                'post_content' => isset($page_data['content']) ? $page_data['content'] : '',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => 1,
            ));
            
            if ($page_id && !is_wp_error($page_id)) {
                $created++;
                if (!empty($page_data['template'])) {
                    update_post_meta($page_id, '_wp_page_template', $page_data['template']);
                }
            }
        }
        
        // Set front page
        $front_page = get_page_by_path('home');
        if (!$front_page) {
            $front_id = wp_insert_post(array(
                'post_title'   => 'Home',
                'post_name'    => 'home',
                'post_content' => '',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => 1,
            ));
            if ($front_id && !is_wp_error($front_id)) {
                $created++;
                update_option('show_on_front', 'page');
                update_option('page_on_front', $front_id);
            }
        } else {
            update_option('show_on_front', 'page');
            update_option('page_on_front', $front_page->ID);
        }
        
        flush_rewrite_rules();
        
        return $created + $updated;
    }
    
    /**
     * Update page content if empty
     */
    private function maybe_update_page_content($slug, $content) {
        $page = get_page_by_path($slug);
        if ($page) {
            // Always update providers page
            $should_update = empty($page->post_content) || $slug === 'providers';
            if ($should_update) {
                wp_update_post(array(
                    'ID' => $page->ID,
                    'post_content' => $content,
                ));
                return 1;
            }
        }
        return 0;
    }
    
    /**
     * Get About page content
     */
    private function get_about_content() {
        return '<h2>Our Mission</h2>
<p>My Health Hub connects individuals and families in the Raleigh/Triangle area with verified mental health professionals. We believe everyone deserves access to quality mental health care, and finding the right provider should be simple and stress-free.</p>

<h2>What We Do</h2>
<p>We maintain a comprehensive directory of therapists, counselors, psychologists, psychiatrists, and life coaches serving Raleigh, Durham, Cary, Chapel Hill, and surrounding areas. Each listing includes detailed information about the provider\'s specialties, credentials, accepted insurance, and contact information.</p>

<h2>Our Verification Process</h2>
<p>Every professional in our directory undergoes a verification process to ensure they:</p>
<ul>
<li>Hold valid professional licenses in North Carolina</li>
<li>Maintain good standing with their licensing boards</li>
<li>Provide accurate information about their practice</li>
</ul>

<h2>Why Choose My Health Hub?</h2>
<ul>
<li><strong>Local Focus</strong> – We specialize in the Triangle area</li>
<li><strong>Verified Listings</strong> – All providers are vetted</li>
<li><strong>Comprehensive Information</strong> – Detailed profiles to help you make informed decisions</li>
<li><strong>Free to Use</strong> – No cost to search or contact providers</li>
<li><strong>Privacy Focused</strong> – Your information is secure and never sold</li>
</ul>';
    }
    
    /**
     * Get Contact page content
     */
    private function get_contact_content() {
        return '<h2>Get in Touch</h2>
<p>We\'re here to help! Whether you have questions about finding a provider, need assistance with your listing, or have feedback about our directory, we\'d love to hear from you.</p>

<h2>Ways to Reach Us</h2>
<p><strong>Email</strong><br>hello@myhealthub.com</p>
<p><strong>Business Hours</strong><br>Monday – Friday: 9:00 AM – 5:00 PM EST</p>

<h2>Quick Links</h2>
<ul>
<li>Looking for a provider? <a href="' . home_url('/providers/') . '">Browse our directory</a></li>
<li>Want personalized help? <a href="' . home_url('/get-matched/') . '">Get Matched</a></li>
<li>Are you a provider? <a href="' . home_url('/claim-listing/') . '">Claim your listing</a></li>
</ul>';
    }
    
    private function get_providers_content() {
        return '<div class="providers-directory-placeholder" style="text-align: center; padding: 80px 20px;">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#0A74DA" stroke-width="1.5" style="margin-bottom: 24px;">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <h2 style="margin-bottom: 16px;">Provider Directory Coming Soon</h2>
                    <p style="color: #6B7280; max-width: 500px; margin: 0 auto 32px;">We are currently building our network of verified mental health professionals in the Raleigh/Triangle area. Check back soon!</p>
                    <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
                        <a href="' . home_url('/get-matched/') . '" class="btn btn--primary btn--lg">Get Matched</a>
                        <a href="' . home_url('/contact/') . '" class="btn btn--outline btn--lg">Contact Us</a>
                    </div>
                </div>';
    }
    
    /**
     * Seed sample provider listings
     */
    public function seed_sample_providers() {
        // Check if already seeded
        if (get_option('myhh_sample_providers_seeded')) {
            return 0;
        }
        
        // Sample providers data
        $providers = array(
            array(
                'title'        => 'Dr. Sarah Johnson',
                'credentials'  => 'PhD, Licensed Psychologist',
                'short_bio'    => 'Specializing in anxiety disorders and trauma recovery with 15+ years of experience helping individuals find their path to healing.',
                'full_bio'     => 'Dr. Sarah Johnson is a licensed clinical psychologist with over 15 years of experience in private practice. She specializes in anxiety disorders, PTSD, and trauma recovery. Her approach integrates cognitive-behavioral therapy with mindfulness-based interventions to help clients develop lasting coping skills.',
                'specialties'  => array('Anxiety', 'Trauma & PTSD', 'Depression'),
                'city'         => 'Raleigh',
                'session_type' => 'Hybrid',
                'insurance'    => array('Blue Cross Blue Shield of NC', 'Aetna', 'UnitedHealthcare', 'Self-Pay'),
                'languages'    => array('English', 'Spanish'),
                'modalities'   => array('CBT (Cognitive Behavioral Therapy)', 'EMDR', 'Mindfulness-Based'),
                'website'      => 'https://example.com/dr-johnson',
                'booking_link' => 'https://example.com/book/johnson',
                'phone'        => '(919) 555-0101',
            ),
            array(
                'title'        => 'Michael Chen, LCSW',
                'credentials'  => 'LCSW, Licensed Clinical Social Worker',
                'short_bio'    => 'Compassionate therapist focused on helping individuals and couples navigate life transitions, relationship challenges, and personal growth.',
                'full_bio'     => 'Michael Chen brings a warm, collaborative approach to therapy. With specialized training in couples therapy and family systems, he helps clients improve communication and build stronger relationships. He also has extensive experience working with young adults navigating career and life transitions.',
                'specialties'  => array('Relationships', 'Life Transitions', 'Stress Management'),
                'city'         => 'Durham',
                'session_type' => 'Virtual',
                'insurance'    => array('Cigna', 'Medicaid', 'Sliding Scale'),
                'languages'    => array('English', 'Mandarin'),
                'modalities'   => array('Solution-Focused', 'Family Systems', 'Acceptance & Commitment Therapy (ACT)'),
                'website'      => 'https://example.com/mchen',
                'phone'        => '(919) 555-0102',
            ),
            array(
                'title'        => 'Dr. Amara Obi',
                'credentials'  => 'PsyD, Clinical Psychologist',
                'short_bio'    => 'Expert in culturally-affirming therapy for BIPOC communities, specializing in racial trauma, identity, and wellness.',
                'full_bio'     => 'Dr. Amara Obi is passionate about providing culturally-responsive mental health care. She creates a safe, affirming space for clients to explore issues of identity, racial trauma, and cultural adjustment. Her integrative approach draws from narrative therapy, CBT, and liberation psychology.',
                'specialties'  => array('Trauma & PTSD', 'LGBTQ+ Issues', 'Self-Esteem'),
                'city'         => 'Chapel Hill',
                'session_type' => 'Hybrid',
                'insurance'    => array('Blue Cross Blue Shield of NC', 'Medicare', 'Out of Network'),
                'languages'    => array('English'),
                'modalities'   => array('Psychodynamic', 'Trauma-Focused CBT', 'Mindfulness-Based'),
                'website'      => 'https://example.com/drobi',
                'booking_link' => 'https://example.com/book/obi',
                'phone'        => '(919) 555-0103',
            ),
            array(
                'title'        => 'Jennifer Martinez, LPC',
                'credentials'  => 'LPC, NCC, Licensed Professional Counselor',
                'short_bio'    => 'Helping children, adolescents, and families thrive through play therapy, family counseling, and evidence-based interventions.',
                'full_bio'     => 'Jennifer Martinez specializes in working with children, teens, and families. Using play therapy, cognitive-behavioral techniques, and family systems approaches, she helps young clients and their parents navigate challenges including ADHD, anxiety, behavioral issues, and family transitions like divorce.',
                'specialties'  => array('Family Therapy', 'ADHD', 'Eating Disorders'),
                'city'         => 'Cary',
                'session_type' => 'In-Person',
                'insurance'    => array('Aetna', 'UnitedHealthcare', 'Tricare', 'Self-Pay'),
                'languages'    => array('English', 'Spanish'),
                'modalities'   => array('CBT (Cognitive Behavioral Therapy)', 'Family Systems', 'DBT (Dialectical Behavior Therapy)'),
                'website'      => 'https://example.com/jmartinez',
                'phone'        => '(919) 555-0104',
            ),
            array(
                'title'        => 'Dr. William Thompson',
                'credentials'  => 'MD, Psychiatrist',
                'short_bio'    => 'Board-certified psychiatrist offering medication management and holistic treatment approaches for mood and anxiety disorders.',
                'full_bio'     => 'Dr. William Thompson is a board-certified psychiatrist with expertise in medication management for depression, anxiety, bipolar disorder, and ADHD. He takes a collaborative approach, working closely with therapists to provide comprehensive care. He believes in integrating medication with lifestyle interventions and therapy.',
                'specialties'  => array('Depression', 'Anxiety', 'ADHD'),
                'city'         => 'Raleigh',
                'session_type' => 'Hybrid',
                'insurance'    => array('Blue Cross Blue Shield of NC', 'Medicare', 'Medicaid'),
                'languages'    => array('English'),
                'modalities'   => array('Integrative'),
                'website'      => 'https://example.com/drthompson',
                'booking_link' => 'https://example.com/book/thompson',
                'phone'        => '(919) 555-0105',
            ),
        );
        
        $created = 0;
        
        foreach ($providers as $provider) {
            // Check if listing already exists
            $existing = get_page_by_title($provider['title'], OBJECT, 'gd_place');
            if ($existing) {
                continue;
            }
            
            // Create the listing
            $post_id = wp_insert_post(array(
                'post_title'   => $provider['title'],
                'post_type'    => 'gd_place',
                'post_status'  => 'publish',
                'post_content' => $provider['full_bio'],
                'post_author'  => 1,
            ));
            
            if ($post_id && !is_wp_error($post_id)) {
                $created++;
                
                // Set meta fields
                update_post_meta($post_id, 'provider_credentials', $provider['credentials']);
                update_post_meta($post_id, 'short_bio', $provider['short_bio']);
                update_post_meta($post_id, 'full_bio', $provider['full_bio']);
                update_post_meta($post_id, 'session_type', $provider['session_type']);
                update_post_meta($post_id, 'insurance_accepted', $provider['insurance']);
                update_post_meta($post_id, 'languages_spoken', $provider['languages']);
                update_post_meta($post_id, 'modalities', $provider['modalities']);
                update_post_meta($post_id, 'website', $provider['website']);
                update_post_meta($post_id, 'booking_link', $provider['booking_link'] ?? '');
                update_post_meta($post_id, 'phone', $provider['phone']);
                update_post_meta($post_id, 'verification_status', 'Verified');
                
                // Set specialties taxonomy
                if (!empty($provider['specialties'])) {
                    $term_ids = array();
                    foreach ($provider['specialties'] as $specialty) {
                        $term = term_exists($specialty, 'myhh_specialty');
                        if ($term) {
                            $term_ids[] = $term['term_id'];
                        }
                    }
                    if (!empty($term_ids)) {
                        wp_set_post_terms($post_id, $term_ids, 'myhh_specialty');
                    }
                }
                
                // Set city taxonomy
                if (!empty($provider['city'])) {
                    $term = term_exists($provider['city'], 'myhh_city');
                    if ($term) {
                        wp_set_post_terms($post_id, array($term['term_id']), 'myhh_city');
                    }
                }
            }
        }
        
        // Mark as seeded
        if ($created > 0) {
            update_option('myhh_sample_providers_seeded', true);
        }
        
        return $created;
    }
    
    /**
     * Practice submission columns
     */
    public function practice_submission_columns($columns) {
        $new_columns = array(
            'cb'        => $columns['cb'],
            'title'     => __('Practice Name', 'myhealthhub'),
            'provider'  => __('Provider', 'myhealthhub'),
            'email'     => __('Email', 'myhealthhub'),
            'city'      => __('City', 'myhealthhub'),
            'specialty' => __('Specialty', 'myhealthhub'),
            'status'    => __('Status', 'myhealthhub'),
            'date'      => $columns['date'],
        );
        return $new_columns;
    }
    
    /**
     * Practice submission column content
     */
    public function practice_submission_column_content($column, $post_id) {
        switch ($column) {
            case 'provider':
                echo esc_html(get_post_meta($post_id, '_myhh_provider_name', true));
                break;
            case 'email':
                $email = get_post_meta($post_id, '_myhh_email', true);
                if ($email) {
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                }
                break;
            case 'city':
                echo esc_html(get_post_meta($post_id, '_myhh_city', true));
                break;
            case 'specialty':
                echo esc_html(get_post_meta($post_id, '_myhh_specialty', true));
                break;
            case 'status':
                $status = get_post_meta($post_id, '_myhh_status', true);
                $status_labels = array(
                    'pending'  => '<span style="color: #D97706; background: #FEF3C7; padding: 4px 10px; border-radius: 20px; font-size: 12px;">Pending</span>',
                    'reviewed' => '<span style="color: #2563EB; background: #DBEAFE; padding: 4px 10px; border-radius: 20px; font-size: 12px;">Reviewed</span>',
                    'approved' => '<span style="color: #059669; background: #D1FAE5; padding: 4px 10px; border-radius: 20px; font-size: 12px;">Approved</span>',
                    'rejected' => '<span style="color: #DC2626; background: #FEE2E2; padding: 4px 10px; border-radius: 20px; font-size: 12px;">Rejected</span>',
                );
                echo isset($status_labels[$status]) ? $status_labels[$status] : $status_labels['pending'];
                break;
        }
    }
}
