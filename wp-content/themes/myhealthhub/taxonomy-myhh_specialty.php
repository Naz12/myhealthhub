<?php
/**
 * The template for displaying myhh_specialty taxonomy
 * Handles both archive (/specialty/) and single term (/specialty/anxiety/)
 *
 * @package MyHealthHub
 */

get_header();

// Check if we're viewing a specific term or all specialties
$term = get_queried_object();
$is_term_page = $term && isset($term->slug) && $term->slug !== '' && !empty($term->slug);

if ($is_term_page) :
    // ============================================
    // SINGLE TERM PAGE - Show providers in this specialty
    // ============================================
    
    $term_name = $term->name;
    $term_description = $term->description;
    $term_slug = $term->slug;
    
    // Query providers in this specialty (including child terms)
    $paged = max(1, get_query_var('paged'));
    
    // Get all child term IDs to include in query
    $child_terms = get_terms(array(
        'taxonomy'   => 'myhh_specialty',
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
                'taxonomy' => 'myhh_specialty',
                'field'    => 'term_id',
                'terms'    => $term_ids,
                'include_children' => true,
            ),
        ),
    );
    $providers_query = new WP_Query($args);
    $total_providers = $providers_query->found_posts;
    
    // Get child specialties
    $children = get_terms(array(
        'taxonomy'   => 'myhh_specialty',
        'hide_empty' => true,
        'parent'     => $term->term_id,
    ));
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
                <a href="<?php echo esc_url(home_url('/specialty/')); ?>" style="color: rgba(255,255,255,0.7); text-decoration: none;">Specialties</a>
                <span style="color: rgba(255,255,255,0.5); margin: 0 8px;">›</span>
                <span style="color: white;"><?php echo esc_html($term_name); ?></span>
            </nav>
            
            <h1 style="color: white; font-size: clamp(2rem, 5vw, 3rem); margin-bottom: 1rem; line-height: 1.2; font-weight: 700;">
                <?php echo esc_html($term_name); ?> Specialists
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

<!-- Sub-specialties -->
<?php if ($children && !is_wp_error($children) && count($children) > 0) : ?>
<section style="padding: 40px 0; background: white; border-bottom: 1px solid #E5E7EB;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 24px;">
            <h2 style="font-size: 1.125rem; font-weight: 600; color: #1F2937;">Sub-specialties in <?php echo esc_html($term_name); ?></h2>
        </div>
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
            <a href="<?php echo esc_url(get_term_link($term)); ?>" style="padding: 8px 16px; background: #0A74DA; color: white; border-radius: 20px; font-size: 0.875rem; text-decoration: none; font-weight: 500;">All <?php echo esc_html($term_name); ?></a>
            <?php foreach ($children as $child) : ?>
                <a href="<?php echo esc_url(get_term_link($child)); ?>" style="padding: 8px 16px; background: #F3F4F6; color: #374151; border-radius: 20px; font-size: 0.875rem; text-decoration: none;">
                    <?php echo esc_html($child->name); ?> (<?php echo esc_html($child->count); ?>)
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

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
                $thumbnail = get_the_post_thumbnail_url($post_id, 'medium');
                $provider_specialties = get_the_terms($post_id, 'myhh_specialty');
                $provider_cities = get_the_terms($post_id, 'myhh_city');
                
                $specialty_names = array();
                if ($provider_specialties && !is_wp_error($provider_specialties)) {
                    $specialty_names = wp_list_pluck($provider_specialties, 'name');
                }
                
                $city_name_display = '';
                if ($provider_cities && !is_wp_error($provider_cities)) {
                    $city_name_raw = $provider_cities[0]->name;
                    $city_name_display = (strtolower($city_name_raw) !== 'unknown') ? $city_name_raw : '';
                }
                
                // Fallback for city
                if (empty($city_name_display)) {
                    $gd_city = function_exists('geodir_get_post_meta') ? geodir_get_post_meta($post_id, 'city', true) : '';
                    $city_meta = get_post_meta($post_id, 'city', true);
                    $city_value = !empty($gd_city) ? $gd_city : $city_meta;
                    
                    if ($city_value && strtolower($city_value) !== 'unknown') {
                        if (is_numeric($city_value)) {
                            $city_term_fallback = get_term(intval($city_value), 'myhh_city');
                            if ($city_term_fallback && !is_wp_error($city_term_fallback)) {
                                $fb_name = $city_term_fallback->name;
                                $city_name_display = (strtolower($fb_name) !== 'unknown') ? $fb_name : '';
                            }
                        } else {
                            $city_name_display = $city_value;
                        }
                    }
                }
                
                $rating = function_exists('myhh_get_listing_rating') ? myhh_get_listing_rating($post_id) : null;
                ?>
                
                <article style="background: #fff; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease; border: 1px solid #E5E7EB;">
                    <a href="<?php echo esc_url(get_permalink($post_id)); ?>" style="text-decoration: none; color: inherit; display: block;">
                        <?php if ($thumbnail) : ?>
                            <div style="height: 200px; overflow: hidden; position: relative;">
                                <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <div style="position: absolute; top: 12px; right: 12px; background: <?php echo $is_claimed ? 'rgba(5, 150, 105, 0.95)' : 'rgba(107, 114, 128, 0.9)'; ?>; color: #fff; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
                                    <?php echo $is_claimed ? 'Claimed' : 'Listed'; ?>
                                </div>
                            </div>
                        <?php else : ?>
                            <div style="height: 160px; background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%); display: flex; align-items: center; justify-content: center; position: relative;">
                                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#A5B4FC" stroke-width="1.5">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                                <div style="position: absolute; top: 12px; right: 12px; background: <?php echo $is_claimed ? 'rgba(5, 150, 105, 0.95)' : 'rgba(107, 114, 128, 0.9)'; ?>; color: #fff; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;"><?php echo $is_claimed ? 'Claimed' : 'Listed'; ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <div style="padding: 20px;">
                            <h3 style="margin: 0 0 4px; font-size: 1.125rem; color: #1F2937; font-weight: 700;"><?php echo esc_html(get_the_title()); ?></h3>
                            
                            <?php if ($credentials) : ?>
                                <p style="margin: 0 0 8px; color: #0A74DA; font-size: 0.875rem; font-weight: 600;"><?php echo esc_html($credentials); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($rating) : ?>
                                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                                    <span style="color: #D97706; font-size: 0.875rem; font-weight: 600;"><?php echo str_repeat('★', round($rating['average'])); ?><?php echo str_repeat('☆', 5 - round($rating['average'])); ?></span>
                                    <span style="color: #6B7280; font-size: 0.75rem;"><?php echo esc_html($rating['average']); ?> (<?php echo esc_html($rating['count']); ?>)</span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($session_type && strtolower($session_type) !== 'unknown') : ?>
                                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: <?php echo $session_type === 'Virtual' ? '#EEFDF3' : ($session_type === 'Hybrid' ? '#FEF3C7' : '#EEF2FF'); ?>; color: <?php echo $session_type === 'Virtual' ? '#059669' : ($session_type === 'Hybrid' ? '#D97706' : '#4F46E5'); ?>; font-size: 0.75rem; font-weight: 500; border-radius: 6px; margin-bottom: 10px;">
                                    <?php echo esc_html($session_type); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($short_bio) : ?>
                                <p style="color: #6B7280; font-size: 0.875rem; line-height: 1.5; margin: 0 0 12px;">
                                    <?php echo esc_html(wp_trim_words($short_bio, 20)); ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($specialty_names)) : ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px;">
                                    <?php foreach (array_slice($specialty_names, 0, 3) as $spec) : ?>
                                        <span style="padding: 3px 10px; background: #F8FAFC; color: #0A74DA; font-size: 0.75rem; font-weight: 500; border-radius: 20px; border: 1px solid #E5E7EB;"><?php echo esc_html($spec); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div style="display: flex; align-items: center; gap: 6px; font-size: 0.875rem; color: #6B7280;">
                                <?php if ($city_name_display) : ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                    <?php echo esc_html($city_name_display); ?>
                                <?php endif; ?>
                            </div>
                            
                            <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid #E5E7EB;">
                                <span style="color: #0A74DA; font-weight: 600; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 6px;">
                                    View Profile
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M5 12h14M12 5l7 7-7 7"></path>
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </a>
                </article>
            <?php endwhile; ?>
        </div>
        <?php wp_reset_postdata(); ?>
        <?php else : ?>
        <div style="text-align: center; padding: 60px 20px;">
            <p style="color: #6B7280; margin-bottom: 24px;">No providers found in this specialty.</p>
            <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" style="display: inline-flex; align-items: center; gap: 8px; background: #0A74DA; color: white; padding: 14px 28px; border-radius: 12px; font-weight: 600; text-decoration: none;">Browse All Providers</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php else : ?>
<?php
    // ============================================
    // ALL SPECIALTIES PAGE - Show list of specialties
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
                <span style="color: white;">Specialties</span>
            </nav>
            
            <h1 style="color: white; font-size: clamp(2rem, 5vw, 3rem); margin-bottom: 1rem; line-height: 1.2; font-weight: 700;">Browse by Specialty</h1>
            <p style="color: rgba(255,255,255,0.9); font-size: clamp(1rem, 2.5vw, 1.25rem); line-height: 1.7;">Find wellness professionals who specialize in what you're going through.</p>
        </div>
    </div>
    
    <div style="position: absolute; bottom: -1px; left: 0; right: 0;">
        <svg viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" style="width: 100%; height: 80px; display: block;">
            <path d="M0 120L60 110C120 100 240 80 360 70C480 60 600 60 720 65C840 70 960 80 1080 85C1200 90 1320 90 1380 90L1440 90V120H1380C1320 120 1200 120 1080 120C960 120 840 120 720 120C600 120 480 120 360 120C240 120 120 120 60 120H0Z" fill="white"/>
        </svg>
    </div>
</section>

<!-- Specialties Grid -->
<section style="padding: 60px 0; background: white;">
    <div class="container">
        <?php
        $specialties = get_terms(array(
            'taxonomy'   => 'myhh_specialty',
            'hide_empty' => true,
            'parent'     => 0,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ));
        
        if ($specialties && !is_wp_error($specialties)) : ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px;">
            <?php foreach ($specialties as $specialty) :
                $term_link = get_term_link($specialty);
                $children = get_terms(array(
                    'taxonomy'   => 'myhh_specialty',
                    'hide_empty' => true,
                    'parent'     => $specialty->term_id,
                ));
            ?>
            <a href="<?php echo esc_url($term_link); ?>" style="display: block; background: white; border: 1px solid #E5E7EB; border-radius: 16px; padding: 28px; text-decoration: none; transition: all 0.3s;"
               onmouseover="this.style.borderColor='#0A74DA';this.style.boxShadow='0 8px 24px rgba(10,116,218,0.15)'"
               onmouseout="this.style.borderColor='#E5E7EB';this.style.boxShadow='none'">
                <h2 style="font-size: 1.25rem; font-weight: 700; color: #1F2937; margin-bottom: 8px;"><?php echo esc_html($specialty->name); ?></h2>
                <?php if ($specialty->description) : ?>
                <p style="color: #6B7280; font-size: 0.875rem; line-height: 1.5; margin-bottom: 12px;"><?php echo esc_html(wp_trim_words($specialty->description, 15)); ?></p>
                <?php endif; ?>
                <?php if ($children && !is_wp_error($children)) : ?>
                <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px;">
                    <?php foreach (array_slice($children, 0, 3) as $child) : ?>
                        <span style="padding: 3px 10px; background: #F3F4F6; color: #6B7280; font-size: 0.75rem; border-radius: 12px;"><?php echo esc_html($child->name); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <span style="color: #0A74DA; font-weight: 600; font-size: 0.875rem;"><?php echo esc_html($specialty->count); ?> Providers</span>
                    <span style="color: #0A74DA; font-size: 0.875rem;">View →</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Browse by Location -->
<section style="padding: 60px 0; background: #F8FAFC;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 40px;">
            <h2 style="font-size: clamp(1.5rem, 3vw, 2rem); font-weight: 700; color: #1F2937; margin-bottom: 12px;">Browse by Location</h2>
            <p style="color: #6B7280;">Find wellness professionals in your area.</p>
        </div>
        
        <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; max-width: 800px; margin: 0 auto;">
            <?php
            $cities = get_terms(array(
                'taxonomy'   => 'myhh_city',
                'hide_empty' => true,
                'orderby'    => 'count',
                'order'      => 'DESC',
                'number'     => 8,
            ));
            
            if ($cities && !is_wp_error($cities)) :
                foreach ($cities as $city) :
            ?>
                <a href="<?php echo esc_url(get_term_link($city)); ?>" style="display: flex; align-items: center; gap: 8px; padding: 14px 24px; background: white; border: 1px solid #E5E7EB; border-radius: 12px; text-decoration: none;"
                   onmouseover="this.style.borderColor='#0A74DA';this.style.background='#EEF2FF'"
                   onmouseout="this.style.borderColor='#E5E7EB';this.style.background='white'">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0A74DA" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span style="color: #1F2937; font-weight: 600;"><?php echo esc_html($city->name); ?></span>
                    <span style="color: #6B7280; font-size: 0.8rem;">(<?php echo esc_html($city->count); ?>)</span>
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
        <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
            <a href="<?php echo esc_url(home_url('/get-matched/')); ?>" style="display: inline-flex; align-items: center; gap: 10px; background: white; color: #0A74DA; padding: 16px 32px; border-radius: 12px; font-weight: 700; text-decoration: none;">Get Matched Free</a>
            <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" style="display: inline-flex; align-items: center; gap: 10px; background: transparent; color: white; padding: 16px 32px; border-radius: 12px; font-weight: 600; text-decoration: none; border: 2px solid rgba(255,255,255,0.5);">Browse All Providers</a>
        </div>
    </div>
</section>

<?php get_footer(); ?>
