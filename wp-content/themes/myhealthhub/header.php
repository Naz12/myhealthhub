<?php
/**
 * Header template
 *
 * @package MyHealthHub
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a href="#main-content" class="sr-only"><?php _e('Skip to main content', 'myhealthhub'); ?></a>

<header class="site-header">
    <div class="container header-inner">
        <div class="site-branding">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="site-brand">
                <span class="logo-icon" aria-hidden="true">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </span>
                <span class="logo-text">MyHealth<span>Hub</span></span>
            </a>
        </div>

        <nav class="main-navigation" aria-label="<?php esc_attr_e('Primary Navigation', 'myhealthhub'); ?>">
            <ul class="nav-menu">
                <li><a href="<?php echo esc_url(home_url('/about/')); ?>"><?php esc_html_e('About', 'myhealthhub'); ?></a></li>
                <li><a href="<?php echo esc_url(home_url('/contact/')); ?>"><?php esc_html_e('Contact', 'myhealthhub'); ?></a></li>
                <li><a href="<?php echo esc_url(home_url('/get-matched/')); ?>"><?php esc_html_e('Get Matched', 'myhealthhub'); ?></a></li>
                <li><a href="<?php echo esc_url(home_url('/add-your-practice/')); ?>"><?php esc_html_e('List for Free', 'myhealthhub'); ?></a></li>
            </ul>
            <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" class="header-cta"><?php esc_html_e('Find a provider', 'myhealthhub'); ?></a>
        </nav>

        <button class="mobile-menu-toggle" type="button"
                aria-label="<?php esc_attr_e('Toggle menu', 'myhealthhub'); ?>"
                aria-expanded="false" aria-controls="mobile-navigation">
            <span></span><span></span><span></span>
        </button>
    </div>

    <nav id="mobile-navigation" class="mobile-nav" aria-label="<?php esc_attr_e('Mobile Navigation', 'myhealthhub'); ?>" style="display: none;">
        <ul>
            <li><a href="<?php echo esc_url(home_url('/about/')); ?>"><?php esc_html_e('About', 'myhealthhub'); ?></a></li>
            <li><a href="<?php echo esc_url(home_url('/contact/')); ?>"><?php esc_html_e('Contact', 'myhealthhub'); ?></a></li>
            <li><a href="<?php echo esc_url(home_url('/get-matched/')); ?>"><?php esc_html_e('Get Matched', 'myhealthhub'); ?></a></li>
            <li><a href="<?php echo esc_url(home_url('/add-your-practice/')); ?>"><?php esc_html_e('List for Free', 'myhealthhub'); ?></a></li>
            <li><a href="<?php echo esc_url(myhh_get_directory_url()); ?>" class="header-cta"><?php esc_html_e('Find a provider', 'myhealthhub'); ?></a></li>
        </ul>
    </nav>
</header>

<main id="main-content" class="site-main">

<script>
document.addEventListener('DOMContentLoaded', function() {
    var toggle = document.querySelector('.mobile-menu-toggle');
    var mobileNav = document.querySelector('.mobile-nav');

    if (toggle && mobileNav) {
        toggle.addEventListener('click', function() {
            var isExpanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', !isExpanded);
            mobileNav.classList.toggle('active', !isExpanded);
            mobileNav.style.display = isExpanded ? 'none' : 'block';
        });

        var checkMobile = function() {
            if (window.innerWidth <= 768) {
                toggle.style.display = 'flex';
                if (mobileNav.classList.contains('active')) {
                    mobileNav.style.display = 'block';
                }
            } else {
                toggle.style.display = 'none';
                toggle.setAttribute('aria-expanded', 'false');
                mobileNav.classList.remove('active');
                mobileNav.style.display = 'none';
            }
        };

        window.addEventListener('resize', checkMobile);
        checkMobile();
    }
});
</script>
