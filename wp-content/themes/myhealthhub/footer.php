<?php
/**
 * Footer template
 *
 * @package MyHealthHub
 */
?>
</main><!-- #main-content -->

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="footer-brand-link">
                    <span class="footer-brand-icon" aria-hidden="true">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </span>
                    <span>MyHealthHub</span>
                </a>
                <p class="brand-desc"><?php esc_html_e('Connecting the Raleigh/Triangle community with mental health professionals.', 'myhealthhub'); ?></p>
            </div>

            <div class="footer-links">
                <div>
                    <h2><?php esc_html_e('Explore', 'myhealthhub'); ?></h2>
                    <ul>
                        <li><a href="<?php echo esc_url(myhh_get_directory_url()); ?>"><?php esc_html_e('All Providers', 'myhealthhub'); ?></a></li>
                        <li><a href="<?php echo esc_url(home_url('/get-matched/')); ?>"><?php esc_html_e('Get Matched', 'myhealthhub'); ?></a></li>
                        <li><a href="<?php echo esc_url(home_url('/about/')); ?>"><?php esc_html_e('About Us', 'myhealthhub'); ?></a></li>
                        <li><a href="<?php echo esc_url(home_url('/contact/')); ?>"><?php esc_html_e('Contact', 'myhealthhub'); ?></a></li>
                    </ul>
                </div>
                <div>
                    <h2><?php esc_html_e('For Providers', 'myhealthhub'); ?></h2>
                    <ul>
                        <li><a href="<?php echo esc_url(home_url('/add-your-practice/')); ?>"><?php esc_html_e('List for Free', 'myhealthhub'); ?></a></li>
                        <li><a href="<?php echo esc_url(home_url('/listings/')); ?>"><?php esc_html_e('Claim Your Profile', 'myhealthhub'); ?></a></li>
                    </ul>
                </div>
                <div>
                    <h2><?php esc_html_e('Legal', 'myhealthhub'); ?></h2>
                    <ul>
                        <li><a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>"><?php esc_html_e('Privacy Policy', 'myhealthhub'); ?></a></li>
                        <li><a href="<?php echo esc_url(home_url('/terms/')); ?>"><?php esc_html_e('Terms of Service', 'myhealthhub'); ?></a></li>
                        <li><a href="<?php echo esc_url(home_url('/disclaimer/')); ?>"><?php esc_html_e('Disclaimer', 'myhealthhub'); ?></a></li>
                        <li><a href="<?php echo esc_url(home_url('/how-we-verify/')); ?>"><?php esc_html_e('How We Verify', 'myhealthhub'); ?></a></li>
                    </ul>
                </div>
                <div>
                    <h2><?php esc_html_e('Areas Served', 'myhealthhub'); ?></h2>
                    <ul>
                        <li><a href="<?php echo esc_url(home_url('/location/raleigh/')); ?>"><?php esc_html_e('Raleigh', 'myhealthhub'); ?></a></li>
                        <li><a href="<?php echo esc_url(home_url('/location/durham/')); ?>"><?php esc_html_e('Durham', 'myhealthhub'); ?></a></li>
                        <li><a href="<?php echo esc_url(home_url('/location/cary/')); ?>"><?php esc_html_e('Cary', 'myhealthhub'); ?></a></li>
                        <li><a href="<?php echo esc_url(home_url('/location/chapel-hill/')); ?>"><?php esc_html_e('Chapel Hill', 'myhealthhub'); ?></a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo esc_html(date_i18n('Y')); ?> <?php bloginfo('name'); ?>. <?php esc_html_e('All rights reserved.', 'myhealthhub'); ?></p>
            <p><?php esc_html_e('Supporting the Raleigh/Triangle community.', 'myhealthhub'); ?></p>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>

</body>
</html>
