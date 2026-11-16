<?php
/**
 * The template for displaying GeoDirectory archive pages
 *
 * @package MyHealthHub
 * @version 1.1.0
 */

get_header();

// Get filter values
$selected_city = isset($_GET['city']) ? sanitize_text_field($_GET['city']) : '';
$selected_specialty = isset($_GET['specialty']) ? sanitize_text_field($_GET['specialty']) : '';
$selected_session = isset($_GET['session_type']) ? sanitize_text_field($_GET['session_type']) : '';
$selected_verification = isset($_GET['verification']) ? sanitize_text_field($_GET['verification']) : '';

// Get available filters
$cities = get_terms(array('taxonomy' => 'myhh_city', 'hide_empty' => true));
$specialties = get_terms(array('taxonomy' => 'myhh_specialty', 'hide_empty' => true, 'parent' => 0));

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

// Count total providers
$total_providers = wp_count_posts('gd_place')->publish;
?>

<main class="site-main">
    
    <!-- Hero Header Section -->
    <header class="providers-hero" style="background: linear-gradient(135deg, #0A74DA 0%, #0858a8 100%); color: #fff; padding: 60px 0 80px; position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><circle cx=\"50\" cy=\"50\" r=\"40\" fill=\"none\" stroke=\"rgba(255,255,255,0.05)\" stroke-width=\"0.5\"/></svg>') repeat; opacity: 0.3;"></div>
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
                <?php 
                if (is_tax('myhh_specialty')) {
                    echo esc_html(single_term_title('', false));
                    echo ' '; _e('Specialists', 'myhealthhub');
                } elseif (is_tax('myhh_city')) {
                    printf(__('Providers in %s', 'myhealthhub'), single_term_title('', false));
                } else {
                    _e('Find Your Wellness Provider', 'myhealthhub');
                }
                ?>
            </h1>
            
            <p class="providers-hero__subtitle" style="font-size: 1.25rem; opacity: 0.9; margin: 0; max-width: 600px;">
                <?php
                if (is_tax()) {
                    $term_description = term_description();
                    if ($term_description) {
                        echo wp_strip_all_tags($term_description);
                    } else {
                        _e('Connect with verified wellness professionals in the Raleigh/Triangle area.', 'myhealthhub');
                    }
                } else {
                    _e('Connect with verified wellness professionals in the Raleigh/Triangle area.', 'myhealthhub');
                }
                ?>
            </p>
        </div>
    </header>

    <!-- Search & Filter Section -->
    <div class="geodir-search-container" style="background: #fff; margin-top: -30px; position: relative; z-index: 10; padding: 0 0 24px;">
        <div class="container">
            <form method="get" class="geodir-search-form" action="<?php echo esc_url(home_url('/')); ?>" style="background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.12); padding: 24px; display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr 1fr auto; gap: 16px; align-items: end;">
                <input type="hidden" name="post_type" value="gd_place">
                
                <!-- Keyword Search -->
                <div class="search-field">
                    <label for="search-keyword" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 6px;">
                        <?php _e('Search Providers', 'myhealthhub'); ?>
                    </label>
                    <div style="position: relative;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%);">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="M21 21l-4.35-4.35"></path>
                        </svg>
                        <input type="text" id="search-keyword" name="s" placeholder="<?php esc_attr_e('Name, specialty, or keyword...', 'myhealthhub'); ?>" 
                               value="<?php echo esc_attr(get_search_query()); ?>"
                               style="width: 100%; padding: 12px 12px 12px 40px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 0.9375rem; transition: border-color 0.2s, box-shadow 0.2s;"
                               onfocus="this.style.borderColor='#0A74DA';this.style.boxShadow='0 0 0 3px rgba(10,116,218,0.1)';"
                               onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='none';">
                    </div>
                </div>
                
                <!-- City Filter -->
                <div class="search-field">
                    <label for="filter-city" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 6px;">
                        <?php _e('Location', 'myhealthhub'); ?>
                    </label>
                    <select id="filter-city" name="city" style="width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 0.9375rem; background: #fff; cursor: pointer;">
                        <option value=""><?php _e('All Areas', 'myhealthhub'); ?></option>
                        <?php foreach ($cities as $city) : ?>
                            <option value="<?php echo esc_attr($city->slug); ?>" <?php selected($selected_city, $city->slug); ?>>
                                <?php echo esc_html($city->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Specialty Filter (Combined: CSV + NPI Verified) -->
                <div class="search-field">
                    <label for="filter-specialty" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 6px;">
                        <?php _e('Specialty / Provider Type', 'myhealthhub'); ?>
                    </label>
                    <select id="filter-specialty" name="specialty" style="width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 0.9375rem; background: #fff; cursor: pointer;">
                        <option value=""><?php _e('All Specialties', 'myhealthhub'); ?></option>
                        
                        <!-- Treatment Specialties (from CSV) -->
                        <optgroup label="Treatment Specialties">
                            <?php foreach ($specialties as $specialty) : ?>
                                <option value="csv:<?php echo esc_attr($specialty->slug); ?>" <?php selected($selected_specialty, 'csv:' . $specialty->slug); ?>>
                                    <?php echo esc_html($specialty->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        
                        <!-- Provider Types (NPI Verified) -->
                        <optgroup label="Provider Types (NPI Verified)">
                            <?php foreach ($npi_provider_types as $pt) : ?>
                                <option value="npi:<?php echo esc_attr($pt->meta_value); ?>" <?php selected($selected_specialty, 'npi:' . $pt->meta_value); ?>>
                                    <?php echo esc_html($pt->meta_value); ?> (<?php echo esc_html($pt->count); ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                
                <!-- Session Type Filter -->
                <div class="search-field">
                    <label for="filter-session" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 6px;">
                        <?php _e('Session', 'myhealthhub'); ?>
                    </label>
                    <select id="filter-session" name="session_type" style="width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 0.9375rem; background: #fff; cursor: pointer;">
                        <option value=""><?php _e('Any Type', 'myhealthhub'); ?></option>
                        <option value="In-Person" <?php selected($selected_session, 'In-Person'); ?>><?php _e('In-Person', 'myhealthhub'); ?></option>
                        <option value="Virtual" <?php selected($selected_session, 'Virtual'); ?>><?php _e('Virtual', 'myhealthhub'); ?></option>
                        <option value="Hybrid" <?php selected($selected_session, 'Hybrid'); ?>><?php _e('Hybrid', 'myhealthhub'); ?></option>
                    </select>
                </div>
                
                <!-- Verification Filter -->
                <div class="search-field">
                    <label for="filter-verification" class="form-label" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text); margin-bottom: 6px;">
                        <?php _e('Verification', 'myhealthhub'); ?>
                    </label>
                    <select id="filter-verification" name="verification" style="width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 0.9375rem; background: #fff; cursor: pointer;">
                        <option value=""><?php _e('Any Status', 'myhealthhub'); ?></option>
                        <option value="board_confirmed" <?php selected($selected_verification, 'board_confirmed'); ?>><?php _e('Board Verified', 'myhealthhub'); ?></option>
                        <option value="core_verified" <?php selected($selected_verification, 'core_verified'); ?>><?php _e('Verified', 'myhealthhub'); ?></option>
                        <option value="source_only" <?php selected($selected_verification, 'source_only'); ?>><?php _e('Source Verified', 'myhealthhub'); ?></option>
                    </select>
                </div>
                
                <!-- Submit Button -->
                <div class="search-field">
                    <button type="submit" class="btn btn--primary" style="width: 100%; padding: 12px 24px; background: linear-gradient(135deg, #0A74DA 0%, #0858a8 100%); border: none; border-radius: 10px; font-weight: 600; font-size: 0.9375rem; color: #fff; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;"
                            onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(10,116,218,0.4)';"
                            onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='none';">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="M21 21l-4.35-4.35"></path>
                        </svg>
                        <?php _e('Search', 'myhealthhub'); ?>
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
                <div class="geodir-filter-results" style="font-size: 1rem; color: var(--color-text);">
                    <?php
                    global $wp_query;
                    printf(
                        '<strong>%s</strong> %s',
                        number_format_i18n($wp_query->found_posts),
                        _n('provider found', 'providers found', $wp_query->found_posts, 'myhealthhub')
                    );
                    ?>
                </div>
                
                <div class="geodir-filter-sort" style="display: flex; align-items: center; gap: 8px;">
                    <label for="sort-results" style="font-size: 0.875rem; color: var(--color-text-light);">
                        <?php _e('Sort by:', 'myhealthhub'); ?>
                    </label>
                    <select id="sort-results" name="sort" onchange="window.location.href=this.value" style="padding: 8px 12px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 0.875rem; background: #fff; cursor: pointer;">
                        <?php
                        $current_url = remove_query_arg('sort', $_SERVER['REQUEST_URI']);
                        $sort_options = array(
                            '' => __('Recommended', 'myhealthhub'),
                            'name_asc' => __('Name: A-Z', 'myhealthhub'),
                            'name_desc' => __('Name: Z-A', 'myhealthhub'),
                            'recent' => __('Recently Added', 'myhealthhub'),
                        );
                        foreach ($sort_options as $value => $label) :
                            $url = add_query_arg('sort', $value, $current_url);
                            $selected = isset($_GET['sort']) && $_GET['sort'] === $value;
                        ?>
                            <option value="<?php echo esc_url($url); ?>" <?php selected($selected); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Results Grid -->
            <?php if (have_posts()) : ?>
                
                <div class="listings-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 28px;">
                    <?php while (have_posts()) : the_post(); ?>
                        
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
                        
                        // Get rating
                        $rating = function_exists('myhh_get_listing_rating') ? myhh_get_listing_rating($post_id) : null;
                        
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
                        'prev_text' => '<span style="display: inline-flex; align-items: center; gap: 4px;">&larr; Previous</span>',
                        'next_text' => '<span style="display: inline-flex; align-items: center; gap: 4px;">Next &rarr;</span>',
                        'mid_size'  => 2,
                        'type'      => 'list',
                    ));
                    ?>
                </div>
                
            <?php else : ?>
                
                <div class="no-results" style="text-align: center; padding: 80px 40px; background: #fff; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5" style="margin-bottom: 24px;">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="M21 21l-4.35-4.35"></path>
                    </svg>
                    <h3 style="color: var(--color-text); margin-bottom: 12px;"><?php _e('No providers found', 'myhealthhub'); ?></h3>
                    <p style="color: var(--color-text-light); margin-bottom: 32px; max-width: 400px; margin-left: auto; margin-right: auto;">
                        <?php _e('Try adjusting your search filters or browse all providers in our directory.', 'myhealthhub'); ?>
                    </p>
                    <a href="<?php echo esc_url(get_post_type_archive_link('gd_place')); ?>" class="btn btn--primary" style="padding: 12px 32px;">
                        <?php _e('View All Providers', 'myhealthhub'); ?>
                    </a>
                </div>
                
            <?php endif; ?>
            
        </div>
    </div>
</main>

<?php
get_footer();
