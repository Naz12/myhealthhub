<?php
/**
 * The template for displaying myhh_primary_category taxonomy
 * Handles both archive (/wellness-category/) and single term (/wellness-category/mental-health-therapy/)
 *
 * @package MyHealthHub
 * @version 1.0.0 - Milestone 3
 */

get_header();

// Check if we're viewing a specific term or all categories
$term = get_queried_object();
$is_term_page = $term && isset($term->slug) && $term->slug !== '' && !empty($term->slug);
?>

<!-- Responsive Styles -->
<style>
/* Taxonomy Archive Responsive Styles */
@media (max-width: 768px) {
    /* Hero Section */
    .tax-hero { padding: 60px 0 80px !important; }
    .tax-hero h1 { font-size: 1.75rem !important; }
    .tax-hero__subtitle { font-size: 1rem !important; }
    
    /* Filter Section */
    .tax-filter-form { flex-direction: column !important; }
    .tax-filter-form > div { width: 100% !important; }
    .tax-filter-form select { width: 100% !important; }
    
    /* Provider Grid */
    .tax-provider-grid {
        grid-template-columns: 1fr !important;
        gap: 20px !important;
    }
    
    /* Empty State */
    .tax-empty-state { padding: 60px 16px !important; }
    .tax-empty-state h2 { font-size: 1.5rem !important; }
    .tax-empty-state p { font-size: 1rem !important; }
    .tax-empty-state__buttons { flex-direction: column !important; }
    .tax-empty-state__buttons a { width: 100% !important; justify-content: center !important; }
    
    /* Category Cards Grid */
    .tax-category-grid {
        grid-template-columns: 1fr !important;
    }
}

@media (max-width: 480px) {
    .tax-hero { padding: 40px 0 60px !important; }
    .tax-hero h1 { font-size: 1.5rem !important; }
    .tax-empty-state { padding: 40px 12px !important; }
    .tax-empty-state h2 { font-size: 1.25rem !important; }
}
</style>

<?php
if ($is_term_page) :
    // ============================================
    // SINGLE TERM PAGE - Show providers in this category
    // ============================================
    
    $term_name = $term->name;
    $term_description = $term->description;
    $term_slug = $term->slug;
    
    // Query providers in this category
    $paged = max(1, get_query_var('paged'));
    
    $args = array(
        'post_type'      => 'gd_place',
        'posts_per_page' => 12,
        'paged'          => $paged,
        'tax_query'      => array(
            array(
                'taxonomy' => 'myhh_primary_category',
                'field'    => 'slug',
                'terms'    => $term_slug,
            ),
        ),
    );
    $providers_query = new WP_Query($args);
    $total_providers = $providers_query->found_posts;
    
    // Get specialties for filter dropdown
    $specialties = get_terms(array('taxonomy' => 'myhh_specialty', 'hide_empty' => true, 'parent' => 0));
    $cities = get_terms(array('taxonomy' => 'myhh_city', 'hide_empty' => true));
?>

<!-- Hero Section -->
<section class="tax-hero" style="background: linear-gradient(135deg, #0A74DA 0%, #1e40af 50%, #0A74DA 100%); padding: 80px 0 100px; color: white; position: relative; overflow: hidden;">
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
                <a href="<?php echo esc_url(home_url('/wellness-category/')); ?>" style="color: rgba(255,255,255,0.7); text-decoration: none;">Categories</a>
                <span style="color: rgba(255,255,255,0.5); margin: 0 8px;">›</span>
                <span style="color: white;"><?php echo esc_html($term_name); ?></span>
            </nav>
            
            <h1 style="color: white; font-size: clamp(2rem, 5vw, 3rem); margin-bottom: 1rem; line-height: 1.2; font-weight: 700;">
                <?php echo esc_html($term_name); ?>
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
            <?php else : ?>
            <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); padding: 10px 20px; border-radius: 50px; font-size: 0.9375rem;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Coming Soon</span>
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

<!-- Filter Section -->
<?php if ($total_providers > 0) : ?>
<section style="padding: 24px 0; background: white; border-bottom: 1px solid #E5E7EB;">
    <div class="container">
        <form method="get" action="" class="tax-filter-form" style="display: flex; gap: 16px; flex-wrap: wrap; align-items: center;">
            <!-- Specialty Filter -->
            <div>
                <label style="font-size: 0.75rem; font-weight: 600; color: #6B7280; text-transform: uppercase; display: block; margin-bottom: 4px;">Specialty</label>
                <select name="specialty" style="padding: 10px 16px; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 0.875rem;">
                    <option value="">All Specialties</option>
                    <?php if ($specialties && !is_wp_error($specialties)) : foreach ($specialties as $spec) : ?>
                        <option value="csv:<?php echo esc_attr($spec->slug); ?>" <?php selected(isset($_GET['specialty']) ? $_GET['specialty'] : '', 'csv:' . $spec->slug); ?>>
                            <?php echo esc_html($spec->name); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            
            <!-- City Filter -->
            <div>
                <label style="font-size: 0.75rem; font-weight: 600; color: #6B7280; text-transform: uppercase; display: block; margin-bottom: 4px;">Location</label>
                <select name="city" style="padding: 10px 16px; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 0.875rem;">
                    <option value="">All Areas</option>
                    <?php if ($cities && !is_wp_error($cities)) : foreach ($cities as $city) : ?>
                        <option value="<?php echo esc_attr($city->slug); ?>" <?php selected(isset($_GET['city']) ? $_GET['city'] : '', $city->slug); ?>>
                            <?php echo esc_html($city->name); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            
            <div style="margin-left: auto; padding-top: 20px;">
                <button type="submit" style="padding: 10px 20px; background: #0A74DA; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Filter</button>
                <a href="<?php echo esc_url(get_term_link($term)); ?>" style="padding: 10px 16px; color: #6B7280; text-decoration: none; font-size: 0.875rem;">Clear</a>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<!-- Providers Grid -->
<section style="padding: 60px 0; background: white;">
    <div class="container">
        <?php if ($providers_query->have_posts()) : ?>
        <div class="tax-provider-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 28px;">
            <?php while ($providers_query->have_posts()) : $providers_query->the_post(); ?>
                <?php
                $post_id = get_the_ID();
                $credentials = get_post_meta($post_id, 'provider_credentials', true);
                $session_type = get_post_meta($post_id, 'session_type', true);
                $short_bio = get_post_meta($post_id, 'short_bio', true);
                $verification_status = get_post_meta($post_id, 'verification_status', true);
                $thumbnail = get_the_post_thumbnail_url($post_id, 'medium');
                $provider_specialties = get_the_terms($post_id, 'myhh_specialty');
                $provider_cities = get_the_terms($post_id, 'myhh_city');
                
                // Prepare specialties
                $specialty_names = array();
                if ($provider_specialties && !is_wp_error($provider_specialties)) {
                    foreach ($provider_specialties as $spec) {
                        if ($spec->parent == 0) {
                            $specialty_names[] = $spec->name;
                        }
                    }
                }
                
                // Prepare city
                $city_name = '';
                if ($provider_cities && !is_wp_error($provider_cities)) {
                    $city_name_raw = $provider_cities[0]->name;
                    $city_name = (strtolower($city_name_raw) !== 'unknown') ? $city_name_raw : '';
                }
                
                // Generate initials
                $title = get_the_title();
                $initials = '';
                $name_parts = explode(' ', $title);
                foreach ($name_parts as $part) {
                    $initials .= strtoupper(substr($part, 0, 1));
                }
                if (strlen($initials) > 2) {
                    $initials = substr($initials, 0, 2);
                }
                ?>
                
                <div class="provider-card" style="background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s; border: 1px solid #E5E7EB;">
                    <a href="<?php echo esc_url(get_permalink()); ?>" style="text-decoration: none; color: inherit;">
                        <div style="height: 180px; background: linear-gradient(135deg, #EEF2FF 0%, #DBEAFE 100%); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                            <?php if ($thumbnail) : ?>
                                <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($title); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else : ?>
                                <span style="font-size: 3rem; font-weight: 700; color: #0A74DA; opacity: 0.6;"><?php echo esc_html($initials); ?></span>
                            <?php endif; ?>
                            
                            <?php if ($verification_status === 'Verified') : ?>
                                <span style="position: absolute; top: 12px; right: 12px; background: rgba(16, 185, 129, 0.95); color: #fff; font-size: 0.75rem; font-weight: 600; padding: 4px 10px; border-radius: 20px; display: flex; align-items: center; gap: 4px;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <path d="M20 6L9 17l-5-5"></path>
                                    </svg>
                                    Verified
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div style="padding: 20px;">
                            <h3 style="font-size: 1.125rem; font-weight: 700; margin: 0 0 4px; color: #1F2937; line-height: 1.3;">
                                <?php echo esc_html($title); ?><?php if ($credentials) : ?><span style="font-weight: 400; color: #6B7280; font-size: 0.9375rem;"><?php echo ', ' . esc_html($credentials); ?></span><?php endif; ?>
                            </h3>
                            
                            <?php if ($city_name) : ?>
                                <div style="display: flex; align-items: center; gap: 4px; color: #6B7280; font-size: 0.875rem; margin-bottom: 8px;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                    <?php echo esc_html($city_name); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($specialty_names)) : ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px;">
                                    <?php foreach (array_slice($specialty_names, 0, 3) as $specialty) : ?>
                                        <span style="background: #EEF2FF; color: #3730A3; font-size: 0.75rem; font-weight: 500; padding: 4px 10px; border-radius: 6px;"><?php echo esc_html($specialty); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($short_bio) : ?>
                                <p style="color: #6B7280; font-size: 0.875rem; line-height: 1.5; margin: 0 0 12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo esc_html(wp_trim_words($short_bio, 20)); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 0.8125rem; font-weight: 600; color: #0A74DA;">View Profile →</span>
                                <?php if ($session_type && strtolower($session_type) !== 'unknown') : ?>
                                    <?php 
                                    $session_colors = array(
                                        'In-Person' => 'background: #D1FAE5; color: #059669;',
                                        'Virtual' => 'background: #DBEAFE; color: #2563EB;',
                                        'Hybrid' => 'background: #FEF3C7; color: #D97706;',
                                    );
                                    $session_style = isset($session_colors[$session_type]) ? $session_colors[$session_type] : 'background: #E5E7EB; color: #4B5563;';
                                    ?>
                                    <span style="<?php echo $session_style; ?> font-size: 0.6875rem; font-weight: 600; padding: 4px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.025em;">
                                        <?php echo esc_html($session_type); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($providers_query->max_num_pages > 1) : ?>
        <div style="margin-top: 48px; text-align: center;">
            <?php echo paginate_links(array(
                'total' => $providers_query->max_num_pages,
                'prev_text' => '← Previous',
                'next_text' => 'Next →',
            )); ?>
        </div>
        <?php endif; ?>
        
        <?php wp_reset_postdata(); ?>
        
        <?php else : ?>
        
        <!-- Empty State - Category Coming Soon -->
        <div class="tax-empty-state" style="padding: 80px 20px;">
            <div style="max-width: 600px; margin: 0 auto; text-align: center;">
                <!-- Illustration -->
                <div style="width: 120px; height: 120px; margin: 0 auto 32px; background: linear-gradient(135deg, #EEF2FF 0%, #DBEAFE 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <svg width="56" height="56" fill="none" stroke="#0A74DA" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                
                <!-- Category Name -->
                <h2 style="font-size: 1.75rem; font-weight: 700; color: #1F2937; margin-bottom: 16px;">
                    <?php echo esc_html($term_name); ?> — Coming Soon
                </h2>
                
                <!-- Description -->
                <p style="color: #4B5563; font-size: 1.125rem; line-height: 1.7; margin-bottom: 12px;">
                    We're actively expanding our network in this wellness category.
                </p>
                <p style="color: #6B7280; font-size: 1rem; line-height: 1.7; margin-bottom: 40px;">
                    If you're a professional in <?php echo esc_html($term_name); ?>, we'd love to have you join our growing community.
                </p>
                
                <!-- CTA Buttons -->
                <div class="tax-empty-state__buttons" style="display: flex; flex-wrap: wrap; gap: 16px; justify-content: center; margin-bottom: 48px;">
                    <a href="<?php echo esc_url(home_url('/add-your-practice/')); ?>" style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #0A74DA 0%, #1D4ED8 100%); color: white; padding: 16px 32px; border-radius: 12px; font-weight: 600; text-decoration: none; font-size: 1rem; box-shadow: 0 4px 14px rgba(10, 116, 218, 0.3); transition: all 0.3s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 20px rgba(10, 116, 218, 0.4)';" onmouseout="this.style.transform='none';this.style.boxShadow='0 4px 14px rgba(10, 116, 218, 0.3)';">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        List Your Practice
                    </a>
                    <a href="<?php echo esc_url(home_url('/listings/')); ?>" style="display: inline-flex; align-items: center; gap: 8px; background: #F3F4F6; color: #374151; padding: 16px 32px; border-radius: 12px; font-weight: 600; text-decoration: none; font-size: 1rem; transition: all 0.3s;" onmouseover="this.style.background='#E5E7EB';" onmouseout="this.style.background='#F3F4F6';">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Browse All Providers
                    </a>
                </div>
                
                <!-- Alternative: Explore Mental Health -->
                <div style="padding-top: 32px; border-top: 1px solid #E5E7EB;">
                    <p style="color: #9CA3AF; font-size: 0.875rem; margin-bottom: 12px;">
                        Looking for support now?
                    </p>
                    <a href="<?php echo esc_url(home_url('/wellness-category/mental-health-therapy/')); ?>" style="display: inline-flex; align-items: center; gap: 6px; color: #0A74DA; font-weight: 600; text-decoration: none; font-size: 0.9375rem;">
                        Explore Mental Health & Therapy
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php else : ?>
<!-- ============================================ -->
<!-- ALL CATEGORIES PAGE -->
<!-- ============================================ -->

<?php
$categories = get_terms(array(
    'taxonomy' => 'myhh_primary_category',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC',
));
?>

<!-- Hero Section -->
<section style="background: linear-gradient(135deg, #0A74DA 0%, #1e40af 50%, #0A74DA 100%); padding: 80px 0 100px; color: white; position: relative; overflow: hidden;">
    <div class="container" style="position: relative; z-index: 1;">
        <div style="max-width: 800px; margin: 0 auto; text-align: center;">
            <h1 style="color: white; font-size: clamp(2rem, 5vw, 3rem); margin-bottom: 1rem; line-height: 1.2; font-weight: 700;">
                Wellness Categories
            </h1>
            <p style="color: rgba(255,255,255,0.9); font-size: clamp(1rem, 2.5vw, 1.25rem); margin-bottom: 1.5rem; line-height: 1.7;">
                Explore our 12 wellness categories to find the right professionals for your health journey.
            </p>
        </div>
    </div>
</section>

<!-- Categories Grid -->
<section style="padding: 60px 0; background: white;">
    <div class="container">
        <div class="tax-category-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px;">
            <?php if ($categories && !is_wp_error($categories)) : foreach ($categories as $cat) : ?>
                <a href="<?php echo esc_url(get_term_link($cat)); ?>" style="display: block; padding: 32px; background: #F8FAFC; border-radius: 16px; text-decoration: none; transition: all 0.3s; border: 2px solid transparent;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 24px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='none';this.style.boxShadow='none';">
                    <h3 style="font-size: 1.125rem; font-weight: 600; color: #1F2937; margin-bottom: 8px;"><?php echo esc_html($cat->name); ?></h3>
                    <?php if ($cat->description) : ?>
                        <p style="color: #6B7280; font-size: 0.875rem; line-height: 1.5; margin-bottom: 12px;"><?php echo esc_html($cat->description); ?></p>
                    <?php endif; ?>
                    <div style="display: flex; align-items: center; gap: 4px; color: #0A74DA; font-weight: 600; font-size: 0.875rem;">
                        <span><?php echo $cat->count > 0 ? esc_html($cat->count) . ' providers' : 'Coming Soon'; ?></span>
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </div>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>

<?php endif; ?>

<?php get_footer(); ?>
