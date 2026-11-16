<?php
/**
 * Template Part: Browse Categories
 *
 * Renders the "Browse by Category" icon grid using the myhh_primary_category taxonomy.
 * Each card links to the term archive and shows provider count.
 *
 * Expected $args keys:
 *   - 'primary_categories' (array|WP_Error) — Result of get_terms() for myhh_primary_category.
 *                                             Falls back to a fresh query if not provided.
 *
 * @package MyHealthHub
 */

// Accept categories from $args or fall back to a direct query
$primary_categories = isset($args['primary_categories']) ? $args['primary_categories'] : get_terms(array(
    'taxonomy'   => 'myhh_primary_category',
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC',
));

// Category icons and colors keyed by term slug
$category_styles = array(
    'primary-care-medical-services'        => array('color' => '#EFF6FF', 'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z', 'icon_color' => '#2563EB', 'gradient' => 'linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%)'),
    'mental-health-therapy'                => array('color' => '#EEF2FF', 'icon' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z', 'icon_color' => '#4F46E5', 'gradient' => 'linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%)'),
    'fitness-yoga-movement'                => array('color' => '#F0FDF4', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'icon_color' => '#059669', 'gradient' => 'linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%)'),
    'nutrition-dietary-support'            => array('color' => '#FEF3C7', 'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z', 'icon_color' => '#D97706', 'gradient' => 'linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%)'),
    'massage-spa-recovery'                 => array('color' => '#FCE7F3', 'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z', 'icon_color' => '#DB2777', 'gradient' => 'linear-gradient(135deg, #FCE7F3 0%, #FBCFE8 100%)'),
    'sleep-rest-wellness'                  => array('color' => '#E0E7FF', 'icon' => 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z', 'icon_color' => '#4338CA', 'gradient' => 'linear-gradient(135deg, #E0E7FF 0%, #C7D2FE 100%)'),
    'social-connections-support-groups'    => array('color' => '#DBEAFE', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', 'icon_color' => '#2563EB', 'gradient' => 'linear-gradient(135deg, #DBEAFE 0%, #BAE6FD 100%)'),
    'financial-wellness-planning'          => array('color' => '#D1FAE5', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'icon_color' => '#059669', 'gradient' => 'linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%)'),
    'career-work-life-coaching'            => array('color' => '#FEF2F2', 'icon' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'icon_color' => '#DC2626', 'gradient' => 'linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%)'),
    'integrative-holistic-therapies'       => array('color' => '#FDF4FF', 'icon' => 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z', 'icon_color' => '#A855F7', 'gradient' => 'linear-gradient(135deg, #FDF4FF 0%, #FAE8FF 100%)'),
    'meditation-mindfulness-spirituality'  => array('color' => '#F5F3FF', 'icon' => 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z', 'icon_color' => '#7C3AED', 'gradient' => 'linear-gradient(135deg, #F5F3FF 0%, #EDE9FE 100%)'),
    'personal-growth-learning'             => array('color' => '#ECFDF5', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', 'icon_color' => '#059669', 'gradient' => 'linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%)'),
);
?>
<!-- Browse by Category (Explore Wellness) - MODERN REDESIGN -->
<section style="padding: 100px 0; background: linear-gradient(180deg, #ffffff 0%, #f9fafb 100%); position: relative; overflow: hidden;">
    <!-- Animated background shapes -->
    <div style="position: absolute; top: -50px; right: -50px; width: 300px; height: 300px; background: radial-gradient(circle, rgba(102, 126, 234, 0.08) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>
    <div style="position: absolute; bottom: -100px; left: -50px; width: 400px; height: 400px; background: radial-gradient(circle, rgba(245, 87, 108, 0.05) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>
    
    <div class="container" style="position: relative; z-index: 1;">
        <!-- Header Section -->
        <div style="text-align: center; margin-bottom: 80px;">
            <span style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 20px; border-radius: 50px; font-size: 0.875rem; font-weight: 700; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);">✨ Explore Wellness</span>
            <h2 style="font-size: clamp(2rem, 5vw, 3.5rem); font-weight: 800; margin-bottom: 16px; color: #1a1a2e; letter-spacing: -0.02em;">Browse by Category</h2>
            <p style="font-size: 1.125rem; color: #6B7280; max-width: 600px; margin: 0 auto; line-height: 1.7;">Discover wellness services across 12+ categories. Find the perfect professional for your unique needs.</p>
        </div>
        
        <!-- Category Grid -->
        <div class="category-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 24px; max-width: 1300px; margin: 0 auto;">
            <?php
            if ($primary_categories && !is_wp_error($primary_categories)) :
                foreach ($primary_categories as $category) :
                    $style      = isset($category_styles[$category->slug]) ? $category_styles[$category->slug] : array('color' => '#F3F4F6', 'icon_color' => '#6B7280', 'gradient' => 'linear-gradient(135deg, #F3F4F6 0%, #E5E7EB 100%)');
                    $gradient   = isset($style['gradient']) ? $style['gradient'] : 'linear-gradient(135deg, #F3F4F6 0%, #E5E7EB 100%)';
                    $icon_color = $style['icon_color'];
                    $icon_path  = isset($style['icon']) ? $style['icon'] : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
                    $count      = $category->count;
                    $display_count = $count > 0 ? esc_html($count) . ' providers' : 'Coming Soon';
            ?>
                <a href="<?php echo esc_url(get_term_link($category)); ?>" 
                   class="wellness-category-card scroll-scale-up"
                   style="display: flex; flex-direction: column; align-items: center; padding: 32px 20px; background: <?php echo $gradient; ?>; border-radius: 20px; text-decoration: none; border: 2px solid transparent; text-align: center; min-height: 200px; justify-content: center; transition: all 0.3s ease; position: relative; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);"
                   onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 12px 40px rgba(102, 126, 234, 0.15)'; this.style.borderColor='rgba(102, 126, 234, 0.3)';"
                   onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 20px rgba(0, 0, 0, 0.05)'; this.style.borderColor='transparent';">
                    
                    <!-- Background accent -->
                    <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: radial-gradient(circle, rgba(255, 255, 255, 0.4) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>
                    
                    <!-- Icon container with glow -->
                    <div style="position: relative; z-index: 2; margin-bottom: 16px;">
                        <div style="width: 60px; height: 60px; background: rgba(255, 255, 255, 0.7); border-radius: 16px; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08); transition: all 0.3s ease;">
                            <svg width="32" height="32" fill="none" stroke="<?php echo $icon_color; ?>" stroke-width="1.5" viewBox="0 0 24 24" style="filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));">
                                <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo $icon_path; ?>"/>
                            </svg>
                        </div>
                    </div>
                    
                    <!-- Text content -->
                    <span style="color: #1F2937; font-weight: 700; font-size: 0.95rem; line-height: 1.4; margin-bottom: 6px; position: relative; z-index: 2;"><?php echo esc_html($category->name); ?></span>
                    <span style="color: <?php echo $count > 0 ? '#6B7280' : '#9CA3AF'; ?>; font-size: 0.8rem; font-weight: 500; position: relative; z-index: 2;"><?php echo $display_count; ?></span>
                </a>
            <?php endforeach; endif; ?>
        </div>
        
        <!-- View All Specialties Button -->
        <div style="text-align: center; margin-top: 60px;">
            <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" 
               style="display: inline-flex; align-items: center; gap: 10px; padding: 14px 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 700; text-decoration: none; border-radius: 50px; box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3); transition: all 0.3s ease; border: 2px solid rgba(102, 126, 234, 0.3);"
               onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 12px 35px rgba(102, 126, 234, 0.4)';"
               onmouseout="this.style.transform='none'; this.style.boxShadow='0 8px 25px rgba(102, 126, 234, 0.3)';">
                View All Services
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </a>
        </div>
    </div>
</section>
