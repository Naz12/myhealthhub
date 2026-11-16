<?php
/**
 * Template Part: Browse Locations
 *
 * Renders the "Browse by Location" city button grid for SEO internal linking.
 * Displays up to 6 cities from the myhh_city taxonomy.
 *
 * Expected $args keys:
 *   - 'cities' (array|WP_Error) — Result of get_terms() for myhh_city taxonomy.
 *                                 Falls back to a fresh get_terms() call if not provided.
 *
 * @package MyHealthHub
 */

// Accept cities from $args or fall back to a direct query
$cities = isset($args['cities']) ? $args['cities'] : get_terms(array(
    'taxonomy'   => 'myhh_city',
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
));
?>
<!-- Browse by Location - MODERN REDESIGN -->
<section style="padding: 100px 0; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); position: relative; overflow: hidden;">
    <!-- Animated overlay -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: radial-gradient(circle at 20% 50%, rgba(102, 126, 234, 0.1) 0%, transparent 50%), radial-gradient(circle at 80% 80%, rgba(245, 87, 108, 0.08) 0%, transparent 50%); pointer-events: none;"></div>
    
    <div class="container" style="position: relative; z-index: 1;">
        <!-- Header Section -->
        <div style="text-align: center; margin-bottom: 70px;">
            <h2 style="font-size: clamp(2rem, 5vw, 3rem); font-weight: 800; margin-bottom: 16px; color: white; letter-spacing: -0.02em;">Browse by Location</h2>
            <p style="font-size: 1.125rem; color: rgba(255, 255, 255, 0.8); max-width: 700px; margin: 0 auto; line-height: 1.7;">Find wellness professionals near you in the Triangle area. Discover providers in your neighborhood.</p>
        </div>
        
        <!-- Location Cards Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; max-width: 1000px; margin: 0 auto; padding: 0 20px;">
            <?php
            if ($cities && !is_wp_error($cities)) :
                foreach (array_slice($cities, 0, 6) as $index => $city) :
                    // Gradient variations for each card
                    $gradients = array(
                        'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                        'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
                        'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
                        'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)',
                        'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
                        'linear-gradient(135deg, #30b0fe 0%, #5a4fbf 100%)',
                    );
                    $gradient = $gradients[$index % 6];
            ?>
                <a href="<?php echo esc_url(add_query_arg('city', $city->slug, myhh_get_directory_url())); ?>" 
                   class="location-card scroll-scale-up"
                   style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 24px; background: <?php echo $gradient; ?>; border-radius: 20px; text-decoration: none; border: 2px solid rgba(255, 255, 255, 0.2); transition: all 0.3s ease; position: relative; overflow: hidden; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2); min-height: 160px; cursor: pointer;"
                   onmouseover="this.style.transform='translateY(-10px) scale(1.02)'; this.style.boxShadow='0 20px 50px rgba(0, 0, 0, 0.3)'; this.style.borderColor='rgba(255, 255, 255, 0.4)';"
                   onmouseout="this.style.transform='none'; this.style.boxShadow='0 8px 32px rgba(0, 0, 0, 0.2)'; this.style.borderColor='rgba(255, 255, 255, 0.2)';">
                    
                    <!-- Background glow -->
                    <div style="position: absolute; top: -30px; right: -30px; width: 120px; height: 120px; background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>
                    
                    <!-- Icon with animation -->
                    <div style="position: relative; z-index: 2; margin-bottom: 12px;">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" style="filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                    </div>
                    
                    <!-- Text content -->
                    <span style="color: white; font-weight: 800; font-size: 1.25rem; position: relative; z-index: 2; text-align: center; line-height: 1.2;"><?php echo esc_html($city->name); ?></span>
                    <span style="color: rgba(255, 255, 255, 0.9); font-size: 0.9rem; font-weight: 600; position: relative; z-index: 2; margin-top: 6px;"><?php echo esc_html($city->count); ?> providers</span>
                </a>
            <?php endforeach; endif; ?>
        </div>
        
        <!-- Browse All Locations -->
        <div style="text-align: center; margin-top: 60px;">
            <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" 
               style="display: inline-flex; align-items: center; gap: 10px; padding: 14px 32px; background: rgba(255, 255, 255, 0.15); color: white; font-weight: 700; text-decoration: none; border-radius: 50px; border: 2px solid rgba(255, 255, 255, 0.3); transition: all 0.3s ease; backdrop-filter: blur(10px);"
               onmouseover="this.style.background='rgba(255, 255, 255, 0.25)'; this.style.transform='translateY(-2px)';"
               onmouseout="this.style.background='rgba(255, 255, 255, 0.15)'; this.style.transform='none';">
                Explore All Locations
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </a>
        </div>
    </div>
</section>
