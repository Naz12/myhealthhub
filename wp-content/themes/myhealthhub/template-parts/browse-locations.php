<?php
/**
 * Template Part: Browse Locations
 *
 * Renders the "Browse by Location" city button grid for SEO internal linking.
 * Displays up to 6 cities from the myhh_city taxonomy.
 *
 * Expected $args keys:
 *   - 'cities' (array|WP_Error) — Result of get_terms() for myhh_city taxonomy.
 *                                 Falls back to a fresh get_terms() call if not provided.
 *
 * @package MyHealthHub
 */

// Accept cities from $args or fall back to a direct query
$cities = isset($args['cities']) ? $args['cities'] : get_terms(array(
    'taxonomy'   => 'myhh_city',
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
));
?>
<!-- Browse by Location (SEO Internal Links) -->
<section style="padding: 60px 0; background: white;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 40px;">
            <h2 style="font-size: clamp(1.25rem, 3vw, 1.75rem); font-weight: 700; margin-bottom: 12px; color: #1F2937;">Browse by Location</h2>
            <p style="font-size: 1rem; color: #6B7280;">Find wellness professionals near you in the Triangle area.</p>
        </div>
        
        <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; max-width: 800px; margin: 0 auto;">
            <?php
            if ($cities && !is_wp_error($cities)) :
                foreach (array_slice($cities, 0, 6) as $city) :
            ?>
                <a href="<?php echo esc_url(add_query_arg('city', $city->slug, myhh_get_directory_url())); ?>" 
                   class="category-card"
                   style="display: flex; align-items: center; gap: 8px; padding: 14px 24px; background: #F8FAFC; border: 1px solid #E5E7EB; border-radius: 12px; text-decoration: none;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0A74DA" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span style="color: #1F2937; font-weight: 600; font-size: 0.9375rem;"><?php echo esc_html($city->name); ?></span>
                    <span style="color: #6B7280; font-size: 0.75rem;"><?php echo esc_html($city->count); ?></span>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>
