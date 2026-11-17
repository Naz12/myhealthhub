<?php
/**
 * Links for providers to submit or claim a listing.
 *
 * @package MyHealthHub
 */
?>
<section class="home-section home-section-tinted" aria-labelledby="home-provider-title">
    <div class="container">
        <div class="home-section-heading">
            <span class="home-eyebrow">For professionals</span>
            <h2 id="home-provider-title">Connect with your community</h2>
            <p>Join the directory or manage an existing provider profile.</p>
        </div>
        <div class="home-provider-grid">
            <div class="home-provider-card">
                <h3>Add your practice</h3>
                <p>Submit your practice to the directory.</p>
                <a class="home-button home-button-secondary" href="<?php echo esc_url(home_url('/add-your-practice/')); ?>">Submit your practice <span aria-hidden="true">&rarr;</span></a>
            </div>
            <div class="home-provider-card">
                <h3>Claim your profile</h3>
                <p>Find an existing listing and request to manage its information.</p>
                <a class="home-button home-button-secondary" href="<?php echo esc_url(home_url('/listings/')); ?>">Find your profile <span aria-hidden="true">&rarr;</span></a>
            </div>
        </div>
    </div>
</section>
