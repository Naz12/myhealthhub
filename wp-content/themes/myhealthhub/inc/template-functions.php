<?php
/**
 * Custom template functions for this theme
 *
 * @package MyHealthHub
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display pagination for archive pages
 */
if (!function_exists('myhh_pagination')) :
function myhh_pagination() {
    the_posts_pagination(array(
        'mid_size'           => 2,
        'prev_text'          => __('&larr; Previous', 'myhealthhub'),
        'next_text'          => __('Next &rarr;', 'myhealthhub'),
        'before_page_number' => '<span class="meta-nav screen-reader-text">' . __('Page', 'myhealthhub') . ' </span>',
    ));
}
endif;

/**
 * Display post meta information
 */
if (!function_exists('myhh_post_meta')) :
function myhh_post_meta() {
    ?>
    <div class="entry-meta">
        <span class="posted-on">
            <?php echo get_the_date(); ?>
        </span>
        <span class="byline">
            <?php the_author(); ?>
        </span>
    </div>
    <?php
}
endif;

/**
 * Display post thumbnail with lazy loading
 */
if (!function_exists('myhh_post_thumbnail')) :
function myhh_post_thumbnail($size = 'post-thumbnail') {
    if (post_password_required() || is_attachment() || !has_post_thumbnail()) {
        return;
    }
    ?>
    <div class="post-thumbnail">
        <a href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
            <?php
            the_post_thumbnail($size, array(
                'alt' => the_title_attribute(array(
                    'echo' => false,
                )),
                'loading' => 'lazy',
            ));
            ?>
        </a>
    </div>
    <?php
}
endif;

/**
 * Display breadcrumbs
 */
if (!function_exists('myhh_breadcrumbs')) :
function myhh_breadcrumbs() {
    if (is_front_page()) {
        return;
    }
    ?>
    <nav class="breadcrumbs" aria-label="Breadcrumb" style="padding: 1rem 0; font-size: 0.875rem; color: var(--color-text-light);">
        <div class="container">
            <a href="<?php echo esc_url(home_url('/')); ?>" style="color: var(--color-text-light);">
                <?php _e('Home', 'myhealthhub'); ?>
            </a>
            <span style="margin: 0 8px;">/</span>
            <?php
            if (is_category() || is_single()) {
                $category = get_the_category();
                if (!empty($category)) {
                    echo '<a href="' . esc_url(get_category_link($category[0]->term_id)) . '" style="color: var(--color-text-light);">' . esc_html($category[0]->name) . '</a>';
                    echo '<span style="margin: 0 8px;">/</span>';
                }
                if (is_single()) {
                    echo '<span>' . esc_html(get_the_title()) . '</span>';
                }
            } elseif (is_page()) {
                echo '<span>' . esc_html(get_the_title()) . '</span>';
            } elseif (is_search()) {
                echo '<span>' . esc_html__('Search Results', 'myhealthhub') . '</span>';
            } elseif (is_404()) {
                echo '<span>' . esc_html__('Error 404', 'myhealthhub') . '</span>';
            } elseif (is_archive()) {
                the_archive_title('<span>', '</span>');
            }
            ?>
        </div>
    </nav>
    <?php
}
endif;

/**
 * Get related posts
 */
if (!function_exists('myhh_get_related_posts')) :
function myhh_get_related_posts($post_id = null, $number = 3) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $categories = get_the_category($post_id);
    if (empty($categories)) {
        return array();
    }
    
    $category_ids = wp_list_pluck($categories, 'term_id');
    
    $args = array(
        'category__in'   => $category_ids,
        'post__not_in'   => array($post_id),
        'posts_per_page' => $number,
        'orderby'        => 'rand',
    );
    
    return get_posts($args);
}
endif;

/**
 * Display estimated read time
 */
if (!function_exists('myhh_reading_time')) :
function myhh_reading_time() {
    $content = get_post_field('post_content', get_the_ID());
    $word_count = str_word_count(strip_tags($content));
    $reading_time = ceil($word_count / 200);
    
    printf(
        esc_html__('%d min read', 'myhealthhub'),
        $reading_time
    );
}
endif;

/**
 * Check if we're on a GeoDirectory page
 */
if (!function_exists('myhh_is_geodirectory_page')) :
function myhh_is_geodirectory_page() {
    if (!function_exists('geodir_is_page')) {
        return false;
    }
    return geodir_is_page('listing') || geodir_is_page('archive') || geodir_is_page('search');
}
endif;

/**
 * Get provider specialties (placeholder for GeoDirectory integration)
 */
if (!function_exists('myhh_get_provider_specialties')) :
function myhh_get_provider_specialties($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    // This is a placeholder - actual implementation would fetch from GeoDirectory custom fields
    $specialties = get_post_meta($post_id, '_myhh_specialties', true);
    
    if (!empty($specialties)) {
        return is_array($specialties) ? $specialties : explode(',', $specialties);
    }
    
    return array();
}
endif;

/**
 * Format phone number
 */
if (!function_exists('myhh_format_phone')) :
function myhh_format_phone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($phone) === 10) {
        return sprintf('(%s) %s-%s',
            substr($phone, 0, 3),
            substr($phone, 3, 3),
            substr($phone, 6, 4)
        );
    }
    
    return $phone;
}
endif;
