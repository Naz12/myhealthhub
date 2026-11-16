<?php
/**
 * Theme Customizer settings
 *
 * @package MyHealthHub
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Customizer setup
 */
function myhh_customize_register($wp_customize) {
    
    // Site Identity section already exists, add logo option
    $wp_customize->add_setting('myhh_logo', array(
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
        'transport'         => 'refresh',
    ));
    
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'myhh_logo', array(
        'label'       => __('Logo', 'myhealthhub'),
        'description' => __('Upload a custom logo. If empty, site title will be displayed.', 'myhealthhub'),
        'section'     => 'title_tagline',
        'settings'    => 'myhh_logo',
    )));
    
    // Hero Section
    $wp_customize->add_section('myhh_hero', array(
        'title'       => __('Hero Section', 'myhealthhub'),
        'description' => __('Customize the homepage hero section', 'myhealthhub'),
        'priority'    => 100,
    ));
    
    $wp_customize->add_setting('myhh_hero_title', array(
        'default'           => __('Find the Right Mental Health Professional in the Triangle', 'myhealthhub'),
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ));
    
    $wp_customize->add_control('myhh_hero_title', array(
        'label'   => __('Hero Title', 'myhealthhub'),
        'section' => 'myhh_hero',
        'type'    => 'text',
    ));
    
    $wp_customize->add_setting('myhh_hero_subtitle', array(
        'default'           => __('Connect with verified therapists, counselors, and life coaches in Raleigh, Durham, Cary, and Chapel Hill.', 'myhealthhub'),
        'sanitize_callback' => 'sanitize_textarea_field',
        'transport'         => 'refresh',
    ));
    
    $wp_customize->add_control('myhh_hero_subtitle', array(
        'label'   => __('Hero Subtitle', 'myhealthhub'),
        'section' => 'myhh_hero',
        'type'    => 'textarea',
    ));
    
    $wp_customize->add_setting('myhh_hero_button_primary', array(
        'default'           => __('Browse All Providers', 'myhealthhub'),
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ));
    
    $wp_customize->add_control('myhh_hero_button_primary', array(
        'label'   => __('Primary Button Text', 'myhealthhub'),
        'section' => 'myhh_hero',
        'type'    => 'text',
    ));
    
    $wp_customize->add_setting('myhh_hero_button_secondary', array(
        'default'           => __('Get Matched', 'myhealthhub'),
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ));
    
    $wp_customize->add_control('myhh_hero_button_secondary', array(
        'label'   => __('Secondary Button Text', 'myhealthhub'),
        'section' => 'myhh_hero',
        'type'    => 'text',
    ));
    
    // Contact Info
    $wp_customize->add_section('myhh_contact', array(
        'title'       => __('Contact Information', 'myhealthhub'),
        'description' => __('Contact details displayed throughout the site', 'myhealthhub'),
        'priority'    => 101,
    ));
    
    $wp_customize->add_setting('myhh_contact_email', array(
        'default'           => 'hello@myhealthub.com',
        'sanitize_callback' => 'sanitize_email',
        'transport'         => 'refresh',
    ));
    
    $wp_customize->add_control('myhh_contact_email', array(
        'label'   => __('Contact Email', 'myhealthhub'),
        'section' => 'myhh_contact',
        'type'    => 'email',
    ));
    
    $wp_customize->add_setting('myhh_contact_phone', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ));
    
    $wp_customize->add_control('myhh_contact_phone', array(
        'label'   => __('Contact Phone', 'myhealthhub'),
        'section' => 'myhh_contact',
        'type'    => 'tel',
    ));
    
    // Social Links
    $wp_customize->add_section('myhh_social', array(
        'title'       => __('Social Media', 'myhealthhub'),
        'description' => __('Social media links for the footer', 'myhealthhub'),
        'priority'    => 102,
    ));
    
    $social_networks = array(
        'facebook'  => __('Facebook', 'myhealthhub'),
        'twitter'   => __('Twitter', 'myhealthhub'),
        'instagram' => __('Instagram', 'myhealthhub'),
        'linkedin'  => __('LinkedIn', 'myhealthhub'),
    );
    
    foreach ($social_networks as $key => $label) {
        $wp_customize->add_setting("myhh_social_{$key}", array(
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
            'transport'         => 'refresh',
        ));
        
        $wp_customize->add_control("myhh_social_{$key}", array(
            'label'   => $label,
            'section' => 'myhh_social',
            'type'    => 'url',
        ));
    }
    
    // Footer
    $wp_customize->add_section('myhh_footer', array(
        'title'       => __('Footer', 'myhealthhub'),
        'description' => __('Footer settings', 'myhealthhub'),
        'priority'    => 103,
    ));
    
    $wp_customize->add_setting('myhh_footer_text', array(
        'default'           => '',
        'sanitize_callback' => 'wp_kses_post',
        'transport'         => 'refresh',
    ));
    
    $wp_customize->add_control('myhh_footer_text', array(
        'label'       => __('Footer Text', 'myhealthhub'),
        'description' => __('Additional text to display in the footer', 'myhealthhub'),
        'section'     => 'myhh_footer',
        'type'        => 'textarea',
    ));
}
add_action('customize_register', 'myhh_customize_register');

/**
 * Get customizer logo HTML
 */
function myhh_get_custom_logo() {
    $logo = get_theme_mod('myhh_logo');
    
    if ($logo) {
        return sprintf(
            '<a href="%s" class="custom-logo-link" rel="home">' .
            '<img src="%s" class="custom-logo" alt="%s" style="max-height: 40px; width: auto;">' .
            '</a>',
            esc_url(home_url('/')),
            esc_url($logo),
            esc_attr(get_bloginfo('name'))
        );
    }
    
    return myhh_site_logo();
}
