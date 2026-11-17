<?php
/**
 * Homepage directory browsing, using the data prepared by front-page.php.
 *
 * @package MyHealthHub
 */
$primary_categories = isset($args['primary_categories']) ? $args['primary_categories'] : array();
$browse_items = isset($args['browse_items']) ? $args['browse_items'] : array();
$cities = isset($args['cities']) ? $args['cities'] : array();
?>
<section class="home-section home-browse" aria-labelledby="home-browse-title">
    <div class="container">
        <div class="home-section-heading">
            <span class="home-eyebrow">Explore your options</span>
            <h2 id="home-browse-title">Browse by category, specialty or location</h2>
            <p>Explore the directory yourself, or tell Mia what you need for guided recommendations.</p>
        </div>

        <div class="home-browse-controls" aria-label="Browse options">
            <button type="button" class="wellness-tab-btn active" data-tab="categories" aria-controls="wellness-categories" aria-pressed="true">Categories</button>
            <button type="button" class="wellness-tab-btn" data-tab="specialties" aria-controls="wellness-specialties" aria-pressed="false">Specialties</button>
            <button type="button" class="wellness-tab-btn" data-tab="locations" aria-controls="wellness-locations" aria-pressed="false">Locations</button>
        </div>

        <div id="wellness-categories" class="wellness-tab-content" data-tab="categories">
            <div class="home-card-grid">
                <?php if ($primary_categories && !is_wp_error($primary_categories)) : ?>
                    <?php foreach ($primary_categories as $category) : ?>
                        <a class="home-discovery-card" href="<?php echo esc_url(get_term_link($category)); ?>">
                            <span class="home-card-icon" aria-hidden="true">+</span>
                            <span class="home-card-title"><?php echo esc_html($category->name); ?></span>
                            <span class="home-card-detail"><?php echo esc_html($category->count); ?> providers</span>
                            <span class="home-card-arrow" aria-hidden="true">&rarr;</span>
                        </a>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="home-empty">No categories are available right now.</p>
                <?php endif; ?>
            </div>
        </div>

        <div id="wellness-specialties" class="wellness-tab-content" data-tab="specialties">
            <div class="home-card-grid">
                <?php if ($browse_items) : ?>
                    <?php foreach (array_slice($browse_items, 0, 12) as $item) : ?>
                        <a class="home-discovery-card" href="<?php echo esc_url($item['link']); ?>">
                            <span class="home-card-icon" aria-hidden="true">+</span>
                            <span class="home-card-title"><?php echo esc_html($item['name']); ?></span>
                            <span class="home-card-detail"><?php echo esc_html($item['count']); ?> providers</span>
                            <span class="home-card-arrow" aria-hidden="true">&rarr;</span>
                        </a>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="home-empty">No specialties are available right now.</p>
                <?php endif; ?>
            </div>
        </div>

        <div id="wellness-locations" class="wellness-tab-content" data-tab="locations">
            <div class="home-card-grid">
                <?php if ($cities && !is_wp_error($cities)) : ?>
                    <?php foreach (array_slice($cities, 0, 6) as $city) : ?>
                        <a class="home-discovery-card" href="<?php echo esc_url(add_query_arg('city', $city->slug, myhh_get_directory_url())); ?>">
                            <span class="home-card-icon" aria-hidden="true">&#9678;</span>
                            <span class="home-card-title"><?php echo esc_html($city->name); ?></span>
                            <span class="home-card-detail"><?php echo esc_html($city->count); ?> providers</span>
                            <span class="home-card-arrow" aria-hidden="true">&rarr;</span>
                        </a>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="home-empty">No locations are available right now.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="home-section-action"><a class="home-button home-button-secondary" href="<?php echo esc_url(myhh_get_directory_url()); ?>">Browse all providers <span aria-hidden="true">&rarr;</span></a></div>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var buttons = document.querySelectorAll('.home-browse .wellness-tab-btn');
    var panels = document.querySelectorAll('.home-browse .wellness-tab-content');

    function selectBrowse(name) {
        buttons.forEach(function(button) {
            var selected = button.dataset.tab === name;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
        panels.forEach(function(panel) {
            panel.hidden = panel.dataset.tab !== name;
        });
    }

    buttons.forEach(function(button) {
        button.addEventListener('click', function() {
            selectBrowse(button.dataset.tab);
        });
    });
    selectBrowse('categories');
});
</script>
