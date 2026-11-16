<?php
/**
 * Assets Class
 *
 * Handles enqueueing of admin styles and scripts
 *
 * @package MyHealthHub
 * @subpackage Core
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Assets Class
 */
class MyHH_Assets {
    
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
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'myhh') === false) {
            return;
        }
        
        // Admin CSS
        wp_enqueue_style(
            'myhh-admin',
            MYHH_CORE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MYHH_CORE_VERSION
        );
        
        // Admin JS
        wp_enqueue_script(
            'myhh-admin',
            MYHH_CORE_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            MYHH_CORE_VERSION,
            true
        );
    }
}
