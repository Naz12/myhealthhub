<?php
/**
 * Template Name: SEO Directory (City + Specialty)
 * Description: Template for SEO-optimized city + specialty combination pages
 * URL Pattern: /{city}-{specialty}-therapists/
 *
 * @package MyHealthHub
 */

get_header();

// Get city and specialty from query vars
$city_slug = get_query_var('myhh_seo_city');
$specialty_slug = get_query_var('myhh_seo_specialty');

// Get term objects
$city_term = get_term_by('slug', $city_slug, 'myhh_city');
$specialty_term = get_term_by('slug', $specialty_slug, 'myhh_specialty');

// Validate terms exist
if (!$city_term || !$specialty_term) {
    wp_redirect(home_url());
    exit;
}

$city_name = $city_term->name;
$specialty_name = $specialty_term->name;

// Get counts for display
$city_provider_count = $city_term->count;
$specialty_provider_count = $specialty_term->count;

// Build page title
$page_title = $specialty_name . ' Therapists in ' . $city_name;
$page_description = 'Find trusted ' . strtolower($specialty_name) . ' therapists and wellness professionals in ' . $city_name . ', NC. Browse profiles, read testimonials, and connect with the right provider for your needs.';

// Query providers
$args = array(
    'post_type'      => 'gd_place',
    'posts_per_page' => 12,
    'paged'          => max(1, get_query_var('paged')),
    'tax_query'      => array(
        'relation' => 'AND',
        array(
            'taxonomy' => 'myhh_city',
            'field'    => 'slug',
            'terms'    => $city_slug,
        ),
        array(
            'taxonomy' => 'myhh_specialty',
            'field'    => 'slug',
            'terms'    => $specialty_slug,
        ),
    ),
    'orderby'        => 'date',
    'order'          => 'DESC',
);

$providers_query = new WP_Query($args);
$total_providers = $providers_query->found_posts;
?>

<!-- SEO Meta Tags (added via hook in header) -->
<?php add_action('wp_head', function() use ($page_title, $page_description, $city_name, $specialty_name) {
    echo '<meta name="description" content="' . esc_attr($page_description) . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($page_title . ' | MyHealthHub') . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($page_description) . '">' . "\n";
    echo '<link rel="canonical" href="' . esc_url(myhh_get_seo_url($GLOBALS['city_slug'] ?? '', $GLOBALS['specialty_slug'] ?? '')) . '">' . "\n";
}, 1); ?>

<!-- Hero Section -->
<section class="seo-hero" style="background: linear-gradient(135deg, #0A74DA 0%, #1e40af 50%, #0A74DA 100%); padding: 80px 0 100px; color: white; position: relative; overflow: hidden;">
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
            <!-- Breadcrumb -->
            <nav style="margin-bottom: 20px; font-size: 0.875rem;">
                <a href="<?php echo esc_url(home_url()); ?>" style="color: rgba(255,255,255,0.7); text-decoration: none;">Home</a>
                <span style="color: rgba(255,255,255,0.5); margin: 0 8px;">›</span>
                <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" style="color: rgba(255,255,255,0.7); text-decoration: none;">Providers</a>
                <span style="color: rgba(255,255,255,0.5); margin: 0 8px;">›</span>
                <span style="color: white;"><?php echo esc_html($city_name); ?></span>
                <span style="color: rgba(255,255,255,0.5); margin: 0 8px;">›</span>
                <span style="color: white;"><?php echo esc_html($specialty_name); ?></span>
            </nav>
            
            <h1 style="color: white; font-size: clamp(1.75rem, 5vw, 3rem); margin-bottom: 1rem; line-height: 1.2; font-weight: 700;">
                <?php echo esc_html($page_title); ?>
            </h1>
            <p style="color: rgba(255,255,255,0.9); font-size: clamp(1rem, 2.5vw, 1.25rem); margin-bottom: 2rem; line-height: 1.7;">
                <?php echo esc_html($page_description); ?>
            </p>
            
            <?php if ($total_providers > 0) : ?>
            <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); padding: 10px 20px; border-radius: 50px; font-size: 0.9375rem;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span><?php echo esc_html($total_providers); ?> <?php echo $total_providers === 1 ? 'Provider' : 'Providers'; ?> Found</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Wave Divider -->
    <div style="position: absolute; bottom: -1px; left: 0; right: 0;">
        <svg viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" style="width: 100%; height: 80px; display: block;">
            <path d="M0 120L60 110C120 100 240 80 360 70C480 60 600 60 720 65C840 70 960 80 1080 85C1200 90 1320 90 1380 90L1440 90V120H1380C1320 120 1200 120 1080 120C960 120 840 120 720 120C600 120 480 120 360 120C240 120 120 120 60 120H0Z" fill="white"/>
        </svg>
    </div>
</section>

<!-- Main Content -->
<section style="padding: 60px 0; background: white;">
    <div class="container">
        
        <?php if ($providers_query->have_posts()) : ?>
        
        <!-- Results Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: #1F2937; margin: 0;">
                    <?php echo esc_html($total_providers); ?> <?php echo $total_providers === 1 ? 'Provider' : 'Providers'; ?> in <?php echo esc_html($city_name); ?>
                </h2>
                <p style="color: #6B7280; font-size: 0.875rem; margin: 4px 0 0;">
                    Specializing in <?php echo esc_html(strtolower($specialty_name)); ?>
                </p>
            </div>
            <div style="display: flex; gap: 12px;">
                <a href="<?php echo esc_url(get_term_link($specialty_term)); ?>" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 16px; background: #F8FAFC; border: 1px solid #E5E7EB; border-radius: 8px; color: #374151; font-size: 0.875rem; text-decoration: none;" onmouseover="this.style.borderColor='#0A74DA'" onmouseout="this.style.borderColor='#E5E7EB'">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    All <?php echo esc_html($specialty_name); ?> Providers
                </a>
                <a href="<?php echo esc_url(get_term_link($city_term)); ?>" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 16px; background: #F8FAFC; border: 1px solid #E5E7EB; border-radius: 8px; color: #374151; font-size: 0.875rem; text-decoration: none;" onmouseover="this.style.borderColor='#0A74DA'" onmouseout="this.style.borderColor='#E5E7EB'">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    All <?php echo esc_html($city_name); ?> Providers
                </a>
            </div>
        </div>
        
        <!-- Providers Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 28px; margin-bottom: 48px;">
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
                    $city_name_display = $provider_cities[0]->name;
                }
                
                // Fallback for city
                if (empty($city_name_display)) {
                    $gd_city = function_exists('geodir_get_post_meta') ? geodir_get_post_meta($post_id, 'city', true) : '';
                    $city_meta = get_post_meta($post_id, 'city', true);
                    $city_value = !empty($gd_city) ? $gd_city : $city_meta;
                    
                    if ($city_value) {
                        if (is_numeric($city_value)) {
                            $city_term_fallback = get_term(intval($city_value), 'myhh_city');
                            if ($city_term_fallback && !is_wp_error($city_term_fallback)) {
                                $city_name_display = $city_term_fallback->name;
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
                            
                            <?php if ($session_type) : ?>
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
        
        <!-- Pagination -->
        <?php if ($providers_query->max_num_pages > 1) : ?>
        <div style="display: flex; justify-content: center; margin-top: 48px;">
            <?php
            echo paginate_links(array(
                'total'     => $providers_query->max_num_pages,
                'current'   => max(1, get_query_var('paged')),
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
        
        <!-- No Providers Found -->
        <div style="text-align: center; padding: 60px 20px;">
            <div style="width: 80px; height: 80px; background: #F3F4F6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                <svg width="40" height="40" fill="none" stroke="#9CA3AF" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <h2 style="font-size: 1.5rem; color: #1F2937; margin-bottom: 12px;">No Providers Found</h2>
            <p style="color: #6B7280; margin-bottom: 32px; max-width: 400px; margin-left: auto; margin-right: auto;">
                We don't have any <?php echo esc_html(strtolower($specialty_name)); ?> providers listed in <?php echo esc_html($city_name); ?> yet. Try expanding your search or check nearby areas.
            </p>
            <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo esc_url(myhh_get_directory_url() . '?specialty=' . $specialty_slug); ?>" style="display: inline-flex; align-items: center; gap: 8px; background: #0A74DA; color: white; padding: 14px 28px; border-radius: 12px; font-weight: 600; text-decoration: none;">
                    View All <?php echo esc_html($specialty_name); ?> Providers
                </a>
                <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" style="display: inline-flex; align-items: center; gap: 8px; background: #F3F4F6; color: #1F2937; padding: 14px 28px; border-radius: 12px; font-weight: 600; text-decoration: none;">
                    Browse All Providers
                </a>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</section>

<!-- Related Areas Section -->
<section style="padding: 60px 0; background: #F8FAFC;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 40px;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1F2937; margin-bottom: 8px;">
                <?php echo esc_html($specialty_name); ?> in Nearby Areas
            </h2>
            <p style="color: #6B7280;">Explore <?php echo esc_html(strtolower($specialty_name)); ?> providers in other Triangle area cities.</p>
        </div>
        
        <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; max-width: 800px; margin: 0 auto;">
            <?php
            $other_cities = get_terms(array(
                'taxonomy'   => 'myhh_city',
                'hide_empty' => true,
                'exclude'    => $city_term->term_id,
                'number'     => 6,
            ));
            
            if ($other_cities && !is_wp_error($other_cities)) :
                foreach ($other_cities as $other_city) :
            ?>
                <a href="<?php echo esc_url(myhh_get_seo_url($other_city->slug, $specialty_slug)); ?>" 
                   style="display: flex; align-items: center; gap: 8px; padding: 14px 24px; background: white; border: 1px solid #E5E7EB; border-radius: 12px; text-decoration: none; transition: all 0.2s;"
                   onmouseover="this.style.borderColor='#0A74DA';this.style.background='#EEF2FF'"
                   onmouseout="this.style.borderColor='#E5E7EB';this.style.background='white'">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0A74DA" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span style="color: #1F2937; font-weight: 600; font-size: 0.9375rem;"><?php echo esc_html($other_city->name); ?></span>
                </a>
            <?php
                endforeach;
            endif;
            ?>
        </div>
    </div>
</section>

<!-- Related Specialties Section -->
<section style="padding: 60px 0; background: white;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 40px;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1F2937; margin-bottom: 8px;">
                Other Specialties in <?php echo esc_html($city_name); ?>
            </h2>
            <p style="color: #6B7280;">Browse providers offering different services in your area.</p>
        </div>
        
        <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; max-width: 800px; margin: 0 auto;">
            <?php
            $other_specialties = get_terms(array(
                'taxonomy'   => 'myhh_specialty',
                'hide_empty' => true,
                'exclude'    => $specialty_term->term_id,
                'number'     => 8,
                'parent'     => 0, // Only top-level specialties
            ));
            
            if ($other_specialties && !is_wp_error($other_specialties)) :
                foreach ($other_specialties as $other_spec) :
            ?>
                <a href="<?php echo esc_url(myhh_get_seo_url($city_slug, $other_spec->slug)); ?>" 
                   style="display: flex; align-items: center; gap: 8px; padding: 12px 20px; background: #F8FAFC; border: 1px solid #E5E7EB; border-radius: 10px; text-decoration: none; transition: all 0.2s;"
                   onmouseover="this.style.borderColor='#0A74DA';this.style.background='#EEF2FF'"
                   onmouseout="this.style.borderColor='#E5E7EB';this.style.background='#F8FAFC'">
                    <span style="color: #1F2937; font-weight: 500; font-size: 0.875rem;"><?php echo esc_html($other_spec->name); ?></span>
                </a>
            <?php
                endforeach;
            endif;
            ?>
        </div>
    </div>
</section>

<!-- Info Section about Specialty -->
<?php if ($specialty_term->description || $total_providers > 0) : ?>
<section style="padding: 60px 0; background: #F8FAFC;">
    <div class="container">
        <div style="max-width: 800px; margin: 0 auto;">
            <h2 style="font-size: 1.75rem; font-weight: 700; color: #1F2937; margin-bottom: 20px; text-align: center;">
                About <?php echo esc_html($specialty_name); ?> in <?php echo esc_html($city_name); ?>
            </h2>
            
            <?php if ($specialty_term->description) : ?>
            <div style="background: white; border-radius: 16px; padding: 32px; margin-bottom: 24px; border: 1px solid #E5E7EB;">
                <p style="color: #4B5563; font-size: 1rem; line-height: 1.8; margin: 0;">
                    <?php echo esc_html($specialty_term->description); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div style="background: white; border-radius: 12px; padding: 24px; text-align: center; border: 1px solid #E5E7EB;">
                    <div style="width: 48px; height: 48px; background: #EEF2FF; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                        <svg width="24" height="24" fill="none" stroke="#0A74DA" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #1F2937;"><?php echo esc_html($total_providers); ?></div>
                    <div style="color: #6B7280; font-size: 0.875rem;"><?php echo $total_providers === 1 ? 'Provider' : 'Providers'; ?> Listed</div>
                </div>
                
                <div style="background: white; border-radius: 12px; padding: 24px; text-align: center; border: 1px solid #E5E7EB;">
                    <div style="width: 48px; height: 48px; background: #FEF3C7; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                        <svg width="24" height="24" fill="none" stroke="#D97706" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #1F2937;"><?php echo esc_html($city_name); ?></div>
                    <div style="color: #6B7280; font-size: 0.875rem;">Triangle Area, NC</div>
                </div>
                
                <div style="background: white; border-radius: 12px; padding: 24px; text-align: center; border: 1px solid #E5E7EB;">
                    <div style="width: 48px; height: 48px; background: #DCFCE7; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                        <svg width="24" height="24" fill="none" stroke="#16A34A" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #1F2937;">Claimed</div>
                    <div style="color: #6B7280; font-size: 0.875rem;">Provider Profiles</div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- FAQ Section for SEO -->
<section style="padding: 60px 0; background: white;">
    <div class="container">
        <div style="max-width: 800px; margin: 0 auto;">
            <h2 style="font-size: 1.75rem; font-weight: 700; color: #1F2937; margin-bottom: 32px; text-align: center;">
                Frequently Asked Questions
            </h2>
            
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div style="background: #F8FAFC; border-radius: 12px; padding: 24px; border: 1px solid #E5E7EB;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #1F2937; margin: 0 0 8px;">
                        How do I find a <?php echo esc_html(strtolower($specialty_name)); ?> therapist in <?php echo esc_html($city_name); ?>?
                    </h3>
                    <p style="color: #6B7280; font-size: 0.9375rem; line-height: 1.7; margin: 0;">
                        Browse the provider listings above to find <?php echo esc_html(strtolower($specialty_name)); ?> therapists in <?php echo esc_html($city_name); ?>. Each profile includes credentials, specialties, session types (in-person or virtual), and testimonials from clients.
                    </p>
                </div>
                
                <div style="background: #F8FAFC; border-radius: 12px; padding: 24px; border: 1px solid #E5E7EB;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #1F2937; margin: 0 0 8px;">
                        What should I look for in a <?php echo esc_html(strtolower($specialty_name)); ?> provider?
                    </h3>
                    <p style="color: #6B7280; font-size: 0.9375rem; line-height: 1.7; margin: 0;">
                        Look for licensed professionals with experience in <?php echo esc_html(strtolower($specialty_name)); ?>, appropriate credentials (LCSW, LPC, LMFT, etc.), and a approach that resonates with your needs. Consider whether you prefer in-person sessions in <?php echo esc_html($city_name); ?> or virtual therapy.
                    </p>
                </div>
                
                <div style="background: #F8FAFC; border-radius: 12px; padding: 24px; border: 1px solid #E5E7EB;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #1F2937; margin: 0 0 8px;">
                        Are there virtual <?php echo esc_html(strtolower($specialty_name)); ?> therapy options in <?php echo esc_html($city_name); ?>?
                    </h3>
                    <p style="color: #6B7280; font-size: 0.9375rem; line-height: 1.7; margin: 0;">
                        Yes, many providers offer virtual sessions. Look for the "Virtual" or "Hybrid" label on provider profiles. Virtual therapy allows you to connect with <?php echo esc_html(strtolower($specialty_name)); ?> specialists from the comfort of your home in <?php echo esc_html($city_name); ?>.
                    </p>
                </div>
                
                <div style="background: #F8FAFC; border-radius: 12px; padding: 24px; border: 1px solid #E5E7EB;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #1F2937; margin: 0 0 8px;">
                        How much does <?php echo esc_html(strtolower($specialty_name)); ?> therapy cost in <?php echo esc_html($city_name); ?>?
                    </h3>
                    <p style="color: #6B7280; font-size: 0.9375rem; line-height: 1.7; margin: 0;">
                        Costs vary by provider, session type, and insurance coverage. Many providers offer sliding scale fees. Contact individual providers through their profiles to discuss fees and insurance options.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section style="padding: 80px 0; background: linear-gradient(135deg, #0A74DA 0%, #1e40af 100%); color: white;">
    <div class="container" style="text-align: center;">
        <h2 style="font-size: clamp(1.5rem, 4vw, 2.25rem); font-weight: 700; margin-bottom: 16px;">
            Can't Find What You're Looking For?
        </h2>
        <p style="font-size: 1.1rem; opacity: 0.9; margin-bottom: 32px; max-width: 500px; margin-left: auto; margin-right: auto;">
            Our free matching service can help you find the perfect provider for your needs.
        </p>
        <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
            <a href="<?php echo esc_url(home_url('/get-matched/')); ?>" style="display: inline-flex; align-items: center; gap: 10px; background: white; color: #0A74DA; padding: 16px 32px; border-radius: 12px; font-weight: 700; font-size: 1rem; text-decoration: none;">
                Get Matched Free
            </a>
            <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" style="display: inline-flex; align-items: center; gap: 10px; background: transparent; color: white; padding: 16px 32px; border-radius: 12px; font-weight: 600; font-size: 1rem; text-decoration: none; border: 2px solid rgba(255,255,255,0.5);">
                Browse All Providers
            </a>
        </div>
    </div>
</section>

<!-- Structured Data for SEO -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "ItemList",
    "name": "<?php echo esc_attr($page_title); ?>",
    "description": "<?php echo esc_attr($page_description); ?>",
    "numberOfItems": <?php echo esc_attr($total_providers); ?>,
    "itemListElement": [
        <?php
        $providers_for_json = new WP_Query($args);
        $items = array();
        while ($providers_for_json->have_posts()) : $providers_for_json->the_post();
            $items[] = '
        {
            "@type": "ListItem",
            "position": ' . ($providers_for_json->current_post + 1) . ',
            "item": {
                "@type": "Person",
                "name": "' . esc_attr(get_the_title()) . '",
                "url": "' . esc_url(get_permalink()) . '",
                "jobTitle": "' . esc_attr(get_post_meta(get_the_ID(), 'provider_credentials', true)) . '",
                "address": {
                    "@type": "PostalAddress",
                    "addressLocality": "' . esc_attr($city_name) . '",
                    "addressRegion": "NC"
                }
            }
        }';
        endwhile;
        wp_reset_postdata();
        echo implode(',', $items);
        ?>
    ]
}
</script>

<!-- FAQ Schema for SEO -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": [
        {
            "@type": "Question",
            "name": "How do I find a <?php echo esc_attr(strtolower($specialty_name)); ?> therapist in <?php echo esc_attr($city_name); ?>?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Browse the provider listings on this page to find <?php echo esc_attr(strtolower($specialty_name)); ?> therapists in <?php echo esc_attr($city_name); ?>. Each profile includes credentials, specialties, session types, and testimonials from clients."
            }
        },
        {
            "@type": "Question",
            "name": "What should I look for in a <?php echo esc_attr(strtolower($specialty_name)); ?> provider?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Look for licensed professionals with experience in <?php echo esc_attr(strtolower($specialty_name)); ?>, appropriate credentials (LCSW, LPC, LMFT, etc.), and an approach that resonates with your needs. Consider whether you prefer in-person sessions or virtual therapy."
            }
        },
        {
            "@type": "Question",
            "name": "Are there virtual <?php echo esc_attr(strtolower($specialty_name)); ?> therapy options in <?php echo esc_attr($city_name); ?>?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Yes, many providers offer virtual sessions. Look for the Virtual or Hybrid label on provider profiles. Virtual therapy allows you to connect with <?php echo esc_attr(strtolower($specialty_name)); ?> specialists from the comfort of your home."
            }
        },
        {
            "@type": "Question",
            "name": "How much does <?php echo esc_attr(strtolower($specialty_name)); ?> therapy cost in <?php echo esc_attr($city_name); ?>?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Costs vary by provider, session type, and insurance coverage. Many providers offer sliding scale fees. Contact individual providers through their profiles to discuss fees and insurance options."
            }
        }
    ]
}
</script>

<?php get_footer(); ?>
