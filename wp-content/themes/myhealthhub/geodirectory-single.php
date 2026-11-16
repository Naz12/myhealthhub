<?php
/**
 * The template for displaying GeoDirectory single listing pages
 *
 * @package MyHealthHub
 * @version 2.1.0
 */

get_header();
?>
<style>
/* Provider single page hover states — no inline onmouseover/onmouseout handlers */
.gd-session-chip:hover      { background: var(--chip-hover-bg) !important; color: var(--chip-hover-color) !important; }
.gd-city-chip:hover         { background: rgba(255,255,255,0.35) !important; }
.gd-specialty-chip:hover    { background: #4F46E5 !important; color: #fff !important; }
.gd-license-chip:hover      { background: #4F46E5 !important; color: #fff !important; }
.gd-share-btn svg           { pointer-events: none; color: inherit; transition: color 0.2s ease; }
.gd-share-btn--fb           { background: #EFF6FF; color: #1877F2; }
.gd-share-btn--fb:hover     { background: #1877F2 !important; color: #fff !important; }
.gd-share-btn--tw           { background: #EFF6FF; color: #1DA1F2; }
.gd-share-btn--tw:hover     { background: #1DA1F2 !important; color: #fff !important; }
.gd-share-btn--li           { background: #EFF6FF; color: #0A66C2; }
.gd-share-btn--li:hover     { background: #0A66C2 !important; color: #fff !important; }
.gd-share-btn--copy         { background: #F3F4F6; color: #6B7280; }
.gd-share-btn--copy:hover   { background: #6B7280 !important; color: #fff !important; }
</style>
<?php
// Get provider meta data
$post_id = get_the_ID();
$provider_credentials = get_post_meta($post_id, 'provider_credentials', true);
$session_type = get_post_meta($post_id, 'session_type', true);
$booking_link = get_post_meta($post_id, 'booking_link', true);
$website = get_post_meta($post_id, 'website', true);
$insurance_accepted = get_post_meta($post_id, 'insurance_accepted', true);
$languages_spoken = get_post_meta($post_id, 'languages_spoken', true);
$is_claimed = get_post_meta($post_id, '_myhh_claimed', true);
$modalities = get_post_meta($post_id, 'modalities', true);
$short_bio = get_post_meta($post_id, 'short_bio', true);
$full_bio = get_post_meta($post_id, 'full_bio', true);
$phone = get_post_meta($post_id, 'phone', true);
$email = get_post_meta($post_id, 'email', true);
$address = get_post_meta($post_id, 'street', true);
$city_meta = get_post_meta($post_id, 'city', true);
$zip = get_post_meta($post_id, 'zip', true);
$verification_status = get_post_meta($post_id, 'verification_status', true);
$source_url = get_post_meta($post_id, 'source_url', true);
$license_family = get_post_meta($post_id, 'license_family', true);
$verification_source_url = get_post_meta($post_id, 'verification_source_url', true);
$listing_tier = get_post_meta($post_id, 'listing_tier', true);

// NPI Enriched data
$npi_license_number = get_post_meta($post_id, 'npi_license_number', true);
$npi_license_state = get_post_meta($post_id, 'npi_license_state', true);
$npi_primary_taxonomy = get_post_meta($post_id, 'npi_primary_taxonomy', true);
$npi_secondary_taxonomy = get_post_meta($post_id, 'npi_secondary_taxonomy', true);
$npi_active_status = get_post_meta($post_id, 'npi_active_status', true);
$npi_sole_proprietor = get_post_meta($post_id, 'npi_sole_proprietor', true);

// Get NPI from source URL (handle both provider-view and API formats)
$npi_number = '';
if (preg_match('/provider-view\/(\d+)/', $source_url, $m)) {
    $npi_number = $m[1];
} elseif (preg_match('/number=(\d+)/', $source_url, $m)) {
    $npi_number = $m[1];
}

// Build correct NPI provider-view URL
$npi_registry_url = $npi_number ? 'https://npiregistry.cms.hhs.gov/provider-view/' . $npi_number : $source_url;

// Get taxonomies
$specialties = get_the_terms($post_id, 'myhh_specialty');
$cities = get_the_terms($post_id, 'myhh_city');
$license_types = get_the_terms($post_id, 'myhh_license_type');

// Get profile image
$thumbnail = get_the_post_thumbnail_url($post_id, 'large');
$provider_name = get_the_title();

// Generate initials for placeholder
$initials = '';
$name_parts = explode(' ', $provider_name);
foreach ($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
if (strlen($initials) > 2) {
    $initials = substr($initials, 0, 2);
}
?>

<main class="site-main">
    <?php while (have_posts()) : the_post(); ?>
        
        <article id="post-<?php the_ID(); ?>" <?php post_class('geodir-single-wrapper'); ?>>
            
            <!-- Breadcrumb Navigation -->
            <nav style="background: #fff; border-bottom: 1px solid #E5E7EB; padding: 12px 0;">
                <div class="container">
                    <ol style="display: flex; align-items: center; gap: 8px; list-style: none; margin: 0; padding: 0; font-size: 0.875rem;">
                        <li>
                            <a href="<?php echo esc_url(home_url('/')); ?>" style="color: #6B7280; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                </svg>
                                Home
                            </a>
                        </li>
                        <li style="color: #D1D5DB;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </li>
                        <li>
                            <a href="<?php echo esc_url(home_url('/listings/')); ?>" style="color: #6B7280; text-decoration: none;">
                                Providers
                            </a>
                        </li>
                        <li style="color: #D1D5DB;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </li>
                        <?php if ($license_types && !is_wp_error($license_types)) : ?>
                        <li>
                            <a href="<?php echo esc_url(home_url('/listings/?license_type=' . $license_types[0]->slug)); ?>" style="color: #6B7280; text-decoration: none;">
                                <?php echo esc_html($license_types[0]->name); ?>
                            </a>
                        </li>
                        <li style="color: #D1D5DB;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </li>
                        <?php endif; ?>
                        <li style="color: #1F2937; font-weight: 500; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo esc_html($provider_name); ?>
                        </li>
                    </ol>
                </div>
            </nav>
            
            <!-- Header Section -->
            <header class="geodir-single-header" style="background: linear-gradient(135deg, #0A74DA 0%, #0858a8 100%); color: #fff; padding: 48px 0; position: relative; overflow: hidden;">
                <!-- Decorative Background -->
                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; opacity: 0.1; pointer-events: none;">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                                <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/>
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#grid)" />
                    </svg>
                </div>
                
                <div class="container" style="position: relative; z-index: 1;">
                    <div style="display: flex; align-items: center; gap: 24px; flex-wrap: wrap;">
                        
                        <!-- Profile Image -->
                        <div style="flex-shrink: 0;">
                            <?php if ($thumbnail) : ?>
                                <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($provider_name); ?>" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid rgba(255,255,255,0.3); box-shadow: 0 8px 24px rgba(0,0,0,0.2);">
                            <?php else : ?>
                                <div style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, rgba(255,255,255,0.25) 0%, rgba(255,255,255,0.15) 100%); border: 4px solid rgba(255,255,255,0.3); display: flex; align-items: center; justify-content: center; font-size: 2.25rem; font-weight: 700; color: #fff;">
                                    <?php echo esc_html($initials); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Provider Info -->
                        <div style="flex: 1; min-width: 200px;">
                            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 8px;">
                                <span style="display: inline-flex; align-items: center; gap: 5px; background: <?php echo $is_claimed ? 'rgba(5, 150, 105, 0.95)' : 'rgba(107, 114, 128, 0.9)'; ?>; color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 0.8125rem; font-weight: 600;">
                                    <?php if ($is_claimed) : ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                        </svg>
                                        Claimed by Provider
                                    <?php else : ?>
                                        Listed
                                    <?php endif; ?>
                                </span>
                                
                                <?php if ($verification_status) : ?>
                                    <?php
                                    $verified_badge_style = '';
                                    $verified_label = '';
                                    $verified_icon = '';
                                    
                                    if ($verification_status === 'board_confirmed') {
                                        $verified_badge_style = 'background: rgba(5, 150, 105, 0.95); color: #fff;';
                                        $verified_label = 'Board Verified';
                                        $verified_icon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>';
                                    } elseif ($verification_status === 'source_only') {
                                        $verified_badge_style = 'background: rgba(217, 119, 6, 0.95); color: #fff;';
                                        $verified_label = 'Verified Source';
                                        $verified_icon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 12l2 2 4-4"></path><circle cx="12" cy="12" r="10"></circle></svg>';
                                    } elseif ($verification_status === 'core_verified') {
                                        $verified_badge_style = 'background: rgba(37, 99, 235, 0.95); color: #fff;';
                                        $verified_label = 'Verified';
                                        $verified_icon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"></path></svg>';
                                    }
                                    
                                    if ($verified_label) : ?>
                                    <span style="display: inline-flex; align-items: center; gap: 5px; <?php echo $verified_badge_style; ?> padding: 5px 12px; border-radius: 20px; font-size: 0.8125rem; font-weight: 600;">
                                        <?php echo $verified_icon; ?>
                                        <?php echo esc_html($verified_label); ?>
                                    </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if ($session_type && strtolower($session_type) !== 'unknown') : ?>
                                    <?php
                                    $session_colors = array(
                                        'In-Person' => array('bg' => '#D1FAE5', 'color' => '#059669', 'hover_bg' => '#059669', 'hover_color' => '#fff'),
                                        'Virtual' => array('bg' => '#DBEAFE', 'color' => '#2563EB', 'hover_bg' => '#2563EB', 'hover_color' => '#fff'),
                                        'Hybrid' => array('bg' => '#FEF3C7', 'color' => '#D97706', 'hover_bg' => '#D97706', 'hover_color' => '#fff'),
                                    );
                                    $s = isset($session_colors[$session_type]) ? $session_colors[$session_type] : array('bg' => 'rgba(255,255,255,0.2)', 'color' => '#fff', 'hover_bg' => 'rgba(255,255,255,0.35)', 'hover_color' => '#fff');
                                    ?>
                                    <a href="<?php echo esc_url(home_url('/listings/?session_type=' . urlencode($session_type))); ?>" class="gd-session-chip" style="--chip-hover-bg: <?php echo esc_attr($s['hover_bg']); ?>; --chip-hover-color: <?php echo esc_attr($s['hover_color']); ?>; display: inline-flex; align-items: center; gap: 5px; background: <?php echo esc_attr($s['bg']); ?>; color: <?php echo esc_attr($s['color']); ?>; padding: 5px 12px; border-radius: 20px; font-size: 0.8125rem; font-weight: 600; text-decoration: none; transition: all 0.2s ease;">
                                        <?php if ($session_type === 'Virtual') : ?>
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                                        <?php elseif ($session_type === 'In-Person') : ?>
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                        <?php else : ?>
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line></svg>
                                        <?php endif; ?>
                                        <?php echo esc_html($session_type); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <h1 style="font-size: 2rem; font-weight: 700; margin: 0 0 4px; line-height: 1.2;"><?php echo esc_html($provider_name); ?></h1>
                            
                            <?php if ($provider_credentials) : ?>
                                <p style="color: rgba(255,255,255,0.9); font-size: 1rem; margin: 0 0 8px; font-weight: 500;">
                                    <?php echo esc_html($provider_credentials); ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($cities && !is_wp_error($cities)) : ?>
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <?php foreach ($cities as $city) : ?>
                                        <a href="<?php echo esc_url(home_url('/listings/?city=' . $city->slug)); ?>" class="gd-city-chip" style="display: inline-flex; align-items: center; gap: 5px; padding: 6px 14px; background: rgba(255,255,255,0.2); color: #fff; font-size: 0.875rem; font-weight: 500; border-radius: 20px; text-decoration: none; transition: all 0.2s ease;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path>
                                                <circle cx="12" cy="10" r="3"></circle>
                                            </svg>
                                            <?php echo esc_html($city->name); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                </div>
            </header>
            
            <!-- Main Content -->
            <div style="background: #F8FAFC; padding: 40px 0 60px;">
                <div class="container">
                    <div class="geodir-single-content" style="display: grid; gap: 32px;">
                    
                    <!-- Left Column: Main Content -->
                    <div class="geodir-single-main">
                        
                        <!-- Bio & Specialties Card -->
                        <div class="provider-card-main" style="background: #fff; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #E5E7EB; overflow: hidden; margin-bottom: 24px;">
                            <?php if ($short_bio) : ?>
                                <div style="padding: 28px 28px 0;">
                                    <p style="font-size: 1.0625rem; color: #374151; line-height: 1.75; margin: 0;">
                                        <?php echo esc_html($short_bio); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($specialties && !is_wp_error($specialties)) : ?>
                                <div style="padding: 20px 28px 28px;">
                                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                        <?php foreach ($specialties as $specialty) : ?>
                                            <a href="<?php echo esc_url(home_url('/listings/?specialty=' . $specialty->slug)); ?>" class="gd-specialty-chip" style="display: inline-flex; align-items: center; gap: 5px; padding: 8px 16px; background: #EEF2FF; color: #4F46E5; font-size: 0.875rem; font-weight: 500; border-radius: 24px; text-decoration: none; transition: all 0.2s ease;">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                                </svg>
                                                <?php echo esc_html($specialty->name); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Info Cards Grid -->
                        <div style="display: grid; gap: 20px;">
                            
                            <!-- Treatment Approaches -->
                            <?php if ($modalities) : ?>
                                <div class="info-card" style="background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #F3F4F6; overflow: hidden;">
                                    <div style="padding: 20px 24px; border-bottom: 1px solid #F3F4F6; display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 36px; height: 36px; background: #EEF2FF; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <path d="M12 6v6l4 2"></path>
                                            </svg>
                                        </div>
                                        <h3 style="font-size: 1rem; font-weight: 600; margin: 0; color: #1F2937;"><?php _e('Treatment Approaches', 'myhealthhub'); ?></h3>
                                    </div>
                                    <div style="padding: 20px 24px;">
                                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                            <?php 
                                            $modality_array = is_array($modalities) ? $modalities : explode(',', $modalities);
                                            foreach ($modality_array as $modality) : 
                                                $modality = trim($modality);
                                                if ($modality) :
                                            ?>
                                                <span style="padding: 8px 14px; background: #F8FAFC; color: #374151; font-size: 0.875rem; font-weight: 500; border-radius: 8px;">
                                                    <?php echo esc_html($modality); ?>
                                                </span>
                                            <?php endif; endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Insurance Accepted -->
                            <?php if ($insurance_accepted) : ?>
                                <div class="info-card" style="background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #F3F4F6; overflow: hidden;">
                                    <div style="padding: 20px 24px; border-bottom: 1px solid #F3F4F6; display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 36px; height: 36px; background: #D1FAE5; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2">
                                                <path d="M9 12l2 2 4-4"></path>
                                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                            </svg>
                                        </div>
                                        <h3 style="font-size: 1rem; font-weight: 600; margin: 0; color: #1F2937;"><?php _e('Insurance Accepted', 'myhealthhub'); ?></h3>
                                    </div>
                                    <div style="padding: 20px 24px;">
                                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                            <?php 
                                            $insurances = is_array($insurance_accepted) ? $insurance_accepted : explode(',', $insurance_accepted);
                                            foreach ($insurances as $insurance) : 
                                                $insurance = trim($insurance);
                                                if ($insurance) :
                                            ?>
                                                <span style="padding: 6px 12px; background: #F0FDF4; color: #059669; font-size: 0.8125rem; font-weight: 500; border-radius: 6px;">
                                                    <?php echo esc_html($insurance); ?>
                                                </span>
                                            <?php endif; endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Quick Info Row -->
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                                <?php if ($languages_spoken) : ?>
                                    <div class="info-card" style="background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #F3F4F6; padding: 20px;">
                                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <line x1="2" y1="12" x2="22" y2="12"></line>
                                                <path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"></path>
                                            </svg>
                                            <span style="font-size: 0.8125rem; font-weight: 600; color: #6B7280; text-transform: uppercase; letter-spacing: 0.05em;"><?php _e('Languages', 'myhealthhub'); ?></span>
                                        </div>
                                        <p style="color: #1F2937; margin: 0; font-weight: 500;">
                                            <?php echo esc_html(is_array($languages_spoken) ? implode(', ', $languages_spoken) : $languages_spoken); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($session_type && strtolower($session_type) !== 'unknown') : ?>
                                    <div class="info-card" style="background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #F3F4F6; padding: 20px;">
                                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2">
                                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                                <line x1="3" y1="10" x2="21" y2="10"></line>
                                            </svg>
                                            <span style="font-size: 0.8125rem; font-weight: 600; color: #6B7280; text-transform: uppercase; letter-spacing: 0.05em;"><?php _e('Sessions', 'myhealthhub'); ?></span>
                                        </div>
                                        <p style="color: #1F2937; margin: 0; font-weight: 500;"><?php echo esc_html($session_type); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- License & Verification Info -->
                            <?php 
                            $license_family_clean = ($license_family && strtolower($license_family) !== 'unknown') ? $license_family : '';
                            $license_type_name = '';
                            if ($license_types && !is_wp_error($license_types)) {
                                $license_type_name = $license_types[0]->name;
                            }
                            ?>
                            <?php if ($license_family_clean || $license_type_name || $npi_license_number || $npi_primary_taxonomy || $source_url) : ?>
                                <div class="info-card" style="background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #F3F4F6; overflow: hidden;">
                                    <div style="padding: 20px 24px; border-bottom: 1px solid #F3F4F6; display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 36px; height: 36px; background: #EEF2FF; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2">
                                                <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                            </svg>
                                        </div>
                                        <h3 style="font-size: 1rem; font-weight: 600; margin: 0; color: #1F2937;"><?php _e('License & Credentials', 'myhealthhub'); ?></h3>
                                    </div>
                                    <div style="padding: 20px 24px;">
                                        <?php if ($license_type_name) : ?>
                                            <div style="margin-bottom: 16px;">
                                                <span style="font-size: 0.75rem; color: #6B7280; text-transform: uppercase; letter-spacing: 0.05em;">License Type</span>
                                                <div style="margin-top: 6px;">
                                                    <a href="<?php echo esc_url(home_url('/listings/?license_type=' . $license_types[0]->slug)); ?>" class="gd-license-chip" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: #EEF2FF; color: #4F46E5; font-size: 0.875rem; font-weight: 600; border-radius: 8px; text-decoration: none; transition: all 0.2s ease;">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                                        </svg>
                                                        <?php echo esc_html($license_type_name); ?>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php elseif ($license_family_clean) : ?>
                                            <div style="margin-bottom: 16px;">
                                                <span style="font-size: 0.75rem; color: #6B7280; text-transform: uppercase; letter-spacing: 0.05em;">License Type</span>
                                                <p style="margin: 4px 0 0; color: #1F2937; font-weight: 500;">
                                                    <?php 
                                                    $license_labels = array(
                                                        'counselor_lcmhc' => 'Licensed Clinical Mental Health Counselor (LCMHC)',
                                                        'counselor_lpc' => 'Licensed Professional Counselor (LPC)',
                                                        'psychologist' => 'Licensed Psychologist',
                                                        'social_worker_lcsw' => 'Licensed Clinical Social Worker (LCSW)',
                                                        'marriage_therapist_lmft' => 'Licensed Marriage and Family Therapist (LMFT)',
                                                        'psychiatrist' => 'Psychiatrist (MD/DO)',
                                                        'addiction_specialist' => 'Licensed Clinical Addiction Specialist (LCAS)',
                                                    );
                                                    echo esc_html($license_labels[$license_family] ?? $license_family); 
                                                    ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($npi_license_number && $npi_license_number !== $npi_number) : ?>
                                            <div style="margin-bottom: 16px; display: flex; gap: 24px;">
                                                <div>
                                                    <span style="font-size: 0.75rem; color: #6B7280; text-transform: uppercase; letter-spacing: 0.05em;">License Number</span>
                                                    <p style="margin: 4px 0 0; color: #1F2937; font-weight: 500; font-family: monospace; font-size: 0.9375rem;">
                                                        <?php echo esc_html($npi_license_number); ?>
                                                    </p>
                                                </div>
                                                <?php if ($npi_license_state) : ?>
                                                <div>
                                                    <span style="font-size: 0.75rem; color: #6B7280; text-transform: uppercase; letter-spacing: 0.05em;">State</span>
                                                    <p style="margin: 4px 0 0; color: #1F2937; font-weight: 500;">
                                                        <?php echo esc_html($npi_license_state); ?>
                                                    </p>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($npi_primary_taxonomy) : ?>
                                            <div style="margin-bottom: 16px;">
                                                <span style="font-size: 0.75rem; color: #6B7280; text-transform: uppercase; letter-spacing: 0.05em;">Specialization (NPI)</span>
                                                <p style="margin: 4px 0 0; color: #1F2937; font-weight: 500;">
                                                    <?php echo esc_html($npi_primary_taxonomy); ?>
                                                </p>
                                                <?php if ($npi_secondary_taxonomy) : ?>
                                                <p style="margin: 4px 0 0; color: #6B7280; font-size: 0.875rem;">
                                                    Also: <?php echo esc_html($npi_secondary_taxonomy); ?>
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($npi_number) : ?>
                                            <div style="margin-bottom: 16px;">
                                                <span style="font-size: 0.75rem; color: #6B7280; text-transform: uppercase; letter-spacing: 0.05em;">NPI Number</span>
                                                <p style="margin: 4px 0 0; color: #1F2937; font-weight: 500; font-family: monospace;">
                                                    <?php echo esc_html($npi_number); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($npi_active_status) : ?>
                                            <div style="display: flex; gap: 16px; margin-bottom: 16px;">
                                                <div style="display: flex; align-items: center; gap: 6px;">
                                                    <?php if ($npi_active_status === 'A') : ?>
                                                        <span style="width: 8px; height: 8px; background: #10B981; border-radius: 50%; display: inline-block;"></span>
                                                        <span style="color: #059669; font-weight: 500; font-size: 0.875rem;">Active Status</span>
                                                    <?php else : ?>
                                                        <span style="width: 8px; height: 8px; background: #9CA3AF; border-radius: 50%; display: inline-block;"></span>
                                                        <span style="color: #6B7280; font-weight: 500; font-size: 0.875rem;"><?php echo esc_html($npi_active_status); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($npi_sole_proprietor) : ?>
                                                <div style="display: flex; align-items: center; gap: 6px;">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2">
                                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                                        <circle cx="12" cy="7" r="4"></circle>
                                                    </svg>
                                                    <span style="color: #6B7280; font-size: 0.875rem;"><?php echo $npi_sole_proprietor === 'YES' ? 'Sole Proprietor' : 'Group Practice'; ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($npi_number) : ?>
                                            <div>
                                                <a href="https://npiregistry.cms.hhs.gov/" target="_blank" rel="noopener noreferrer" style="color: #0A74DA; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; font-size: 0.875rem;">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                                        <polyline points="15 3 21 3 21 9"></polyline>
                                                        <line x1="10" y1="14" x2="21" y2="3"></line>
                                                    </svg>
                                                    Verify on NPI Registry
                                                </a>
                                                <span style="color: #6B7280; font-size: 0.75rem; margin-left: 8px;">(Search: <?php echo esc_html($npi_number); ?>)</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- About Section (only if different from short_bio) -->
                            <?php if ($full_bio && $full_bio !== $short_bio) : ?>
                                <div class="info-card" style="background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #F3F4F6; overflow: hidden;">
                                    <div style="padding: 20px 24px; border-bottom: 1px solid #F3F4F6;">
                                        <h3 style="font-size: 1rem; font-weight: 600; margin: 0; color: #1F2937;"><?php _e('About', 'myhealthhub'); ?></h3>
                                    </div>
                                    <div style="padding: 20px 24px;">
                                        <div style="color: #4B5563; line-height: 1.8; font-size: 0.9375rem;">
                                            <?php echo wpautop(esc_html($full_bio)); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                        </div>
                        
                        <!-- Testimonials Section -->
                        <?php 
                        if (function_exists('myhh_testimonials_section')) {
                            myhh_testimonials_section($post_id);
                        }
                        ?>
                    </div>
                    
                    <!-- Right Column: Sidebar -->
                    <aside class="geodir-single-sidebar">
                        <div style="background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.1); border: 1px solid #F3F4F6; overflow: hidden;">
                            
                            <!-- Header -->
                            <div style="padding: 20px 24px; border-bottom: 1px solid #F3F4F6; background: #FAFAFA;">
                                <h3 style="font-size: 1rem; font-weight: 600; margin: 0; color: #1F2937;"><?php _e('Contact & Booking', 'myhealthhub'); ?></h3>
                            </div>
                            
                            <div style="padding: 24px;">
                                <?php if ($booking_link) : ?>
                                    <a href="<?php echo esc_url($booking_link); ?>" target="_blank" style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 14px 20px; background: linear-gradient(135deg, #0A74DA 0%, #0858a8 100%); color: #fff; border-radius: 10px; font-weight: 600; font-size: 0.9375rem; text-decoration: none; margin-bottom: 12px; box-shadow: 0 4px 14px rgba(10,116,218,0.25);">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                            <line x1="16" y1="2" x2="16" y2="6"></line>
                                            <line x1="8" y1="2" x2="8" y2="6"></line>
                                            <line x1="3" y1="10" x2="21" y2="10"></line>
                                        </svg>
                                        <?php _e('Book Appointment', 'myhealthhub'); ?>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($website) : ?>
                                    <a href="<?php echo esc_url($website); ?>" target="_blank" style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 12px 20px; background: #fff; color: #0A74DA; border-radius: 10px; font-weight: 600; font-size: 0.875rem; text-decoration: none; border: 2px solid #E5E7EB; margin-bottom: 20px;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <line x1="2" y1="12" x2="22" y2="12"></line>
                                            <path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"></path>
                                        </svg>
                                        <?php _e('Visit Website', 'myhealthhub'); ?>
                                    </a>
                                <?php endif; ?>
                                
                                <!-- Contact Details -->
                                <?php if ($phone || $email || $address) : ?>
                                    <div style="border-top: 1px solid #F3F4F6; padding-top: 20px; margin-top: 4px;">
                                        
                                        <?php if ($phone) : ?>
                                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                                                <div style="width: 32px; height: 32px; background: #EFF6FF; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0A74DA" stroke-width="2">
                                                        <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"></path>
                                                    </svg>
                                                </div>
                                                <div style="min-width: 0;">
                                                    <div style="font-size: 0.6875rem; color: #9CA3AF; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.05em;">Phone</div>
                                                    <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>" style="color: #0A74DA; font-weight: 500; text-decoration: none; font-size: 0.875rem;"><?php echo esc_html($phone); ?></a>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($email) : ?>
                                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                                                <div style="width: 32px; height: 32px; background: #EFF6FF; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0A74DA" stroke-width="2">
                                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                                        <polyline points="22,6 12,13 2,6"></polyline>
                                                    </svg>
                                                </div>
                                                <div style="min-width: 0;">
                                                    <div style="font-size: 0.6875rem; color: #9CA3AF; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.05em;">Email</div>
                                                    <a href="mailto:<?php echo esc_attr($email); ?>" style="color: #0A74DA; font-weight: 500; text-decoration: none; font-size: 0.875rem; word-break: break-all;"><?php echo esc_html($email); ?></a>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($address || $cities || $zip) : ?>
                                            <div style="display: flex; align-items: flex-start; gap: 12px;">
                                                <div style="width: 32px; height: 32px; background: #EFF6FF; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0A74DA" stroke-width="2">
                                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path>
                                                        <circle cx="12" cy="10" r="3"></circle>
                                                    </svg>
                                                </div>
                                                <div style="min-width: 0;">
                                                    <div style="font-size: 0.6875rem; color: #9CA3AF; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.05em;">Address</div>
                                                    <p style="color: #374151; font-size: 0.875rem; margin: 0; line-height: 1.6;">
                                                        <?php if ($address) : ?>
                                                            <?php echo esc_html($address); ?><br>
                                                        <?php endif; ?>
                                                        <?php 
                                                        // Show city from taxonomy or post meta
                                                        $city_display = '';
                                                        if ($cities && !is_wp_error($cities)) {
                                                            $city_display = implode(', ', wp_list_pluck($cities, 'name'));
                                                        } elseif ($city_meta) {
                                                            $city_display = $city_meta;
                                                        }
                                                        // Format ZIP for display
                                                        $zip_display = $zip;
                                                        if ($zip && strlen($zip) > 5) {
                                                            $zip_display = substr($zip, 0, 5);
                                                        }
                                                        if ($city_display) : 
                                                        ?>
                                                            <?php echo esc_html($city_display); ?><?php if ($zip_display) : ?>, NC <?php echo esc_html($zip_display); ?><?php endif; ?>
                                                        <?php elseif ($zip_display) : ?>
                                                            NC <?php echo esc_html($zip_display); ?>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Share & Actions -->
                                <div style="border-top: 1px solid #F3F4F6; padding-top: 20px; margin-top: 20px;">
                                    <div style="font-size: 0.6875rem; color: #9CA3AF; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Share Profile</div>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(get_permalink()); ?>" target="_blank" rel="noopener noreferrer" class="gd-share-btn gd-share-btn--fb" style="width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s ease;" title="Share on Facebook">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                                            </svg>
                                        </a>
                                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(get_permalink()); ?>&text=<?php echo urlencode($provider_name . ' - Mental Health Provider'); ?>" target="_blank" rel="noopener noreferrer" class="gd-share-btn gd-share-btn--tw" style="width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s ease;" title="Share on Twitter">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                                <path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"></path>
                                            </svg>
                                        </a>
                                        <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode(get_permalink()); ?>&title=<?php echo urlencode($provider_name); ?>" target="_blank" rel="noopener noreferrer" class="gd-share-btn gd-share-btn--li" style="width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s ease;" title="Share on LinkedIn">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                                <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path>
                                                <rect x="2" y="9" width="4" height="12"></rect>
                                                <circle cx="4" cy="4" r="2"></circle>
                                            </svg>
                                        </a>
                                        <button id="gd-copy-link-btn" class="gd-share-btn gd-share-btn--copy" style="width: 40px; height: 40px; border-radius: 8px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease;" title="Copy Link">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                                                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Claim Listing -->
                                <div style="border-top: 1px solid #F3F4F6; padding-top: 20px; margin-top: 20px;">
                                    <?php 
                                    if (function_exists('myhh_claim_listing_section')) {
                                        myhh_claim_listing_section($post_id); 
                                    }
                                    ?>
                                </div>

                                <!-- Know a Provider? — Invite/Share Section (icon mode) -->
                                <style>
                                .myhh-referral-section {
                                    border-top: 1px solid #F3F4F6;
                                    padding-top: 16px;
                                    margin-top: 20px;
                                }
                                .myhh-referral-label {
                                    font-size: 0.6875rem;
                                    font-weight: 700;
                                    color: #9CA3AF;
                                    text-transform: uppercase;
                                    letter-spacing: 0.08em;
                                    margin: 0 0 4px;
                                }
                                .myhh-referral-hint {
                                    font-size: 0.8rem;
                                    color: #6B7280;
                                    margin: 0 0 12px;
                                }
                                .myhh-invite-icons {
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    gap: 10px;
                                    flex-wrap: wrap;
                                }
                                .myhh-invite-icon-btn {
                                    width: 40px;
                                    height: 40px;
                                    border-radius: 50%;
                                    background: #F3F4F6;
                                    border: 1px solid #E5E7EB;
                                    display: inline-flex;
                                    align-items: center;
                                    justify-content: center;
                                    cursor: pointer;
                                    text-decoration: none;
                                    color: #4B5563;
                                    transition: background 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
                                    flex-shrink: 0;
                                }
                                .myhh-invite-icon-btn:hover {
                                    background: #CCFBF1;
                                    border-color: #5EEAD4;
                                    transform: translateY(-2px);
                                    box-shadow: 0 4px 10px rgba(13,148,136,0.18);
                                    color: #0D9488;
                                    text-decoration: none;
                                }
                                </style>

                                <div class="myhh-referral-section">
                                    <p class="myhh-referral-label">Know a Provider?</p>
                                    <p class="myhh-referral-hint">Share a free invite to join MyHealthHub</p>
                                    <div class="myhh-invite-icons">

                                        <!-- Email -->
                                        <a href="mailto:?subject=Join%20MyHealthHub%20%E2%80%94%20Free%20Provider%20Listing&body=Hi%2C%0A%0AI%20thought%20you%20might%20be%20interested%20in%20listing%20your%20practice%20on%20MyHealthHub%2C%20a%20wellness%20provider%20directory%20serving%20the%20Raleigh-Durham%20Triangle%20area.%20You%20can%20add%20your%20practice%20for%20free%20here%3A%20https%3A%2F%2Fmyhealthub.com%2Fadd-your-practice%2F"
                                           class="myhh-invite-icon-btn" title="Email an Invite" aria-label="Email an Invite">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <rect x="2" y="4" width="20" height="16" rx="2"/>
                                                <polyline points="2,4 12,13 22,4"/>
                                            </svg>
                                        </a>

                                        <!-- Copy Link -->
                                        <button
                                            id="myhh-copy-invite-btn"
                                            class="myhh-invite-icon-btn"
                                            title="Copy Invite Link"
                                            aria-label="Copy Invite Link"
                                            onclick="(function(btn){
                                                navigator.clipboard.writeText('https://myhealthub.com/add-your-practice/?ref=colleague').then(function(){
                                                    btn.title = 'Copied!';
                                                    btn.style.background = '#CCFBF1';
                                                    btn.style.borderColor = '#0D9488';
                                                    btn.style.color = '#0D9488';
                                                    setTimeout(function(){
                                                        btn.title = 'Copy Invite Link';
                                                        btn.style.background = '';
                                                        btn.style.borderColor = '';
                                                        btn.style.color = '';
                                                    }, 2000);
                                                });
                                            })(this);">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                                                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                                            </svg>
                                        </button>

                                        <!-- WhatsApp -->
                                        <a href="https://wa.me/?text=Hi%21%20I%20thought%20you%20might%20want%20to%20list%20your%20practice%20on%20MyHealthHub%20for%20free%3A%20https%3A%2F%2Fmyhealthub.com%2Fadd-your-practice%2F%3Fref%3Dcolleague"
                                           target="_blank" rel="noopener noreferrer"
                                           class="myhh-invite-icon-btn" title="Share via WhatsApp" aria-label="Share via WhatsApp">
                                            <svg width="18" height="18" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true">
                                                <path d="M16.002 2C8.271 2 2 8.269 2 16c0 2.462.667 4.772 1.832 6.762L2 30l7.443-1.799A13.93 13.93 0 0 0 16.002 30C23.731 30 30 23.731 30 16S23.731 2 16.002 2zm0 25.6a11.55 11.55 0 0 1-5.883-1.605l-.421-.25-4.417 1.069 1.101-4.295-.274-.44A11.543 11.543 0 0 1 4.4 16c0-6.394 5.208-11.6 11.602-11.6S27.6 9.606 27.6 16 22.394 27.6 16.002 27.6zm6.36-8.674c-.349-.174-2.065-1.018-2.386-1.134-.32-.116-.552-.174-.784.174-.232.348-.9 1.134-1.103 1.366-.203.232-.406.261-.754.087-.349-.174-1.474-.543-2.808-1.73-1.038-.925-1.738-2.067-1.942-2.415-.203-.349-.022-.537.152-.711.156-.155.349-.406.523-.61.174-.203.232-.348.348-.58.116-.232.058-.436-.029-.61-.087-.174-.784-1.889-1.074-2.587-.283-.68-.57-.588-.784-.599l-.667-.012a1.28 1.28 0 0 0-.928.435c-.32.348-1.219 1.191-1.219 2.905s1.248 3.371 1.422 3.603c.174.232 2.456 3.751 5.952 5.262.832.359 1.481.574 1.987.735.835.266 1.595.228 2.196.138.67-.099 2.065-.843 2.357-1.658.29-.814.29-1.512.203-1.658-.087-.145-.32-.232-.668-.406z"/>
                                            </svg>
                                        </a>

                                        <!-- SMS -->
                                        <a href="sms:?body=Hi%21%20I%20thought%20you%20might%20want%20to%20list%20your%20practice%20on%20MyHealthHub%20for%20free%3A%20https%3A%2F%2Fmyhealthub.com%2Fadd-your-practice%2F%3Fref%3Dcolleague"
                                           class="myhh-invite-icon-btn" title="Share via SMS" aria-label="Share via SMS">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                            </svg>
                                        </a>

                                        <!-- Web Share (progressive enhancement) -->
                                        <button
                                            id="myhh-native-share-btn"
                                            class="myhh-invite-icon-btn"
                                            style="display:none;"
                                            title="Share"
                                            aria-label="Share"
                                            onclick="navigator.share({ title: 'Join MyHealthHub', text: 'List your practice for free on MyHealthHub!', url: 'https://myhealthub.com/add-your-practice/?ref=colleague' });">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                                                <polyline points="16 6 12 2 8 6"/>
                                                <line x1="12" y1="2" x2="12" y2="15"/>
                                            </svg>
                                        </button>

                                    </div>
                                </div>
                                <script>
                                (function(){
                                    // Wire copy-link button via event listener (replaces inline onclick)
                                    var copyBtn = document.getElementById('gd-copy-link-btn');
                                    if (copyBtn) {
                                        copyBtn.addEventListener('click', function() {
                                            navigator.clipboard.writeText(window.location.href).then(function() {
                                                copyBtn.title = 'Copied!';
                                                setTimeout(function() { copyBtn.title = 'Copy Link'; }, 2000);
                                            });
                                        });
                                    }
                                    // Show native share button where supported
                                    if (navigator.share) {
                                        var btn = document.getElementById('myhh-native-share-btn');
                                        if (btn) btn.style.display = 'inline-flex';
                                    }
                                })();
                                </script>
                            
                            <!-- Location Map -->
                            <?php
                            // Check if provider has coordinates
                            $provider_lat = get_post_meta($post_id, 'latitude', true);
                            $provider_lng = get_post_meta($post_id, 'longitude', true);
                            
                            // Also check GeoDirectory's native storage
                            if (empty($provider_lat) && function_exists('geodir_get_post_meta')) {
                                $provider_lat = geodir_get_post_meta($post_id, 'latitude', true);
                                $provider_lng = geodir_get_post_meta($post_id, 'longitude', true);
                            }
                            
                            if (!empty($provider_lat) && !empty($provider_lng)) :
                            ?>
                            <div style="border-top: 1px solid #F3F4F6; padding-top: 20px; margin-top: 20px;">
                                <div style="font-size: 0.6875rem; color: #9CA3AF; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Location</div>
                                <div id="single-provider-map" style="border-radius: 12px; overflow: hidden; height: 200px;"></div>
                                <script>
                                (function() {
                                    var providerLat = <?php echo floatval($provider_lat); ?>;
                                    var providerLng = <?php echo floatval($provider_lng); ?>;
                                    var providerTitle = <?php echo json_encode(get_the_title($post_id)); ?>;

                                    async function renderSingleProviderMap() {
                                        var { Map } = await google.maps.importLibrary('maps');
                                        var { AdvancedMarkerElement } = await google.maps.importLibrary('marker');
                                        var pos = { lat: providerLat, lng: providerLng };
                                        var map = new Map(document.getElementById('single-provider-map'), {
                                            zoom: 15,
                                            center: pos,
                                            mapId: 'myhh_single_provider_map',
                                            disableDefaultUI: true,
                                            zoomControl: true
                                        });
                                        new AdvancedMarkerElement({ position: pos, map: map, title: providerTitle });
                                    }

                                    function tryInit() {
                                        if (typeof google !== 'undefined' && google.maps && google.maps.importLibrary) {
                                            renderSingleProviderMap();
                                        } else {
                                            window.addEventListener('load', function() {
                                                var check = setInterval(function() {
                                                    if (typeof google !== 'undefined' && google.maps && google.maps.importLibrary) {
                                                        clearInterval(check);
                                                        renderSingleProviderMap();
                                                    }
                                                }, 100);
                                            });
                                        }
                                    }

                                    if (document.readyState === 'loading') {
                                        document.addEventListener('DOMContentLoaded', tryInit);
                                    } else {
                                        tryInit();
                                    }
                                })();
                                </script>
                            </div>
                            <?php endif; ?>
                        </div>
                    </aside>
                    
                </div>
                
                <!-- Related Providers Section -->
                <?php
                // Build query for related providers
                $related_args = array(
                    'post_type' => 'gd_place',
                    'post_status' => 'publish',
                    'posts_per_page' => 6,
                    'post__not_in' => array($post_id),
                    'orderby' => 'rand',
                    'tax_query' => array('relation' => 'OR'),
                );
                
                // Add license type match
                if ($license_types && !is_wp_error($license_types)) {
                    $related_args['tax_query'][] = array(
                        'taxonomy' => 'myhh_license_type',
                        'field' => 'term_id',
                        'terms' => wp_list_pluck($license_types, 'term_id'),
                    );
                }
                
                // Add city match
                if ($cities && !is_wp_error($cities)) {
                    $related_args['tax_query'][] = array(
                        'taxonomy' => 'myhh_city',
                        'field' => 'term_id',
                        'terms' => wp_list_pluck($cities, 'term_id'),
                    );
                }
                
                // Add specialty match
                if ($specialties && !is_wp_error($specialties)) {
                    $related_args['tax_query'][] = array(
                        'taxonomy' => 'myhh_specialty',
                        'field' => 'term_id',
                        'terms' => wp_list_pluck($specialties, 'term_id'),
                    );
                }
                
                // Only run query if we have taxonomy criteria
                $related_providers = array();
                if (count($related_args['tax_query']) > 1) {
                    $related_query = new WP_Query($related_args);
                    $related_providers = $related_query->posts;
                }
                ?>
                
                <?php if (!empty($related_providers)) : ?>
                <div style="margin-top: 48px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
                        <h2 style="font-size: 1.5rem; font-weight: 700; color: #1F2937; margin: 0; display: flex; align-items: center; gap: 12px;">
                            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #0A74DA 0%, #0858a8 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                            Similar Providers
                        </h2>
                        <a href="<?php echo esc_url(home_url('/listings/')); ?>" style="display: inline-flex; align-items: center; gap: 6px; color: #0A74DA; text-decoration: none; font-weight: 600; font-size: 0.9375rem;">
                            View All
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </a>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                        <?php foreach ($related_providers as $related_post) : 
                            $related_id = $related_post->ID;
                            $related_thumbnail = get_the_post_thumbnail_url($related_id, 'medium');
                            $related_credentials = get_post_meta($related_id, 'provider_credentials', true);
                            $related_short_bio = get_post_meta($related_id, 'short_bio', true);
                            $related_session = get_post_meta($related_id, 'session_type', true);
                            $related_phone = get_post_meta($related_id, 'phone', true);
                            $related_website = get_post_meta($related_id, 'website', true);
                            $related_booking = get_post_meta($related_id, 'booking_link', true);
                            $related_verification = get_post_meta($related_id, 'verification_status', true);
                            $related_claimed = get_post_meta($related_id, 'claimed', true);
                            $related_street = get_post_meta($related_id, 'street', true);
                            $related_zip = get_post_meta($related_id, 'zip', true);
                            $related_npi_taxonomy = get_post_meta($related_id, 'npi_primary_taxonomy', true);
                            $related_npi_license = get_post_meta($related_id, 'npi_license_number', true);
                            $related_npi_state = get_post_meta($related_id, 'npi_license_state', true);

                            $related_city = get_the_terms($related_id, 'myhh_city');
                            $related_city_name = ($related_city && !is_wp_error($related_city)) ? $related_city[0]->name : '';

                            $related_license_terms = get_the_terms($related_id, 'myhh_license_type');
                            $related_license_name = ($related_license_terms && !is_wp_error($related_license_terms)) ? $related_license_terms[0]->name : '';

                            $related_specialties = get_the_terms($related_id, 'myhh_specialty');
                            $related_specialty_names = array();
                            if ($related_specialties && !is_wp_error($related_specialties)) {
                                $related_specialty_names = wp_list_pluck($related_specialties, 'name');
                            }

                            get_template_part('template-parts/listing-card', null, array(
                                'title'       => get_the_title($related_id),
                                'permalink'   => get_permalink($related_id),
                                'image'       => $related_thumbnail,
                                'excerpt'     => get_the_excerpt($related_id),
                                'short_bio'   => $related_short_bio,
                                'credentials' => $related_credentials,
                                'session_type'=> $related_session,
                                'specialties' => $related_specialty_names,
                                'location'    => $related_city_name,
                                'street'      => $related_street,
                                'zip'         => $related_zip,
                                'phone'       => $related_phone,
                                'website'     => $related_website,
                                'booking_link'=> $related_booking,
                                'verification_status' => $related_verification,
                                'claimed'     => (bool) $related_claimed,
                                'license_type'=> $related_license_name,
                                'npi_taxonomy'=> $related_npi_taxonomy,
                                'npi_license' => $related_npi_license,
                                'npi_license_state' => $related_npi_state,
                            ));
                        endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Source Attribution -->
                <?php if ($npi_number) : ?>
                    <div style="margin-top: 32px; padding: 16px 20px; background: #F8FAFC; border-radius: 12px; border: 1px solid #E5E7EB;">
                        <p style="margin: 0; font-size: 0.8125rem; color: #6B7280; display: flex; align-items: center; gap: 8px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                            Provider information sourced from
                            <a href="https://npiregistry.cms.hhs.gov/" target="_blank" rel="noopener noreferrer" style="color: #0A74DA; text-decoration: none; font-weight: 500;">
                                NPI Registry
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
        </article>
        
    <?php endwhile; ?>
</main>

<?php
get_footer();
