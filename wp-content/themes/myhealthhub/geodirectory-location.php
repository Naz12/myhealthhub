<?php
/**
 * Template Name: GeoDirectory Location
 * Description: Custom template for GeoDirectory location pages
 *
 * @package MyHealthHub
 * @version 1.0.0
 */

get_header();

// Get location from URL or query var
$location_slug = get_query_var('gd_location');
if (empty($location_slug)) {
    // Try to get from the request URI
    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
    if (preg_match('#/location/([^/]+)/?#', $request_uri, $matches)) {
        $location_slug = sanitize_title($matches[1]);
    }
}

// Get providers in this location
$paged = max(1, get_query_var('paged'));

$args = array(
    'post_type'      => 'gd_place',
    'posts_per_page' => 12,
    'paged'          => $paged,
    'post_status'    => 'publish',
);

// Filter by location slug if we have one
if (!empty($location_slug)) {
    // Try to match by city meta or taxonomy
    $args['tax_query'] = array(
        'relation' => 'OR',
        array(
            'taxonomy' => 'myhh_city',
            'field'    => 'slug',
            'terms'    => $location_slug,
        ),
    );
}

$providers_query = new WP_Query($args);
$total_providers = $providers_query->found_posts;

// Get location name from slug
$location_name = ucfirst(str_replace('-', ' ', $location_slug));
if (empty($location_slug)) {
    $location_name = 'All Locations';
}

// Get all cities for filter
$cities = get_terms(array('taxonomy' => 'myhh_city', 'hide_empty' => true));
$specialties = get_terms(array('taxonomy' => 'myhh_specialty', 'hide_empty' => true, 'parent' => 0));
?>

<main class="site-main">
    
    <!-- Hero Header Section -->
    <header class="location-hero" style="background: linear-gradient(135deg, #0A74DA 0%, #0858a8 100%); color: #fff; padding: 80px 0 100px; position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; opacity: 0.1;">
            <svg width="100%" height="100%" style="position: absolute;">
                <defs>
                    <pattern id="grid" width="60" height="60" patternUnits="userSpaceOnUse">
                        <path d="M 60 0 L 0 0 0 60" fill="none" stroke="white" stroke-width="1"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)" />
            </svg>
        </div>
        
        <div class="container" style="position: relative; z-index: 1;">
            <div style="max-width: 800px; margin: 0 auto; text-align: center;">
                <nav style="margin-bottom: 20px; font-size: 0.875rem;">
                    <a href="<?php echo esc_url(home_url()); ?>" style="color: rgba(255,255,255,0.7); text-decoration: none;">Home</a>
                    <span style="color: rgba(255,255,255,0.5); margin: 0 8px;">›</span>
                    <a href="<?php echo esc_url(home_url('/listings/')); ?>" style="color: rgba(255,255,255,0.7); text-decoration: none;">Providers</a>
                    <span style="color: rgba(255,255,255,0.5); margin: 0 8px;">›</span>
                    <span style="color: white;"><?php echo esc_html($location_name); ?></span>
                </nav>
                
                <div style="display: inline-flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.15); padding: 8px 18px; border-radius: 30px; margin-bottom: 20px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span style="font-weight: 500;">Triangle Area</span>
                </div>
                
                <h1 style="color: white; font-size: clamp(2rem, 5vw, 3rem); margin-bottom: 1rem; line-height: 1.2; font-weight: 700;">
                    Mental Health Providers in <?php echo esc_html($location_name); ?>
                </h1>
                
                <p style="color: rgba(255,255,255,0.9); font-size: clamp(1rem, 2.5vw, 1.25rem); margin-bottom: 1.5rem; line-height: 1.7; max-width: 600px; margin-left: auto; margin-right: auto;">
                    Discover trusted therapists, counselors, and wellness professionals serving the <?php echo esc_html($location_name); ?> community.
                </p>
                
                <?php if ($total_providers > 0) : ?>
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); padding: 10px 20px; border-radius: 50px; font-size: 0.9375rem;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span><?php echo esc_html($total_providers); ?> Verified Providers</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Search & Filter Section -->
    <div class="search-container" style="background: #fff; margin-top: -30px; position: relative; z-index: 10; padding: 0 0 24px;">
        <div class="container">
            <form method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>" style="background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.12); padding: 24px; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 16px; align-items: end;">
                <input type="hidden" name="post_type" value="gd_place">
                
                <!-- Keyword Search -->
                <div class="search-field">
                    <label for="search-keyword" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #374151; margin-bottom: 6px;">
                        Search Providers
                    </label>
                    <div style="position: relative;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%);">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="M21 21l-4.35-4.35"></path>
                        </svg>
                        <input type="text" id="search-keyword" name="s" placeholder="Name, specialty, or keyword..." 
                               style="width: 100%; padding: 12px 12px 12px 40px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 0.9375rem; box-sizing: border-box;">
                    </div>
                </div>
                
                <!-- City Filter -->
                <div class="search-field">
                    <label for="filter-city" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #374151; margin-bottom: 6px;">
                        Location
                    </label>
                    <select id="filter-city" name="city" style="width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 0.9375rem; background: #fff; cursor: pointer; box-sizing: border-box;">
                        <option value=""><?php echo esc_html($location_name); ?></option>
                        <?php foreach ($cities as $city) : ?>
                            <option value="<?php echo esc_attr($city->slug); ?>">
                                <?php echo esc_html($city->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Specialty Filter -->
                <div class="search-field">
                    <label for="filter-specialty" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #374151; margin-bottom: 6px;">
                        Specialty
                    </label>
                    <select id="filter-specialty" name="specialty" style="width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 0.9375rem; background: #fff; cursor: pointer; box-sizing: border-box;">
                        <option value="">All Specialties</option>
                        <?php foreach ($specialties as $specialty) : ?>
                            <option value="<?php echo esc_attr($specialty->slug); ?>">
                                <?php echo esc_html($specialty->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Session Type Filter -->
                <div class="search-field">
                    <label for="filter-session" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #374151; margin-bottom: 6px;">
                        Session
                    </label>
                    <select id="filter-session" name="session_type" style="width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 0.9375rem; background: #fff; cursor: pointer; box-sizing: border-box;">
                        <option value="">Any Type</option>
                        <option value="in-person">In-Person</option>
                        <option value="virtual">Virtual</option>
                        <option value="hybrid">Hybrid</option>
                    </select>
                </div>
                
                <!-- Submit Button -->
                <div class="search-field">
                    <button type="submit" style="width: 100%; padding: 12px 24px; background: linear-gradient(135deg, #0A74DA 0%, #0858a8 100%); border: none; border-radius: 10px; font-weight: 600; font-size: 0.9375rem; color: #fff; cursor: pointer;">
                        Search
                    </button>
                </div>
                
            </form>
        </div>
    </div>

    <!-- Results Section -->
    <div class="page-content" style="background: #F8FAFC; padding: 40px 0 80px;">
        <div class="container">
            
            <!-- Results Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
                <div style="font-size: 1rem; color: #374151;">
                    <strong><?php echo esc_html($total_providers); ?></strong> 
                    <?php echo _n('provider', 'providers', $total_providers, 'myhealthhub'); ?> 
                    found in <?php echo esc_html($location_name); ?>
                </div>
                
                <div style="display: flex; align-items: center; gap: 8px;">
                    <label for="sort-results" style="font-size: 0.875rem; color: #6B7280;">Sort by:</label>
                    <select id="sort-results" style="padding: 8px 12px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 0.875rem; background: #fff; cursor: pointer;">
                        <option value="">Recommended</option>
                        <option value="name_asc">Name: A-Z</option>
                        <option value="name_desc">Name: Z-A</option>
                        <option value="recent">Recently Added</option>
                    </select>
                </div>
            </div>
            
            <!-- Results Grid -->
            <?php if ($providers_query->have_posts()) : ?>
                
                <div class="listings-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 28px;">
                    <?php while ($providers_query->have_posts()) : $providers_query->the_post(); ?>
                        
                        <?php
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
                        
                        $specialty_names = array();
                        if ($provider_specialties && !is_wp_error($provider_specialties)) {
                            $specialty_names = wp_list_pluck($provider_specialties, 'name');
                        }
                        
                        $city_name = '';
                        if ($provider_cities && !is_wp_error($provider_cities)) {
                            $city_name = $provider_cities[0]->name;
                        }
                        
                        $thumbnail = get_the_post_thumbnail_url($post_id, 'medium');
                        $rating = function_exists('myhh_get_listing_rating') ? myhh_get_listing_rating($post_id) : null;
                        
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
                <?php if ($providers_query->max_num_pages > 1) : ?>
                <div style="margin-top: 48px; display: flex; justify-content: center;">
                    <?php
                    echo paginate_links(array(
                        'total'     => $providers_query->max_num_pages,
                        'current'   => $paged,
                        'prev_text' => '<span style="display: inline-flex; align-items: center; gap: 4px;">&larr; Previous</span>',
                        'next_text' => '<span style="display: inline-flex; align-items: center; gap: 4px;">Next &rarr;</span>',
                        'mid_size'  => 2,
                        'type'      => 'list',
                    ));
                    ?>
                </div>
                <?php endif; ?>
                
                <?php wp_reset_postdata(); ?>
                
            <?php else : ?>
                
                <div class="no-results" style="text-align: center; padding: 80px 40px; background: #fff; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5" style="margin-bottom: 24px;">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="M21 21l-4.35-4.35"></path>
                    </svg>
                    <h3 style="color: #1F2937; margin-bottom: 12px;">No providers found in <?php echo esc_html($location_name); ?></h3>
                    <p style="color: #6B7280; margin-bottom: 32px; max-width: 400px; margin-left: auto; margin-right: auto;">
                        We're expanding our network! Try browsing providers in nearby areas or check back soon.
                    </p>
                    <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
                        <a href="<?php echo esc_url(home_url('/listings/')); ?>" style="display: inline-flex; align-items: center; gap: 8px; background: #0A74DA; color: white; padding: 14px 24px; border-radius: 10px; font-weight: 600; text-decoration: none;">
                            View All Providers
                        </a>
                        <a href="<?php echo esc_url(home_url('/get-matched/')); ?>" style="display: inline-flex; align-items: center; gap: 8px; background: white; color: #0A74DA; padding: 14px 24px; border-radius: 10px; font-weight: 600; text-decoration: none; border: 2px solid #0A74DA;">
                            Get Matched
                        </a>
                    </div>
                </div>
                
            <?php endif; ?>
            
        </div>
    </div>
    
    <!-- Location Info Section -->
    <section style="padding: 60px 0; background: white;">
        <div class="container">
            <div style="max-width: 800px; margin: 0 auto;">
                <h2 style="font-size: 1.75rem; font-weight: 700; color: #1F2937; margin-bottom: 16px; text-align: center;">
                    About Mental Health Services in <?php echo esc_html($location_name); ?>
                </h2>
                <p style="color: #6B7280; line-height: 1.8; text-align: center; margin-bottom: 32px;">
                    <?php echo esc_html($location_name); ?> is home to a diverse community of mental health professionals dedicated to supporting the well-being of Triangle area residents. From individual therapy to family counseling, our verified providers offer a range of specialties to meet your unique needs.
                </p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px;">
                    <div style="text-align: center; padding: 24px; background: #F8FAFC; border-radius: 12px;">
                        <div style="width: 48px; height: 48px; background: #EEF2FF; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0A74DA" stroke-width="2">
                                <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <h3 style="font-size: 1rem; font-weight: 600; color: #1F2937; margin-bottom: 4px;">Verified Providers</h3>
                        <p style="font-size: 0.875rem; color: #6B7280;">All professionals license-checked</p>
                    </div>
                    <div style="text-align: center; padding: 24px; background: #F8FAFC; border-radius: 12px;">
                        <div style="width: 48px; height: 48px; background: #D1FAE5; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2">
                                <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <h3 style="font-size: 1rem; font-weight: 600; color: #1F2937; margin-bottom: 4px;">Diverse Specialties</h3>
                        <p style="font-size: 0.875rem; color: #6B7280;">Therapy, counseling, psychiatry</p>
                    </div>
                    <div style="text-align: center; padding: 24px; background: #F8FAFC; border-radius: 12px;">
                        <div style="width: 48px; height: 48px; background: #FEF3C7; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2">
                                <path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                        </div>
                        <h3 style="font-size: 1rem; font-weight: 600; color: #1F2937; margin-bottom: 4px;">Easy Contact</h3>
                        <p style="font-size: 0.875rem; color: #6B7280;">Reach providers directly</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php
get_footer();
