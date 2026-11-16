<?php
/**
 * SEO and structured data functions
 *
 * @package MyHealthHub
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Output JSON-LD structured data for Organization
 */
function myhh_organization_schema() {
    $schema = array(
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => get_bloginfo('name'),
        'url'      => home_url('/'),
        'logo'     => array(
            '@type' => 'ImageObject',
            'url'   => get_theme_mod('myhh_logo', ''),
        ),
        'contactPoint' => array(
            '@type'       => 'ContactPoint',
            'email'       => get_theme_mod('myhh_contact_email', 'hello@myhealthub.com'),
            'contactType' => 'customer service',
            'areaServed'  => 'US-NC',
        ),
    );
    
    $phone = get_theme_mod('myhh_contact_phone');
    if ($phone) {
        $schema['contactPoint']['telephone'] = $phone;
    }
    
    echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
}
add_action('wp_head', 'myhh_organization_schema', 5);

/**
 * Output JSON-LD structured data for WebSite
 */
function myhh_website_schema() {
    $schema = array(
        '@context' => 'https://schema.org',
        '@type'    => 'WebSite',
        'name'     => get_bloginfo('name'),
        'url'      => home_url('/'),
        'potentialAction' => array(
            '@type'       => 'SearchAction',
            'target'      => home_url('/?s={search_term_string}'),
            'query-input' => 'required name=search_term_string',
        ),
    );
    
    echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
}
add_action('wp_head', 'myhh_website_schema', 5);

/**
 * Output JSON-LD structured data for LocalBusiness (provider listings)
 */
function myhh_localbusiness_schema() {
    if (!is_singular('gd_place')) {
        return;
    }
    
    $post_id = get_the_ID();
    
    $schema = array(
        '@context' => 'https://schema.org',
        '@type'    => 'LocalBusiness',
        'name'     => get_the_title(),
        'url'      => get_permalink(),
        'description' => get_the_excerpt(),
    );
    
    // Add image if available
    if (has_post_thumbnail()) {
        $schema['image'] = get_the_post_thumbnail_url($post_id, 'full');
    }
    
    // Add address if available (placeholder for GeoDirectory integration)
    $address = get_post_meta($post_id, '_myhh_address', true);
    if ($address) {
        $schema['address'] = array(
            '@type'           => 'PostalAddress',
            'streetAddress'   => $address,
            'addressLocality' => get_post_meta($post_id, '_myhh_city', true),
            'addressRegion'   => 'NC',
            'postalCode'      => get_post_meta($post_id, '_myhh_zip', true),
        );
    }
    
    // Add phone if available
    $phone = get_post_meta($post_id, '_myhh_phone', true);
    if ($phone) {
        $schema['telephone'] = $phone;
    }
    
    echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
}
add_action('wp_head', 'myhh_localbusiness_schema', 10);

/**
 * Add meta description
 */
function myhh_meta_description() {
    if (is_singular()) {
        $description = get_the_excerpt();
    } elseif (is_category() || is_tag() || is_tax()) {
        $description = term_description();
    } elseif (is_home() || is_front_page()) {
        $description = get_bloginfo('description');
    } else {
        $description = '';
    }
    
    if (!empty($description)) {
        echo '<meta name="description" content="' . esc_attr(strip_tags($description)) . '">' . "\n";
    }
}
add_action('wp_head', 'myhh_meta_description', 1);

/**
 * Add Open Graph meta tags
 */
function myhh_open_graph_tags() {
    if (!is_singular()) {
        return;
    }
    
    $post_id = get_the_ID();
    ?>
    <meta property="og:title" content="<?php echo esc_attr(get_the_title()); ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?php echo esc_url(get_permalink()); ?>">
    <meta property="og:site_name" content="<?php echo esc_attr(get_bloginfo('name')); ?>">
    <meta property="og:description" content="<?php echo esc_attr(get_the_excerpt()); ?>">
    <?php if (has_post_thumbnail()) : ?>
        <meta property="og:image" content="<?php echo esc_url(get_the_post_thumbnail_url($post_id, 'large')); ?>">
    <?php endif; ?>
    <?php
}
add_action('wp_head', 'myhh_open_graph_tags', 5);

/**
 * Add Twitter Card meta tags
 */
function myhh_twitter_card_tags() {
    if (!is_singular()) {
        return;
    }
    
    $post_id = get_the_ID();
    ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo esc_attr(get_the_title()); ?>">
    <meta name="twitter:description" content="<?php echo esc_attr(get_the_excerpt()); ?>">
    <?php if (has_post_thumbnail()) : ?>
        <meta name="twitter:image" content="<?php echo esc_url(get_the_post_thumbnail_url($post_id, 'large')); ?>">
    <?php endif; ?>
    <?php
}
add_action('wp_head', 'myhh_twitter_card_tags', 5);

/**
 * Add canonical URL
 */
function myhh_canonical_url() {
    if (is_singular()) {
        $canonical = get_permalink();
    } elseif (is_home() || is_front_page()) {
        $canonical = home_url('/');
    } elseif (is_category() || is_tag() || is_tax()) {
        $canonical = get_term_link(get_queried_object());
    } elseif (is_post_type_archive()) {
        $canonical = get_post_type_archive_link(get_post_type());
    } else {
        $canonical = '';
    }
    
    if (!empty($canonical) && !is_wp_error($canonical)) {
        echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
    }
}
add_action('wp_head', 'myhh_canonical_url', 1);

/**
 * Add robots meta tag
 */
function myhh_robots_meta() {
    if (is_search() || is_404()) {
        echo '<meta name="robots" content="noindex, nofollow">' . "\n";
    } elseif (is_singular('myhh_lead') || is_singular('myhh_claim')) {
        echo '<meta name="robots" content="noindex, nofollow">' . "\n";
    }
}
add_action('wp_head', 'myhh_robots_meta', 1);

/**
 * Add custom robots.txt rules
 */
function myhh_robots_txt($output, $public) {
    // Only modify if site is public
    if ($public) {
        $output = "User-agent: *\n";
        $output .= "Allow: /\n\n";
        $output .= "# Disallow admin and sensitive areas\n";
        $output .= "Disallow: /wp-admin/\n";
        $output .= "Disallow: /wp-includes/\n";
        $output .= "Disallow: /wp-content/plugins/\n";
        $output .= "Disallow: /wp-content/themes/\n";
        $output .= "Allow: /wp-admin/admin-ajax.php\n\n";
        $output .= "# Sitemap\n";
        $output .= "Sitemap: " . home_url('/sitemap.xml') . "\n";
    }
    return $output;
}
add_filter('robots_txt', 'myhh_robots_txt', 10, 2);

/**
 * Add sitemap for GeoDirectory listings
 */
function myhh_sitemap($sitemap) {
    // Add GeoDirectory listings to sitemap
    $providers = get_posts(array(
        'post_type'      => 'gd_place',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ));
    
    foreach ($providers as $provider) {
        $sitemap[] = array(
            'loc'     => get_permalink($provider->ID),
            'lastmod' => get_the_modified_date('c', $provider->ID),
        );
    }
    
    // Add custom taxonomies
    $specialties = get_terms(array('taxonomy' => 'myhh_specialty', 'hide_empty' => true));
    foreach ($specialties as $term) {
        $sitemap[] = array(
            'loc' => get_term_link($term),
        );
    }
    
    $cities = get_terms(array('taxonomy' => 'myhh_city', 'hide_empty' => true));
    foreach ($cities as $term) {
        $sitemap[] = array(
            'loc' => get_term_link($term),
        );
    }
    
    return $sitemap;
}
add_filter('wp_sitemaps_posts', 'myhh_sitemap');
