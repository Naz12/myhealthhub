<?php
/**
 * GeoDirectory Integration Class
 *
 * Handles all GeoDirectory-specific configurations and customizations.
 * This class only loads when GeoDirectory is active.
 *
 * @package MyHealthHub
 * @subpackage Core
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GeoDirectory Integration Class
 */
class MyHH_GeoDirectory_Integration {
    
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
        // Only proceed if GeoDirectory is active
        if (!$this->is_geodirectory_active()) {
            return;
        }
        
        $this->init();
    }
    
    /**
     * Check if GeoDirectory is active
     */
    private function is_geodirectory_active() {
        return class_exists('GeoDirectory');
    }
    
    /**
     * Initialize integration
     */
    private function init() {
        // Register custom fields
        add_filter('geodir_custom_fields', array($this, 'register_custom_fields'));
        add_filter('geodir_custom_fields_predefined', array($this, 'register_predefined_fields'));
        
        // Register taxonomies
        add_action('init', array($this, 'register_taxonomies'), 20);
        
        // Setup location for Raleigh/Triangle
        add_action('geodir_after_default_locations_set', array($this, 'setup_default_location'));
        
        // Customize listing output
        add_filter('geodir_get_template_part', array($this, 'customize_templates'), 10, 3);
        
        // Override archive item template with our custom cards
        add_filter('geodir_bypass_archive_item_template_content', array($this, 'custom_archive_item_template'), 10, 3);
        
        // Override single template content to prevent GeoDirectory default output
        add_filter('geodir_overwrite_single_template_content', array($this, 'override_single_template_content'), 10, 3);
        
        // Disable GeoDirectory location output on single pages
        add_filter('geodir_show_listing_location', '__return_false', 10);
        add_filter('geodir_post_address_hidden', '__return_true', 10);
        
        // Add custom search filters
        add_filter('geodir_search_cpt', array($this, 'customize_search'));
        
        // Modify listing query
        add_filter('geodir_filter_query_vars', array($this, 'modify_listing_query'));
        
        // Enqueue custom assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 20);
        
        // Add custom body classes
        add_filter('body_class', array($this, 'add_body_classes'));
        
        // Customize map markers
        add_filter('geodir_map_marker_icon', array($this, 'customize_map_marker'), 10, 3);
        
        // Setup hook - runs once on activation
        add_action('admin_init', array($this, 'maybe_run_setup'));
        
        // Schedule daily check for corrupted city terms
        add_action('admin_init', array($this, 'schedule_city_term_check'));
        add_action('myhh_check_city_terms', array($this, 'fix_corrupted_city_terms'));
        
        // Auto-assign coordinates when provider is saved (fallback for missing coordinates)
        add_action('save_post_gd_place', array($this, 'auto_assign_coordinates'), 20, 3);
        add_action('publish_gd_place', array($this, 'auto_assign_coordinates'), 20, 3);
        
        // Randomize listing order for variety (unless specific sort is requested)
        add_action('pre_get_posts', array($this, 'randomize_listing_order'));
        
        // Admin menu for category migration (Milestone 3)
        add_action('admin_menu', array($this, 'add_category_migration_menu'));
        
        // Check and run auto-migration if needed (Milestone 3)
        add_action('admin_init', array($this, 'maybe_run_auto_migration'));
    }
    
    /**
     * Register custom fields for provider listings
     */
    public function register_custom_fields($fields) {
        // Professional Information
        $fields[] = array(
            'post_type'           => 'gd_place',
            'data_type'           => 'VARCHAR',
            'field_type'          => 'text',
            'field_type_key'      => 'provider_credentials',
            'admin_title'         => __('Credentials/License', 'myhealthhub'),
            'frontend_title'      => __('Credentials', 'myhealthhub'),
            'frontend_desc'       => __('e.g., LCSW, PhD, MD, LPC', 'myhealthhub'),
            'is_active'           => '1',
            'for_admin_use'       => '0',
            'is_default'          => '0',
            'show_in'             => '[detail]',
            'show_on_listing'     => '1',
            'show_in_email'       => '1',
            'is_required'         => '1',
            'validation_pattern'  => '',
            'validation_msg'      => '',
            'field_icon'          => 'fas fa-certificate',
        );
        
        $fields[] = array(
            'post_type'           => 'gd_place',
            'data_type'           => 'VARCHAR',
            'field_type'          => 'text',
            'field_type_key'      => 'license_number',
            'admin_title'         => __('License Number', 'myhealthhub'),
            'frontend_title'      => __('License Number', 'myhealthhub'),
            'frontend_desc'       => __('For verification purposes', 'myhealthhub'),
            'is_active'           => '1',
            'for_admin_use'       => '1',
            'is_default'          => '0',
            'show_in'             => '[]',
            'show_on_listing'     => '0',
            'show_in_email'       => '0',
            'is_required'         => '0',
            'field_icon'          => 'fas fa-id-card',
        );
        
        // Practice Details
        $fields[] = array(
            'post_type'           => 'gd_place',
            'data_type'           => 'TINYINT',
            'field_type'          => 'radio',
            'field_type_key'      => 'session_type',
            'admin_title'         => __('Session Type', 'myhealthhub'),
            'frontend_title'      => __('Session Type', 'myhealthhub'),
            'frontend_desc'       => '',
            'is_active'           => '1',
            'for_admin_use'       => '0',
            'is_default'          => '0',
            'show_in'             => '[detail]',
            'show_on_listing'     => '1',
            'option_values'       => 'In-Person,/nVirtual,/nHybrid',
            'field_icon'          => 'fas fa-video',
        );
        
        $fields[] = array(
            'post_type'           => 'gd_place',
            'data_type'           => 'TINYINT',
            'field_type'          => 'multiselect',
            'field_type_key'      => 'insurance_accepted',
            'admin_title'         => __('Insurance Accepted', 'myhealthhub'),
            'frontend_title'      => __('Insurance Accepted', 'myhealthhub'),
            'frontend_desc'       => __('Select all that apply', 'myhealthhub'),
            'is_active'           => '1',
            'for_admin_use'       => '0',
            'is_default'          => '0',
            'show_in'             => '[detail]',
            'show_on_listing'     => '1',
            'option_values'       => $this->get_insurance_options(),
            'field_icon'          => 'fas fa-file-medical',
        );
        
        $fields[] = array(
            'post_type'           => 'gd_place',
            'data_type'           => 'TINYINT',
            'field_type'          => 'multiselect',
            'field_type_key'      => 'languages_spoken',
            'admin_title'         => __('Languages Spoken', 'myhealthhub'),
            'frontend_title'      => __('Languages', 'myhealthhub'),
            'frontend_desc'       => '',
            'is_active'           => '1',
            'for_admin_use'       => '0',
            'is_default'          => '0',
            'show_in'             => '[detail]',
            'show_on_listing'     => '1',
            'option_values'       => 'English,/nSpanish,/nFrench,/nMandarin,/nVietnamese,/nKorean,/nArabic,/nHindi,/nOther',
            'field_icon'          => 'fas fa-language',
        );
        
        // Contact & Booking
        $fields[] = array(
            'post_type'           => 'gd_place',
            'data_type'           => 'VARCHAR',
            'field_type'          => 'url',
            'field_type_key'      => 'booking_link',
            'admin_title'         => __('Online Booking Link', 'myhealthhub'),
            'frontend_title'      => __('Book Online', 'myhealthhub'),
            'frontend_desc'       => __('Link to online scheduling system', 'myhealthhub'),
            'is_active'           => '1',
            'for_admin_use'       => '0',
            'is_default'          => '0',
            'show_in'             => '[detail]',
            'show_on_listing'     => '1',
            'field_icon'          => 'fas fa-calendar-check',
        );
        
        $fields[] = array(
            'post_type'           => 'gd_place',
            'data_type'           => 'VARCHAR',
            'field_type'          => 'url',
            'field_type_key'      => 'website',
            'admin_title'         => __('Website', 'myhealthhub'),
            'frontend_title'      => __('Website', 'myhealthhub'),
            'frontend_desc'       => '',
            'is_active'           => '1',
            'for_admin_use'       => '0',
            'is_default'          => '0',
            'show_in'             => '[detail]',
            'show_on_listing'     => '1',
            'field_icon'          => 'fas fa-globe',
        );
        
        // Treatment Modalities
        $fields[] = array(
            'post_type'           => 'gd_place',
            'data_type'           => 'TINYINT',
            'field_type'          => 'multiselect',
            'field_type_key'      => 'modalities',
            'admin_title'         => __('Treatment Modalities', 'myhealthhub'),
            'frontend_title'      => __('Treatment Modalities', 'myhealthhub'),
            'frontend_desc'       => __('Select all treatment approaches you use', 'myhealthhub'),
            'is_active'           => '1',
            'for_admin_use'       => '0',
            'is_default'          => '0',
            'show_in'             => '[detail]',
            'show_on_listing'     => '1',
            'option_values'       => 'CBT (Cognitive Behavioral Therapy),/nDBT (Dialectical Behavior Therapy),/nEMDR,/nPsychodynamic,/nMindfulness-Based,/nSolution-Focused,/nFamily Systems,/nHumanistic,/nIntegrative,/nTrauma-Focused CBT,/nAcceptance & Commitment Therapy (ACT),/nInterpersonal Therapy',
            'field_icon'          => 'fas fa-brain',
        );
        
        // Short Bio
        $fields[] = array(
            'post_type'           => 'gd_place',
            'data_type'           => 'VARCHAR',
            'field_type'          => 'text',
            'field_type_key'      => 'short_bio',
            'admin_title'         => __('Short Bio', 'myhealthhub'),
            'frontend_title'      => __('Short Bio', 'myhealthhub'),
            'frontend_desc'       => __('Brief introduction (max 200 characters)', 'myhealthhub'),
            'is_active'           => '1',
            'for_admin_use'       => '0',
            'is_default'          => '0',
            'show_in'             => '[detail]',
            'show_on_listing'     => '1',
            'is_required'         => '0',
            'extra_fields'        => array('maxlength' => 200),
            'field_icon'          => 'fas fa-user',
        );
        
        // Full Bio / About
        $fields[] = array(
            'post_type'           => 'gd_place',
            'data_type'           => 'TEXT',
            'field_type'          => 'textarea',
            'field_type_key'      => 'full_bio',
            'admin_title'         => __('Full Bio / About', 'myhealthhub'),
            'frontend_title'      => __('About', 'myhealthhub'),
            'frontend_desc'       => __('Detailed description of your practice and approach', 'myhealthhub'),
            'is_active'           => '1',
            'for_admin_use'       => '0',
            'is_default'          => '0',
            'show_in'             => '[detail]',
            'show_on_listing'     => '0',
            'is_required'         => '0',
            'field_icon'          => 'fas fa-file-alt',
        );
        
        // Verification Status (internal use)
        $fields[] = array(
            'post_type'           => 'gd_place',
            'data_type'           => 'TINYINT',
            'field_type'          => 'select',
            'field_type_key'      => 'verification_status',
            'admin_title'         => __('Verification Status', 'myhealthhub'),
            'frontend_title'      => '',
            'frontend_desc'       => '',
            'is_active'           => '1',
            'for_admin_use'       => '1',
            'is_default'          => '0',
            'show_in'             => '[]',
            'show_on_listing'     => '0',
            'option_values'       => 'Pending,/nVerified,/nRejected',
            'field_icon'          => 'fas fa-check-circle',
        );
        
        return $fields;
    }
    
    /**
     * Register predefined fields
     */
    public function register_predefined_fields($fields) {
        // Add custom predefined fields if needed
        return $fields;
    }
    
    /**
     * Register custom taxonomies for specialties
     */
    public function register_taxonomies() {
        // Specialty taxonomy
        if (!taxonomy_exists('myhh_specialty')) {
            register_taxonomy('myhh_specialty', 'gd_place', array(
                'labels' => array(
                    'name'          => __('Specialties', 'myhealthhub'),
                    'singular_name' => __('Specialty', 'myhealthhub'),
                    'search_items'  => __('Search Specialties', 'myhealthhub'),
                    'all_items'     => __('All Specialties', 'myhealthhub'),
                    'edit_item'     => __('Edit Specialty', 'myhealthhub'),
                    'add_new_item'  => __('Add New Specialty', 'myhealthhub'),
                ),
                'hierarchical'      => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'query_var'         => true,
                'rewrite'           => array('slug' => 'specialty'),
                'show_in_rest'      => true,
            ));
            
            // Seed default specialties
            $this->seed_specialties();
        }
        
        // City/Area taxonomy for Triangle region
        if (!taxonomy_exists('myhh_city')) {
            register_taxonomy('myhh_city', 'gd_place', array(
                'labels' => array(
                    'name'          => __('Cities', 'myhealthhub'),
                    'singular_name' => __('City', 'myhealthhub'),
                    'search_items'  => __('Search Cities', 'myhealthhub'),
                    'all_items'     => __('All Cities', 'myhealthhub'),
                    'edit_item'     => __('Edit City', 'myhealthhub'),
                    'add_new_item'  => __('Add New City', 'myhealthhub'),
                ),
                'hierarchical'      => false,
                'show_ui'           => true,
                'show_admin_column' => true,
                'query_var'         => true,
                'rewrite'           => array('slug' => 'city'),
                'show_in_rest'      => true,
            ));
            
            // Seed Triangle cities
            $this->seed_cities();
        }
        
        // License Type taxonomy
        if (!taxonomy_exists('myhh_license_type')) {
            register_taxonomy('myhh_license_type', 'gd_place', array(
                'labels' => array(
                    'name'          => __('License Types', 'myhealthhub'),
                    'singular_name' => __('License Type', 'myhealthhub'),
                    'search_items'  => __('Search License Types', 'myhealthhub'),
                    'all_items'     => __('All License Types', 'myhealthhub'),
                    'edit_item'     => __('Edit License Type', 'myhealthhub'),
                    'add_new_item'  => __('Add New License Type', 'myhealthhub'),
                ),
                'hierarchical'      => false,
                'show_ui'           => true,
                'show_admin_column' => true,
                'query_var'         => true,
                'rewrite'           => array('slug' => 'license-type'),
                'show_in_rest'      => true,
            ));
            
            // Seed license types
            $this->seed_license_types();
        }
        
        // Primary Category taxonomy (Milestone 3 - 12 wellness categories)
        if (!taxonomy_exists('myhh_primary_category')) {
            register_taxonomy('myhh_primary_category', 'gd_place', array(
                'labels' => array(
                    'name'          => __('Primary Categories', 'myhealthhub'),
                    'singular_name' => __('Primary Category', 'myhealthhub'),
                    'search_items'  => __('Search Primary Categories', 'myhealthhub'),
                    'all_items'     => __('All Primary Categories', 'myhealthhub'),
                    'edit_item'     => __('Edit Primary Category', 'myhealthhub'),
                    'add_new_item'  => __('Add New Primary Category', 'myhealthhub'),
                    'menu_name'     => __('Wellness Categories', 'myhealthhub'),
                ),
                'hierarchical'      => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'query_var'         => true,
                'rewrite'           => array('slug' => 'wellness-category'),
                'show_in_rest'      => true,
                'capabilities'      => array(
                    'manage_terms' => 'manage_categories',
                    'edit_terms'   => 'manage_categories',
                    'delete_terms' => 'manage_categories',
                    'assign_terms' => 'edit_posts',
                ),
            ));
            
            // Seed primary categories
            $this->seed_primary_categories();
            
            // Auto-migrate existing providers to Mental Health & Therapy
            $this->auto_migrate_existing_providers();
            
            // Flush rewrite rules for new taxonomy
            flush_rewrite_rules();
        }
    }
    
    /**
     * Seed default specialties
     */
    private function seed_specialties() {
        $specialties = array(
            'Anxiety' => array(
                'Generalized Anxiety',
                'Panic Disorder',
                'Social Anxiety',
                'OCD',
            ),
            'Depression' => array(
                'Major Depression',
                'Postpartum Depression',
                'Seasonal Affective',
            ),
            'Trauma & PTSD' => array(
                'Childhood Trauma',
                'PTSD',
                'Complex PTSD',
                'EMDR',
            ),
            'Relationships' => array(
                'Couples Therapy',
                'Family Therapy',
                'Premarital Counseling',
                'Divorce Recovery',
            ),
            'Life Transitions' => array(
                'Career Changes',
                'Grief & Loss',
                'Aging & Elder Care',
                'Retirement',
            ),
            'Addiction' => array(
                'Substance Abuse',
                'Behavioral Addictions',
                'Recovery Support',
            ),
            'Eating Disorders' => array(),
            'ADHD' => array(),
            'LGBTQ+ Issues' => array(),
            'Stress Management' => array(),
            'Self-Esteem' => array(),
            'Anger Management' => array(),
            'Sleep Issues' => array(),
            'Chronic Illness' => array(),
            'Mindfulness' => array(),
        );
        
        foreach ($specialties as $parent => $children) {
            $parent_term = term_exists($parent, 'myhh_specialty');
            if (!$parent_term) {
                $result = wp_insert_term($parent, 'myhh_specialty');
                if (!is_wp_error($result)) {
                    $parent_id = $result['term_id'];
                    
                    foreach ($children as $child) {
                        if (!term_exists($child, 'myhh_specialty', $parent_id)) {
                            wp_insert_term($child, 'myhh_specialty', array('parent' => $parent_id));
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Seed Triangle cities
     */
    private function seed_cities() {
        $cities = array(
            'Raleigh' => array('description' => 'Capital city, central location'),
            'Durham' => array('description' => 'Home to Duke University'),
            'Chapel Hill' => array('description' => 'Home to UNC-Chapel Hill'),
            'Cary' => array('description' => 'Suburban community between Raleigh and Durham'),
            'Apex' => array('description' => 'Historic downtown, family-friendly'),
            'Morrisville' => array('description' => 'Near RTP, diverse community'),
            'Holly Springs' => array('description' => 'Growing southern suburb'),
            'Fuquay-Varina' => array('description' => 'Small town charm'),
            'Wake Forest' => array('description' => 'Northern Wake County'),
            'Garner' => array('description' => 'Southeast of Raleigh'),
            'Knightdale' => array('description' => 'Eastern Wake County'),
            'Carrboro' => array('description' => 'Adjacent to Chapel Hill'),
        );
        
        foreach ($cities as $city => $data) {
            if (!term_exists($city, 'myhh_city')) {
                wp_insert_term($city, 'myhh_city', $data);
            }
        }
        
        // Fix any corrupted city terms (names that are numeric)
        $this->fix_corrupted_city_terms();
    }
    
    /**
     * Fix city terms that have numeric names (corrupted data)
     * This prevents the issue where term IDs get stored as term names
     */
    private function fix_corrupted_city_terms() {
        $valid_cities = array(
            'raleigh' => 'Raleigh',
            'durham' => 'Durham',
            'chapel-hill' => 'Chapel Hill',
            'cary' => 'Cary',
            'apex' => 'Apex',
            'morrisville' => 'Morrisville',
            'holly-springs' => 'Holly Springs',
            'fuquay-varina' => 'Fuquay-Varina',
            'wake-forest' => 'Wake Forest',
            'garner' => 'Garner',
            'knightdale' => 'Knightdale',
            'carrboro' => 'Carrboro',
        );
        
        $terms = get_terms(array(
            'taxonomy'   => 'myhh_city',
            'hide_empty' => false,
        ));
        
        if (is_wp_error($terms) || empty($terms)) {
            return;
        }
        
        global $wpdb;
        $terms_table = $wpdb->prefix . 'terms';
        
        foreach ($terms as $term) {
            // Check if the name is numeric (corrupted)
            if (is_numeric($term->name)) {
                // Try to find the correct name from slug
                $slug = $term->slug;
                $correct_name = isset($valid_cities[$slug]) ? $valid_cities[$slug] : null;
                
                // If slug is also numeric, try to get city from post meta
                if (!$correct_name) {
                    $providers = get_posts(array(
                        'post_type' => 'gd_place',
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'myhh_city',
                                'field' => 'term_id',
                                'terms' => $term->term_id,
                            ),
                        ),
                        'posts_per_page' => 1,
                    ));
                    
                    if (!empty($providers)) {
                        $city_meta = get_post_meta($providers[0]->ID, 'city', true);
                        if (!empty($city_meta) && !is_numeric($city_meta)) {
                            $correct_name = $city_meta;
                        }
                    }
                }
                
                // Fix the term name
                if ($correct_name) {
                    $wpdb->update(
                        $terms_table,
                        array('name' => $correct_name, 'slug' => sanitize_title($correct_name)),
                        array('term_id' => $term->term_id)
                    );
                    clean_term_cache($term->term_id, 'myhh_city');
                }
            }
        }
    }
    
    /**
     * Seed license types
     */
    private function seed_license_types() {
        $license_types = array(
            'LCMHC' => array('description' => 'Licensed Clinical Mental Health Counselor'),
            'LPC' => array('description' => 'Licensed Professional Counselor'),
            'LCSW' => array('description' => 'Licensed Clinical Social Worker'),
            'LMFT' => array('description' => 'Licensed Marriage and Family Therapist'),
            'Psychologist' => array('description' => 'Licensed Psychologist'),
            'LCAS' => array('description' => 'Licensed Clinical Addiction Specialist'),
            'Psychiatrist' => array('description' => 'Psychiatrist (MD/DO)'),
        );
        
        foreach ($license_types as $type => $data) {
            if (!term_exists($type, 'myhh_license_type')) {
                wp_insert_term($type, 'myhh_license_type', $data);
            }
        }
    }
    
    /**
     * Seed primary categories (Milestone 3 - 12 wellness categories)
     */
    private function seed_primary_categories() {
        $categories = array(
            'Primary Care & Medical Services' => array(
                'slug' => 'primary-care-medical-services',
                'description' => 'General healthcare, family medicine, internal medicine, and medical specialists.',
            ),
            'Mental Health & Therapy' => array(
                'slug' => 'mental-health-therapy',
                'description' => 'Counseling, psychotherapy, psychiatry, and mental health support services.',
            ),
            'Fitness, Yoga & Movement' => array(
                'slug' => 'fitness-yoga-movement',
                'description' => 'Personal training, yoga, pilates, dance, and movement-based wellness.',
            ),
            'Nutrition & Dietary Support' => array(
                'slug' => 'nutrition-dietary-support',
                'description' => 'Registered dietitians, nutritionists, and dietary counseling.',
            ),
            'Massage, Spa & Recovery' => array(
                'slug' => 'massage-spa-recovery',
                'description' => 'Massage therapy, spa services, and physical recovery treatments.',
            ),
            'Sleep & Rest Wellness' => array(
                'slug' => 'sleep-rest-wellness',
                'description' => 'Sleep specialists, sleep coaching, and rest optimization.',
            ),
            'Social Connections & Support Groups' => array(
                'slug' => 'social-connections-support-groups',
                'description' => 'Support groups, community organizations, and social wellness programs.',
            ),
            'Financial Wellness & Planning' => array(
                'slug' => 'financial-wellness-planning',
                'description' => 'Financial advisors, financial therapy, and money wellness coaching.',
            ),
            'Career & Work-Life Coaching' => array(
                'slug' => 'career-work-life-coaching',
                'description' => 'Career coaches, executive coaching, and work-life balance specialists.',
            ),
            'Integrative & Holistic Therapies' => array(
                'slug' => 'integrative-holistic-therapies',
                'description' => 'Acupuncture, naturopathy, functional medicine, and holistic health.',
            ),
            'Meditation, Mindfulness & Spirituality' => array(
                'slug' => 'meditation-mindfulness-spirituality',
                'description' => 'Meditation teachers, mindfulness coaches, and spiritual wellness guides.',
            ),
            'Personal Growth & Learning' => array(
                'slug' => 'personal-growth-learning',
                'description' => 'Life coaching, personal development, and educational wellness programs.',
            ),
        );
        
        foreach ($categories as $name => $data) {
            if (!term_exists($name, 'myhh_primary_category')) {
                wp_insert_term($name, 'myhh_primary_category', array(
                    'slug'        => $data['slug'],
                    'description' => $data['description'],
                ));
            }
        }
    }
    
    /**
     * Auto-assign coordinates when a provider is saved
     * This is a fallback for providers without coordinates
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public function auto_assign_coordinates($post_id, $post, $update) {
        // Don't run on autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Don't run on revisions
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // Check if coordinates already exist
        $existing_lat = get_post_meta($post_id, 'latitude', true);
        $existing_lng = get_post_meta($post_id, 'longitude', true);
        
        if (!empty($existing_lat) && !empty($existing_lng)) {
            // Coordinates already set, no need to auto-assign
            return;
        }
        
        // City coordinates for Triangle area
        $city_coords = array(
            'Raleigh' => array('lat' => 35.7796, 'lng' => -78.6382),
            'Durham' => array('lat' => 35.9949, 'lng' => -78.8986),
            'Chapel Hill' => array('lat' => 35.9132, 'lng' => -79.0558),
            'Cary' => array('lat' => 35.7915, 'lng' => -78.7811),
            'Apex' => array('lat' => 35.7327, 'lng' => -78.8503),
            'Morrisville' => array('lat' => 35.8329, 'lng' => -78.8242),
            'Holly Springs' => array('lat' => 35.6513, 'lng' => -78.8336),
            'Fuquay-Varina' => array('lat' => 35.5838, 'lng' => -78.8003),
            'Wake Forest' => array('lat' => 35.9729, 'lng' => -78.5097),
            'Garner' => array('lat' => 35.7113, 'lng' => -78.6142),
            'Knightdale' => array('lat' => 35.7877, 'lng' => -78.5053),
            'Carrboro' => array('lat' => 35.9102, 'lng' => -79.0695),
            'Pittsboro' => array('lat' => 35.7201, 'lng' => -79.1775),
            'Hillsborough' => array('lat' => 36.0754, 'lng' => -79.0997),
        );
        
        // Get city from post meta
        $city = get_post_meta($post_id, 'city', true);
        
        // If no city meta, try to get from taxonomy
        if (empty($city)) {
            $city_terms = get_the_terms($post_id, 'myhh_city');
            if ($city_terms && !is_wp_error($city_terms) && !empty($city_terms)) {
                $city = $city_terms[0]->name;
            }
        }
        
        // If still no city, can't auto-assign
        if (empty($city)) {
            return;
        }
        
        // Check if we have coordinates for this city
        if (!isset($city_coords[$city])) {
            // Try case-insensitive match
            $city_lower = strtolower($city);
            foreach ($city_coords as $city_name => $coords) {
                if (strtolower($city_name) === $city_lower) {
                    $city_coords[$city] = $coords;
                    break;
                }
            }
        }
        
        if (!isset($city_coords[$city])) {
            return;
        }
        
        // Add small random offset to prevent markers from overlapping
        $lat = $city_coords[$city]['lat'] + (rand(-100, 100) / 10000);
        $lng = $city_coords[$city]['lng'] + (rand(-100, 100) / 10000);
        
        // Store coordinates
        update_post_meta($post_id, 'latitude', $lat);
        update_post_meta($post_id, 'longitude', $lng);
    }
    
    /**
     * Setup default location for Raleigh/Triangle
     */
    public function setup_default_location() {
        $default_location = array(
            'city'       => 'Raleigh',
            'region'     => 'NC',
            'country'    => 'US',
            'latitude'   => '35.7796',
            'longitude'  => '-78.6382',
            'is_default' => '1',
        );
        
        // GeoDirectory will handle the actual location creation
        do_action('geodir_set_default_location', $default_location);
    }
    
    /**
     * Customize GeoDirectory templates
     */
    public function customize_templates($template, $slug, $name) {
        // Use theme templates when available
        if ($slug === 'content-listing' && $name === 'listing') {
            $theme_template = locate_template('template-parts/listing-card.php');
            if ($theme_template) {
                return $theme_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Customize search functionality
     */
    public function customize_search($cpt) {
        // Add custom search parameters
        return $cpt;
    }
    
    /**
     * Modify listing query
     */
    public function modify_listing_query($query_vars) {
        // Add custom query modifications
        return $query_vars;
    }
    
    /**
     * Enqueue custom assets for GeoDirectory pages
     */
    public function enqueue_assets() {
        if (!geodir_is_page('listing') && !geodir_is_page('archive') && !geodir_is_page('search')) {
            return;
        }
        
        // Enqueue custom styles for GeoDirectory
        wp_enqueue_style(
            'myhh-geodirectory',
            MYHH_CORE_PLUGIN_URL . 'assets/css/geodirectory.css',
            array(),
            MYHH_CORE_VERSION
        );
    }
    
    /**
     * Add custom body classes
     */
    public function add_body_classes($classes) {
        if (function_exists('geodir_is_page')) {
            if (geodir_is_page('listing')) {
                $classes[] = 'myhh-listing-page';
            }
            if (geodir_is_page('archive')) {
                $classes[] = 'myhh-archive-page';
            }
            if (geodir_is_page('search')) {
                $classes[] = 'myhh-search-page';
            }
        }
        
        return $classes;
    }
    
    /**
     * Customize map marker icons
     */
    public function customize_map_marker($icon, $post_id, $post_type) {
        // Return custom marker URL based on category or other criteria
        return $icon;
    }
    
    /**
     * Maybe run setup routine
     */
    public function maybe_run_setup() {
        if (get_option('myhh_geodirectory_setup_complete')) {
            return;
        }
        
        // Run setup
        $this->setup_default_location();
        
        // Mark as complete
        update_option('myhh_geodirectory_setup_complete', true);
    }
    
    /**
     * Schedule daily check for corrupted city terms
     */
    public function schedule_city_term_check() {
        if (!wp_next_scheduled('myhh_check_city_terms')) {
            wp_schedule_event(time(), 'daily', 'myhh_check_city_terms');
        }
    }
    
    /**
     * Override archive item template with custom provider card
     */
    public function custom_archive_item_template($content, $original_content, $page_id) {
        global $gd_post;
        
        if (!$gd_post || !isset($gd_post->ID)) {
            return $content;
        }
        
        $post_id = $gd_post->ID;
        
        // Get listing data
        $credentials = get_post_meta($post_id, 'provider_credentials', true);
        $session_type = get_post_meta($post_id, 'session_type', true);
        $short_bio = get_post_meta($post_id, 'short_bio', true);
        $verification_status = get_post_meta($post_id, 'verification_status', true);
        $provider_specialties = get_the_terms($post_id, 'myhh_specialty');
        $provider_cities = get_the_terms($post_id, 'myhh_city');
        
        // Prepare specialties array
        $specialty_names = array();
        if ($provider_specialties && !is_wp_error($provider_specialties)) {
            $specialty_names = wp_list_pluck($provider_specialties, 'name');
        }
        
        // Prepare city
        $city_name = '';
        if ($provider_cities && !is_wp_error($provider_cities)) {
            $city_name = $provider_cities[0]->name;
        }
        
        // Get thumbnail
        $thumbnail = get_the_post_thumbnail_url($post_id, 'medium');
        $title = get_the_title($post_id);
        $permalink = get_permalink($post_id);
        
        // Generate initials for placeholder
        $initials = '';
        $name_parts = explode(' ', $title);
        foreach ($name_parts as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        if (strlen($initials) > 2) {
            $initials = substr($initials, 0, 2);
        }
        
        // Build the card HTML
        ob_start();
        ?>
        <div class="provider-card" style="background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s; border: 1px solid #E5E7EB;">
            <a href="<?php echo esc_url($permalink); ?>" style="text-decoration: none; color: inherit;">
                <div class="provider-card__image" style="height: 180px; background: linear-gradient(135deg, #EEF2FF 0%, #DBEAFE 100%); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                    <?php if ($thumbnail) : ?>
                        <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($title); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else : ?>
                        <span style="font-size: 3rem; font-weight: 700; color: #0A74DA; opacity: 0.6;"><?php echo esc_html($initials); ?></span>
                    <?php endif; ?>
                    
                    <?php if ($verification_status === 'Verified') : ?>
                        <span style="position: absolute; top: 12px; right: 12px; background: rgba(16, 185, 129, 0.95); color: #fff; font-size: 0.75rem; font-weight: 600; padding: 4px 10px; border-radius: 20px; display: flex; align-items: center; gap: 4px;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <path d="M20 6L9 17l-5-5"></path>
                            </svg>
                            Verified
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="provider-card__content" style="padding: 20px;">
                    <h3 style="font-size: 1.125rem; font-weight: 700; margin: 0 0 4px; color: #1F2937; line-height: 1.3;">
                        <?php echo esc_html($title); ?><?php if ($credentials) : ?><span style="font-weight: 400; color: #6B7280; font-size: 0.9375rem;"><?php echo ', ' . esc_html($credentials); ?></span><?php endif; ?>
                    </h3>
                    
                    <?php if ($city_name) : ?>
                        <div style="display: flex; align-items: center; gap: 4px; color: #6B7280; font-size: 0.875rem; margin-bottom: 8px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <?php echo esc_html($city_name); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($specialty_names)) : ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px;">
                            <?php foreach (array_slice($specialty_names, 0, 3) as $specialty) : ?>
                                <span style="background: #EEF2FF; color: #3730A3; font-size: 0.75rem; font-weight: 500; padding: 4px 10px; border-radius: 6px;"><?php echo esc_html($specialty); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($short_bio) : ?>
                        <p style="color: #6B7280; font-size: 0.875rem; line-height: 1.5; margin: 0 0 12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?php echo esc_html(wp_trim_words($short_bio, 20)); ?>
                        </p>
                    <?php endif; ?>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.8125rem; font-weight: 600; color: #0A74DA;">View Profile →</span>
                        <?php if ($session_type) : ?>
                            <?php 
                            $session_colors = array(
                                'In-Person' => 'background: #D1FAE5; color: #059669;',
                                'Virtual' => 'background: #DBEAFE; color: #2563EB;',
                                'Hybrid' => 'background: #FEF3C7; color: #D97706;',
                            );
                            $session_style = isset($session_colors[$session_type]) ? $session_colors[$session_type] : 'background: #E5E7EB; color: #4B5563;';
                            ?>
                            <span style="<?php echo $session_style; ?> font-size: 0.6875rem; font-weight: 600; padding: 4px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.025em;">
                                <?php echo esc_html($session_type); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get insurance options
     */
    private function get_insurance_options() {
        $insurances = array(
            'Aetna',
            'Blue Cross Blue Shield of NC',
            'Cigna',
            'UnitedHealthcare',
            'Humana',
            'Medicaid',
            'Medicare',
            'Optum',
            'Tricare',
            'Self-Pay',
            'Sliding Scale',
            'Out of Network',
        );
        
        return implode(',/n', $insurances);
    }
    
    /**
     * Override single template content to use our custom template
     * This prevents GeoDirectory from outputting default content that may include term IDs
     */
    public function override_single_template_content($content, $original_content, $page_id) {
        // Only override for gd_place post type
        if (!is_singular('gd_place')) {
            return $content;
        }
        
        // Check if our custom template exists
        $custom_template = get_template_directory() . '/geodirectory-single.php';
        if (!file_exists($custom_template)) {
            return $content;
        }
        
        // Return empty string to let our template file handle the output
        // This tells GeoDirectory not to inject any default content
        return '';
    }
    
    /**
     * Randomize listing order for variety across license types and specialties
     * Only applies when no specific sort is requested
     */
    public function randomize_listing_order($query) {
        // Only affect main query for gd_place archive/listings
        if (!is_admin() && $query->is_main_query() && $query->get('post_type') === 'gd_place') {
            // Check if a specific sort is requested
            $sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : '';
            
            // Only randomize if no sort or "Recommended" (default) is selected
            if (empty($sort) || $sort === '') {
                // Set random order for variety across all categories and specialties
                $query->set('orderby', 'rand');
            } elseif ($sort === 'name_asc') {
                $query->set('orderby', 'title');
                $query->set('order', 'ASC');
            } elseif ($sort === 'name_desc') {
                $query->set('orderby', 'title');
                $query->set('order', 'DESC');
            } elseif ($sort === 'recent') {
                $query->set('orderby', 'date');
                $query->set('order', 'DESC');
            }
        }
    }
    
    /**
     * Get diverse providers for display (used by templates)
     * Returns providers with variety across license types, specialties, and cities
     * 
     * @param int $count Number of providers to return
     * @return array Array of WP_Post objects
     */
    public static function get_diverse_providers($count = 6) {
        $license_types = get_terms(array(
            'taxonomy' => 'myhh_license_type',
            'hide_empty' => true,
            'orderby' => 'count',
            'order' => 'DESC'
        ));
        $specialties = get_terms(array(
            'taxonomy' => 'myhh_specialty',
            'hide_empty' => true,
            'parent' => 0,
            'orderby' => 'count',
            'order' => 'DESC'
        ));
        $cities = get_terms(array(
            'taxonomy' => 'myhh_city',
            'hide_empty' => true,
            'orderby' => 'count',
            'order' => 'DESC'
        ));
        
        $diverse_providers = array();
        $used_ids = array();
        $used_license_types = array();
        $used_specialties = array();
        $used_cities = array();
        
        // Helper function to get provider's primary specialty
        $get_primary_specialty = function($post_id) {
            $terms = get_the_terms($post_id, 'myhh_specialty');
            if ($terms && !is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $term) {
                    if ($term->parent == 0) {
                        return $term->term_id;
                    }
                    $parent = get_term($term->parent, 'myhh_specialty');
                    if ($parent && !is_wp_error($parent)) {
                        return $parent->term_id;
                    }
                }
                return $terms[0]->term_id;
            }
            return 0;
        };
        
        // Step 1: Get 1 provider from each license type (category variety)
        if ($license_types && !is_wp_error($license_types)) {
            foreach ($license_types as $lt) {
                $providers = get_posts(array(
                    'post_type'      => 'gd_place',
                    'posts_per_page' => 1,
                    'orderby'        => 'rand',
                    'tax_query'      => array(
                        array(
                            'taxonomy' => 'myhh_license_type',
                            'field'    => 'term_id',
                            'terms'    => $lt->term_id,
                        ),
                    ),
                ));
                foreach ($providers as $p) {
                    if (!in_array($p->ID, $used_ids)) {
                        $diverse_providers[] = $p;
                        $used_ids[] = $p->ID;
                        $used_license_types[] = $lt->term_id;
                        $spec_id = $get_primary_specialty($p->ID);
                        if ($spec_id) $used_specialties[] = $spec_id;
                    }
                }
                if (count($diverse_providers) >= floor($count * 0.6)) break;
            }
        }
        
        // Step 2: Fill remaining slots with providers from different specialties
        if (count($diverse_providers) < $count && $specialties && !is_wp_error($specialties)) {
            foreach ($specialties as $spec) {
                if (in_array($spec->term_id, $used_specialties)) {
                    continue;
                }
                
                $providers = get_posts(array(
                    'post_type'      => 'gd_place',
                    'posts_per_page' => 1,
                    'orderby'        => 'rand',
                    'post__not_in'   => $used_ids,
                    'tax_query'      => array(
                        array(
                            'taxonomy' => 'myhh_specialty',
                            'field'    => 'term_id',
                            'terms'    => $spec->term_id,
                        ),
                    ),
                ));
                foreach ($providers as $p) {
                    if (!in_array($p->ID, $used_ids)) {
                        $diverse_providers[] = $p;
                        $used_ids[] = $p->ID;
                        $used_specialties[] = $spec->term_id;
                    }
                }
                if (count($diverse_providers) >= $count) break;
            }
        }
        
        // Step 3: If still not enough, fill from different cities
        if (count($diverse_providers) < $count && $cities && !is_wp_error($cities)) {
            foreach ($cities as $city) {
                if (in_array($city->term_id, $used_cities)) {
                    continue;
                }
                
                $providers = get_posts(array(
                    'post_type'      => 'gd_place',
                    'posts_per_page' => 1,
                    'orderby'        => 'rand',
                    'post__not_in'   => $used_ids,
                    'tax_query'      => array(
                        array(
                            'taxonomy' => 'myhh_city',
                            'field'    => 'term_id',
                            'terms'    => $city->term_id,
                        ),
                    ),
                ));
                foreach ($providers as $p) {
                    if (!in_array($p->ID, $used_ids)) {
                        $diverse_providers[] = $p;
                        $used_ids[] = $p->ID;
                        $used_cities[] = $city->term_id;
                    }
                }
                if (count($diverse_providers) >= $count) break;
            }
        }
        
        // Shuffle for extra variety
        shuffle($diverse_providers);
        
        return array_slice($diverse_providers, 0, $count);
    }
    
    /**
     * Add admin menu for category migration
     */
    public function add_category_migration_menu() {
        // Only show if migration hasn't been completed
        if (!get_option('myhh_primary_category_migration_complete')) {
            add_submenu_page(
                'edit.php?post_type=gd_place',
                __('Migrate Categories', 'myhealthhub'),
                __('Migrate Categories', 'myhealthhub'),
                'manage_options',
                'myhh-migrate-categories',
                array($this, 'render_migration_page')
            );
        }
    }
    
    /**
     * Render the migration admin page
     */
    public function render_migration_page() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'myhealthhub'));
        }
        
        $migration_result = null;
        
        // Handle migration execution
        if (isset($_POST['myhh_run_migration']) && check_admin_referer('myhh_migrate_categories', 'myhh_migration_nonce')) {
            $migration_result = $this->run_primary_category_migration();
        }
        
        // Get counts
        $total_providers = wp_count_posts('gd_place')->publish;
        $providers_with_category = $this->count_providers_with_primary_category();
        $providers_without_category = $total_providers - $providers_with_category;
        
        ?>
        <div class="wrap">
            <h1><?php _e('Primary Category Migration', 'myhealthhub'); ?></h1>
            
            <?php if ($migration_result !== null) : ?>
                <div class="notice notice-<?php echo $migration_result['success'] ? 'success' : 'error'; ?> is-dismissible">
                    <p><strong><?php echo esc_html($migration_result['message']); ?></strong></p>
                    <?php if (!empty($migration_result['details'])) : ?>
                        <p><?php echo esc_html($migration_result['details']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
                <h2><?php _e('Migration Summary', 'myhealthhub'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Total Published Providers', 'myhealthhub'); ?></th>
                        <td><?php echo esc_html($total_providers); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Providers with Primary Category', 'myhealthhub'); ?></th>
                        <td><?php echo esc_html($providers_with_category); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Providers Needing Migration', 'myhealthhub'); ?></th>
                        <td><?php echo esc_html($providers_without_category); ?></td>
                    </tr>
                </table>
                
                <?php if ($providers_without_category > 0) : ?>
                    <p style="color: #6B7280; margin-top: 20px;">
                        <?php _e('This migration will assign all providers without a primary category to "Mental Health & Therapy". This is a safe, conservative migration that preserves all existing data.', 'myhealthhub'); ?>
                    </p>
                    <form method="post" action="">
                        <?php wp_nonce_field('myhh_migrate_categories', 'myhh_migration_nonce'); ?>
                        <input type="hidden" name="myhh_run_migration" value="1">
                        <p class="submit">
                            <button type="submit" class="button button-primary button-large" onclick="return confirm('<?php esc_attr_e('Are you sure you want to run the migration?', 'myhealthhub'); ?>');">
                                <?php _e('Run Migration', 'myhealthhub'); ?>
                            </button>
                        </p>
                    </form>
                <?php else : ?>
                    <p style="color: #059669; font-weight: 600; margin-top: 20px;">
                        <?php _e('All providers already have a primary category assigned.', 'myhealthhub'); ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <?php if (get_option('myhh_primary_category_migration_complete')) : ?>
                <div class="notice notice-info inline" style="margin-top: 20px;">
                    <p><?php _e('Migration was completed on:', 'myhealthhub'); ?> <?php echo esc_html(get_option('myhh_primary_category_migration_complete')); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Count providers that already have a primary category
     */
    private function count_providers_with_primary_category() {
        $args = array(
            'post_type'      => 'gd_place',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'myhh_primary_category',
                    'operator' => 'EXISTS',
                ),
            ),
        );
        $query = new WP_Query($args);
        return $query->found_posts;
    }
    
    /**
     * Run the primary category migration
     */
    private function run_primary_category_migration() {
        $default_category_slug = 'mental-health-therapy';
        $default_category = get_term_by('slug', $default_category_slug, 'myhh_primary_category');
        
        if (!$default_category) {
            return array(
                'success' => false,
                'message' => __('Default category "Mental Health & Therapy" not found. Please ensure primary categories are seeded.', 'myhealthhub'),
            );
        }
        
        // Get all providers without a primary category
        $args = array(
            'post_type'      => 'gd_place',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'myhh_primary_category',
                    'operator' => 'NOT EXISTS',
                ),
            ),
        );
        
        $query = new WP_Query($args);
        $provider_ids = $query->posts;
        
        if (empty($provider_ids)) {
            update_option('myhh_primary_category_migration_complete', current_time('mysql'));
            return array(
                'success' => true,
                'message' => __('No providers needed migration.', 'myhealthhub'),
            );
        }
        
        $assigned_count = 0;
        $failed_count = 0;
        
        foreach ($provider_ids as $provider_id) {
            $result = wp_set_object_terms($provider_id, $default_category->term_id, 'myhh_primary_category', true);
            if (!is_wp_error($result)) {
                $assigned_count++;
            } else {
                $failed_count++;
            }
        }
        
        // Mark migration as complete
        update_option('myhh_primary_category_migration_complete', current_time('mysql'));
        
        $message = sprintf(
            __('Migration complete: %d providers assigned to "Mental Health & Therapy".', 'myhealthhub'),
            $assigned_count
        );
        
        $details = '';
        if ($failed_count > 0) {
            $details = sprintf(__('Failed: %d providers.', 'myhealthhub'), $failed_count);
        }
        
        return array(
            'success' => $failed_count === 0,
            'message' => $message,
            'details' => $details,
        );
    }
    
    /**
     * Auto-migrate existing providers when taxonomy is first registered
     * This runs once when the primary category taxonomy is created
     */
    private function auto_migrate_existing_providers() {
        // Check if migration has already been done
        if (get_option('myhh_primary_category_migration_complete')) {
            return;
        }
        
        $default_category_slug = 'mental-health-therapy';
        $default_category = get_term_by('slug', $default_category_slug, 'myhh_primary_category');
        
        if (!$default_category) {
            return; // Category doesn't exist yet
        }
        
        // Get all providers without a primary category
        $args = array(
            'post_type'      => 'gd_place',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'myhh_primary_category',
                    'operator' => 'NOT EXISTS',
                ),
            ),
        );
        
        $query = new WP_Query($args);
        $provider_ids = $query->posts;
        
        if (empty($provider_ids)) {
            update_option('myhh_primary_category_migration_complete', current_time('mysql'));
            return;
        }
        
        $assigned_count = 0;
        $batch_size = 100;
        
        foreach (array_chunk($provider_ids, $batch_size) as $batch) {
            foreach ($batch as $provider_id) {
                $result = wp_set_object_terms($provider_id, $default_category->term_id, 'myhh_primary_category', true);
                if (!is_wp_error($result)) {
                    $assigned_count++;
                }
            }
            // Clear cache after each batch
            wp_cache_flush();
        }
        
        // Mark migration as complete
        update_option('myhh_primary_category_migration_complete', current_time('mysql'));
        
        // Log the result
        error_log(sprintf(
            'MyHealthHub: Auto-migrated %d providers to Mental Health & Therapy category.',
            $assigned_count
        ));
    }
    
    /**
     * Maybe run auto-migration on admin_init
     * This is a fallback in case the taxonomy already existed when the plugin was updated
     */
    public function maybe_run_auto_migration() {
        // Only run if migration hasn't been completed and taxonomy exists
        if (get_option('myhh_primary_category_migration_complete')) {
            return;
        }
        
        // Check if taxonomy exists
        if (!taxonomy_exists('myhh_primary_category')) {
            return;
        }
        
        // Run the auto-migration
        $this->auto_migrate_existing_providers();
    }
}

// Initialize
MyHH_GeoDirectory_Integration::get_instance();
