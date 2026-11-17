<?php
/**
 * Suggested city and specialty directory searches.
 *
 * @package MyHealthHub
 */
$popular_searches = array(
    array('city' => 'raleigh', 'specialty' => 'anxiety', 'label' => 'Anxiety Therapists in Raleigh'),
    array('city' => 'raleigh', 'specialty' => 'depression', 'label' => 'Depression Therapists in Raleigh'),
    array('city' => 'durham', 'specialty' => 'anxiety', 'label' => 'Anxiety Therapists in Durham'),
    array('city' => 'cary', 'specialty' => 'couples-therapy', 'label' => 'Couples Therapy in Cary'),
    array('city' => 'raleigh', 'specialty' => 'trauma-ptsd', 'label' => 'Trauma Therapists in Raleigh'),
    array('city' => 'durham', 'specialty' => 'depression', 'label' => 'Depression Therapists in Durham'),
    array('city' => 'chapel-hill', 'specialty' => 'anxiety', 'label' => 'Anxiety Therapists in Chapel Hill'),
    array('city' => 'raleigh', 'specialty' => 'stress-management', 'label' => 'Stress Management in Raleigh'),
);
?>
<section class="home-section home-section-tinted" aria-labelledby="home-searches-title">
    <div class="container">
        <div class="home-section-heading">
            <span class="home-eyebrow">A place to start</span>
            <h2 id="home-searches-title">Suggested searches</h2>
            <p>Explore specialties and providers in communities near you.</p>
        </div>
        <div class="home-search-grid">
            <?php foreach ($popular_searches as $search) :
                $seo_url = function_exists('myhh_get_seo_url')
                    ? myhh_get_seo_url($search['city'], $search['specialty'])
                    : add_query_arg(array('city' => $search['city'], 'specialty' => $search['specialty']), myhh_get_directory_url());
                ?>
                <a class="home-search-card" href="<?php echo esc_url($seo_url); ?>">
                    <span class="home-card-icon" aria-hidden="true">&#9906;</span>
                    <span><?php echo esc_html($search['label']); ?></span>
                    <span class="home-card-arrow" aria-hidden="true">&rarr;</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
