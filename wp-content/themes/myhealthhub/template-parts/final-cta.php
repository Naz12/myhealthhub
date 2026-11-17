<?php
/**
 * Homepage provider discovery and matching calls to action.
 *
 * @package MyHealthHub
 */
?>
<section class="home-section" aria-labelledby="home-cta-title">
    <div class="container">
        <div class="home-final-cta">
            <h2 id="home-cta-title">Take the next step toward the right support</h2>
            <p>Explore providers or get help finding a match that fits your needs.</p>
            <div class="home-cta-actions">
                <a class="home-button home-button-light" href="<?php echo esc_url(myhh_get_directory_url()); ?>">Find a provider <span aria-hidden="true">&rarr;</span></a>
                <a class="home-cta-link" href="<?php echo esc_url(home_url('/get-matched/')); ?>">Get matched</a>
            </div>
        </div>
    </div>
</section>
