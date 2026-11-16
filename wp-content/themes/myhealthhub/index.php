<?php
/**
 * Main template file
 *
 * @package MyHealthHub
 */

get_header();
?>

<?php if (is_front_page() || is_home()) : ?>
    
    <?php myhh_hero(); ?>
    
    <?php myhh_trust_strip(); ?>
    
    <?php myhh_search_section(); ?>
    
    <section class="content-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-header__title"><?php _e('Featured Providers', 'myhealthhub'); ?></h2>
                <p class="section-header__desc"><?php _e('Browse our network of verified mental health professionals in the Triangle area.', 'myhealthhub'); ?></p>
            </div>
            
            <?php if (myhh_is_geodirectory_active()) : ?>
                <?php echo do_shortcode('[gd_listings post_type="gd_place" title_tag="h3" layout="grid" row_items="3"]'); ?>
            <?php else : ?>
                <div class="featured-providers-placeholder">
                    <p><?php _e('Install and configure GeoDirectory to display provider listings here.', 'myhealthhub'); ?></p>
                </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 2rem;">
                <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" class="btn btn--primary btn--lg">
                    <?php _e('View All Providers', 'myhealthhub'); ?>
                </a>
            </div>
        </div>
    </section>
    
    <section class="content-section content-section--light">
        <div class="container">
            <div class="section-header">
                <h2 class="section-header__title"><?php _e('How It Works', 'myhealthhub'); ?></h2>
                <p class="section-header__desc"><?php _e('Finding the right mental health professional is simple.', 'myhealthhub'); ?></p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; text-align: center;">
                <div>
                    <div style="width: 64px; height: 64px; background: var(--color-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 1.5rem; font-weight: 700;">1</div>
                    <h3><?php _e('Search', 'myhealthhub'); ?></h3>
                    <p><?php _e('Browse our directory of verified therapists, counselors, and coaches.', 'myhealthhub'); ?></p>
                </div>
                <div>
                    <div style="width: 64px; height: 64px; background: var(--color-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 1.5rem; font-weight: 700;">2</div>
                    <h3><?php _e('Compare', 'myhealthhub'); ?></h3>
                    <p><?php _e('Review profiles, specialties, and credentials to find your match.', 'myhealthhub'); ?></p>
                </div>
                <div>
                    <div style="width: 64px; height: 64px; background: var(--color-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 1.5rem; font-weight: 700;">3</div>
                    <h3><?php _e('Connect', 'myhealthhub'); ?></h3>
                    <p><?php _e('Book directly or use our Get Matched service for personalized help.', 'myhealthhub'); ?></p>
                </div>
            </div>
        </div>
    </section>
    
<?php else : ?>
    
    <div class="page-content">
        <div class="container">
            <?php if (have_posts()) : ?>
                <?php while (have_posts()) : the_post(); ?>
                    <article <?php post_class(); ?>>
                        <header class="entry-header">
                            <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
                        </header>
                        
                        <div class="entry-content">
                            <?php the_content(); ?>
                        </div>
                    </article>
                <?php endwhile; ?>
            <?php else : ?>
                <p><?php _e('No content found.', 'myhealthhub'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
<?php endif; ?>

<?php
get_footer();
