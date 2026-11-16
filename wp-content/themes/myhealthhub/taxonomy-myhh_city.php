<?php
/**
 * The template for displaying myhh_city taxonomy
 * Handles both archive (/city/) and single term (/city/raleigh/)
 *
 * @package MyHealthHub
 */

get_header();

// Check if we're viewing a specific term or all cities
$term = get_queried_object();
$is_term_page = $term && isset($term->slug) && $term->slug !== '' && !empty($term->slug);

if ($is_term_page) :
    // ============================================
    // SINGLE CITY PAGE - Show providers in this city
    // ============================================
    
    $term_name = $term->name;
    $term_description = $term->description;
    $term_slug = $term->slug;
    
    // Query providers in this city (including child terms if any)
    $paged = max(1, get_query_var('paged'));
    
    // Get all child term IDs to include in query (for hierarchical cities)
    $child_terms = get_terms(array(
        'taxonomy'   => 'myhh_city',
        'hide_empty' => false,
        'parent'     => $term->term_id,
        'fields'     => 'ids',
    ));
    
    // Build term IDs array (parent + children)
    $term_ids = array($term->term_id);
    if ($child_terms && !is_wp_error($child_terms)) {
        $term_ids = array_merge($term_ids, $child_terms);
    }
    
    $args = array(
        'post_type'      => 'gd_place',
        'posts_per_page' => 12,
        'paged'          => $paged,
        'tax_query'      => array(
            array(
                'taxonomy' => 'myhh_city',
                'field'    => 'term_id',
                'terms'    => $term_ids,
                'include_children' => true,
            ),
        ),
    );
    $providers_query = new WP_Query($args);
    $total_providers = $providers_query->found_posts;
?>

<!-- Hero Section -->
<section style="background: linear-gradient(135deg, #0A74DA 0%, #1e40af 50%, #0A74DA 100%); padding: 80px 0 100px; color: white; position: relative; overflow: hidden;">
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
                <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" style="color: rgba(255,255,255,0.7); text-decoration: none;">Providers</a>
                <span style="color: rgba(255,255,255,0.5); margin: 0 8px;">›</span>
                <span style="color: white;"><?php echo esc_html($term_name); ?></span>
            </nav>
            
            <h1 style="color: white; font-size: clamp(2rem, 5vw, 3rem); margin-bottom: 1rem; line-height: 1.2; font-weight: 700;">
                Wellness Providers in <?php echo esc_html($term_name); ?>
            </h1>
            
            <?php if ($term_description) : ?>
            <p style="color: rgba(255,255,255,0.9); font-size: clamp(1rem, 2.5vw, 1.25rem); margin-bottom: 1.5rem; line-height: 1.7;">
                <?php echo esc_html($term_description); ?>
            </p>
            <?php endif; ?>
            
            <?php if ($total_providers > 0) : ?>
            <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); padding: 10px 20px; border-radius: 50px; font-size: 0.9375rem;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span><?php echo esc_html($total_providers); ?> Providers Found</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div style="position: absolute; bottom: -1px; left: 0; right: 0;">
        <svg viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" style="width: 100%; height: 80px; display: block;">
            <path d="M0 120L60 110C120 100 240 80 360 70C480 60 600 60 720 65C840 70 960 80 1080 85C1200 90 1320 90 1380 90L1440 90V120H1380C1320 120 1200 120 1080 120C960 120 840 120 720 120C600 120 480 120 360 120C240 120 120 120 60 120H0Z" fill="white"/>
        </svg>
    </div>
</section>

<!-- Providers Grid -->
<section style="padding: 60px 0; background: white;">
    <div class="container">
        <?php if ($providers_query->have_posts()) : ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 28px;">
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
                $thumbnail = get_the_post_thumbnail_url($post_id, 'medium');
                $provider_specialties = get_the_terms($post_id, 'myhh_specialty');
                
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
                    'location'    => $term->name,
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
        
        <?php if ($providers_query->max_num_pages > 1) : ?>
        <div style="display: flex; justify-content: center; margin-top: 48px;">
            <?php echo paginate_links(array('total' => $providers_query->max_num_pages, 'current' => $paged, 'mid_size' => 2)); ?>
        </div>
        <?php endif; ?>
        
        <?php wp_reset_postdata(); ?>
        <?php else : ?>
        <div style="text-align: center; padding: 60px 20px;">
            <p style="color: #6B7280; margin-bottom: 24px;">No providers found in <?php echo esc_html($term_name); ?> yet.</p>
            <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" style="display: inline-flex; align-items: center; gap: 8px; background: #0A74DA; color: white; padding: 14px 28px; border-radius: 12px; font-weight: 600; text-decoration: none;">Browse All Providers</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Other Cities -->
<section style="padding: 60px 0; background: #F8FAFC;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 40px;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1F2937;">Other Locations</h2>
            <p style="color: #6B7280; margin-top: 8px;">Explore wellness providers in nearby areas</p>
        </div>
        <?php
        $other_cities = get_terms(array(
            'taxonomy'   => 'myhh_city',
            'hide_empty' => true,
            'exclude'    => $term->term_id,
            'number'     => 8,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ));
        if ($other_cities && !is_wp_error($other_cities) && count($other_cities) > 0) :
        ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px;">
            <?php foreach ($other_cities as $city) : ?>
                <a href="<?php echo esc_url(get_term_link($city)); ?>" 
                   style="display: flex; align-items: center; gap: 12px; padding: 16px 20px; background: white; border: 1px solid #E5E7EB; border-radius: 12px; text-decoration: none; transition: all 0.2s;"
                   onmouseover="this.style.borderColor='#0A74DA';this.style.background='#EEF2FF';this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(10,116,218,0.15)';"
                   onmouseout="this.style.borderColor='#E5E7EB';this.style.background='white';this.style.transform='translateY(0)';this.style.boxShadow='none';">
                    <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #EEF2FF 0%, #DBEAFE 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0A74DA" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                    </div>
                    <div>
                        <span style="color: #1F2937; font-weight: 600; font-size: 0.9375rem;"><?php echo esc_html($city->name); ?></span>
                        <span style="color: #6B7280; font-size: 0.8125rem; margin-left: 6px;"><?php echo esc_html($city->count); ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php else : ?>
        <div style="text-align: center; padding: 40px; background: white; border-radius: 12px;">
            <p style="color: #6B7280; margin: 0;">No other locations available at this time.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Map Section -->
<section style="padding: 60px 0; background: white;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 32px;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1F2937;">Provider Locations</h2>
            <p style="color: #6B7280; margin-top: 8px;">Find wellness professionals near you in <?php echo esc_html($term_name); ?></p>
        </div>
        
        <?php
        // Query ALL providers with coordinates for the map (not just the paginated 12)
        $map_providers_query = new WP_Query([
            'post_type' => 'gd_place',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => [
                [
                    'taxonomy' => 'myhh_city',
                    'field' => 'term_id',
                    'terms' => $term_ids,
                ],
            ],
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'latitude',
                    'compare' => 'EXISTS',
                ],
                [
                    'key' => 'latitude',
                    'value' => '',
                    'compare' => '!=',
                ],
                [
                    'key' => 'longitude',
                    'compare' => 'EXISTS',
                ],
                [
                    'key' => 'longitude',
                    'value' => '',
                    'compare' => '!=',
                ],
            ],
        ]);
        
        $map_post_ids = $map_providers_query->posts;
        
        // Calculate center coordinates for the city
        $city_center_lat = 35.7796; // Default to Raleigh, NC
        $city_center_lng = -78.6382;
        $city_zoom = 11;
        
        // Collect all provider data for markers
        $map_markers = array();
        if (!empty($map_post_ids)) {
            $total_lat = 0;
            $total_lng = 0;
            $count = 0;
            foreach ($map_post_ids as $pid) {
                $lat = floatval(get_post_meta($pid, 'latitude', true));
                $lng = floatval(get_post_meta($pid, 'longitude', true));
                if ($lat && $lng) {
                    $total_lat += $lat;
                    $total_lng += $lng;
                    $count++;
                    
                    $map_markers[] = array(
                        'id' => $pid,
                        'title' => get_the_title($pid),
                        'lat' => $lat,
                        'lng' => $lng,
                        'url' => get_permalink($pid),
                    );
                }
            }
            if ($count > 0) {
                $city_center_lat = $total_lat / $count;
                $city_center_lng = $total_lng / $count;
                $city_zoom = 12;
            }
        }
        ?>
        
        <?php if (!empty($map_markers)) : ?>
        <!-- Google Maps with Custom Markers -->
        <div id="provider-map" style="height: 450px; border-radius: 16px; overflow: hidden;"></div>
        <script>
        (function() {
            var cityLat = <?php echo $city_center_lat; ?>;
            var cityLng = <?php echo $city_center_lng; ?>;
            var cityZoom = <?php echo $city_zoom; ?>;
            var markers = <?php echo json_encode($map_markers); ?>;

            async function renderProviderMap() {
                var { Map } = await google.maps.importLibrary('maps');
                var { AdvancedMarkerElement } = await google.maps.importLibrary('marker');
                var center = { lat: cityLat, lng: cityLng };
                var map = new Map(document.getElementById('provider-map'), {
                    zoom: cityZoom,
                    center: center,
                    mapId: 'myhh_city_map',
                    disableDefaultUI: false
                });

                var infoWindow = new google.maps.InfoWindow();

                markers.forEach(function(m) {
                    var marker = new AdvancedMarkerElement({
                        position: { lat: m.lat, lng: m.lng },
                        map: map,
                        title: m.title
                    });

                    marker.addListener('click', function() {
                        infoWindow.setContent('<div style="padding:8px;max-width:200px;"><strong><a href="' + m.url + '" style="color:#0A74DA;text-decoration:none;">' + m.title + '</a></strong></div>');
                        infoWindow.open(map, marker);
                    });
                });
            }

            function tryInit() {
                if (typeof google !== 'undefined' && google.maps && google.maps.importLibrary) {
                    renderProviderMap();
                } else {
                    window.addEventListener('load', function() {
                        var check = setInterval(function() {
                            if (typeof google !== 'undefined' && google.maps && google.maps.importLibrary) {
                                clearInterval(check);
                                renderProviderMap();
                            }
                        }, 100);
                    });
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', tryInit);
            } else {
                tryInit();
            }
        })();
        </script>
        <?php else : ?>
        <!-- Placeholder when no coordinates -->
        <div style="background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%); border-radius: 16px; height: 400px; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
            <div style="text-align: center; position: relative; z-index: 1;">
                <div style="width: 80px; height: 80px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#0A74DA" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                </div>
                <h3 style="color: #1F2937; font-size: 1.25rem; font-weight: 600; margin-bottom: 8px;"><?php echo esc_html($total_providers); ?> Providers in <?php echo esc_html($term_name); ?></h3>
                <p style="color: #6B7280; margin-bottom: 20px;">Map locations not available</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php else : ?>
<?php
    // ============================================
    // ALL CITIES PAGE - Show list of cities
    // ============================================
?>

<!-- Hero Section -->
<section style="background: linear-gradient(135deg, #0A74DA 0%, #1e40af 50%, #0A74DA 100%); padding: 80px 0 100px; color: white; position: relative; overflow: hidden;">
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
                <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" style="color: rgba(255,255,255,0.7); text-decoration: none;">Providers</a>
                <span style="color: rgba(255,255,255,0.5); margin: 0 8px;">›</span>
                <span style="color: white;">Locations</span>
            </nav>
            
            <h1 style="color: white; font-size: clamp(2rem, 5vw, 3rem); margin-bottom: 1rem; line-height: 1.2; font-weight: 700;">Browse by Location</h1>
            <p style="color: rgba(255,255,255,0.9); font-size: clamp(1rem, 2.5vw, 1.25rem); line-height: 1.7;">Find wellness professionals in the Triangle area.</p>
        </div>
    </div>
    
    <div style="position: absolute; bottom: -1px; left: 0; right: 0;">
        <svg viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" style="width: 100%; height: 80px; display: block;">
            <path d="M0 120L60 110C120 100 240 80 360 70C480 60 600 60 720 65C840 70 960 80 1080 85C1200 90 1320 90 1380 90L1440 90V120H1380C1320 120 1200 120 1080 120C960 120 840 120 720 120C600 120 480 120 360 120C240 120 120 120 60 120H0Z" fill="white"/>
        </svg>
    </div>
</section>

<!-- Cities Grid -->
<section style="padding: 60px 0; background: white;">
    <div class="container">
        <?php
        $cities = get_terms(array(
            'taxonomy'   => 'myhh_city',
            'hide_empty' => true,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ));
        
        if ($cities && !is_wp_error($cities)) : ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
            <?php foreach ($cities as $city) : ?>
            <a href="<?php echo esc_url(get_term_link($city)); ?>" style="display: flex; align-items: center; gap: 16px; padding: 20px; background: white; border: 1px solid #E5E7EB; border-radius: 12px; text-decoration: none;"
               onmouseover="this.style.borderColor='#0A74DA';this.style.boxShadow='0 4px 12px rgba(10,116,218,0.1)'"
               onmouseout="this.style.borderColor='#E5E7EB';this.style.boxShadow='none'">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #EEF2FF 0%, #DBEAFE 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0A74DA" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                </div>
                <div>
                    <div style="font-size: 1.125rem; font-weight: 600; color: #1F2937;"><?php echo esc_html($city->name); ?></div>
                    <div style="color: #6B7280; font-size: 0.875rem;"><?php echo esc_html($city->count); ?> Providers</div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Browse by Specialty -->
<section style="padding: 60px 0; background: #F8FAFC;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 40px;">
            <h2 style="font-size: clamp(1.5rem, 3vw, 2rem); font-weight: 700; color: #1F2937;">Browse by Specialty</h2>
            <p style="color: #6B7280;">Find providers by their area of expertise.</p>
        </div>
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
            <?php
            $specialties = get_terms(array(
                'taxonomy'   => 'myhh_specialty',
                'hide_empty' => true,
                'parent'     => 0,
                'number'     => 10,
            ));
            if ($specialties && !is_wp_error($specialties)) :
                foreach ($specialties as $spec) :
            ?>
                <a href="<?php echo esc_url(get_term_link($spec)); ?>" style="padding: 10px 18px; background: white; border: 1px solid #E5E7EB; border-radius: 20px; text-decoration: none;"
                   onmouseover="this.style.borderColor='#0A74DA';this.style.background='#EEF2FF'"
                   onmouseout="this.style.borderColor='#E5E7EB';this.style.background='white'">
                    <span style="color: #374151; font-weight: 500;"><?php echo esc_html($spec->name); ?> (<?php echo esc_html($spec->count); ?>)</span>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>

<?php endif; ?>

<!-- CTA Section -->
<section style="padding: 80px 0; background: linear-gradient(135deg, #0A74DA 0%, #1e40af 100%); color: white;">
    <div class="container" style="text-align: center;">
        <h2 style="font-size: clamp(1.5rem, 4vw, 2.25rem); font-weight: 700; margin-bottom: 16px;">Not Sure What You Need?</h2>
        <p style="font-size: 1.1rem; opacity: 0.9; margin-bottom: 32px; max-width: 500px; margin-left: auto; margin-right: auto;">Our free matching service can help you find the right provider.</p>
        <a href="<?php echo esc_url(home_url('/get-matched/')); ?>" style="display: inline-flex; align-items: center; gap: 10px; background: white; color: #0A74DA; padding: 16px 32px; border-radius: 12px; font-weight: 700; text-decoration: none;">Get Matched Free</a>
    </div>
</section>

<?php get_footer(); ?>
