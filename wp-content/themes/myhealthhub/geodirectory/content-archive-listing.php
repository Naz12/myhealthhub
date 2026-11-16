<?php
/**
 * Template for the list of places
 *
 * Override of GeoDirectory template for modern grid layout
 *
 * @package MyHealthHub
 * @version 1.0.0
 */

do_action('geodir_before_listing_listview');

global $wp_query;

// Count results
$found_posts = $wp_query->found_posts;

?>
<div class="geodir-listings-wrapper" style="margin-bottom: 32px;">
    
    <!-- Results Count -->
    <div style="margin-bottom: 24px; font-size: 1rem; color: #1F2937;">
        <?php printf('<strong>%s</strong> %s', number_format_i18n($found_posts), _n('provider found', 'providers found', $found_posts, 'myhealthhub')); ?>
    </div>
    
    <!-- Grid Layout -->
    <?php if (have_posts()) : ?>
        <div class="listings-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 28px;">
            <?php while (have_posts()) : the_post(); ?>
                <?php geodir_get_template_part('content', 'listing'); ?>
            <?php endwhile; ?>
        </div>
        
        <!-- Pagination -->
        <div class="geodir-pagination" style="margin-top: 48px; display: flex; justify-content: center;">
            <?php
            echo paginate_links(array(
                'prev_text' => '<span>&larr; Previous</span>',
                'next_text' => '<span>Next &rarr;</span>',
                'mid_size' => 2,
            ));
            ?>
        </div>
    <?php else : ?>
        <div class="no-results" style="text-align: center; padding: 60px 40px; background: #fff; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5" style="margin-bottom: 20px;">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="M21 21l-4.35-4.35"></path>
            </svg>
            <h3 style="color: #1F2937; margin-bottom: 12px;">No providers found</h3>
            <p style="color: #6B7280; margin-bottom: 24px;">Try adjusting your search filters.</p>
        </div>
    <?php endif; ?>
    
</div>
<?php

do_action('geodir_after_listing_listview');
