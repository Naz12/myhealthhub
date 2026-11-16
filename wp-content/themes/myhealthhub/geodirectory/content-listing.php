<?php
/**
 * The template for displaying listing content within loops
 *
 * Override of GeoDirectory template
 *
 * @package MyHealthHub
 * @version 2.0.0 - Enhanced with NPI data
 */

if (!defined('ABSPATH')) {
    exit;
}

global $gd_post;

// Get listing data
$post_id = $gd_post->ID;
$credentials = get_post_meta($post_id, 'provider_credentials', true);
$session_type = get_post_meta($post_id, 'session_type', true);
$short_bio = get_post_meta($post_id, 'short_bio', true);
$is_claimed = get_post_meta($post_id, '_myhh_claimed', true);
$phone = get_post_meta($post_id, 'phone', true);
$website = get_post_meta($post_id, 'website', true);
$booking_link = get_post_meta($post_id, 'booking_link', true);
$verification_status = get_post_meta($post_id, 'verification_status', true);
$provider_specialties = get_the_terms($post_id, 'myhh_specialty');
$provider_cities = get_the_terms($post_id, 'myhh_city');

// NPI Enriched data
$street = get_post_meta($post_id, 'street', true);
$zip = get_post_meta($post_id, 'zip', true);
$npi_license_number = get_post_meta($post_id, 'npi_license_number', true);
$npi_license_state = get_post_meta($post_id, 'npi_license_state', true);
$npi_primary_taxonomy = get_post_meta($post_id, 'npi_primary_taxonomy', true);
$npi_active_status = get_post_meta($post_id, 'npi_active_status', true);
$npi_sole_proprietor = get_post_meta($post_id, 'npi_sole_proprietor', true);
$license_types = get_the_terms($post_id, 'myhh_license_type');

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

// Fallback: check GeoDirectory and post meta
if (empty($city_name)) {
    // Try GeoDirectory's custom meta first
    $gd_city = function_exists('geodir_get_post_meta') ? geodir_get_post_meta($post_id, 'city', true) : '';
    
    // Also check standard post meta
    $city_meta = get_post_meta($post_id, 'city', true);
    
    // Use whichever has a value
    $city_value = !empty($gd_city) ? $gd_city : $city_meta;
    
    if ($city_value) {
        if (is_numeric($city_value)) {
            // It's a term ID, get the term name
            $city_term = get_term(intval($city_value), 'myhh_city');
            if ($city_term && !is_wp_error($city_term)) {
                $city_name = $city_term->name;
            }
        } else {
            // It's already a string name
            $city_name = $city_value;
        }
    }
}

// Get thumbnail
$thumbnail = get_the_post_thumbnail_url($post_id, 'medium');

// Prepare license type
$license_type_name = '';
if ($license_types && !is_wp_error($license_types)) {
    $license_type_name = $license_types[0]->name;
}

// Build full address for display
$full_address = '';
if ($street) {
    $full_address = $street;
}

// Load our custom listing card
get_template_part('template-parts/listing-card', null, array(
    'title'       => get_the_title($post_id),
    'permalink'   => get_permalink($post_id),
    'image'       => $thumbnail,
    'excerpt'     => get_the_excerpt($post_id),
    'short_bio'   => $short_bio,
    'credentials' => $credentials,
    'session_type'=> $session_type,
    'specialties' => $specialty_names,
    'location'    => $city_name,
    'street'      => $street,
    'zip'         => $zip,
    'phone'       => $phone,
    'website'     => $website,
    'booking_link'=> $booking_link,
    'verification_status' => $verification_status,
    'claimed'     => (bool) $is_claimed,
    'license_type'=> $license_type_name,
    'npi_taxonomy'=> $npi_primary_taxonomy,
    'npi_license' => $npi_license_number,
    'npi_license_state' => $npi_license_state,
));
