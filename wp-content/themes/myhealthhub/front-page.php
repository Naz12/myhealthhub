<?php
/**
 * Front page template
 *
 * Clean orchestrator — all CSS lives in assets/css/, all JS in assets/js/,
 * and each visual section in template-parts/.
 *
 * @package MyHealthHub
 */

get_header();

/* ── Data Loading ──────────────────────────────────────────────── */

// City terms (for Browse by Location)
$cities = get_terms(array('taxonomy' => 'myhh_city', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC'));
$specialties = get_terms(array('taxonomy' => 'myhh_specialty', 'hide_empty' => true, 'parent' => 0, 'orderby' => 'count', 'order' => 'DESC'));
$license_types = get_terms(array('taxonomy' => 'myhh_license_type', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC'));

// Get primary categories for category browse section (Milestone 3)
$primary_categories = get_terms(array('taxonomy' => 'myhh_primary_category', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC'));

// Get NPI provider types (verified from NPI Registry)
global $wpdb;
$npi_provider_types = $wpdb->get_results("
    SELECT meta_value, COUNT(*) as count 
    FROM {$wpdb->postmeta} pm
    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID AND p.post_status = 'publish' AND p.post_type = 'gd_place'
    WHERE pm.meta_key = 'npi_primary_taxonomy' 
    AND pm.meta_value != '' 
    GROUP BY pm.meta_value 
    ORDER BY count DESC
");

// Build combined Browse by Specialty list (CSV specialties + NPI provider types)
$browse_items = array();
if ($specialties && !is_wp_error($specialties)) {
    foreach ($specialties as $spec) {
        if ($spec->count > 0) {
            $browse_items[] = array(
                'name'  => $spec->name,
                'count' => (int) $spec->count,
                'link'  => myhh_get_directory_url() . '?specialty=csv:' . $spec->slug,
                'type'  => 'csv',
                'slug'  => $spec->slug,
            );
        }
    }
}
if ($npi_provider_types) {
    foreach ($npi_provider_types as $pt) {
        $browse_items[] = array(
            'name'  => $pt->meta_value,
            'count' => (int) $pt->count,
            'link'  => myhh_get_directory_url() . '?specialty=npi:' . urlencode($pt->meta_value),
            'type'  => 'npi',
            'slug'  => sanitize_title($pt->meta_value),
        );
    }
}
// Sort combined list by count descending
usort($browse_items, function($a, $b) { return $b['count'] - $a['count']; });

/* ── Page Sections ─────────────────────────────────────────────── */

// Hero with Mia avatar + inline chat
get_template_part('template-parts/hero-main');

// Live voice overlay panel
get_template_part('template-parts/voice-overlay');

// Featured providers carousel
get_template_part('template-parts/featured-providers');

// Browse by Category (primary categories)
get_template_part('template-parts/browse-categories', null, array(
    'primary_categories' => $primary_categories,
));

// Browse by Specialty (CSV + NPI combined)
get_template_part('template-parts/browse-specialties', null, array(
    'browse_items' => $browse_items,
));

// Browse by Location (city buttons)
get_template_part('template-parts/browse-locations', null, array(
    'cities' => $cities,
));

// Popular searches (SEO links)
get_template_part('template-parts/popular-searches');

// How It Works (3-step journey)
get_template_part('template-parts/how-it-works');

// Insurance logos
get_template_part('template-parts/insurance-section');

// Testimonials
get_template_part('template-parts/testimonials-section');

// Final CTA
get_template_part('template-parts/final-cta');

// For Providers section
get_template_part('template-parts/for-providers');

get_footer();
