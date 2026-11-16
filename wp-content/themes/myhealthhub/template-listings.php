<?php
/**
 * Template Name: GeoDirectory Listings
 *
 * Custom template for the /listings/ page with polished design
 *
 * @package MyHealthHub
 * @version 2.0.0 - Enhanced with NPI data search
 */

get_header();

// Get filter values
$selected_city = isset($_GET['city']) ? sanitize_text_field($_GET['city']) : '';
$selected_specialty = isset($_GET['specialty']) ? sanitize_text_field($_GET['specialty']) : '';
$selected_session = isset($_GET['session_type']) ? sanitize_text_field($_GET['session_type']) : '';
$selected_verification = isset($_GET['verification']) ? sanitize_text_field($_GET['verification']) : '';
$selected_primary_category = isset($_GET['primary_category']) ? sanitize_text_field($_GET['primary_category']) : '';
$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
?>

<!-- Responsive Styles -->
<style>
/* Listings Page Responsive Styles */
@media (max-width: 1024px) {
    .geodir-search-form {
        grid-template-columns: repeat(3, 1fr) !important;
    }
}

@media (max-width: 768px) {
    /* Hero Section */
    .providers-hero { padding: 40px 0 60px !important; }
    .providers-hero__title { font-size: 1.75rem !important; }
    .providers-hero__subtitle { font-size: 1rem !important; }
    
    /* Filter Form */
    .geodir-search-form {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
        padding: 16px !important;
    }
    .geodir-search-form .search-field { width: 100% !important; }
    .geodir-search-form input,
    .geodir-search-form select { width: 100% !important; }
    
    /* Provider Grid */
    .providers-grid {
        grid-template-columns: 1fr !important;
        gap: 20px !important;
    }
    
    /* Pagination */
    .pagination { flex-wrap: wrap; justify-content: center !important; }
}

@media (max-width: 480px) {
    .providers-hero { padding: 32px 0 48px !important; }
    .providers-hero__title { font-size: 1.5rem !important; }
}
</style>

<?php
// Get available filters
$primary_categories = get_terms(array('taxonomy' => 'myhh_primary_category', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC'));
$cities = get_terms(array('taxonomy' => 'myhh_city', 'hide_empty' => true));
$specialties = get_terms(array('taxonomy' => 'myhh_specialty', 'hide_empty' => true, 'parent' => 0));
$license_types = get_terms(array('taxonomy' => 'myhh_license_type', 'hide_empty' => true));

// Get unique NPI provider types from database (verified data from NPI Registry)
global $wpdb;
$npi_provider_types = $wpdb->get_results("
    SELECT meta_value, COUNT(*) as count 
    FROM {$wpdb->postmeta} 
    WHERE meta_key = 'npi_primary_taxonomy' 
    AND meta_value != '' 
    GROUP BY meta_value 
    ORDER BY count DESC
");

// Build query args
$args = array(
    'post_type' => 'gd_place',
    'posts_per_page' => 12,
    'paged' => get_query_var('paged') ? get_query_var('paged') : 1,
);

// Add search
if ($search_query) {
    // Standard WordPress search searches post_title and post_content
    $args['s'] = $search_query;
}

// Add taxonomy filters
$tax_query = array();

// Filter by license type
$selected_license = isset($_GET['license_type']) ? sanitize_text_field($_GET['license_type']) : '';
if ($selected_license) {
    $tax_query[] = array(
        'taxonomy' => 'myhh_license_type',
        'field' => 'slug',
        'terms' => $selected_license,
    );
}

if ($selected_city) {
    $tax_query[] = array(
        'taxonomy' => 'myhh_city',
        'field' => 'slug',
        'terms' => $selected_city,
    );
}

// Filter by primary category (Milestone 3)
if ($selected_primary_category) {
    $tax_query[] = array(
        'taxonomy' => 'myhh_primary_category',
        'field' => 'slug',
        'terms' => $selected_primary_category,
    );
}

if ($selected_specialty) {
    // Check if it's a CSV specialty (prefix: csv:) or NPI provider type (prefix: npi:)
    if (strpos($selected_specialty, 'csv:') === 0) {
        $tax_query[] = array(
            'taxonomy' => 'myhh_specialty',
            'field' => 'slug',
            'terms' => substr($selected_specialty, 4),
        );
    } elseif (strpos($selected_specialty, 'npi:') === 0) {
        $meta_query[] = array(
            'key' => 'npi_primary_taxonomy',
            'value' => substr($selected_specialty, 4),
            'compare' => '=',
        );
    } else {
        // Legacy support: no prefix (assume CSV taxonomy)
        $tax_query[] = array(
            'taxonomy' => 'myhh_specialty',
            'field' => 'slug',
            'terms' => $selected_specialty,
        );
    }
}
if (!empty($tax_query)) {
    $tax_query['relation'] = 'AND';
    $args['tax_query'] = $tax_query;
}

// Add meta query for session type and verification
$meta_query = array();

// Filter by specific session type (exclude 'unknown' when filtering)
if ($selected_session) {
    $meta_query[] = array(
        'key' => 'session_type',
        'value' => $selected_session,
        'compare' => 'LIKE',
    );
}

// Filter by verification status
if ($selected_verification) {
    $meta_query[] = array(
        'key' => 'verification_status',
        'value' => $selected_verification,
        'compare' => '=',
    );
}

if (!empty($meta_query)) {
    $meta_query['relation'] = 'AND';
    $args['meta_query'] = $meta_query;
}

$providers = new WP_Query($args);
$total_providers = wp_count_posts('gd_place')->publish;
?>

<main class="site-main">
    
    <!-- Hero Header Section -->
    <header class="providers-hero" style="background: linear-gradient(135deg, #0A74DA 0%, #0858a8 100%); color: #fff; padding: 60px 0 80px; position: relative; overflow: hidden;">
        <div class="container" style="position: relative; z-index: 1;">
            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span style="background: rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 20px; font-size: 0.875rem; font-weight: 500;">
                    <?php echo esc_html($total_providers); ?> Providers
                </span>
            </div>
            
            <h1 class="providers-hero__title" style="font-size: 2.75rem; font-weight: 700; margin: 0 0 12px; line-height: 1.2;">
                Find Your Wellness Provider
            </h1>
            
            <p class="providers-hero__subtitle" style="font-size: 1.25rem; opacity: 0.9; margin: 0; max-width: 600px;">
                Connect with verified wellness professionals in the Raleigh/Triangle area.
            </p>
        </div>
    </header>

    <!-- Search & Filter Section -->
    <div class="geodir-search-container" style="background: #fff; margin-top: -30px; position: relative; z-index: 10; padding: 0 0 24px;">
        <div class="container">
            <form method="get" class="geodir-search-form" action="" style="background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.12); padding: 24px; display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr 1fr 1fr 1fr auto; gap: 16px; align-items: end;">
                <input type="hidden" name="post_type" value="gd_place">
                
                <!-- Keyword Search -->
                <div class="search-field">
                    <label for="search-keyword" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #1F2937; margin-bottom: 6px;">
                        Search Providers
                    </label>
                    <div style="position: relative;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%);">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="M21 21l-4.35-4.35"></path>
                        </svg>
                        <input type="text" id="search-keyword" name="s" placeholder="Name, specialty, or keyword..." 
                               value="<?php echo esc_attr($search_query); ?>"
                               style="width: 100%; padding: 12px 12px 12px 40px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 0.9375rem;">
                    </div>
                </div>
                
                <!-- Primary Category Filter -->
                <div class="search-field">
                    <label for="filter-category" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #1F2937; margin-bottom: 6px;">
                        Category
                    </label>
                    <select id="filter-category" name="primary_category" style="width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 0.9375rem; background: #fff; cursor: pointer;">
                        <option value="">All Categories</option>
                        <?php if ($primary_categories && !is_wp_error($primary_categories)) : foreach ($primary_categories as $category) : ?>
                            <option value="<?php echo esc_attr($category->slug); ?>" <?php selected($selected_primary_category, $category->slug); ?>>
                                <?php echo esc_html($category->name); ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
                
                <!-- City Filter -->
                <div class="search-field">
                    <label for="filter-city" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #1F2937; margin-bottom: 6px;">
                        Location
                    </label>
                    <select id="filter-city" name="city" style="width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 0.9375rem; background: #fff; cursor: pointer;">
                        <option value="">All Areas</option>
                        <?php if ($cities) : foreach ($cities as $city) : ?>
                            <option value="<?php echo esc_attr($city->slug); ?>" <?php selected($selected_city, $city->slug); ?>>
                                <?php echo esc_html($city->name); ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
                
                <!-- Specialty Filter (Combined: CSV + NPI Verified) -->
                <div class="search-field">
                    <label for="filter-specialty" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #1F2937; margin-bottom: 6px;">
                        Specialty / Provider Type
                    </label>
                    <select id="filter-specialty" name="specialty" style="width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 0.9375rem; background: #fff; cursor: pointer;">
                        <option value="">All Specialties</option>
                        
                        <!-- Treatment Specialties (from CSV) -->
                        <optgroup label="Treatment Specialties">
                            <?php if ($specialties) : foreach ($specialties as $specialty) : ?>
                                <option value="csv:<?php echo esc_attr($specialty->slug); ?>" <?php selected($selected_specialty, 'csv:' . $specialty->slug); ?>>
                                    <?php echo esc_html($specialty->name); ?>
                                </option>
                            <?php endforeach; endif; ?>
                        </optgroup>
                        
                        <!-- Provider Types (NPI Verified) -->
                        <optgroup label="Provider Types (NPI Verified)">
                            <?php if ($npi_provider_types) : foreach ($npi_provider_types as $pt) : ?>
                                <option value="npi:<?php echo esc_attr($pt->meta_value); ?>" <?php selected($selected_specialty, 'npi:' . $pt->meta_value); ?>>
                                    <?php echo esc_html($pt->meta_value); ?> (<?php echo esc_html($pt->count); ?>)
                                </option>
                            <?php endforeach; endif; ?>
                        </optgroup>
                    </select>
                </div>
                
                <!-- Session Type Filter -->
                <div class="search-field">
                    <label for="filter-session" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #1F2937; margin-bottom: 6px;">
                        Session
                    </label>
                    <select id="filter-session" name="session_type" style="width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 0.9375rem; background: #fff; cursor: pointer;">
                        <option value="">Any Type</option>
                        <option value="In-Person" <?php selected($selected_session, 'In-Person'); ?>>In-Person</option>
                        <option value="Virtual" <?php selected($selected_session, 'Virtual'); ?>>Virtual</option>
                        <option value="Hybrid" <?php selected($selected_session, 'Hybrid'); ?>>Hybrid</option>
                    </select>
                </div>
                
                <!-- License Type Filter -->
                <div class="search-field">
                    <label for="filter-license" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #1F2937; margin-bottom: 6px;">
                        License Type
                    </label>
                    <select id="filter-license" name="license_type" style="width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 0.9375rem; background: #fff; cursor: pointer;">
                        <option value="">All Types</option>
                        <?php if ($license_types) : foreach ($license_types as $lt) : ?>
                            <option value="<?php echo esc_attr($lt->slug); ?>" <?php selected($selected_license, $lt->slug); ?>>
                                <?php echo esc_html($lt->name); ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
                
                <!-- Verification Filter -->
                <div class="search-field">
                    <label for="filter-verification" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #1F2937; margin-bottom: 6px;">
                        Verification
                    </label>
                    <select id="filter-verification" name="verification" style="width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 0.9375rem; background: #fff; cursor: pointer;">
                        <option value="">Any Status</option>
                        <option value="board_confirmed" <?php selected($selected_verification, 'board_confirmed'); ?>>Board Verified</option>
                        <option value="core_verified" <?php selected($selected_verification, 'core_verified'); ?>>Verified</option>
                        <option value="source_only" <?php selected($selected_verification, 'source_only'); ?>>Source Verified</option>
                    </select>
                </div>
                
                <!-- Submit Button -->
                <div class="search-field">
                    <button type="submit" style="width: 100%; padding: 12px 24px; background: linear-gradient(135deg, #0A74DA 0%, #0858a8 100%); border: none; border-radius: 10px; font-weight: 600; font-size: 0.9375rem; color: #fff; cursor: pointer;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="M21 21l-4.35-4.35"></path>
                        </svg>
                        Search
                    </button>
                </div>
                
            </form>
        </div>
    </div>

    <!-- Results Section -->
    <div class="page-content" style="background: #F8FAFC; padding: 40px 0 80px;">
        <div class="container">
            
            <!-- Filter Bar -->
            <div class="geodir-filter-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
                <div class="geodir-filter-results" style="font-size: 1rem; color: #1F2937;">
                    <?php
                    printf(
                        '<strong>%s</strong> %s',
                        number_format_i18n($providers->found_posts),
                        _n('provider found', 'providers found', $providers->found_posts, 'myhealthhub')
                    );
                    ?>
                </div>
                
                <div class="geodir-filter-sort" style="display: flex; align-items: center; gap: 8px;">
                    <label for="sort-results" style="font-size: 0.875rem; color: #6B7280;">
                        Sort by:
                    </label>
                    <select id="sort-results" name="sort" style="padding: 8px 12px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 0.875rem; background: #fff; cursor: pointer;">
                        <option value="">Recommended</option>
                        <option value="name_asc">Name: A-Z</option>
                        <option value="recent">Recently Added</option>
                    </select>
                </div>
            </div>
            
            <!-- Results Grid -->
            <?php if ($providers->have_posts()) : ?>
                
                <div class="listings-grid providers-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 28px;">
                    <?php while ($providers->have_posts()) : $providers->the_post(); ?>
                        
                        <?php
                        // Get listing data
                        $post_id = get_the_ID();
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
                        $provider_license_types = get_the_terms($post_id, 'myhh_license_type');
                        
                        // Prepare license type
                        $license_type_name = '';
                        if ($provider_license_types && !is_wp_error($provider_license_types)) {
                            $license_type_name = $provider_license_types[0]->name;
                        }
                        
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
                        
                        // Load listing card template
                        get_template_part('template-parts/listing-card', null, array(
                            'title'       => get_the_title(),
                            'permalink'   => get_permalink(),
                            'image'       => $thumbnail,
                            'excerpt'     => get_the_excerpt(),
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
                        ?>
                        
                    <?php endwhile; ?>
                </div>
                
                <!-- Pagination -->
                <div class="geodir-pagination" style="margin-top: 48px; display: flex; justify-content: center;">
                    <?php
                    echo paginate_links(array(
                        'total' => $providers->max_num_pages,
                        'current' => $providers->query_vars['paged'] ? $providers->query_vars['paged'] : 1,
                        'prev_text' => '<span>&larr; Previous</span>',
                        'next_text' => '<span>Next &rarr;</span>',
                        'mid_size' => 2,
                    ));
                    ?>
                </div>
                
                <?php wp_reset_postdata(); ?>
                
            <?php else : ?>
                
                <div class="no-results" style="text-align: center; padding: 80px 40px; background: #fff; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5" style="margin-bottom: 24px;">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="M21 21l-4.35-4.35"></path>
                    </svg>
                    <h3 style="color: #1F2937; margin-bottom: 12px;">No providers found</h3>
                    <p style="color: #6B7280; margin-bottom: 32px; max-width: 400px; margin-left: auto; margin-right: auto;">
                        Try adjusting your search filters or browse all providers in our directory.
                    </p>
                    <a href="<?php echo esc_url(get_permalink()); ?>" style="display: inline-block; padding: 12px 32px; background: linear-gradient(135deg, #0A74DA 0%, #0858a8 100%); color: #fff; border-radius: 10px; font-weight: 600; text-decoration: none;">
                        View All Providers
                    </a>
                </div>
                
            <?php endif; ?>
            
        </div>
    </div>
</main>

<?php
get_footer();
