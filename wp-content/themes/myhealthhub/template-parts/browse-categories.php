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
    'primary-care-medical-services'        => array('color' => '#EFF6FF', 'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z', 'icon_color' => '#2563EB'),
    'mental-health-therapy'                => array('color' => '#EEF2FF', 'icon' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z', 'icon_color' => '#4F46E5'),
    'fitness-yoga-movement'                => array('color' => '#F0FDF4', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'icon_color' => '#059669'),
    'nutrition-dietary-support'            => array('color' => '#FEF3C7', 'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z', 'icon_color' => '#D97706'),
    'massage-spa-recovery'                 => array('color' => '#FCE7F3', 'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z', 'icon_color' => '#DB2777'),
    'sleep-rest-wellness'                  => array('color' => '#E0E7FF', 'icon' => 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z', 'icon_color' => '#4338CA'),
    'social-connections-support-groups'    => array('color' => '#DBEAFE', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', 'icon_color' => '#2563EB'),
    'financial-wellness-planning'          => array('color' => '#D1FAE5', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'icon_color' => '#059669'),
    'career-work-life-coaching'            => array('color' => '#FEF2F2', 'icon' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'icon_color' => '#DC2626'),
    'integrative-holistic-therapies'       => array('color' => '#FDF4FF', 'icon' => 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z', 'icon_color' => '#A855F7'),
    'meditation-mindfulness-spirituality'  => array('color' => '#F5F3FF', 'icon' => 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z', 'icon_color' => '#7C3AED'),
    'personal-growth-learning'             => array('color' => '#ECFDF5', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', 'icon_color' => '#059669'),
);
?>
<!-- Browse by Category (Milestone 3) -->
<section style="padding: 80px 0; background: #fff;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 56px;">
            <span style="display: inline-block; background: #EEF2FF; color: #0A74DA; padding: 6px 16px; border-radius: 20px; font-size: 0.875rem; font-weight: 600; margin-bottom: 16px;">Explore Wellness</span>
            <h2 style="font-size: clamp(1.5rem, 4vw, 2.5rem); font-weight: 700; margin-bottom: 16px; color: #1F2937;">Browse by Category</h2>
            <p style="font-size: 1.125rem; color: #6B7280;">Discover wellness services across 12 categories.</p>
        </div>
        
        <div class="category-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; max-width: 1200px; margin: 0 auto;">
            <?php
            if ($primary_categories && !is_wp_error($primary_categories)) :
                foreach ($primary_categories as $category) :
                    $style      = isset($category_styles[$category->slug]) ? $category_styles[$category->slug] : array('color' => '#F3F4F6', 'icon_color' => '#6B7280');
                    $bg_color   = $style['color'];
                    $icon_color = $style['icon_color'];
                    $icon_path  = isset($style['icon']) ? $style['icon'] : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
                    $count         = $category->count;
                    $display_count = $count > 0 ? esc_html($count) . ' providers' : 'Coming Soon';
            ?>
                <a href="<?php echo esc_url(get_term_link($category)); ?>" 
                   class="featured-provider-card"
                   style="display: flex; flex-direction: column; align-items: center; padding: 24px 16px; background: <?php echo $bg_color; ?>; border-radius: 16px; text-decoration: none; border: 2px solid transparent; text-align: center; min-height: 150px; justify-content: center;">
                    <svg width="32" height="32" fill="none" stroke="<?php echo $icon_color; ?>" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom: 12px; flex-shrink: 0;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo $icon_path; ?>"/>
                    </svg>
                    <span style="color: #1F2937; font-weight: 600; font-size: 0.875rem; line-height: 1.3; margin-bottom: 4px;"><?php echo esc_html($category->name); ?></span>
                    <span style="color: <?php echo $count > 0 ? '#6B7280' : '#9CA3AF'; ?>; font-size: 0.75rem;"><?php echo $display_count; ?></span>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>
