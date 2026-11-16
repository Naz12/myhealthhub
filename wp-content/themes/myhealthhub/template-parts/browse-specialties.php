<?php
/**
 * Template Part: Browse Specialties
 *
 * Renders the "Browse by Specialty" grid combining CSV taxonomy specialties
 * and NPI provider types, sorted by provider count (highest first).
 * Displays the top 10 items from the combined list.
 *
 * Expected $args keys:
 *   - 'browse_items' (array) — Pre-built combined specialty list from front-page.php.
 *                              Each item is an array with keys:
 *                              'name', 'count', 'link', 'type' ('csv'|'npi'), 'slug'.
 *                              Falls back to building the list internally if not provided.
 *
 * @package MyHealthHub
 */

// Accept pre-built list from parent or build it inline
if ( isset($args['browse_items']) ) {
    $browse_items = $args['browse_items'];
} else {
    global $wpdb;
    $browse_items = array();

    $specialties = get_terms(array('taxonomy' => 'myhh_specialty', 'hide_empty' => true, 'parent' => 0, 'orderby' => 'count', 'order' => 'DESC'));
    if ($specialties && !is_wp_error($specialties)) {
        foreach ($specialties as $spec) {
            if ($spec->count > 0) {
                $browse_items[] = array(
                    'name'  => $spec->name,
                    'count' => (int) $spec->count,
                    'link'  => myhh_get_directory_url() . '?specialty=csv:' . $spec->slug,
                    'type'  => 'csv',
                    'slug'  => $spec->slug,
                );
            }
        }
    }

    $npi_provider_types = $wpdb->get_results("
        SELECT meta_value, COUNT(*) as count
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID AND p.post_status = 'publish' AND p.post_type = 'gd_place'
        WHERE pm.meta_key = 'npi_primary_taxonomy'
        AND pm.meta_value != ''
        GROUP BY pm.meta_value
        ORDER BY count DESC
    ");
    if ($npi_provider_types) {
        foreach ($npi_provider_types as $pt) {
            $browse_items[] = array(
                'name'  => $pt->meta_value,
                'count' => (int) $pt->count,
                'link'  => myhh_get_directory_url() . '?specialty=npi:' . urlencode($pt->meta_value),
                'type'  => 'npi',
                'slug'  => sanitize_title($pt->meta_value),
            );
        }
    }

    usort($browse_items, function($a, $b) { return $b['count'] - $a['count']; });
}

// Color and icon mapping for CSV specialties
$specialty_styles = array(
    'general-counseling'                    => array('color' => '#EEF2FF', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'),
    'general-counseling-child-teen-therapy' => array('color' => '#FEF3C7', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'),
    'relationships'                         => array('color' => '#D1FAE5', 'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'),
    'anxiety'                               => array('color' => '#EEF2FF', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'),
    'depression'                            => array('color' => '#FEF3C7', 'icon' => 'M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'),
    'trauma'                                => array('color' => '#FCE7F3', 'icon' => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'),
);

// NPI provider type color palette (rotate through)
$npi_colors = array('#F0FDF4', '#EFF6FF', '#FDF4FF', '#FFF7ED', '#F5F3FF', '#ECFDF5', '#EEF2FF', '#FEF2F2', '#FFFBEB', '#F0FDFA');

// NPI verified shield icon path
$npi_icon = 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z';

// Display top 10 from combined list (sorted by count)
$display_items = array_slice($browse_items, 0, 10);
$npi_color_idx = 0;
?>
<!-- Browse by Specialty -->
<section style="padding: 80px 0; background: #F8FAFC;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 56px;">
            <span style="display: inline-block; background: #EEF2FF; color: #0A74DA; padding: 6px 16px; border-radius: 20px; font-size: 0.875rem; font-weight: 600; margin-bottom: 16px;">Find Your Need</span>
            <h2 style="font-size: clamp(1.5rem, 4vw, 2.5rem); font-weight: 700; margin-bottom: 16px; color: #1F2937;">Browse by Specialty</h2>
            <p style="font-size: 1.125rem; color: #6B7280;">Find the right specialist for your unique situation.</p>
        </div>
        
        <div class="specialty-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(175px, 1fr)); gap: 16px; max-width: 1100px; margin: 0 auto;">
            <?php
            if ($display_items) :
                foreach ($display_items as $item) :
                    if ($item['type'] === 'csv') {
                        $bg_color   = isset($specialty_styles[$item['slug']]) ? $specialty_styles[$item['slug']]['color'] : '#F3F4F6';
                        $icon_path  = isset($specialty_styles[$item['slug']]) ? $specialty_styles[$item['slug']]['icon'] : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
                        $icon_stroke = '#374151';
                        $badge_html = '';
                    } else {
                        $bg_color    = $npi_colors[$npi_color_idx % count($npi_colors)];
                        $npi_color_idx++;
                        $icon_path   = $npi_icon;
                        $icon_stroke = '#059669';
                        $badge_html  = '<span style="display: inline-flex; align-items: center; gap: 3px; background: #ECFDF5; color: #059669; font-size: 0.625rem; font-weight: 600; padding: 2px 6px; border-radius: 8px; margin-top: 4px;"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>NPI Verified</span>';
                    }
            ?>
                <a href="<?php echo esc_url($item['link']); ?>" 
                   class="featured-provider-card"
                   style="display: flex; flex-direction: column; align-items: center; padding: 20px 12px; background: <?php echo $bg_color; ?>; border-radius: 16px; text-decoration: none; border: 2px solid transparent; text-align: center; min-height: 140px; justify-content: center;">
                    <svg width="28" height="28" fill="none" stroke="<?php echo $icon_stroke; ?>" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom: 8px; flex-shrink: 0;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo $icon_path; ?>"/>
                    </svg>
                    <span style="color: #1F2937; font-weight: 600; font-size: 0.85rem; line-height: 1.3;"><?php echo esc_html($item['name']); ?></span>
                    <span style="color: #6B7280; font-size: 0.75rem; margin-top: 4px;"><?php echo esc_html($item['count']); ?> providers</span>
                    <?php echo $badge_html; ?>
                </a>
            <?php endforeach; endif; ?>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" style="display: inline-flex; align-items: center; gap: 8px; color: #0A74DA; font-weight: 600; text-decoration: none;">
                View All Specialties
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </a>
        </div>
    </div>
</section>
