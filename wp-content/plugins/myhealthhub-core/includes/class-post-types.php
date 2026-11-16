<?php
/**
 * Post Types Class
 *
 * Handles registration of custom post types and taxonomies
 *
 * @package MyHealthHub
 * @subpackage Core
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post Types Class
 */
class MyHH_Post_Types {
    
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
        add_action('init', array($this, 'register_post_types'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('wp_loaded', array($this, 'seed_default_terms'));
    }
    
    /**
     * Register custom post types
     */
    public function register_post_types() {
        // Leads CPT
        register_post_type('myhh_lead', array(
            'labels' => array(
                'name'                  => __('Leads', 'myhealthhub'),
                'singular_name'         => __('Lead', 'myhealthhub'),
                'add_new'               => __('Add New', 'myhealthhub'),
                'add_new_item'          => __('Add New Lead', 'myhealthhub'),
                'edit_item'             => __('Edit Lead', 'myhealthhub'),
                'new_item'              => __('New Lead', 'myhealthhub'),
                'view_item'             => __('View Lead', 'myhealthhub'),
                'search_items'          => __('Search Leads', 'myhealthhub'),
                'not_found'             => __('No leads found', 'myhealthhub'),
                'not_found_in_trash'    => __('No leads found in trash', 'myhealthhub'),
            ),
            'public'                => false,
            'publicly_queryable'    => false,
            'show_ui'               => true,
            'show_in_menu'          => 'myhh-dashboard',
            'capability_type'       => 'post',
            'has_archive'           => false,
            'hierarchical'          => false,
            'supports'              => array('title'),
        ));
        
        // Claims CPT
        register_post_type('myhh_claim', array(
            'labels' => array(
                'name'                  => __('Listing Claims', 'myhealthhub'),
                'singular_name'         => __('Claim', 'myhealthhub'),
                'add_new'               => __('Add New', 'myhealthhub'),
                'add_new_item'          => __('Add New Claim', 'myhealthhub'),
                'edit_item'             => __('Edit Claim', 'myhealthhub'),
                'new_item'              => __('New Claim', 'myhealthhub'),
                'view_item'             => __('View Claim', 'myhealthhub'),
                'search_items'          => __('Search Claims', 'myhealthhub'),
                'not_found'             => __('No claims found', 'myhealthhub'),
                'not_found_in_trash'    => __('No claims found in trash', 'myhealthhub'),
            ),
            'public'                => false,
            'publicly_queryable'    => false,
            'show_ui'               => true,
            'show_in_menu'          => 'myhh-dashboard',
            'capability_type'       => 'post',
            'has_archive'           => false,
            'hierarchical'          => false,
            'supports'              => array('title', 'editor'),
        ));
        
        // Testimonials CPT
        register_post_type('myhh_testimonial', array(
            'labels' => array(
                'name'                  => __('Testimonials', 'myhealthhub'),
                'singular_name'         => __('Testimonial', 'myhealthhub'),
                'add_new'               => __('Add New', 'myhealthhub'),
                'add_new_item'          => __('Add New Testimonial', 'myhealthhub'),
                'edit_item'             => __('Edit Testimonial', 'myhealthhub'),
                'new_item'              => __('New Testimonial', 'myhealthhub'),
                'view_item'             => __('View Testimonial', 'myhealthhub'),
                'search_items'          => __('Search Testimonials', 'myhealthhub'),
                'not_found'             => __('No testimonials found', 'myhealthhub'),
                'not_found_in_trash'    => __('No testimonials found in trash', 'myhealthhub'),
            ),
            'public'                => false,
            'publicly_queryable'    => false,
            'show_ui'               => true,
            'show_in_menu'          => 'myhh-dashboard',
            'capability_type'       => 'post',
            'has_archive'           => false,
            'hierarchical'          => false,
            'supports'              => array('title', 'editor'),
        ));
        
        // Practice Submissions CPT
        register_post_type('myhh_submission', array(
            'labels' => array(
                'name'                  => __('Practice Submissions', 'myhealthhub'),
                'singular_name'         => __('Practice Submission', 'myhealthhub'),
                'add_new'               => __('Add New', 'myhealthhub'),
                'add_new_item'          => __('Add New Submission', 'myhealthhub'),
                'edit_item'             => __('Edit Submission', 'myhealthhub'),
                'new_item'              => __('New Submission', 'myhealthhub'),
                'view_item'             => __('View Submission', 'myhealthhub'),
                'search_items'          => __('Search Submissions', 'myhealthhub'),
                'not_found'             => __('No submissions found', 'myhealthhub'),
                'not_found_in_trash'    => __('No submissions found in trash', 'myhealthhub'),
            ),
            'public'                => false,
            'publicly_queryable'    => false,
            'show_ui'               => true,
            'show_in_menu'          => 'myhh-dashboard',
            'capability_type'       => 'post',
            'has_archive'           => false,
            'hierarchical'          => false,
            'supports'              => array('title', 'editor'),
        ));
    }
    
    /**
     * Register taxonomies
     */
    public function register_taxonomies() {
        // Register testimonial status taxonomy
        register_taxonomy('myhh_testimonial_status', 'myhh_testimonial', array(
            'labels' => array(
                'name'          => __('Status', 'myhealthhub'),
                'singular_name' => __('Status', 'myhealthhub'),
            ),
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => false,
            'rewrite'           => false,
        ));
    }
    
    /**
     * Seed default taxonomy terms
     */
    public function seed_default_terms() {
        global $wpdb;
        
        // Guard: do nothing if the terms table doesn't exist yet
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->terms}'");
        if (!$table_exists) {
            return;
        }
        
        // Seed testimonial status terms
        if (!term_exists('pending', 'myhh_testimonial_status')) {
            wp_insert_term('Pending', 'myhh_testimonial_status', array('slug' => 'pending'));
        }
        if (!term_exists('approved', 'myhh_testimonial_status')) {
            wp_insert_term('Approved', 'myhh_testimonial_status', array('slug' => 'approved'));
        }
    }
}
