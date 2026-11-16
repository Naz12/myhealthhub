<?php
/**
 * Footer template
 *
 * @package MyHealthHub
 */
?>
</main><!-- #main-content -->

<style>
/* Footer hover states — replaces all onmouseover/onmouseout inline handlers */
.footer-social-link:hover { background: #0A74DA !important; }
.footer-nav-link:hover    { color: #0A74DA !important; }
.footer-nav-link--blue:hover { color: #60A5FA !important; }
</style>

<footer class="site-footer" style="background: #1F2937; color: white; padding: 64px 0 24px;">
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 24px;">
        
        <!-- Main Footer Grid -->
        <div class="footer-grid" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 32px; margin-bottom: 48px;">
            
            <!-- Brand Column -->
            <div>
                <a href="<?php echo esc_url(home_url('/')); ?>" style="display: inline-flex; align-items: center; gap: 10px; text-decoration: none; margin-bottom: 16px;">
                    <div style="width: 42px; height: 42px; background: linear-gradient(135deg, #0A74DA 0%, #1D4ED8 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <svg width="24" height="24" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </div>
                    <span style="font-size: 1.25rem; font-weight: 700; color: white;">
                        MyHealth<span style="color: #0A74DA;">Hub</span>
                    </span>
                </a>
                <p class="brand-desc" style="color: #9CA3AF; font-size: 0.95rem; line-height: 1.7; margin-bottom: 24px; max-width: 300px;">
                    Connecting you with verified mental health professionals in the Raleigh/Triangle area. Your journey to better mental health starts here.
                </p>
                <div class="social-links" style="display: flex; gap: 12px;">
                    <a href="#" class="footer-social-link" style="width: 40px; height: 40px; background: #374151; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: background 0.2s;">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg>
                    </a>
                    <a href="#" class="footer-social-link" style="width: 40px; height: 40px; background: #374151; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: background 0.2s;">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </a>
                    <a href="#" class="footer-social-link" style="width: 40px; height: 40px; background: #374151; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: background 0.2s;">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                    </a>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div>
                <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 20px; color: white;">Quick Links</h4>
                <ul style="list-style: none; margin: 0; padding: 0;">
                    <li style="margin-bottom: 12px;">
                        <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" class="footer-nav-link" style="color: #9CA3AF; text-decoration: none; font-size: 0.9rem; transition: color 0.2s;">All Providers</a>
                    </li>
                    <li style="margin-bottom: 12px;">
                        <a href="<?php echo esc_url(home_url('/get-matched/')); ?>" class="footer-nav-link" style="color: #9CA3AF; text-decoration: none; font-size: 0.9rem; transition: color 0.2s;">Get Matched</a>
                    </li>
                    <li style="margin-bottom: 12px;">
                        <a href="<?php echo esc_url(home_url('/about/')); ?>" class="footer-nav-link" style="color: #9CA3AF; text-decoration: none; font-size: 0.9rem; transition: color 0.2s;">About Us</a>
                    </li>
                    <li style="margin-bottom: 12px;">
                        <a href="<?php echo esc_url(home_url('/contact/')); ?>" class="footer-nav-link" style="color: #9CA3AF; text-decoration: none; font-size: 0.9rem; transition: color 0.2s;">Contact</a>
                    </li>
                </ul>
            </div>
            
            <!-- Legal -->
            <div>
                <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 20px; color: white;">Legal</h4>
                <ul style="list-style: none; margin: 0; padding: 0;">
                    <li style="margin-bottom: 12px;">
                        <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>" class="footer-nav-link" style="color: #9CA3AF; text-decoration: none; font-size: 0.9rem; transition: color 0.2s;">Privacy Policy</a>
                    </li>
                    <li style="margin-bottom: 12px;">
                        <a href="<?php echo esc_url(home_url('/terms/')); ?>" class="footer-nav-link" style="color: #9CA3AF; text-decoration: none; font-size: 0.9rem; transition: color 0.2s;">Terms of Service</a>
                    </li>
                    <li style="margin-bottom: 12px;">
                        <a href="<?php echo esc_url(home_url('/disclaimer/')); ?>" class="footer-nav-link" style="color: #9CA3AF; text-decoration: none; font-size: 0.9rem; transition: color 0.2s;">Disclaimer</a>
                    </li>
                    <li style="margin-bottom: 12px;">
                        <a href="<?php echo esc_url(home_url('/how-we-verify/')); ?>" class="footer-nav-link" style="color: #9CA3AF; text-decoration: none; font-size: 0.9rem; transition: color 0.2s;">How We Verify</a>
                    </li>
                </ul>
            </div>
            
            <!-- For Providers -->
            <div>
                <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 20px; color: white;">For Providers</h4>
                <ul style="list-style: none; margin: 0; padding: 0;">
                    <li style="margin-bottom: 12px;">
                        <a href="<?php echo esc_url(home_url('/add-your-practice/')); ?>" class="footer-nav-link footer-nav-link--blue" style="color: #0A74DA; text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: color 0.2s;">List for Free</a>
                    </li>
                    <li style="margin-bottom: 12px;">
                        <a href="<?php echo esc_url(home_url('/listings/')); ?>" class="footer-nav-link" style="color: #9CA3AF; text-decoration: none; font-size: 0.9rem; transition: color 0.2s;">Claim Your Profile</a>
                    </li>
                </ul>
            </div>
            
            <!-- Areas Served -->
            <div>
                <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 20px; color: white;">Areas Served</h4>
                <ul style="list-style: none; margin: 0; padding: 0;">
                    <li style="margin-bottom: 12px;">
                        <a href="<?php echo esc_url(home_url('/location/raleigh/')); ?>" class="footer-nav-link" style="color: #9CA3AF; text-decoration: none; font-size: 0.9rem; transition: color 0.2s;">Raleigh</a>
                    </li>
                    <li style="margin-bottom: 12px;">
                        <a href="<?php echo esc_url(home_url('/location/durham/')); ?>" class="footer-nav-link" style="color: #9CA3AF; text-decoration: none; font-size: 0.9rem; transition: color 0.2s;">Durham</a>
                    </li>
                    <li style="margin-bottom: 12px;">
                        <a href="<?php echo esc_url(home_url('/location/cary/')); ?>" class="footer-nav-link" style="color: #9CA3AF; text-decoration: none; font-size: 0.9rem; transition: color 0.2s;">Cary</a>
                    </li>
                    <li style="margin-bottom: 12px;">
                        <a href="<?php echo esc_url(home_url('/location/chapel-hill/')); ?>" class="footer-nav-link" style="color: #9CA3AF; text-decoration: none; font-size: 0.9rem; transition: color 0.2s;">Chapel Hill</a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Bottom Bar -->
        <div class="footer-bottom" style="border-top: 1px solid #374151; padding-top: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <p style="color: #6B7280; font-size: 0.875rem; margin: 0;">
                &copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. All rights reserved.
            </p>
            <p style="color: #6B7280; font-size: 0.875rem; margin: 0;">
                Connecting the Raleigh/Triangle community with quality mental health care.
            </p>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>

</body>
</html>
