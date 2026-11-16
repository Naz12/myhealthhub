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

<header class="site-header" style="position: sticky !important; top: 0 !important; z-index: 1000 !important; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important; border-bottom: 3px solid #f5576c !important; box-shadow: 0 10px 40px rgba(102, 126, 234, 0.2) !important;">
    <div class="container" style="display: flex; align-items: center; justify-content: space-between; height: 72px; max-width: 1200px; margin: 0 auto; padding: 0 24px;">
        
        <!-- Logo -->
        <div class="site-branding" style="display: flex; align-items: center;">
            <a href="<?php echo esc_url(home_url('/')); ?>" style="display: flex; align-items: center; text-decoration: none; gap: 10px;">
                <div class="logo-icon" style="width: 42px; height: 42px; background: rgba(255, 255, 255, 0.25) !important; border-radius: 10px; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);">
                    <svg width="24" height="24" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </div>
                <span class="logo-text" style="font-size: 1.35rem; font-weight: 700; color: #fff !important; text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);">
                    MyHealth<span style="color: #fff !important;">Hub</span>
                </span>
            </a>
        </div>
        
        <!-- Desktop Navigation -->
        <nav class="main-navigation" style="display: flex; align-items: center; gap: 8px;" aria-label="<?php _e('Primary Navigation', 'myhealthhub'); ?>">
            <ul class="nav-menu" style="display: flex; align-items: center; gap: 4px; list-style: none; margin: 0; padding: 0;">
                <li style="position: relative;">
                    <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" 
                       style="display: block; padding: 10px 16px; color: rgba(255, 255, 255, 0.9) !important; font-weight: 500; font-size: 0.95rem; text-decoration: none; border-radius: 8px; transition: all 0.2s;"
                       onmouseover="this.style.background='rgba(255, 255, 255, 0.15)';this.style.color='#fff';"
                       onmouseout="this.style.background='transparent';this.style.color='rgba(255, 255, 255, 0.9)';">
                        All Providers
                    </a>
                </li>
                <li style="position: relative;">
                    <a href="<?php echo esc_url(home_url('/about/')); ?>" 
                       style="display: block; padding: 10px 16px; color: rgba(255, 255, 255, 0.9) !important; font-weight: 500; font-size: 0.95rem; text-decoration: none; border-radius: 8px; transition: all 0.2s;"
                       onmouseover="this.style.background='rgba(255, 255, 255, 0.15)';this.style.color='#fff';"
                       onmouseout="this.style.background='transparent';this.style.color='rgba(255, 255, 255, 0.9)';">
                        About
                    </a>
                </li>
                <li style="position: relative;">
                    <a href="<?php echo esc_url(home_url('/contact/')); ?>" 
                       style="display: block; padding: 10px 16px; color: rgba(255, 255, 255, 0.9) !important; font-weight: 500; font-size: 0.95rem; text-decoration: none; border-radius: 8px; transition: all 0.2s;"
                       onmouseover="this.style.background='rgba(255, 255, 255, 0.15)';this.style.color='#fff';"
                       onmouseout="this.style.background='transparent';this.style.color='rgba(255, 255, 255, 0.9)';">
                        Contact
                    </a>
                </li>
            </ul>
            
            <!-- List for Free Link -->
            <a href="<?php echo esc_url(home_url('/add-your-practice/')); ?>" 
               style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 16px; color: #fff; font-weight: 500; font-size: 0.9rem; text-decoration: none; border-radius: 8px; margin-left: 8px; border: 2px solid rgba(255, 255, 255, 0.3); transition: all 0.2s;"
               onmouseover="this.style.background='rgba(255, 255, 255, 0.15)';this.style.borderColor='rgba(255, 255, 255, 0.6)';"
               onmouseout="this.style.background='transparent';this.style.borderColor='rgba(255, 255, 255, 0.3)';">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                List for Free
            </a>
            
            <!-- CTA Button -->
            <a href="<?php echo esc_url(home_url('/get-matched/')); ?>" 
               style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.25) 0%, rgba(255, 255, 255, 0.15) 100%); color: white; font-weight: 600; font-size: 0.95rem; text-decoration: none; border-radius: 10px; margin-left: 12px; transition: all 0.2s; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15); border: 2px solid rgba(255, 255, 255, 0.25);"
               onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 20px rgba(0, 0, 0, 0.2)';this.style.background='linear-gradient(135deg, rgba(255, 255, 255, 0.35) 0%, rgba(255, 255, 255, 0.25) 100%)';"
               onmouseout="this.style.transform='none';this.style.boxShadow='0 2px 12px rgba(0, 0, 0, 0.15)';this.style.background='linear-gradient(135deg, rgba(255, 255, 255, 0.25) 0%, rgba(255, 255, 255, 0.15) 100%)';">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
                Get Matched
            </a>
        </nav>
        
        <!-- Mobile Menu Toggle -->
        <button class="mobile-menu-toggle" 
                aria-label="<?php _e('Toggle menu', 'myhealthhub'); ?>" 
                aria-expanded="false"
                onclick="document.querySelector('.mobile-nav').classList.toggle('active');"
                style="display: none; background: none; border: none; cursor: pointer; padding: 6px;">
            <span style="display: block; width: 20px; height: 2px; background: #374151; margin: 4px 0; border-radius: 2px;"></span>
            <span style="display: block; width: 20px; height: 2px; background: #374151; margin: 4px 0; border-radius: 2px;"></span>
            <span style="display: block; width: 20px; height: 2px; background: #374151; margin: 4px 0; border-radius: 2px;"></span>
        </button>
    </div>
    
    <!-- Mobile Navigation -->
    <div class="mobile-nav" style="display: none; background: white; border-top: 1px solid #E5E7EB; padding: 16px 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
        <ul style="list-style: none; margin: 0; padding: 0;">
            <li style="border-bottom: 1px solid #F3F4F6;">
                <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" style="display: block; padding: 12px 0; color: #374151; font-weight: 500; text-decoration: none;">All Providers</a>
            </li>
            <li style="border-bottom: 1px solid #F3F4F6;">
                <a href="<?php echo esc_url(home_url('/about/')); ?>" style="display: block; padding: 12px 0; color: #374151; font-weight: 500; text-decoration: none;">About</a>
            </li>
            <li style="border-bottom: 1px solid #F3F4F6;">
                <a href="<?php echo esc_url(home_url('/contact/')); ?>" style="display: block; padding: 12px 0; color: #374151; font-weight: 500; text-decoration: none;">Contact</a>
            </li>
            <li style="border-bottom: 1px solid #F3F4F6;">
                <a href="<?php echo esc_url(home_url('/add-your-practice/')); ?>" style="display: block; padding: 12px 0; color: #0A74DA; font-weight: 500; text-decoration: none;">List for Free</a>
            </li>
            <li style="margin-top: 12px;">
                <a href="<?php echo esc_url(home_url('/get-matched/')); ?>" style="display: block; padding: 14px 20px; background: linear-gradient(135deg, #0A74DA 0%, #1D4ED8 100%); color: white; font-weight: 600; text-align: center; border-radius: 10px; text-decoration: none;">Get Matched</a>
            </li>
        </ul>
    </div>
</header>

<!-- Sticky spacer -->
<div style="height: 1px;"></div>

<main id="main-content" class="site-main">

<script>
document.addEventListener('DOMContentLoaded', function() {
    var lastScroll = 0;
    var header = document.querySelector('.site-header');
    
    window.addEventListener('scroll', function() {
        var currentScroll = window.pageYOffset;
        
        if (currentScroll <= 0) {
            header.style.boxShadow = '0 1px 3px rgba(0,0,0,0.05)';
        } else {
            header.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
        }
        
        lastScroll = currentScroll;
    });
    
    // Mobile menu toggle
    var toggle = document.querySelector('.mobile-menu-toggle');
    var mobileNav = document.querySelector('.mobile-nav');
    
    if (toggle && mobileNav) {
        toggle.addEventListener('click', function() {
            var isExpanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', !isExpanded);
            mobileNav.style.display = isExpanded ? 'none' : 'block';
        });
        
        // Show mobile toggle on small screens
        var checkMobile = function() {
            if (window.innerWidth <= 768) {
                toggle.style.display = 'block';
                if (mobileNav.classList.contains('active')) {
                    mobileNav.style.display = 'block';
                }
            } else {
                toggle.style.display = 'none';
                mobileNav.style.display = 'none';
            }
        };
        
        window.addEventListener('resize', checkMobile);
        checkMobile();
    }
});
</script>
