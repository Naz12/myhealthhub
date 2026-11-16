<?php
/**
 * The template for displaying search results pages
 *
 * @package MyHealthHub
 * @version 1.0.0
 */

get_header();
?>

<main class="site-main">
    <header class="page-header">
        <div class="container">
            <h1 class="page-header__title">
                <?php
                printf(
                    /* translators: %s: search query. */
                    esc_html__('Search Results for: %s', 'myhealthhub'),
                    '<span>' . get_search_query() . '</span>'
                );
                ?>
            </h1>
            <p class="page-header__subtitle">
                <?php
                global $wp_query;
                printf(
                    esc_html(_n('Found %s result', 'Found %s results', $wp_query->found_posts, 'myhealthhub')),
                    number_format_i18n($wp_query->found_posts)
                );
                ?>
            </p>
        </div>
    </header>

    <div class="page-content">
        <div class="container">
            <?php if (have_posts()) : ?>
                
                <div class="search-results" style="max-width: 800px; margin: 0 auto;">
                    <?php while (have_posts()) : the_post(); ?>
                        <article class="search-result" style="padding: 30px 0; border-bottom: 1px solid var(--color-border);">
                            <header class="entry-header">
                                <?php the_title(sprintf('<h2 class="entry-title" style="margin-bottom: 10px;"><a href="%s" rel="bookmark">', esc_url(get_permalink())), '</a></h2>'); ?>
                                
                                <div class="entry-meta" style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 10px;">
                                    <?php
                                    $post_type = get_post_type();
                                    $post_type_obj = get_post_type_object($post_type);
                                    if ($post_type_obj) {
                                        echo esc_html($post_type_obj->label);
                                    }
                                    ?>
                                    <span style="margin: 0 8px;">•</span>
                                    <?php echo get_the_date(); ?>
                                </div>
                            </header>
                            
                            <div class="entry-summary">
                                <?php the_excerpt(); ?>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
                
                <div class="pagination" style="margin-top: 40px; text-align: center;">
                    <?php
                    the_posts_pagination(array(
                        'mid_size'  => 2,
                        'prev_text' => __('&larr; Previous', 'myhealthhub'),
                        'next_text' => __('Next &rarr;', 'myhealthhub'),
                    ));
                    ?>
                </div>
                
            <?php else : ?>
                
                <div class="no-results" style="text-align: center; padding: 60px 20px;">
                    <h2><?php _e('Nothing Found', 'myhealthhub'); ?></h2>
                    <p style="color: var(--color-text-light); margin-bottom: 30px;">
                        <?php _e('Sorry, but nothing matched your search terms. Please try again with different keywords.', 'myhealthhub'); ?>
                    </p>
                    <?php get_search_form(); ?>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
get_footer();
