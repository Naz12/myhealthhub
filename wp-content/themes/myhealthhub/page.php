<?php
/**
 * Page template
 *
 * @package MyHealthHub
 */

get_header();
?>

<?php myhh_breadcrumbs(); ?>

<?php myhh_page_header(); ?>

<div class="page-content">
    <div class="container">
        <?php if (have_posts()) : ?>
            <?php while (have_posts()) : the_post(); ?>
                <article <?php post_class('page-article'); ?>>
                    <div class="entry-content">
                        <?php the_content(); ?>
                        
                        <?php
                        wp_link_pages(array(
                            'before' => '<div class="page-links">' . __('Pages:', 'myhealthhub'),
                            'after'  => '</div>',
                        ));
                        ?>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php else : ?>
            <p><?php _e('Sorry, no content found.', 'myhealthhub'); ?></p>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
