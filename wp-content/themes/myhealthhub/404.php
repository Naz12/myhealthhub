<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @package MyHealthHub
 * @version 1.0.0
 */

get_header();
?>

<main class="site-main">
    <section class="error-404 not-found">
        <div class="container">
            <div class="error-content" style="text-align: center; padding: 80px 20px;">
                <h1 class="error-title" style="font-size: 120px; margin: 0; color: var(--color-primary); opacity: 0.3;">404</h1>
                <h2 style="margin-bottom: 20px;"><?php _e('Page Not Found', 'myhealthhub'); ?></h2>
                <p style="color: var(--color-text-light); margin-bottom: 30px; max-width: 500px; margin-left: auto; margin-right: auto;">
                    <?php _e('The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.', 'myhealthhub'); ?>
                </p>
                
                <div class="error-actions" style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="btn btn--primary">
                        <?php _e('Go Home', 'myhealthhub'); ?>
                    </a>
                    <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" class="btn btn--outline">
                        <?php _e('Browse Providers', 'myhealthhub'); ?>
                    </a>
                </div>
                
                <div class="error-search" style="margin-top: 40px; max-width: 500px; margin-left: auto; margin-right: auto;">
                    <p style="margin-bottom: 15px; color: var(--color-text-light);">
                        <?php _e('Or try searching:', 'myhealthhub'); ?>
                    </p>
                    <?php get_search_form(); ?>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
get_footer();
