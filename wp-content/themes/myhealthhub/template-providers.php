<?php
/**
 * Template Name: Providers Page
 *
 * @package MyHealthHub
 */

get_header();
?>

<!-- Hero Section -->
<section class="page-hero" style="background: linear-gradient(135deg, #0A74DA 0%, #1e40af 100%); padding: 80px 0; color: white; text-align: center;">
    <div class="container">
        <h1 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 16px;">Find a Provider</h1>
        <p style="font-size: 1.25rem; opacity: 0.9; max-width: 600px; margin: 0 auto;">Browse our directory of verified mental health professionals in the Raleigh/Triangle area.</p>
    </div>
</section>

<!-- Search Section -->
<section style="padding: 40px 0; background: #F8FAFC; border-bottom: 1px solid #E5E7EB;">
    <div class="container">
        <div class="search-box" style="background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <form method="get" action="" class="search-form" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                <div style="flex: 2; min-width: 250px; position: relative;">
                    <svg style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; color: #9CA3AF;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="s" placeholder="Search by name, specialty, or keyword..." 
                           style="width: 100%; padding: 14px 14px 14px 48px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem;">
                </div>
                <div style="flex: 1; min-width: 180px;">
                    <select name="city" style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; background: white;">
                        <option value="">All Locations</option>
                        <option value="raleigh">Raleigh</option>
                        <option value="durham">Durham</option>
                        <option value="cary">Cary</option>
                        <option value="chapel-hill">Chapel Hill</option>
                        <option value="apex">Apex</option>
                    </select>
                </div>
                <div style="flex: 1; min-width: 180px;">
                    <select name="specialty" style="width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 1rem; background: white;">
                        <option value="">All Specialties</option>
                        <option value="anxiety">Anxiety</option>
                        <option value="depression">Depression</option>
                        <option value="trauma">Trauma & PTSD</option>
                        <option value="relationships">Relationships</option>
                        <option value="couples">Couples Therapy</option>
                        <option value="family">Family</option>
                        <option value="grief">Grief</option>
                    </select>
                </div>
                <button type="submit" style="padding: 14px 28px; background: linear-gradient(135deg, #0A74DA 0%, #1D4ED8 100%); color: white; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer;">
                    Search
                </button>
            </form>
        </div>
    </div>
</section>

<!-- Providers Directory -->
<section class="section-standard" style="padding: 80px 0; background: white;">
    <div class="container">
        <!-- Placeholder - Directory Coming Soon -->
        <div class="directory-placeholder" style="text-align: center; padding: 80px 20px;">
            <div style="width: 100px; height: 100px; background: linear-gradient(135deg, #EEF2FF 0%, #DBEAFE 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 32px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#0A74DA" stroke-width="1.5">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
            <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 16px; color: #1F2937;">Provider Directory Coming Soon</h2>
            <p style="color: #6B7280; max-width: 500px; margin: 0 auto 32px; font-size: 1.1rem; line-height: 1.7;">We are currently building our network of verified mental health professionals in the Raleigh/Triangle area. Our full directory will launch soon!</p>
            
            <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; margin-bottom: 48px;">
                <a href="<?php echo esc_url(home_url('/get-matched/')); ?>" style="background: linear-gradient(135deg, #0A74DA 0%, #1D4ED8 100%); color: white; padding: 16px 32px; border-radius: 12px; font-weight: 600; text-decoration: none;">Get Matched Now</a>
                <a href="<?php echo esc_url(home_url('/contact/')); ?>" style="background: white; color: #0A74DA; border: 2px solid #0A74DA; padding: 14px 30px; border-radius: 12px; font-weight: 600; text-decoration: none;">Contact Us</a>
            </div>
            
            <!-- Stats -->
            <div class="directory-stats" style="display: flex; justify-content: center; gap: 64px; flex-wrap: wrap; padding-top: 48px; border-top: 1px solid #E5E7EB;">
                <div>
                    <div class="stat-number" style="font-size: 2.5rem; font-weight: 800; color: #0A74DA;">500+</div>
                    <div class="stat-label" style="color: #6B7280; font-size: 0.95rem;">Providers Joining</div>
                </div>
                <div>
                    <div class="stat-number" style="font-size: 2.5rem; font-weight: 800; color: #0A74DA;">12</div>
                    <div class="stat-label" style="color: #6B7280; font-size: 0.95rem;">Cities Covered</div>
                </div>
                <div>
                    <div class="stat-number" style="font-size: 2.5rem; font-weight: 800; color: #0A74DA;">50+</div>
                    <div class="stat-label" style="color: #6B7280; font-size: 0.95rem;">Specialties</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Browse by Specialty -->
<section class="section-standard" style="padding: 80px 0; background: #F8FAFC;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 48px;">
            <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 8px; color: #1F2937;">Browse by Specialty</h2>
            <p style="color: #6B7280;">Coming soon - filter providers by specialty</p>
        </div>
        
        <div class="specialty-grid" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; max-width: 900px; margin: 0 auto;">
            <?php
            $specialties = array(
                array('name' => 'Anxiety', 'color' => '#EEF2FF'),
                array('name' => 'Depression', 'color' => '#FEF3C7'),
                array('name' => 'Trauma', 'color' => '#FCE7F3'),
                array('name' => 'Relationships', 'color' => '#D1FAE5'),
                array('name' => 'Couples', 'color' => '#E0E7FF'),
                array('name' => 'Family', 'color' => '#FED7AA'),
                array('name' => 'Grief', 'color' => '#E5E7EB'),
                array('name' => 'Stress', 'color' => '#A7F3D0'),
                array('name' => 'Self-Esteem', 'color' => '#C7D2FE'),
                array('name' => 'Work-Life', 'color' => '#FBCFE8'),
            );
            foreach ($specialties as $s) : ?>
                <div style="background: <?php echo $s['color']; ?>; padding: 20px; border-radius: 12px; text-align: center;">
                    <span style="color: #374151; font-weight: 600; font-size: 0.95rem;"><?php echo $s['name']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section" style="padding: 80px 0; background: linear-gradient(135deg, #0A74DA 0%, #1e40af 100%); color: white; text-align: center;">
    <div class="container">
        <h2 style="font-size: 2rem; font-weight: 700; margin-bottom: 16px;">Are You a Mental Health Provider?</h2>
        <p style="font-size: 1.125rem; opacity: 0.9; margin-bottom: 32px; max-width: 500px; margin-left: auto; margin-right: auto;">Join our directory and connect with more patients in the Raleigh/Triangle area.</p>
        <a href="<?php echo esc_url(home_url('/claim-listing/')); ?>" style="display: inline-block; background: white; color: #0A74DA; padding: 16px 32px; border-radius: 12px; font-weight: 600; text-decoration: none;">Claim Your Listing</a>
    </div>
</section>

<?php
get_footer();
