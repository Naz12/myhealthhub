<?php
/**
 * My Health Hub Theme Functions
 *
 * @package MyHealthHub
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Theme version for cache busting (MYHH_VERSION defined in mu-plugin)
if (!defined('MYHH_VERSION')) {
    define('MYHH_VERSION', '1.2.1');
}
define('MYHH_THEME_DIR', get_template_directory());
define('MYHH_THEME_URI', get_template_directory_uri());

/**
 * Load required files
 */
require_once MYHH_THEME_DIR . '/inc/template-functions.php';
require_once MYHH_THEME_DIR . '/inc/customizer.php';
require_once MYHH_THEME_DIR . '/inc/seo-functions.php';

/**
 * Theme Setup
 */
function myhh_theme_setup() {
    // Add theme support
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'script',
        'style',
    ));
    add_theme_support('responsive-embeds');
    add_theme_support('customize-selective-refresh-widgets');
    
    // Register menus
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'myhealthhub'),
        'footer'  => __('Footer Menu', 'myhealthhub'),
    ));
    
    // Set content width
    global $content_width;
    if (!isset($content_width)) {
        $content_width = 1200;
    }
}
add_action('after_setup_theme', 'myhh_theme_setup');

/**
 * Filter directory listings by taxonomy query params
 */
function myhh_filter_directory_listings($query) {
    if (!is_admin() && $query->is_main_query()) {
        // Handle gd_place archive, taxonomy pages, OR search for gd_place
        $is_gd_place_search = isset($_GET['post_type']) && $_GET['post_type'] === 'gd_place';
        
        if (is_post_type_archive('gd_place') || is_tax('myhh_specialty') || is_tax('myhh_city') || is_tax('myhh_primary_category') || ($query->is_search() && $is_gd_place_search)) {
            
            // Set post type for search
            if ($query->is_search() && $is_gd_place_search) {
                $query->set('post_type', 'gd_place');
            }
            
            $tax_query = array();
            $meta_query = array();
        
        // Filter by specialty (combined: CSV taxonomy OR NPI provider type)
        if (!empty($_GET['specialty'])) {
            $specialty_value = sanitize_text_field($_GET['specialty']);
            
            // Check if it's a CSV specialty (prefix: csv:)
            if (strpos($specialty_value, 'csv:') === 0) {
                $specialty_slug = substr($specialty_value, 4); // Remove 'csv:' prefix
                $tax_query[] = array(
                    'taxonomy' => 'myhh_specialty',
                    'field'    => 'slug',
                    'terms'    => $specialty_slug,
                );
            }
            // Check if it's an NPI provider type (prefix: npi:)
            elseif (strpos($specialty_value, 'npi:') === 0) {
                $provider_type = substr($specialty_value, 4); // Remove 'npi:' prefix
                $meta_query[] = array(
                    'key'     => 'npi_primary_taxonomy',
                    'value'   => $provider_type,
                    'compare' => '=',
                );
            }
            // Legacy support: no prefix (assume CSV taxonomy)
            else {
                $tax_query[] = array(
                    'taxonomy' => 'myhh_specialty',
                    'field'    => 'slug',
                    'terms'    => $specialty_value,
                );
            }
        }
        
        // Filter by primary category (Milestone 3)
        if (!empty($_GET['primary_category'])) {
            $primary_category_slug = sanitize_text_field($_GET['primary_category']);
            $tax_query[] = array(
                'taxonomy' => 'myhh_primary_category',
                'field'    => 'slug',
                'terms'    => $primary_category_slug,
            );
        }
        
        // Filter by city
        if (!empty($_GET['city'])) {
            $city_slug = sanitize_text_field($_GET['city']);
            $tax_query[] = array(
                'taxonomy' => 'myhh_city',
                'field'    => 'slug',
                'terms'    => $city_slug,
            );
        }
        
        // Apply tax query if we have filters
        if (!empty($tax_query)) {
            $tax_query['relation'] = 'AND';
            $query->set('tax_query', $tax_query);
        }
        
        // Filter by specific session type
        if (!empty($_GET['session_type'])) {
            $session_type = sanitize_text_field($_GET['session_type']);
            $meta_query[] = array(
                'key'     => 'session_type',
                'value'   => $session_type,
                'compare' => 'LIKE',
            );
        }
        
        // Filter by verification status
        if (!empty($_GET['verification'])) {
            $verification = sanitize_text_field($_GET['verification']);
            $meta_query[] = array(
                'key'     => 'verification_status',
                'value'   => $verification,
                'compare' => '=',
            );
        }
        
        // Apply meta query if we have filters
        if (!empty($meta_query)) {
            $meta_query['relation'] = 'AND';
            $query->set('meta_query', $meta_query);
        }
        } // End inner if (gd_place search/archive check)
    }
}
add_action('pre_get_posts', 'myhh_filter_directory_listings');

/**
 * SEO URL Rewrite Rules for City + Specialty Pages
 * Creates URLs like: /raleigh-anxiety-therapists/
 */

// Register query vars
function myhh_seo_query_vars($vars) {
    $vars[] = 'myhh_seo_city';
    $vars[] = 'myhh_seo_specialty';
    return $vars;
}
add_filter('query_vars', 'myhh_seo_query_vars');

// Add rewrite rules using regex patterns
function myhh_seo_rewrite_rules() {
    // Pattern: /{city}-{specialty}-therapists/
    // Use non-greedy match for city to ensure proper parsing
    add_rewrite_rule(
        '^([a-z]+)-([a-z-]+)-therapists/?$',
        'index.php?myhh_seo_city=$matches[1]&myhh_seo_specialty=$matches[2]',
        'top'
    );
}
add_action('init', 'myhh_seo_rewrite_rules');

// Template redirect for SEO URLs
function myhh_seo_template_redirect($template) {
    $city = get_query_var('myhh_seo_city');
    $specialty = get_query_var('myhh_seo_specialty');
    
    if ($city && $specialty) {
        // Verify the terms exist
        $city_term = get_term_by('slug', $city, 'myhh_city');
        $specialty_term = get_term_by('slug', $specialty, 'myhh_specialty');
        
        if ($city_term && $specialty_term) {
            // Look for custom template
            $new_template = locate_template('template-seo-directory.php');
            if ($new_template) {
                return $new_template;
            }
        }
    }
    
    return $template;
}
add_filter('template_include', 'myhh_seo_template_redirect');

// Helper function to generate SEO URLs
function myhh_get_seo_url($city_slug, $specialty_slug) {
    // Build the SEO-friendly URL
    $path = $city_slug . '-' . $specialty_slug . '-therapists';
    return home_url('/' . $path . '/');
}

// Flush rewrite rules on theme activation and plugin activation
function myhh_flush_rewrite_rules() {
    myhh_seo_rewrite_rules();
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'myhh_flush_rewrite_rules');

// Also flush on init if a flag is set (for manual activation)
function myhh_maybe_flush_rules() {
    if (get_transient('myhh_flush_rewrite_rules')) {
        flush_rewrite_rules();
        delete_transient('myhh_flush_rewrite_rules');
    }
}
add_action('init', 'myhh_maybe_flush_rules', 999);

/**
 * Enqueue Scripts and Styles
 */
function myhh_enqueue_assets() {
    // Google Fonts - Inter
    wp_enqueue_style(
        'myhh-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
        array(),
        null
    );
    
    // Main stylesheet
    wp_enqueue_style(
        'myhh-style',
        get_stylesheet_uri(),
        array('myhh-fonts'),
        MYHH_VERSION
    );
    
    // Responsive stylesheet
    wp_enqueue_style(
        'myhh-responsive',
        MYHH_THEME_URI . '/assets/css/responsive.css',
        array('myhh-style'),
        MYHH_VERSION
    );
    
    // Theme JavaScript
    wp_enqueue_script(
        'myhh-scripts',
        MYHH_THEME_URI . '/assets/js/main.js',
        array(),
        MYHH_VERSION,
        true
    );
    
    // Pass AJAX URL to JavaScript
    wp_localize_script('myhh-scripts', 'myhh_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('myhh_nonce'),
    ));

    // Share link rendering between Mia's hero, live transcript and floating chat.
    $mia_message_links_version = file_exists(MYHH_THEME_DIR . '/assets/js/mia-message-links.js')
        ? filemtime(MYHH_THEME_DIR . '/assets/js/mia-message-links.js') : MYHH_VERSION;
    wp_enqueue_script('myhh-mia-message-links', MYHH_THEME_URI . '/assets/js/mia-message-links.js', array(), $mia_message_links_version . '-links-20261008a', true);
    wp_enqueue_style('myhh-mia-message-links', MYHH_THEME_URI . '/assets/css/mia-message-links.css', array('myhh-style'), $mia_message_links_version . '-links-20261008a');

    // Front-page-only Mia Hero assets
    if (is_front_page()) {
        $mia_realtime_v2 = defined('MYHH_MIA_REALTIME_V2')
            ? (bool) MYHH_MIA_REALTIME_V2
            : get_option('myhh_mia_realtime_v2', '1') !== '0';
        // Mia Hero CSS
        $mia_hero_css_version = file_exists(MYHH_THEME_DIR . '/assets/css/mia-hero.css')
            ? filemtime(MYHH_THEME_DIR . '/assets/css/mia-hero.css')
            : MYHH_VERSION;
        $mia_voice_states_version = file_exists(MYHH_THEME_DIR . '/assets/css/mia-voice-states.css')
            ? filemtime(MYHH_THEME_DIR . '/assets/css/mia-voice-states.css')
            : MYHH_VERSION;
        $mia_mobile_polish_version = file_exists(MYHH_THEME_DIR . '/assets/css/mia-mobile-polish.css')
            ? filemtime(MYHH_THEME_DIR . '/assets/css/mia-mobile-polish.css')
            : MYHH_VERSION;
        wp_enqueue_style('myhh-mia-hero', MYHH_THEME_URI . '/assets/css/mia-hero.css', array('myhh-style'), $mia_hero_css_version);
        wp_enqueue_style('myhh-mia-voice-states', MYHH_THEME_URI . '/assets/css/mia-voice-states.css', array('myhh-mia-hero'), $mia_voice_states_version);

        // Mia Hero JavaScript (utils → chat → voice chain)
        $mia_voice_utils_version = file_exists(MYHH_THEME_DIR . '/assets/js/mia-voice-utils.js')
            ? filemtime(MYHH_THEME_DIR . '/assets/js/mia-voice-utils.js')
            : MYHH_VERSION;
        $mia_chat_version = file_exists(MYHH_THEME_DIR . '/assets/js/mia-hero-chat.js')
            ? filemtime(MYHH_THEME_DIR . '/assets/js/mia-hero-chat.js')
            : MYHH_VERSION;
        wp_enqueue_script('myhh-mia-voice-utils', MYHH_THEME_URI . '/assets/js/mia-voice-utils.js', array(), $mia_voice_utils_version, true);
        wp_enqueue_script('myhh-mia-hero-chat', MYHH_THEME_URI . '/assets/js/mia-hero-chat.js', array('myhh-mia-voice-utils', 'myhh-mia-message-links'), $mia_chat_version . '-links-20261008a', true);
        $mia_voice_version_base = file_exists(MYHH_THEME_DIR . '/assets/js/mia-hero-voice.js')
            ? filemtime(MYHH_THEME_DIR . '/assets/js/mia-hero-voice.js')
            : MYHH_VERSION;
        // Keep a release marker because some FTPS deployments preserve the
        // remote mtime, which otherwise leaves browsers on an older Mia router.
        $mia_voice_version = $mia_voice_version_base . '-realtime-20260908f';
        wp_enqueue_script('myhh-mia-hero-voice', MYHH_THEME_URI . '/assets/js/mia-hero-voice.js', array('myhh-mia-hero-chat'), $mia_voice_version, true);

        $mia_openai_voice_version = file_exists(MYHH_THEME_DIR . '/assets/js/mia-openai-live.js')
            ? filemtime(MYHH_THEME_DIR . '/assets/js/mia-openai-live.js') : MYHH_VERSION;
        wp_enqueue_script('myhh-mia-openai-live', MYHH_THEME_URI . '/assets/js/mia-openai-live.js', array('myhh-mia-hero-voice'), $mia_openai_voice_version . '-openai-20261008a', true);

        // [DEBUG] Mia floating debug console — only loads when localStorage mia_debug=1
        $mia_debug_version = file_exists(MYHH_THEME_DIR . '/assets/js/mia-debug-console.js')
            ? filemtime(MYHH_THEME_DIR . '/assets/js/mia-debug-console.js')
            : MYHH_VERSION;
        wp_enqueue_script('myhh-mia-debug-console', MYHH_THEME_URI . '/assets/js/mia-debug-console.js', array('myhh-mia-hero-voice'), $mia_debug_version, true);

        wp_localize_script('myhh-mia-hero-voice', 'miaConfig', array(
            'aiProvider'        => get_option('myhh_mia_ai_provider', 'grok') === 'openai' ? 'openai' : 'grok',
            'ajaxUrl'           => admin_url('admin-ajax.php'),
            'nonce'             => wp_create_nonce('mia_chat_nonce'),
            'voiceProcessorUrl' => add_query_arg('ver', 'realtime-20260908f', plugins_url('myhealthhub-core/assets/js/mia-voice-processor.js')),
            'directoryUrl'      => home_url('/directory/'),
            'bargeinEnabled'    => true,
            'realtimeV2'        => (bool) apply_filters('myhh_mia_realtime_v2_enabled', $mia_realtime_v2),
        ));
        // Localization stringifies scalar values; preserve typed feature switches.
        wp_add_inline_script('myhh-mia-hero-voice', 'Object.assign(window.miaConfig, ' . wp_json_encode(array(
            // Independent rollback switches; server turn detection is unchanged.
            'audioPacket20msPrototype' => (bool) apply_filters('myhh_mia_audio_packet_20ms_enabled', true),
            'transportQueueRecoveryPrototype' => (bool) apply_filters('myhh_mia_transport_queue_recovery_enabled', true),
            'adaptiveAcknowledgementPrototype' => (bool) apply_filters('myhh_mia_adaptive_acknowledgement_enabled', true),
            'overlapLookupPrototype' => false,
        )) . ');', 'before');
    }
}
add_action('wp_enqueue_scripts', 'myhh_enqueue_assets');

/**
 * Register Widget Areas
 */
function myhh_widgets_init() {
    register_sidebar(array(
        'name'          => __('Sidebar', 'myhealthhub'),
        'id'            => 'sidebar-1',
        'description'   => __('Add widgets here.', 'myhealthhub'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ));
}
add_action('widgets_init', 'myhh_widgets_init');

/**
 * Custom Excerpt Length
 */
function myhh_excerpt_length($length) {
    return 25;
}
add_filter('excerpt_length', 'myhh_excerpt_length', 999);

/**
 * Custom Excerpt More
 */
function myhh_excerpt_more($more) {
    return '...';
}
add_filter('excerpt_more', 'myhh_excerpt_more');

/**
 * Add custom body classes
 */
function myhh_body_classes($classes) {
    // Add class if viewing a GeoDirectory listing
    if (function_exists('geodir_is_page') && geodir_is_page('listing')) {
        $classes[] = 'gd-listing-page';
    }
    
    return $classes;
}
add_filter('body_class', 'myhh_body_classes');

/**
 * Custom template tags
 */

/**
 * Display the site logo or title
 */
function myhh_site_logo() {
    $site_title = get_bloginfo('name');
    $home_url   = home_url('/');
    ?>
    <a href="<?php echo esc_url($home_url); ?>" class="site-logo">
        <?php echo esc_html($site_title); ?>
    </a>
    <?php
}

/**
 * Display the primary navigation
 */
function myhh_primary_nav() {
    wp_nav_menu(array(
        'theme_location'  => 'primary',
        'menu_id'         => 'primary-menu',
        'menu_class'      => 'nav-menu',
        'container'       => 'nav',
        'container_class' => 'main-nav',
        'fallback_cb'     => false,
    ));
}

/**
 * Display the footer navigation
 */
function myhh_footer_nav() {
    wp_nav_menu(array(
        'theme_location'  => 'footer',
        'menu_id'         => 'footer-menu',
        'menu_class'      => 'footer-menu',
        'container'       => false,
        'depth'           => 1,
        'fallback_cb'     => false,
    ));
}

/**
 * Display page header
 */
function myhh_page_header($title = '', $subtitle = '') {
    if (empty($title)) {
        $title = get_the_title();
    }
    ?>
    <header class="page-header">
        <div class="container">
            <h1 class="page-header__title"><?php echo esc_html($title); ?></h1>
            <?php if ($subtitle) : ?>
                <p class="page-header__subtitle"><?php echo esc_html($subtitle); ?></p>
            <?php endif; ?>
        </div>
    </header>
    <?php
}

/**
 * Helper function to check if GeoDirectory is active
 */
function myhh_is_geodirectory_active() {
    return class_exists('GeoDirectory');
}

/**
 * Get directory page URL
 */
function myhh_get_directory_url() {
    if (myhh_is_geodirectory_active()) {
        return get_post_type_archive_link('gd_place');
    }
    return home_url('/providers/');
}

/**
 * Display trust strip
 */
function myhh_trust_strip() {
    ?>
    <div class="trust-strip">
        <div class="container">
            <div class="trust-strip__content">
                <div class="trust-strip__item">
                    <svg class="trust-strip__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    <span>Verified Listings</span>
                </div>
                <div class="trust-strip__item">
                    <svg class="trust-strip__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <span>Local Providers</span>
                </div>
                <div class="trust-strip__item">
                    <svg class="trust-strip__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Quick Booking</span>
                </div>
                <div class="trust-strip__item">
                    <svg class="trust-strip__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                    </svg>
                    <span>Free to Use</span>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Display hero section
 */
function myhh_hero() {
    ?>
    <section class="hero">
        <div class="container">
            <h1 class="hero__title">Find the Right Wellness Professional in the Triangle</h1>
            <p class="hero__subtitle">Connect with verified therapists, counselors, and wellness experts in Raleigh, Durham, Cary, and Chapel Hill.</p>
            <div class="hero__cta">
                <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" class="btn btn--primary btn--lg">Browse All Providers</a>
                <a href="<?php echo esc_url(home_url('/get-matched/')); ?>" class="btn btn--outline btn--lg">Get Matched</a>
            </div>
        </div>
    </section>
    <?php
}

/**
 * Display search section
 */
function myhh_search_section() {
    ?>
    <section class="search-section">
        <div class="container">
            <div class="search-box">
                <h2 class="search-box__title">Search Providers</h2>
                <?php if (myhh_is_geodirectory_active()) : ?>
                    <?php echo do_shortcode('[gd_search]'); ?>
                <?php else : ?>
                    <form class="search-form" action="<?php echo esc_url(home_url('/')); ?>" method="get">
                        <input type="text" name="s" placeholder="Search by name, specialty..." aria-label="Search">
                        <select name="location" aria-label="Location">
                            <option value="">All Locations</option>
                            <option value="raleigh">Raleigh</option>
                            <option value="durham">Durham</option>
                            <option value="cary">Cary</option>
                            <option value="chapel-hill">Chapel Hill</option>
                        </select>
                        <button type="submit" class="btn btn--primary">Search</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
}

/**
 * Display claim listing button/form
 */
function myhh_claim_listing_section($listing_id = 0) {
    if (!$listing_id) {
        $listing_id = get_the_ID();
    }
    
    // Check if already claimed
    $is_claimed = get_post_meta($listing_id, '_myhh_claimed', true);
    
    if ($is_claimed) {
        ?>
        <div style="background: #D1FAE5; border: 1px solid #6EE7B7; border-radius: 12px; padding: 16px; text-align: center;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 8px; color: #059669; font-weight: 600; font-size: 0.875rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Profile Claimed
            </div>
        </div>
        <?php
        return;
    }
    ?>
    <div style="background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%); border: 1px solid #C7D2FE; border-radius: 12px; padding: 20px;">
        <div style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 14px;">
            <div style="width: 36px; height: 36px; background: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2">
                    <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div>
                <h4 style="font-size: 0.9375rem; font-weight: 600; margin: 0 0 6px; color: #1F2937;">Is This Your Profile?</h4>
                <p style="color: #6B7280; font-size: 0.8125rem; margin: 0; line-height: 1.5;">This profile was created using publicly available information. Claim your profile to update your details, add photos, and respond to testimonials.</p>
            </div>
        </div>
        <a href="<?php echo esc_url(add_query_arg('listing_id', $listing_id, home_url('/claim-listing/'))); ?>" 
           style="display: flex; align-items: center; justify-content: center; gap: 8px; background: linear-gradient(135deg, #4F46E5 0%, #6366F1 100%); color: white; padding: 12px 20px; border-radius: 10px; font-weight: 600; text-decoration: none; font-size: 0.9375rem; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); transition: all 0.2s;"
           onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 16px rgba(79, 70, 229, 0.4)'"
           onmouseout="this.style.transform='none';this.style.boxShadow='0 4px 12px rgba(79, 70, 229, 0.3)'">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>
            Claim Your Free Profile
        </a>
        <p style="text-align: center; color: #9CA3AF; font-size: 0.75rem; margin: 10px 0 0;">Free • Takes 2 minutes</p>
        
        <!-- Secondary CTA for providers not listed -->
        <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #C7D2FE; text-align: center;">
            <p style="color: #6B7280; font-size: 0.8125rem; margin: 0 0 8px;">Not listed yet?</p>
            <a href="<?php echo esc_url(home_url('/add-your-practice/')); ?>" style="color: #4F46E5; font-weight: 500; font-size: 0.875rem; text-decoration: none;" 
               onmouseover="this.style.textDecoration='underline'" 
               onmouseout="this.style.textDecoration='none'">
                Add Your Practice →
            </a>
        </div>
    </div>
    <?php
}

/**
 * Display testimonials section
 */
function myhh_testimonials_section($listing_id = 0) {
    if (!$listing_id) {
        $listing_id = get_the_ID();
    }
    
    // Get approved testimonials for this listing
    $testimonials = get_posts(array(
        'post_type'      => 'myhh_testimonial',
        'posts_per_page' => 10,
        'meta_query'     => array(
            array(
                'key'   => '_myhh_listing_id',
                'value' => $listing_id,
            ),
        ),
        'tax_query'      => array(
            array(
                'taxonomy' => 'myhh_testimonial_status',
                'field'    => 'slug',
                'terms'    => 'approved',
            ),
        ),
    ));
    
    if (empty($testimonials)) {
        return;
    }
    
    // Calculate average rating
    $total_rating = 0;
    $count = count($testimonials);
    foreach ($testimonials as $testimonial) {
        $rating = get_post_meta($testimonial->ID, '_myhh_rating', true);
        $total_rating += intval($rating ?: 5);
    }
    $avg_rating = $count > 0 ? round($total_rating / $count, 1) : 0;
    ?>
    <div class="testimonials-section" style="margin-top: 24px;">
        <div style="background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #F3F4F6; overflow: hidden;">
            <div style="padding: 20px 24px; border-bottom: 1px solid #F3F4F6; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; background: #FEF3C7; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                        </svg>
                    </div>
                    <h3 style="font-size: 1rem; font-weight: 600; margin: 0; color: #1F2937;"><?php _e('Client Reviews', 'myhealthhub'); ?></h3>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 1.25rem; font-weight: 700; color: #D97706;"><?php echo esc_html($avg_rating); ?> ★</div>
                    <div style="font-size: 0.75rem; color: #6B7280;"><?php echo esc_html($count); ?> review<?php echo $count > 1 ? 's' : ''; ?></div>
                </div>
            </div>
            <div style="padding: 20px 24px;">
                <?php foreach ($testimonials as $testimonial) : 
                    $rating = get_post_meta($testimonial->ID, '_myhh_rating', true);
                    $author = get_post_meta($testimonial->ID, '_myhh_author_name', true);
                ?>
                    <div style="padding: 16px 0; border-bottom: 1px solid #F3F4F6;">
                        <?php if ($rating) : ?>
                            <div style="color: #D97706; font-size: 0.875rem; margin-bottom: 8px;">
                                <?php echo str_repeat('★', intval($rating)); ?><?php echo str_repeat('☆', 5 - intval($rating)); ?>
                            </div>
                        <?php endif; ?>
                        <p style="color: #4B5563; line-height: 1.6; margin: 0 0 8px; font-size: 0.9375rem;">
                            "<?php echo esc_html($testimonial->post_content); ?>"
                        </p>
                        <div style="color: #6B7280; font-size: 0.8125rem;">
                            — <?php echo esc_html($author ?: 'Anonymous'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Submit Testimonial Link -->
        <div style="text-align: center; margin-top: 16px;">
            <a href="<?php echo esc_url(add_query_arg('listing_id', $listing_id, home_url('/submit-testimonial/'))); ?>" style="display: inline-flex; align-items: center; gap: 6px; color: #0A74DA; font-weight: 500; text-decoration: none; font-size: 0.875rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <?php _e('Leave a Review', 'myhealthhub'); ?>
            </a>
        </div>
    </div>
    <?php
}

/**
 * Add custom image sizes
 */
function myhh_add_image_sizes() {
    add_image_size('myhh-card', 400, 300, true);
    add_image_size('myhh-hero', 1200, 600, true);
}
add_action('after_setup_theme', 'myhh_add_image_sizes');

/**
 * Get average rating for a listing
 */
function myhh_get_listing_rating($listing_id) {
    $testimonials = get_posts(array(
        'post_type'      => 'myhh_testimonial',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'   => '_myhh_listing_id',
                'value' => $listing_id,
            ),
        ),
        'tax_query'      => array(
            array(
                'taxonomy' => 'myhh_testimonial_status',
                'field'    => 'slug',
                'terms'    => 'approved',
            ),
        ),
    ));
    
    if (empty($testimonials)) {
        return false;
    }
    
    $total_rating = 0;
    $count = count($testimonials);
    
    foreach ($testimonials as $testimonial_id) {
        $rating = get_post_meta($testimonial_id, '_myhh_rating', true);
        $total_rating += intval($rating ?: 5);
    }
    
    return array(
        'average' => round($total_rating / $count, 1),
        'count'   => $count,
    );
}

/**
 * Disable WordPress admin bar for non-admins
 */
function myhh_disable_admin_bar() {
    if (!current_user_can('administrator') && !is_admin()) {
        show_admin_bar(false);
    }
}
add_action('after_setup_theme', 'myhh_disable_admin_bar');

/**
 * Create required pages on theme activation
 */
function myhh_create_required_pages() {
    // Define required pages with their templates
    $required_pages = array(
        'add-your-practice' => array(
            'title'     => 'Add Your Practice',
            'template'  => 'template-add-your-practice.php',
            'content'   => '',
        ),
        'get-matched' => array(
            'title'     => 'Get Matched',
            'template'  => 'template-get-matched.php',
            'content'   => '',
        ),
        'claim-listing' => array(
            'title'     => 'Claim Listing',
            'template'  => 'template-claim-listing.php',
            'content'   => '',
        ),
        'about' => array(
            'title'     => 'About',
            'template'  => 'template-about.php',
            'content'   => '',
        ),
        'contact' => array(
            'title'     => 'Contact',
            'template'  => 'template-contact.php',
            'content'   => '',
        ),
        'providers' => array(
            'title'     => 'Providers',
            'template'  => 'template-providers.php',
            'content'   => '',
        ),
        'listings' => array(
            'title'     => 'Listings',
            'template'  => 'template-listings.php',
            'content'   => '',
        ),
        'testimonials' => array(
            'title'     => 'Testimonials',
            'template'  => 'template-testimonial.php',
            'content'   => '',
        ),
        'seo-directory' => array(
            'title'     => 'Directory',
            'template'  => 'template-seo-directory.php',
            'content'   => '',
        ),
    );
    
    foreach ($required_pages as $slug => $page_data) {
        // Check if page exists
        $existing_page = get_page_by_path($slug);
        
        if (!$existing_page) {
            // Create the page
            $page_id = wp_insert_post(array(
                'post_title'   => $page_data['title'],
                'post_name'    => $slug,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => $page_data['content'],
                'post_author'  => 1,
            ));
            
            // Assign template if page was created successfully
            if (!is_wp_error($page_id) && !empty($page_data['template'])) {
                update_post_meta($page_id, '_wp_page_template', $page_data['template']);
            }
        } else {
            // Page exists, ensure template is assigned
            if (!empty($page_data['template'])) {
                update_post_meta($existing_page->ID, '_wp_page_template', $page_data['template']);
            }
        }
    }
}
add_action('after_switch_theme', 'myhh_create_required_pages');

/**
 * Manual trigger for creating pages (for admin use)
 * Visit: /wp-admin/?myhh_create_pages=1 while logged in as admin
 */
function myhh_manual_create_pages() {
    if (isset($_GET['myhh_create_pages']) && current_user_can('administrator')) {
        myhh_create_required_pages();
        flush_rewrite_rules();
        wp_die('Required pages have been created/updated. <a href="' . admin_url() . '">Return to admin</a>');
    }
}
add_action('admin_init', 'myhh_manual_create_pages');

/**
 * Disable GeoDirectory's automatic map injection on city taxonomy pages
 * We use our own custom Google Maps implementation instead
 */
function myhh_disable_geodir_map_on_city_pages() {
    if (is_tax('myhh_city')) {
        // Remove GeoDirectory map widget from rendering on city pages
        remove_action('geodir_archive_before_loop', 'geodir_action_map_archive', 10);
        remove_action('geodir_before_archive_item', 'geodir_action_map_archive', 10);
        
        // Remove the GeoDirectory map script enqueuing for city pages
        add_filter('geodir_get_map_script', '__return_empty_string');
    }
}
add_action('template_redirect', 'myhh_disable_geodir_map_on_city_pages', 5);

/**
 * Redirect GeoDirectory location pages to city taxonomy pages
 * This ensures we use our polished taxonomy template
 */
function myhh_redirect_location_to_city() {
    if (!is_admin()) {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
        
        // Match /location/{city}/ pattern
        if (preg_match('#^/location/([^/]+)/?$#', $request_uri, $matches)) {
            $city_slug = $matches[1];
            $redirect_url = home_url('/city/' . $city_slug . '/');
            wp_redirect($redirect_url, 301);
            exit;
        }
    }
}
add_action('template_redirect', 'myhh_redirect_location_to_city');

/**
 * Suppress Google Maps console warnings from GeoDirectory plugin:
 * 1. Add loading=async to the Maps JS API URL (fixes "loaded directly without loading=async" warning)
 * 2. Override DirectionsRenderer/DirectionsService instantiation to prevent deprecated API warnings
 * 3. Silence goMap.min.js debug console.log statements
 */
add_filter('geodir_map_api_google_data', 'myhh_fix_geodir_maps_api_url', 20);
function myhh_fix_geodir_maps_api_url($google_data) {
    if (!empty($google_data['scripts'])) {
        foreach ($google_data['scripts'] as &$script) {
            if (!empty($script['src']) && strpos($script['src'], 'maps.googleapis.com/maps/api/js') !== false) {
                // Add loading=async if not already present
                if (strpos($script['src'], 'loading=async') === false) {
                    $script['src'] = add_query_arg('loading', 'async', $script['src']);
                }
            }
        }
        unset($script);
    }
    return $google_data;
}

add_action('wp_footer', 'myhh_suppress_geodir_maps_console', 999);
function myhh_suppress_geodir_maps_console() {
    ?>
    <script>
    (function() {
        // Suppress goMap debug logs and DirectionsRenderer/Service deprecation warnings
        var _consoleWarn = console.warn.bind(console);
        var _consoleLog  = console.log.bind(console);
        var suppressWarnPatterns = [
            'DirectionsRenderer is deprecated',
            'DirectionsService is deprecated',
            'loaded directly without loading=async'
        ];
        var suppressLogPatterns = [
            'Google Maps API Loaded',
            'Leaflet | OpenStreetMap'
        ];
        console.warn = function() {
            var msg = arguments[0] ? String(arguments[0]) : '';
            for (var i = 0; i < suppressWarnPatterns.length; i++) {
                if (msg.indexOf(suppressWarnPatterns[i]) !== -1) return;
            }
            _consoleWarn.apply(console, arguments);
        };
        console.log = function() {
            var msg = arguments[0] ? String(arguments[0]) : '';
            for (var i = 0; i < suppressLogPatterns.length; i++) {
                if (msg.indexOf(suppressLogPatterns[i]) !== -1) return;
            }
            _consoleLog.apply(console, arguments);
        };
    })();
    </script>
    <?php
}
