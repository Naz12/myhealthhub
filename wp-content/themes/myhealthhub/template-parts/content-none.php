<?php
/**
 * Template part for displaying a message that posts cannot be found
 *
 * @package MyHealthHub
 * @version 1.0.0
 */
?>

<section class="no-results not-found" style="text-align: center; padding: 60px 20px;">
    <header class="page-header">
        <h1 class="page-title"><?php esc_html_e('Nothing Found', 'myhealthhub'); ?></h1>
    </header>

    <div class="page-content">
        <?php if (is_home() && current_user_can('publish_posts')) : ?>
            
            <p>
                <?php
                printf(
                    wp_kses(
                        /* translators: 1: link to WP admin new post page. */
                        __('Ready to publish your first post? <a href="%1$s">Get started here</a>.', 'myhealthhub'),
                        array(
                            'a' => array(
                                'href' => array(),
                            ),
                        )
                    ),
                    esc_url(admin_url('post-new.php'))
                );
                ?>
            </p>
            
        <?php elseif (is_search()) : ?>
            
            <p style="color: var(--color-text-light); margin-bottom: 30px;">
                <?php esc_html_e('Sorry, but nothing matched your search terms. Please try again with different keywords.', 'myhealthhub'); ?>
            </p>
            <?php get_search_form(); ?>
            
        <?php else : ?>
            
            <p style="color: var(--color-text-light); margin-bottom: 30px;">
                <?php esc_html_e('It seems we can\'t find what you\'re looking for. Perhaps searching can help.', 'myhealthhub'); ?>
            </p>
            <?php get_search_form(); ?>
            
        <?php endif; ?>
    </div>
</section>
