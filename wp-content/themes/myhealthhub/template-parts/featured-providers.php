<?php
/**
 * Template Part: Featured Providers
 *
 * Renders a diverse grid of up to 6 featured provider cards.
 * Providers are selected to maximise diversity across license types, specialties, and cities.
 * Each card delegates rendering to template-parts/listing-card.php via get_template_part().
 *
 * No $args parameters required — this template runs its own WP_Query logic internally
 * using the same algorithm from front-page.php.
 *
 * @package MyHealthHub
 */

// Get a diverse mix of providers across license types, specialties, and cities
$license_types = get_terms(array('taxonomy' => 'myhh_license_type', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC'));
$specialties   = get_terms(array('taxonomy' => 'myhh_specialty',    'hide_empty' => true, 'parent' => 0, 'orderby' => 'count', 'order' => 'DESC'));
$cities        = get_terms(array('taxonomy' => 'myhh_city',         'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC'));

$diverse_providers   = array();
$used_ids            = array();
$used_license_types  = array();
$used_specialties    = array();
$used_cities         = array();

// Helper: get provider's primary specialty term_id
$get_primary_specialty = function($post_id) {
    $terms = get_the_terms($post_id, 'myhh_specialty');
    if ($terms && !is_wp_error($terms) && !empty($terms)) {
        foreach ($terms as $term) {
            if ($term->parent == 0) {
                return $term->term_id;
            }
            $parent = get_term($term->parent, 'myhh_specialty');
            if ($parent && !is_wp_error($parent)) {
                return $parent->term_id;
            }
        }
        return $terms[0]->term_id;
    }
    return 0;
};

// Step 1: 1 provider per license type (up to 4)
if ($license_types && !is_wp_error($license_types)) {
    foreach ($license_types as $lt) {
        $providers = get_posts(array(
            'post_type'      => 'gd_place',
            'posts_per_page' => 1,
            'orderby'        => 'rand',
            'tax_query'      => array(array(
                'taxonomy' => 'myhh_license_type',
                'field'    => 'term_id',
                'terms'    => $lt->term_id,
            )),
        ));
        foreach ($providers as $p) {
            if (!in_array($p->ID, $used_ids)) {
                $diverse_providers[]   = $p;
                $used_ids[]            = $p->ID;
                $used_license_types[]  = $lt->term_id;
                $spec_id = $get_primary_specialty($p->ID);
                if ($spec_id) $used_specialties[] = $spec_id;
            }
        }
        if (count($diverse_providers) >= 4) break;
    }
}

// Step 2: Fill remaining slots from different specialties (up to 6 total)
if (count($diverse_providers) < 6 && $specialties && !is_wp_error($specialties)) {
    foreach ($specialties as $spec) {
        if (in_array($spec->term_id, $used_specialties)) continue;
        $providers = get_posts(array(
            'post_type'      => 'gd_place',
            'posts_per_page' => 1,
            'orderby'        => 'rand',
            'post__not_in'   => $used_ids,
            'tax_query'      => array(array(
                'taxonomy' => 'myhh_specialty',
                'field'    => 'term_id',
                'terms'    => $spec->term_id,
            )),
        ));
        foreach ($providers as $p) {
            if (!in_array($p->ID, $used_ids)) {
                $diverse_providers[] = $p;
                $used_ids[]          = $p->ID;
                $used_specialties[]  = $spec->term_id;
            }
        }
        if (count($diverse_providers) >= 6) break;
    }
}

// Step 3: Fill remaining slots from different cities (up to 6 total)
if (count($diverse_providers) < 6 && $cities && !is_wp_error($cities)) {
    foreach ($cities as $city) {
        if (in_array($city->term_id, $used_cities)) continue;
        $providers = get_posts(array(
            'post_type'      => 'gd_place',
            'posts_per_page' => 1,
            'orderby'        => 'rand',
            'post__not_in'   => $used_ids,
            'tax_query'      => array(array(
                'taxonomy' => 'myhh_city',
                'field'    => 'term_id',
                'terms'    => $city->term_id,
            )),
        ));
        foreach ($providers as $p) {
            if (!in_array($p->ID, $used_ids)) {
                $diverse_providers[] = $p;
                $used_ids[]          = $p->ID;
                $used_cities[]       = $city->term_id;
            }
        }
        if (count($diverse_providers) >= 6) break;
    }
}

// Shuffle for extra variety, cap at 6
shuffle($diverse_providers);
$featured_providers = array_slice($diverse_providers, 0, 6);
?>

<?php if ($featured_providers) : ?>
<section style="padding: 80px 0; background: white;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 56px;">
            <span style="display: inline-block; background: #D1FAE5; color: #059669; padding: 6px 16px; border-radius: 20px; font-size: 0.875rem; font-weight: 600; margin-bottom: 16px;">Featured Providers</span>
            <h2 style="font-size: clamp(1.5rem, 4vw, 2.5rem); font-weight: 700; margin-bottom: 16px; color: #1F2937;">Top Wellness Professionals</h2>
            <p style="font-size: 1.125rem; color: #6B7280; max-width: 600px; margin: 0 auto;">Wellness professionals ready to help you on your journey.</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 28px;">
            <?php foreach ($featured_providers as $provider) :
                $provider_id         = $provider->ID;
                $credentials         = get_post_meta($provider_id, 'provider_credentials', true);
                $session_type        = get_post_meta($provider_id, 'session_type', true);
                $short_bio           = get_post_meta($provider_id, 'short_bio', true);
                $is_claimed          = get_post_meta($provider_id, '_myhh_claimed', true);
                $verification_status = get_post_meta($provider_id, 'verification_status', true);
                $thumbnail           = get_the_post_thumbnail_url($provider_id, 'medium');
                $phone               = get_post_meta($provider_id, 'phone', true);
                $website             = get_post_meta($provider_id, 'website', true);
                $booking_link        = get_post_meta($provider_id, 'booking_link', true);
                $street              = get_post_meta($provider_id, 'street', true);
                $zip                 = get_post_meta($provider_id, 'zip', true);
                $npi_primary_taxonomy = get_post_meta($provider_id, 'npi_primary_taxonomy', true);
                $npi_license_number  = get_post_meta($provider_id, 'npi_license_number', true);
                $npi_license_state   = get_post_meta($provider_id, 'npi_license_state', true);
                $provider_specialties = get_the_terms($provider_id, 'myhh_specialty');
                $provider_cities      = get_the_terms($provider_id, 'myhh_city');
                $provider_license     = get_the_terms($provider_id, 'myhh_license_type');

                $specialty_names = array();
                if ($provider_specialties && !is_wp_error($provider_specialties)) {
                    $specialty_names = wp_list_pluck($provider_specialties, 'name');
                }

                $city_name = '';
                if ($provider_cities && !is_wp_error($provider_cities)) {
                    $city_name = $provider_cities[0]->name;
                }

                $license_name = '';
                if ($provider_license && !is_wp_error($provider_license)) {
                    $license_name = $provider_license[0]->name;
                }

                // Fallback: check GeoDirectory meta and raw post meta for city
                if (empty($city_name)) {
                    $gd_city    = function_exists('geodir_get_post_meta') ? geodir_get_post_meta($provider_id, 'city', true) : '';
                    $city_meta  = get_post_meta($provider_id, 'city', true);
                    $city_value = !empty($gd_city) ? $gd_city : $city_meta;
                    if ($city_value) {
                        if (is_numeric($city_value)) {
                            $city_term = get_term(intval($city_value), 'myhh_city');
                            if ($city_term && !is_wp_error($city_term)) {
                                $city_name = $city_term->name;
                            }
                        } else {
                            $city_name = $city_value;
                        }
                    }
                }

                // Delegate card rendering to shared listing-card template part
                get_template_part('template-parts/listing-card', null, array(
                    'title'               => $provider->post_title,
                    'permalink'           => get_permalink($provider_id),
                    'image'               => $thumbnail,
                    'excerpt'             => get_the_excerpt($provider_id),
                    'short_bio'           => $short_bio,
                    'credentials'         => $credentials,
                    'session_type'        => $session_type,
                    'specialties'         => $specialty_names,
                    'location'            => $city_name,
                    'street'              => $street,
                    'zip'                 => $zip,
                    'phone'               => $phone,
                    'website'             => $website,
                    'booking_link'        => $booking_link,
                    'verification_status' => $verification_status,
                    'claimed'             => (bool) $is_claimed,
                    'license_type'        => $license_name,
                    'npi_taxonomy'        => $npi_primary_taxonomy,
                    'npi_license'         => $npi_license_number,
                    'npi_license_state'   => $npi_license_state,
                ));
            endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 48px;">
            <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" style="display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(135deg, #0A74DA 0%, #1D4ED8 100%); color: white; padding: 16px 36px; border-radius: 12px; font-weight: 600; text-decoration: none; font-size: 1rem; box-shadow: 0 8px 24px rgba(10, 116, 218, 0.25); transition: all 0.3s;">
                View All Providers
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>
