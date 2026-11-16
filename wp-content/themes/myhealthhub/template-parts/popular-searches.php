<?php
/**
 * Template Part: Popular Searches
 *
 * Renders SEO-optimised city + specialty link combinations as pill buttons.
 * Uses myhh_get_seo_url() when available, otherwise falls back to add_query_arg().
 *
 * No $args parameters required — search combinations are hardcoded for SEO purposes.
 *
 * @package MyHealthHub
 */
?>
<!-- Popular Searches (SEO City + Specialty Links) -->
<section style="padding: 60px 0; background: white;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 40px;">
            <h2 style="font-size: clamp(1.25rem, 3vw, 1.75rem); font-weight: 700; margin-bottom: 12px; color: #1F2937;">Popular Searches</h2>
            <p style="font-size: 1rem; color: #6B7280;">Find the right specialist in your area.</p>
        </div>
        
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; max-width: 900px; margin: 0 auto;">
            <?php
            // Popular city + specialty combinations for SEO
            $popular_searches = array(
                array('city' => 'raleigh',      'specialty' => 'anxiety',           'label' => 'Anxiety Therapists in Raleigh'),
                array('city' => 'raleigh',      'specialty' => 'depression',        'label' => 'Depression Therapists in Raleigh'),
                array('city' => 'durham',       'specialty' => 'anxiety',           'label' => 'Anxiety Therapists in Durham'),
                array('city' => 'cary',         'specialty' => 'couples-therapy',   'label' => 'Couples Therapy in Cary'),
                array('city' => 'raleigh',      'specialty' => 'trauma-ptsd',       'label' => 'Trauma Therapists in Raleigh'),
                array('city' => 'durham',       'specialty' => 'depression',        'label' => 'Depression Therapists in Durham'),
                array('city' => 'chapel-hill',  'specialty' => 'anxiety',           'label' => 'Anxiety Therapists in Chapel Hill'),
                array('city' => 'raleigh',      'specialty' => 'stress-management', 'label' => 'Stress Management in Raleigh'),
            );
            foreach ($popular_searches as $search) :
                $seo_url = function_exists('myhh_get_seo_url')
                    ? myhh_get_seo_url($search['city'], $search['specialty'])
                    : add_query_arg(array('city' => $search['city'], 'specialty' => $search['specialty']), myhh_get_directory_url());
            ?>
                <a href="<?php echo esc_url($seo_url); ?>" 
                   class="category-card"
                   style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: #F8FAFC; border: 1px solid #E5E7EB; border-radius: 20px; text-decoration: none; font-size: 0.875rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0A74DA" stroke-width="2">
                        <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <span style="color: #374151; font-weight: 500;"><?php echo esc_html($search['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
