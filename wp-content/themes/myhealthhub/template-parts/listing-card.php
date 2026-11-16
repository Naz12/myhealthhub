<?php
/**
 * Template part for displaying a provider listing card
 *
 * @package MyHealthHub
 * @version 2.0.0 - Polished with NPI data
 * @param array $args Arguments passed to the template
 */

// Extract arguments with defaults
$args = wp_parse_args($args, array(
    'title'       => '',
    'permalink'   => '',
    'image'       => '',
    'excerpt'     => '',
    'short_bio'   => '',
    'credentials' => '',
    'session_type'=> '',
    'specialties' => array(),
    'location'    => '',
    'street'      => '',
    'zip'         => '',
    'phone'       => '',
    'website'     => '',
    'booking_link'=> '',
    'verification_status' => '',
    'claimed'     => false,
    'rating'      => null,
    'license_type'=> '',
    'npi_taxonomy'=> '',
    'npi_license' => '',
    'npi_license_state' => '',
));

// Skip if no title
if (empty($args['title'])) {
    return;
}

// Clean up values - hide placeholder/unknown values
$session_type_clean = (!empty($args['session_type']) && strtolower($args['session_type']) !== 'unknown') ? $args['session_type'] : '';
$bio_clean = '';
if (!empty($args['short_bio'])) {
    $bio_clean = $args['short_bio'];
} elseif (!empty($args['excerpt'])) {
    $bio_clean = $args['excerpt'];
}
// Trim bio and ensure it's meaningful
$bio_clean = wp_trim_words(trim($bio_clean), 20);
if (strtolower($bio_clean) === 'unknown' || strtolower($bio_clean) === 'n/a') {
    $bio_clean = '';
}

$website_clean = (!empty($args['website']) && filter_var($args['website'], FILTER_VALIDATE_URL)) ? $args['website'] : '';
$phone_clean = (!empty($args['phone'])) ? $args['phone'] : '';
$location_clean = (!empty($args['location']) && strtolower($args['location']) !== 'unknown') ? $args['location'] : '';
$street_clean = (!empty($args['street'])) ? $args['street'] : '';
$zip_clean = (!empty($args['zip'])) ? $args['zip'] : '';
$license_type_clean = (!empty($args['license_type'])) ? $args['license_type'] : '';

// Format ZIP for display (show first 5 digits)
if ($zip_clean && strlen($zip_clean) > 5) {
    $zip_display = substr($zip_clean, 0, 5);
} else {
    $zip_display = $zip_clean;
}

// Build location display
$location_display = '';
if ($street_clean && $location_clean) {
    $location_display = $location_clean;
} elseif ($location_clean) {
    $location_display = $location_clean;
}

// Determine verification badge
$verified_badge = array(
    'style' => '',
    'label' => '',
    'icon' => ''
);
if ($args['verification_status'] === 'board_confirmed') {
    $verified_badge = array(
        'style' => 'background: #D1FAE5; color: #059669;',
        'label' => 'Board Verified',
        'icon' => '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>'
    );
} elseif ($args['verification_status'] === 'source_only') {
    $verified_badge = array(
        'style' => 'background: #FEF3C7; color: #D97706;',
        'label' => 'Source Verified',
        'icon' => '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"></path><circle cx="12" cy="12" r="10"></circle></svg>'
    );
} elseif ($args['verification_status'] === 'core_verified') {
    $verified_badge = array(
        'style' => 'background: #DBEAFE; color: #2563EB;',
        'label' => 'Verified',
        'icon' => '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7"></path></svg>'
    );
}
?>

<article class="listing-card" style="background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06); overflow: hidden; transition: all 0.25s ease; border: 1px solid #E5E7EB;">
    <a href="<?php echo esc_url($args['permalink']); ?>" style="text-decoration: none; color: inherit; display: block;">
        
        <?php if ($args['image']) : ?>
            <div class="listing-card__image" style="height: 180px; overflow: hidden; position: relative; background: #F3F4F6;">
                <img src="<?php echo esc_url($args['image']); ?>" alt="<?php echo esc_attr($args['title']); ?>" 
                     style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease;" loading="lazy">
            </div>
        <?php else : ?>
            <div class="listing-card__image" style="height: 120px; background: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%); display: flex; align-items: center; justify-content: center; position: relative;">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#0EA5E9" stroke-width="1.5" opacity="0.5">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
        <?php endif; ?>
        
        <div class="listing-card__content" style="padding: 16px;">
            <!-- Status Badges Row -->
            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 8px;">
                <?php if ($verified_badge['label']) : ?>
                    <span style="display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; <?php echo $verified_badge['style']; ?> font-size: 0.6875rem; font-weight: 600; border-radius: 4px;">
                        <?php echo $verified_badge['icon']; ?>
                        <?php echo esc_html($verified_badge['label']); ?>
                    </span>
                <?php endif; ?>
                
                <?php if ($session_type_clean) : ?>
                    <span style="display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; background: <?php echo $session_type_clean === 'Virtual' ? '#ECFDF5' : ($session_type_clean === 'Hybrid' ? '#FFFBEB' : '#F0F9FF'); ?>; color: <?php echo $session_type_clean === 'Virtual' ? '#059669' : ($session_type_clean === 'Hybrid' ? '#D97706' : '#0284C7'); ?>; font-size: 0.6875rem; font-weight: 500; border-radius: 4px;">
                        <?php echo esc_html($session_type_clean); ?>
                    </span>
                <?php endif; ?>
                
                <?php if ($args['claimed']) : ?>
                    <span style="display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; background: #F0FDF4; color: #16A34A; font-size: 0.6875rem; font-weight: 600; border-radius: 4px;">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"></path></svg>
                        Claimed
                    </span>
                <?php endif; ?>
            </div>
            
            <!-- Provider Name -->
            <h3 class="listing-card__title" style="margin: 0 0 2px; font-size: 1rem; color: #111827; font-weight: 700; line-height: 1.3;">
                <?php echo esc_html($args['title']); ?>
            </h3>
            
            <!-- Credentials & License Type -->
            <div style="margin-bottom: 8px;">
                <?php if ($args['credentials']) : ?>
                    <span style="color: #0284C7; font-size: 0.8125rem; font-weight: 600;"><?php echo esc_html($args['credentials']); ?></span>
                    <?php if ($license_type_clean) : ?>
                        <span style="color: #9CA3AF; font-size: 0.75rem; margin: 0 4px;">•</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($license_type_clean) : ?>
                    <span style="color: #6B7280; font-size: 0.75rem; font-weight: 500;"><?php echo esc_html($license_type_clean); ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Location (City Only - Full address on details page) -->
            <?php if ($location_clean) : ?>
                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" style="flex-shrink: 0;">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span style="color: #6B7280; font-size: 0.8125rem;"><?php echo esc_html($location_clean); ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Specialties -->
            <?php if (!empty($args['specialties'])) : ?>
                <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 8px;">
                    <?php foreach (array_slice($args['specialties'], 0, 3) as $specialty) : ?>
                        <span style="display: inline-block; padding: 2px 8px; background: #F8FAFC; color: #475569; font-size: 0.6875rem; font-weight: 500; border-radius: 4px; border: 1px solid #E2E8F0;">
                            <?php echo esc_html($specialty); ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if (count($args['specialties']) > 3) : ?>
                        <span style="display: inline-block; padding: 2px 8px; background: #F8FAFC; color: #94A3B8; font-size: 0.6875rem; border-radius: 4px; border: 1px solid #E2E8F0;">
                            +<?php echo count($args['specialties']) - 3; ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- NPI Provider Type (Verified from NPI Registry) -->
            <?php if (!empty($args['npi_taxonomy'])) : ?>
                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" style="flex-shrink: 0;">
                        <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    <span style="color: #059669; font-size: 0.75rem; font-weight: 500;"><?php echo esc_html($args['npi_taxonomy']); ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Contact info available on details page -->
            
            <!-- Action Footer -->
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; padding-top: 10px; border-top: 1px solid #F3F4F6;">
                <span style="color: #0284C7; font-weight: 600; font-size: 0.8125rem; display: inline-flex; align-items: center; gap: 4px;">
                    View Profile
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"></path>
                    </svg>
                </span>
                
                <?php if ($args['booking_link']) : ?>
                    <a href="<?php echo esc_url($args['booking_link']); ?>" target="_blank" rel="noopener noreferrer" 
                       style="display: inline-flex; align-items: center; gap: 5px; background: #059669; color: white; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; text-decoration: none;"
                       onclick="event.stopPropagation(); event.preventDefault();">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        Book
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </a>
</article>

<style>
.listing-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1), 0 2px 4px rgba(0,0,0,0.06);
    border-color: #BAE6FD;
}
.listing-card:hover .listing-card__image img {
    transform: scale(1.03);
}
.listing-card:hover .listing-card__title {
    color: #0284C7;
}
</style>
