<?php
/**
 * Plugin Name: My Health Hub Core
 * Plugin URI: https://myhealthub.com
 * Description: Core functionality for the My Health Hub directory - handles leads, claims, testimonials, and admin features.
 * Version: 1.0.0
 * Author: My Health Hub Team
 * Author URI: https://myhealthub.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: myhealthhub
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('MYHH_CORE_VERSION', '1.0.0');
define('MYHH_CORE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MYHH_CORE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MYHH_CORE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class MyHH_Core {
    
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
        $this->load_dependencies();
        $this->init();
    }
    
    /**
     * Load required class files
     */
    private function load_dependencies() {
        require_once MYHH_CORE_PLUGIN_DIR . 'includes/class-post-types.php';
        require_once MYHH_CORE_PLUGIN_DIR . 'includes/class-form-handler.php';
        require_once MYHH_CORE_PLUGIN_DIR . 'includes/class-admin.php';
        require_once MYHH_CORE_PLUGIN_DIR . 'includes/class-assets.php';
        require_once MYHH_CORE_PLUGIN_DIR . 'includes/class-geodirectory-integration.php';
        require_once MYHH_CORE_PLUGIN_DIR . 'includes/class-sample-provider-seeder.php';
        require_once MYHH_CORE_PLUGIN_DIR . 'includes/class-mia-intent-engine.php';
        require_once MYHH_CORE_PLUGIN_DIR . 'includes/class-mia-chatbot.php';
        require_once MYHH_CORE_PLUGIN_DIR . 'includes/class-email-automation.php';
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Initialize components
        MyHH_Post_Types::get_instance();
        MyHH_Form_Handler::get_instance();
        MyHH_Admin::get_instance();
        MyHH_Assets::get_instance();
        
        // Initialize email automation (Milestone 3)
        MyHH_Email_Automation::get_instance();
        
        // Seed default data on init
        add_action('wp_loaded', array($this, 'seed_default_data'));
        
        // Manual seed trigger via URL
        add_action('init', array($this, 'maybe_manual_seed'));
        
        // Admin menu for provider seeder
        add_action('admin_menu', array($this, 'add_seeder_menu'));
        
        // Handle seeder actions
        add_action('admin_init', array($this, 'handle_seeder_actions'));
        
        // Disable GeoDirectory admin hints on frontend
        add_filter('geodir_disable_hints', '__return_true');
        
        // Activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Seed default data (pages, menus, terms)
     */
    public function seed_default_data() {
        // Only run if not already done (check using option)
        if (get_option('myhh_core_data_seeded')) {
            return;
        }
        
        global $wpdb;
        
        // Guard: do nothing if the posts table doesn't exist yet
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->posts}'");
        if (!$table_exists) {
            return;
        }
        
        // Seed pages
        $this->seed_required_pages();
        
        // Seed menus
        $this->create_navigation_menus();
        
        // Mark as done
        update_option('myhh_core_data_seeded', true);
    }
    
    /**
     * Seed all required pages
     */
    private function seed_required_pages() {
        $pages = array(
            array(
                'title'    => 'Get Matched',
                'slug'     => 'get-matched',
                'template' => 'template-get-matched.php',
                'content'  => '',
            ),
            array(
                'title'    => 'Claim Listing',
                'slug'     => 'claim-listing',
                'template' => 'template-claim-listing.php',
                'content'  => '',
            ),
            array(
                'title'    => 'Submit Testimonial',
                'slug'     => 'submit-testimonial',
                'template' => 'template-testimonial.php',
                'content'  => '',
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
                'content'  => $this->get_privacy_content(),
            ),
            array(
                'title'    => 'Terms of Service',
                'slug'     => 'terms',
                'template' => '',
                'content'  => $this->get_terms_content(),
            ),
            array(
                'title'    => 'Disclaimer',
                'slug'     => 'disclaimer',
                'template' => '',
                'content'  => $this->get_disclaimer_content(),
            ),
            array(
                'title'    => 'How We Verify Listings',
                'slug'     => 'how-we-verify',
                'template' => '',
                'content'  => $this->get_verification_content(),
            ),
        );
        
        foreach ($pages as $page_data) {
            // Check if page already exists by slug
            $existing = get_page_by_path($page_data['slug']);
            if ($existing) {
                continue;
            }
            
            $page_id = wp_insert_post(array(
                'post_title'   => $page_data['title'],
                'post_name'    => $page_data['slug'],
                'post_content' => $page_data['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => 1,
            ));
            
            // Assign page template if specified
            if ($page_id && !is_wp_error($page_id) && !empty($page_data['template'])) {
                update_post_meta($page_id, '_wp_page_template', $page_data['template']);
            }
        }
        
        // Create and set a static front page
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
                update_option('show_on_front', 'page');
                update_option('page_on_front', $front_id);
            }
        } else {
            update_option('show_on_front', 'page');
            update_option('page_on_front', $front_page->ID);
        }
        
        // Set permalinks to post name
        if (get_option('permalink_structure') === '') {
            update_option('permalink_structure', '/%postname%/');
            flush_rewrite_rules();
        }
    }
    
    /**
     * Create navigation menus
     */
    private function create_navigation_menus() {
        // Check if menus already exist
        $existing_primary = wp_get_nav_menu_object('Primary Menu');
        $existing_footer = wp_get_nav_menu_object('Footer Menu');
        
        // Create primary menu
        if (!$existing_primary) {
            $primary_menu_id = wp_create_nav_menu('Primary Menu');
            if ($primary_menu_id) {
                $providers_page = get_page_by_path('providers');
                $about_page = get_page_by_path('about');
                $contact_page = get_page_by_path('contact');
                $get_matched_page = get_page_by_path('get-matched');
                
                // Add menu items
                if ($providers_page) {
                    wp_update_nav_menu_item($primary_menu_id, 0, array(
                        'menu-item-title' => __('All Providers', 'myhealthhub'),
                        'menu-item-url' => home_url('/providers/'),
                        'menu-item-status' => 'publish',
                        'menu-item-type' => 'custom',
                    ));
                }
                
                if ($about_page) {
                    wp_update_nav_menu_item($primary_menu_id, 0, array(
                        'menu-item-title' => __('About', 'myhealthhub'),
                        'menu-item-object-id' => $about_page->ID,
                        'menu-item-object' => 'page',
                        'menu-item-status' => 'publish',
                        'menu-item-type' => 'post_type',
                    ));
                }
                
                if ($contact_page) {
                    wp_update_nav_menu_item($primary_menu_id, 0, array(
                        'menu-item-title' => __('Contact', 'myhealthhub'),
                        'menu-item-object-id' => $contact_page->ID,
                        'menu-item-object' => 'page',
                        'menu-item-status' => 'publish',
                        'menu-item-type' => 'post_type',
                    ));
                }
                
                // Set as primary menu
                set_theme_mod('nav_menu_locations', array('primary' => $primary_menu_id));
            }
        }
        
        // Create footer menu
        if (!$existing_footer) {
            $footer_menu_id = wp_create_nav_menu('Footer Menu');
            if ($footer_menu_id) {
                $pages = array(
                    get_page_by_path('about'),
                    get_page_by_path('contact'),
                    get_page_by_path('privacy-policy'),
                    get_page_by_path('terms'),
                    get_page_by_path('disclaimer'),
                    get_page_by_path('how-we-verify'),
                );
                
                foreach ($pages as $page) {
                    if ($page) {
                        wp_update_nav_menu_item($footer_menu_id, 0, array(
                            'menu-item-title' => $page->post_title,
                            'menu-item-object-id' => $page->ID,
                            'menu-item-object' => 'page',
                            'menu-item-status' => 'publish',
                            'menu-item-type' => 'post_type',
                        ));
                    }
                }
                
                // Set as footer menu
                $locations = get_theme_mod('nav_menu_locations', array());
                $locations['footer'] = $footer_menu_id;
                set_theme_mod('nav_menu_locations', $locations);
            }
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Clear the seeded flag to force re-seeding
        delete_option('myhh_core_data_seeded');
        
        // Seed data immediately
        $this->seed_default_data();
        
        // Flush rewrite rules for CPTs
        flush_rewrite_rules();
    }
    
    /**
     * Manual seed trigger for admins
     */
    public function maybe_manual_seed() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['myhh_seed_pages']) && $_GET['myhh_seed_pages'] === '1') {
            delete_option('myhh_core_data_seeded');
            $this->seed_default_data();
            wp_redirect(admin_url('admin.php?page=myhealthhub&message=seeded'));
            exit;
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Add seeder admin menu
     */
    public function add_seeder_menu() {
        add_submenu_page(
            'myhh-dashboard',
            'Sample Providers',
            'Sample Providers',
            'manage_options',
            'myhh-sample-providers',
            array($this, 'render_seeder_page')
        );
    }
    
    /**
     * Render seeder admin page
     */
    public function render_seeder_page() {
        $seeder = new MyHH_Sample_Provider_Seeder();
        $provider_count = wp_count_posts('gd_place')->publish;
        ?>
        <div class="wrap">
            <h1>Sample Provider Data</h1>
            
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2>Current Status</h2>
                <p><strong>Provider Listings:</strong> <?php echo esc_html($provider_count); ?></p>
            </div>
            
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2>Seed Sample Providers</h2>
                <p>This will create 12 sample mental health provider listings with realistic data and profile images.</p>
                <p style="color: #d63638;"><strong>Warning:</strong> This will add new listings to your database. Existing listings with the same names will be skipped.</p>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=myhh-sample-providers&action=seed'), 'myhh_seed_providers')); ?>" 
                   class="button button-primary" 
                   onclick="return confirm('Are you sure you want to seed sample providers?');">
                    Seed Sample Providers
                </a>
            </div>
            
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2>Clear All Providers</h2>
                <p style="color: #d63638;"><strong>Warning:</strong> This will permanently delete ALL provider listings. This action cannot be undone.</p>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=myhh-sample-providers&action=clear'), 'myhh_clear_providers')); ?>" 
                   class="button" 
                   style="color: #d63638; border-color: #d63638;"
                   onclick="return confirm('Are you sure you want to DELETE ALL providers? This cannot be undone!');">
                    Delete All Providers
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle seeder actions
     */
    public function handle_seeder_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'myhh-sample-providers') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $seeder = new MyHH_Sample_Provider_Seeder();
        
        // Seed providers
        if (isset($_GET['action']) && $_GET['action'] === 'seed') {
            check_admin_referer('myhh_seed_providers');
            
            $results = $seeder->seed();
            
            $message = sprintf(
                'Seeding complete! Created: %d, Skipped: %d, Errors: %d',
                $results['created'],
                $results['skipped'],
                count($results['errors'])
            );
            
            wp_redirect(admin_url('admin.php?page=myhh-sample-providers&message=' . urlencode($message)));
            exit;
        }
        
        // Clear providers
        if (isset($_GET['action']) && $_GET['action'] === 'clear') {
            check_admin_referer('myhh_clear_providers');
            
            $count = $seeder->clear();
            
            $message = sprintf('Deleted %d providers.', $count);
            
            wp_redirect(admin_url('admin.php?page=myhh-sample-providers&message=' . urlencode($message)));
            exit;
        }
        
        // Display message
        if (isset($_GET['message'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($_GET['message']) . '</p></div>';
            });
        }
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
</ul>

<h2>The Team</h2>
<p>My Health Hub was founded by a team passionate about improving access to mental health care in our community. We work closely with local providers to ensure our directory remains accurate and up-to-date.</p>';
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
</ul>

<h2>For Providers</h2>
<p>Interested in being listed in our directory? Contact us to learn more about our verification process and listing options.</p>

<h2>Feedback</h2>
<p>Your feedback helps us improve. If you have suggestions for how we can better serve the community, please let us know.</p>';
    }
    
    /**
     * Get Privacy Policy content
     */
    private function get_privacy_content() {
        return '<p><em>Last Updated: ' . date('F j, Y') . '</em></p>

<h2>Your Privacy Matters</h2>
<p>At My Health Hub, we understand that seeking mental health support is a deeply personal journey. This Privacy Policy outlines how we collect, use, and protect your information with the respect and care you deserve.</p>

<h2>Information We Collect</h2>

<h3>Information You Provide</h3>
<p>When you use our services, you may share personal details with us:</p>
<ul>
<li><strong>Contact Information:</strong> Name, email address, and phone number when you reach out or submit forms</li>
<li><strong>Matching Preferences:</strong> Location, therapy preferences, and specific needs when using our "Get Matched" service</li>
<li><strong>Provider Submissions:</strong> Professional credentials, practice details, and contact information when adding your practice</li>
<li><strong>Testimonials:</strong> Experiences and feedback you choose to share</li>
</ul>

<h3>Information Collected Automatically</h3>
<p>To improve your experience, we may collect technical information including your browser type, device information, pages visited, and general location data. This helps us understand how visitors use our directory and identify areas for improvement.</p>

<h2>How We Use Your Information</h2>
<p>We use the information you entrust to us to:</p>
<ul>
<li>Connect you with mental health professionals who match your needs</li>
<li>Process and verify provider listings to maintain directory quality</li>
<li>Respond to your inquiries and provide customer support</li>
<li>Send important updates about our services (you can opt out anytime)</li>
<li>Improve our website and develop new features</li>
<li>Protect the security and integrity of our platform</li>
</ul>

<h2>Information Sharing</h2>
<p><strong>We never sell your personal information.</strong> We only share information in limited circumstances:</p>
<ul>
<li><strong>With Providers:</strong> When you submit a "Get Matched" request, we share relevant details with providers who can help</li>
<li><strong>With Service Partners:</strong> Trusted third parties who help us operate our website (they must protect your data)</li>
<li><strong>When Required:</strong> To comply with legal obligations or protect safety</li>
</ul>

<h2>Data Security</h2>
<p>We implement industry-standard security measures including encrypted data transmission, secure servers, and regular security assessments. While no online platform can guarantee complete security, we are committed to protecting your information to the best of our ability.</p>

<h2>Your Rights and Choices</h2>
<p>You have the right to:</p>
<ul>
<li><strong>Access:</strong> Request a copy of the personal information we hold about you</li>
<li><strong>Correct:</strong> Update or correct inaccurate information</li>
<li><strong>Delete:</strong> Request deletion of your personal information</li>
<li><strong>Opt Out:</strong> Unsubscribe from marketing communications at any time</li>
</ul>
<p>To exercise these rights, please contact us through our <a href="' . home_url('/contact/') . '">Contact page</a>.</p>

<h2>Children\'s Privacy</h2>
<p>Our services are intended for adults seeking mental health resources. We do not knowingly collect information from children under 13. If you believe we have inadvertently collected such information, please contact us immediately.</p>

<h2>Policy Updates</h2>
<p>We may update this Privacy Policy periodically to reflect changes in our practices or legal requirements. We will post the updated policy on this page with a new "Last Updated" date.</p>

<h2>Questions?</h2>
<p>If you have questions or concerns about this Privacy Policy, please <a href="' . home_url('/contact/') . '">contact us</a>. We are committed to addressing your privacy concerns promptly and transparently.</p>';
    }
    
    /**
     * Get Terms of Service content
     */
    private function get_terms_content() {
        return '<p><em>Last Updated: ' . date('F j, Y') . '</em></p>

<h2>Agreement to Terms</h2>
<p>Welcome to My Health Hub. By accessing or using our website and services, you agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use our services.</p>

<h2>What We Offer</h2>
<p>My Health Hub provides a directory of mental health professionals in the Raleigh/Triangle area of North Carolina. Our services include:</p>
<ul>
<li>A searchable directory of therapists, counselors, psychologists, and other wellness professionals</li>
<li>A "Get Matched" service to help connect you with providers suited to your needs</li>
<li>Provider profile management for listed professionals</li>
<li>A platform for testimonials and feedback</li>
</ul>

<h2>Your Responsibilities</h2>
<h3>When Using Our Directory</h3>
<p>You agree to use our services honestly and respectfully. Specifically, you agree to:</p>
<ul>
<li>Provide accurate information when submitting forms or inquiries</li>
<li>Respect the privacy and boundaries of listed providers</li>
<li>Use our "Get Matched" service genuinely to seek mental health support</li>
<li>Not impersonate others or misrepresent your identity</li>
</ul>

<h3>Prohibited Activities</h3>
<p>You may not:</p>
<ul>
<li>Use our services for any illegal purpose</li>
<li>Attempt to gain unauthorized access to our systems or user accounts</li>
<li>Scrape, copy, or distribute provider information without permission</li>
<li>Submit false testimonials or misleading information</li>
<li>Harass, threaten, or harm providers or other users</li>
<li>Interfere with the proper functioning of our website</li>
</ul>

<h2>Provider Listings</h2>
<h3>For Listed Providers</h3>
<p>If you are listed in our directory or submit your practice for inclusion, you are responsible for:</p>
<ul>
<li>Providing accurate and current information about your credentials and services</li>
<li>Maintaining valid professional licenses</li>
<li>Responding to legitimate inquiries in a professional manner</li>
<li>Notifying us of changes to your practice or credentials</li>
</ul>
<p>We verify credentials during the listing process but rely on providers to maintain accuracy. We reserve the right to remove listings that contain false information, violate our guidelines, or receive substantiated complaints.</p>

<h3>Claiming Your Profile</h3>
<p>Providers may claim their listings to manage their profile information. By claiming a listing, you confirm that you are authorized to represent the practice or professional associated with that listing.</p>

<h2>Testimonials and User Content</h2>
<p>When you submit a testimonial or other content, you grant My Health Hub a non-exclusive, royalty-free license to display, reproduce, and distribute that content on our website and marketing materials. You represent that your testimonial is truthful and based on genuine experience.</p>
<p>We moderate all testimonials and reserve the right to reject or remove content that is false, defamatory, offensive, or violates these terms.</p>

<h2>Intellectual Property</h2>
<p>The My Health Hub name, logo, and website content are protected by intellectual property laws. You may not use our branding or copy our content without permission. Provider profiles remain the property of their respective owners.</p>

<h2>Disclaimer of Warranties</h2>
<p>Our services are provided "as is" and "as available." While we strive for accuracy, we cannot guarantee:</p>
<ul>
<li>The completeness or accuracy of provider information</li>
<li>The availability or responsiveness of any provider</li>
<li>The quality or outcomes of services provided by listed professionals</li>
<li>That our website will be uninterrupted or error-free</li>
</ul>

<h2>Limitation of Liability</h2>
<p>To the fullest extent permitted by law, My Health Hub shall not be liable for any indirect, incidental, special, consequential, or punitive damages arising from your use of our services. This includes damages for loss of profits, data, or other intangible losses.</p>

<h2>Indemnification</h2>
<p>You agree to indemnify and hold My Health Hub harmless from any claims, damages, or expenses arising from your use of our services or violation of these terms.</p>

<h2>Governing Law</h2>
<p>These Terms of Service are governed by the laws of the State of North Carolina, without regard to conflict of law principles. Any disputes shall be resolved in the courts of North Carolina.</p>

<h2>Changes to Terms</h2>
<p>We may update these Terms of Service periodically. Continued use of our services after changes constitutes acceptance of the updated terms. We encourage you to review this page regularly.</p>

<h2>Contact Us</h2>
<p>For questions about these Terms of Service, please <a href="' . home_url('/contact/') . '">contact us</a>.</p>';
    }
    
    /**
     * Get Disclaimer content
     */
    private function get_disclaimer_content() {
        return '<p><em>Last Updated: ' . date('F j, Y') . '</em></p>

<h2>Understanding Our Role</h2>
<p>My Health Hub is a directory service designed to help connect individuals with mental health professionals in the Raleigh/Triangle area. We provide information to help you make informed decisions, but we are not a healthcare provider, and our website does not constitute medical advice.</p>

<h2>Not Medical Advice</h2>
<p>The content on this website—including provider profiles, articles, and matching suggestions—is for informational purposes only. It is not intended to be a substitute for professional medical advice, diagnosis, or treatment. Always seek the advice of a qualified mental health professional with any questions you may have regarding a mental health condition or treatment.</p>
<p>Never disregard professional medical advice or delay seeking treatment because of something you have read on this website.</p>

<h2>Emergency Resources</h2>
<p><strong>If you are experiencing a mental health emergency, please seek immediate help:</strong></p>
<div style="background: #FEF3C7; border-left: 4px solid #F59E0B; padding: 20px; margin: 20px 0; border-radius: 8px;">
<p style="margin: 0 0 10px;"><strong>Call 911</strong> if you or someone else is in immediate danger</p>
<p style="margin: 0 0 10px;"><strong>Suicide & Crisis Lifeline:</strong> Call or text <strong>988</strong> (available 24/7)</p>
<p style="margin: 0 0 10px;"><strong>Crisis Text Line:</strong> Text <strong>HOME</strong> to <strong>741741</strong></p>
<p style="margin: 0;"><strong>Go to your nearest emergency room</strong></p>
</div>

<h2>About Our Provider Directory</h2>
<h3>What We Verify</h3>
<p>We verify that listed providers hold valid professional licenses in North Carolina at the time of listing. However:</p>
<ul>
<li>License status can change between our verification periods</li>
<li>We do not evaluate the quality of care provided</li>
<li>We do not endorse any specific provider or treatment approach</li>
<li>We do not monitor ongoing compliance with professional standards</li>
</ul>

<h3>Your Responsibility</h3>
<p>Before engaging any provider, we encourage you to:</p>
<ul>
<li>Verify their current license status with the appropriate licensing board</li>
<li>Confirm they accept your insurance or discuss payment options</li>
<li>Research their background, specialties, and approach</li>
<li>Schedule an initial consultation to assess fit</li>
<li>Trust your instincts—if something feels wrong, seek a second opinion</li>
</ul>

<h2>Testimonials and Reviews</h2>
<p>Testimonials displayed on our website reflect individual experiences and opinions. They are not representative of all client experiences and should not be interpreted as guarantees of outcomes. Your experience with any provider may differ.</p>

<h2>No Therapeutic Relationship</h2>
<p>Using this website does not create a therapist-client, doctor-patient, or any other professional relationship between you and My Health Hub or any listed provider. A professional relationship is only established through direct engagement with a provider.</p>

<h2>Accuracy of Information</h2>
<p>While we strive to keep provider information accurate and up-to-date, we rely on providers to maintain their profiles. Information may change without notice. We recommend confirming important details directly with providers before your appointment.</p>

<h2>Limitation of Liability</h2>
<p>My Health Hub, its owners, employees, and affiliates shall not be liable for any decisions made or actions taken based on information provided on this website. Use of our directory is entirely at your own risk.</p>

<h2>External Links</h2>
<p>Our website may contain links to external websites operated by providers or other third parties. We are not responsible for the content, accuracy, or practices of these external sites.</p>

<h2>Questions?</h2>
<p>If you have questions about this disclaimer, please <a href="' . home_url('/contact/') . '">contact us</a>.</p>';
    }
    
    /**
     * Get Verification page content
     */
    private function get_verification_content() {
        return '<p><em>Last Updated: ' . date('F j, Y') . '</em></p>

<h2>Our Commitment to Quality</h2>
<p>Finding the right mental health provider is a significant decision. At My Health Hub, we believe you deserve confidence in the professionals you find through our directory. Our verification process helps ensure that every listed provider meets professional standards and maintains valid credentials.</p>

<h2>What Verification Means</h2>
<p>When you see a provider in our directory, it means we have confirmed that they hold a valid professional license in North Carolina. However, verification is not an endorsement—we encourage you to conduct your own research and find the provider who best fits your needs.</p>

<h2>Our Verification Process</h2>

<h3>Step 1: Information Collection</h3>
<p>When a provider is added to our directory—either through self-submission or our research—they provide:</p>
<ul>
<li>Full legal name and professional credentials</li>
<li>License number(s) and issuing board(s)</li>
<li>Practice name, location, and contact information</li>
<li>Areas of specialization and services offered</li>
<li>Insurance accepted and payment options</li>
</ul>

<h3>Step 2: License Verification</h3>
<p>We verify professional licenses through official licensing boards in North Carolina, including:</p>
<ul>
<li><strong>North Carolina Psychology Board</strong> – for licensed psychologists</li>
<li><strong>North Carolina Board of Licensed Clinical Mental Health Counselors</strong> – for LPCs and LCMHCs</li>
<li><strong>North Carolina Social Work Certification and Licensure Board</strong> – for LCSWs and LMSWs</li>
<li><strong>North Carolina Medical Board</strong> – for psychiatrists and psychiatric nurse practitioners</li>
<li><strong>North Carolina Board of Marriage and Family Therapy</strong> – for LMFTs</li>
</ul>

<h3>Step 3: Standing Confirmation</h3>
<p>We confirm that each license is:</p>
<ul>
<li>Currently active and valid</li>
<li>In good standing with no current restrictions</li>
<li>Free of disciplinary actions or sanctions</li>
</ul>

<h3>Step 4: Identity Verification</h3>
<p>For providers who claim their listings, we verify their identity through:</p>
<ul>
<li>A verified email address associated with the practice</li>
<li>Proof of professional standing (website, professional directory listing, or license verification page)</li>
<li>Confirmation that the claimant matches the licensed professional</li>
</ul>

<h3>Step 5: Content Review</h3>
<p>We review all profile information for:</p>
<ul>
<li>Accuracy and consistency with license records</li>
<li>Professional presentation and appropriate language</li>
<li>Compliance with our content guidelines</li>
<li>Clear and helpful information for potential clients</li>
</ul>

<h2>Ongoing Monitoring</h2>
<p>Verification is not a one-time event. We maintain ongoing quality through:</p>
<ul>
<li><strong>Periodic Re-verification:</strong> We re-check license status at regular intervals</li>
<li><strong>Disciplinary Monitoring:</strong> We monitor for board actions or sanctions</li>
<li><strong>User Feedback:</strong> We review and investigate reports from directory users</li>
<li><strong>Provider Updates:</strong> We encourage providers to keep their information current</li>
</ul>

<h2>Provider Responsibilities</h2>
<p>Listed providers agree to:</p>
<ul>
<li>Maintain valid, active licenses</li>
<li>Keep their profile information accurate and up-to-date</li>
<li>Notify us of changes to their practice or credentials</li>
<li>Respond to legitimate client inquiries professionally</li>
<li>Comply with our content and conduct guidelines</li>
</ul>

<h2>Claiming Your Listing</h2>
<p>Are you a provider who appears in our directory? Claiming your listing allows you to manage your profile, update information, and respond to testimonials.</p>
<ol>
<li><strong>Find Your Profile:</strong> Locate your listing in our directory</li>
<li><strong>Click "Claim This Listing":</strong> Start the claim process</li>
<li><strong>Verify Your Identity:</strong> Provide documentation proving you are the listed professional</li>
<li><strong>Review and Submit:</strong> Our team reviews claims within 1–2 business days</li>
<li><strong>Manage Your Profile:</strong> Once approved, update your information anytime</li>
</ol>

<h2>Reporting Concerns</h2>
<p>Help us maintain directory quality. If you encounter:</p>
<ul>
<li>Inaccurate provider information</li>
<li>A provider who may be practicing without a valid license</li>
<li>Unprofessional conduct or ethical concerns</li>
<li>Any other issues with a listing</li>
</ul>
<p>Please <a href="' . home_url('/contact/') . '">contact us</a> immediately. We investigate all reports and take appropriate action.</p>

<h2>Understanding Limitations</h2>
<p>While we are committed to accuracy, please understand:</p>
<ul>
<li>License status can change between our verification periods</li>
<li>We do not evaluate clinical competence or treatment outcomes</li>
<li>Provider information may change without notice</li>
<li>Verification does not guarantee a good fit for your needs</li>
</ul>
<p><strong>We always recommend verifying credentials directly with licensing boards before beginning treatment.</strong></p>

<h2>Questions?</h2>
<p>If you have questions about our verification process or want to report a concern, please <a href="' . home_url('/contact/') . '">contact us</a>.</p>';
    }
}

// Initialize plugin
MyHH_Core::get_instance();
