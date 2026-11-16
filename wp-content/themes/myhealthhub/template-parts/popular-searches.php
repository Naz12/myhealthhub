<?php
/**
 * Template Part: Popular Searches
 *
 * Renders SEO-optimised city + specialty link combinations as modern interactive pills.
 * Uses myhh_get_seo_url() when available, otherwise falls back to add_query_arg().
 *
 * No $args parameters required — search combinations are hardcoded for SEO purposes.
 *
 * @package MyHealthHub
 */
?>
<!-- Popular Searches - MODERN INTERACTIVE -->
<section style="padding: 100px 0; background: linear-gradient(135deg, #f9fafb 0%, #ffffff 50%, #f9fafb 100%); position: relative; overflow: hidden;">
    <!-- Background decorative elements -->
    <div style="position: absolute; top: -80px; right: -80px; width: 300px; height: 300px; background: radial-gradient(circle, rgba(102, 126, 234, 0.08) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>
    <div style="position: absolute; bottom: -100px; left: -50px; width: 350px; height: 350px; background: radial-gradient(circle, rgba(245, 87, 108, 0.06) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>
    
    <div class="container" style="position: relative; z-index: 1;">
        <!-- Header -->
        <div style="text-align: center; margin-bottom: 70px;">
            <span style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 18px; border-radius: 50px; font-size: 0.8rem; font-weight: 700; margin-bottom: 18px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);">🔥 Trending Now</span>
            <h2 style="font-size: clamp(1.75rem, 4vw, 3rem); font-weight: 800; margin-bottom: 16px; color: #1a1a2e; letter-spacing: -0.02em;">Popular Searches</h2>
            <p style="font-size: 1.0625rem; color: #6B7280; max-width: 600px; margin: 0 auto; line-height: 1.7;">Discover what others are searching for. Find the right specialist in your area.</p>
        </div>
        
        <!-- Search Pills Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; max-width: 1100px; margin: 0 auto; padding: 0 20px;">
            <?php
            // Popular city + specialty combinations for SEO
            $popular_searches = array(
                array('city' => 'raleigh',      'specialty' => 'anxiety',           'label' => 'Anxiety Therapists in Raleigh',       'icon' => '⚡', 'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'),
                array('city' => 'raleigh',      'specialty' => 'depression',        'label' => 'Depression Therapists in Raleigh',   'icon' => '💙', 'gradient' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)'),
                array('city' => 'durham',       'specialty' => 'anxiety',           'label' => 'Anxiety Therapists in Durham',        'icon' => '🧠', 'gradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)'),
                array('city' => 'cary',         'specialty' => 'couples-therapy',   'label' => 'Couples Therapy in Cary',             'icon' => '💑', 'gradient' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)'),
                array('city' => 'raleigh',      'specialty' => 'trauma-ptsd',       'label' => 'Trauma Therapists in Raleigh',        'icon' => '🛡️', 'gradient' => 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)'),
                array('city' => 'durham',       'specialty' => 'depression',        'label' => 'Depression Therapists in Durham',    'icon' => '🌱', 'gradient' => 'linear-gradient(135deg, #30b0fe 0%, #5a4fbf 100%)'),
                array('city' => 'chapel-hill',  'specialty' => 'anxiety',           'label' => 'Anxiety Therapists in Chapel Hill',   'icon' => '🕊️', 'gradient' => 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)'),
                array('city' => 'raleigh',      'specialty' => 'stress-management', 'label' => 'Stress Management in Raleigh',        'icon' => '🧘', 'gradient' => 'linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%)'),
            );
            
            foreach ($popular_searches as $index => $search) :
                $seo_url = function_exists('myhh_get_seo_url')
                    ? myhh_get_seo_url($search['city'], $search['specialty'])
                    : add_query_arg(array('city' => $search['city'], 'specialty' => $search['specialty']), myhh_get_directory_url());
            ?>
                <a href="<?php echo esc_url($seo_url); ?>" 
                   class="popular-search-pill scroll-slide-left"
                   style="display: flex; align-items: center; gap: 12px; padding: 18px 24px; background: <?php echo $search['gradient']; ?>; border-radius: 16px; text-decoration: none; border: 2px solid transparent; transition: all 0.3s ease; position: relative; overflow: hidden; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1); cursor: pointer;"
                   onmouseover="this.style.transform='translateY(-6px) scale(1.02)'; this.style.boxShadow='0 16px 40px rgba(0, 0, 0, 0.15)'; this.style.borderColor='rgba(255, 255, 255, 0.3)';"
                   onmouseout="this.style.transform='none'; this.style.boxShadow='0 8px 24px rgba(0, 0, 0, 0.1)'; this.style.borderColor='transparent';">
                    
                    <!-- Background shine effect -->
                    <div style="position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%); pointer-events: none;"></div>
                    
                    <!-- Icon -->
                    <span style="font-size: 1.5rem; position: relative; z-index: 2; flex-shrink: 0;"><?php echo $search['icon']; ?></span>
                    
                    <!-- Text -->
                    <div style="position: relative; z-index: 2; flex: 1;">
                        <span style="color: white; font-weight: 700; font-size: 0.95rem; display: block; line-height: 1.2;"><?php echo esc_html($search['label']); ?></span>
                    </div>
                    
                    <!-- Arrow icon -->
                    <svg width="18" height="18" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24" style="position: relative; z-index: 2; flex-shrink: 0; opacity: 0.8; transition: transform 0.3s ease;" class="search-arrow">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- View All Button -->
        <div style="text-align: center; margin-top: 60px;">
            <a href="<?php echo esc_url(myhh_get_directory_url()); ?>" 
               style="display: inline-flex; align-items: center; gap: 10px; padding: 14px 36px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 700; text-decoration: none; border-radius: 50px; box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3); transition: all 0.3s ease; font-size: 0.95rem; border: 2px solid rgba(102, 126, 234, 0.3);"
               onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 12px 35px rgba(102, 126, 234, 0.4)';"
               onmouseout="this.style.transform='none'; this.style.boxShadow='0 8px 25px rgba(102, 126, 234, 0.3)';">
                Browse All Specialists
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </a>
        </div>
    </div>
</section>

<style>
.popular-search-pill:hover .search-arrow {
    transform: translateX(3px);
}
</style>
