<?php
/**
 * Sample Provider Data Seeder
 *
 * Seeds the database with sample mental health provider listings
 *
 * @package MyHealthHub
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class MyHH_Sample_Provider_Seeder {
    
    /**
     * Sample provider data
     */
    private $providers = array();
    
    /**
     * Sample images (using placeholder service)
     */
    private $images = array(
        'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=400&h=400&fit=crop&crop=face',
        'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?w=400&h=400&fit=crop&crop=face',
        'https://images.unsplash.com/photo-1594824476967-48c8b964273f?w=400&h=400&fit=crop&crop=face',
        'https://images.unsplash.com/photo-1582750433449-648ed127bb54?w=400&h=400&fit=crop&crop=face',
        'https://images.unsplash.com/photo-1551836022-deb4988cc6c0?w=400&h=400&fit=crop&crop=face',
        'https://images.unsplash.com/photo-1537368910025-700350fe46c7?w=400&h=400&fit=crop&crop=face',
        'https://images.unsplash.com/photo-1582750433449-648ed127bb54?w=400&h=400&fit=crop&crop=face',
        'https://images.unsplash.com/photo-1618498082410-b4aa22193b38?w=400&h=400&fit=crop&crop=face',
        'https://images.unsplash.com/photo-1622253692010-333f2da6031d?w=400&h=400&fit=crop&crop=face',
        'https://images.unsplash.com/photo-1527613426441-4da17471b66d?w=400&h=400&fit=crop&crop=face',
        'https://images.unsplash.com/photo-1551601651-2a8555f1a136?w=400&h=400&fit=crop&crop=face',
        'https://images.unsplash.com/photo-1612531386530-97286d97c2d2?w=400&h=400&fit=crop&crop=face',
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_providers();
    }
    
    /**
     * Initialize sample provider data
     */
    private function init_providers() {
        $this->providers = array(
            array(
                'title' => 'Dr. Sarah Mitchell',
                'credentials' => 'PhD, Licensed Clinical Psychologist',
                'short_bio' => 'Specializing in anxiety disorders and trauma recovery. I provide a warm, collaborative approach to help you overcome challenges and build resilience.',
                'full_bio' => 'With over 15 years of experience, Dr. Mitchell has helped hundreds of clients overcome anxiety, depression, and trauma. She uses evidence-based treatments including CBT, EMDR, and mindfulness-based approaches. Her practice focuses on creating a safe, non-judgmental space for healing and growth.',
                'session_type' => 'Hybrid',
                'verification_status' => 'Verified',
                'specialties' => array('Anxiety', 'Trauma & PTSD', 'Depression'),
                'city' => 'Raleigh',
                'insurance' => array('Blue Cross Blue Shield of NC', 'Aetna', 'UnitedHealthcare', 'Self-Pay'),
                'languages' => array('English'),
                'modalities' => array('CBT', 'EMDR', 'Mindfulness-Based', 'Trauma-Focused CBT'),
                'phone' => '(919) 555-0101',
                'email' => 'dr.mitchell@example.com',
                'website' => 'https://example.com/drmitchell',
                'booking_link' => 'https://example.com/book/mitchell',
                'address' => '4501 Glenwood Ave, Suite 200',
                'zip' => '27612',
            ),
            array(
                'title' => 'Dr. Michael Chen',
                'credentials' => 'PsyD, Clinical Psychologist',
                'short_bio' => 'Helping individuals and couples build stronger relationships and overcome life\'s challenges with compassion and expertise.',
                'full_bio' => 'Dr. Chen brings a unique blend of clinical expertise and cultural sensitivity to his practice. He specializes in relationship issues, life transitions, and men\'s mental health. His approach integrates psychodynamic therapy with practical skill-building techniques.',
                'session_type' => 'In-Person',
                'verification_status' => 'Verified',
                'specialties' => array('Couples Therapy', 'Relationships', 'Life Transitions'),
                'city' => 'Durham',
                'insurance' => array('Cigna', 'UnitedHealthcare', 'Optum', 'Sliding Scale'),
                'languages' => array('English', 'Mandarin'),
                'modalities' => array('Psychodynamic', 'Couples Therapy', 'Emotionally Focused Therapy'),
                'phone' => '(919) 555-0102',
                'email' => 'dr.chen@example.com',
                'website' => 'https://example.com/drchen',
                'booking_link' => '',
                'address' => '2608 Erwin Rd, Suite 150',
                'zip' => '27705',
            ),
            array(
                'title' => 'Jennifer Williams',
                'credentials' => 'LCSW, Licensed Clinical Social Worker',
                'short_bio' => 'Dedicated to helping children, adolescents, and families navigate emotional and behavioral challenges in a supportive environment.',
                'full_bio' => 'Jennifer has extensive experience working with children and families in school and clinical settings. She specializes in play therapy, family therapy, and helping children process trauma and anxiety. Her warm, engaging approach helps young clients feel comfortable and understood.',
                'session_type' => 'Hybrid',
                'verification_status' => 'Verified',
                'specialties' => array('Family', 'Children & Adolescents', 'Anxiety'),
                'city' => 'Cary',
                'insurance' => array('Blue Cross Blue Shield of NC', 'Medicaid', 'Tricare', 'Self-Pay'),
                'languages' => array('English'),
                'modalities' => array('Play Therapy', 'Family Systems', 'CBT', 'Parent-Child Interaction Therapy'),
                'phone' => '(919) 555-0103',
                'email' => 'j.williams@example.com',
                'website' => '',
                'booking_link' => 'https://example.com/book/williams',
                'address' => '1250 NW Maynard Rd, Suite 105',
                'zip' => '27513',
            ),
            array(
                'title' => 'Dr. Amanda Foster',
                'credentials' => 'PhD, Licensed Marriage and Family Therapist',
                'short_bio' => 'Specializing in couples therapy and family dynamics. I help partners reconnect and build stronger, more fulfilling relationships.',
                'full_bio' => 'Dr. Foster is a certified Gottman Method therapist with specialized training in couples therapy. She helps couples improve communication, rebuild trust, and navigate major life decisions together. She also works with families experiencing conflict or transition.',
                'session_type' => 'Virtual',
                'verification_status' => 'Verified',
                'specialties' => array('Couples Therapy', 'Family', 'Relationships'),
                'city' => 'Raleigh',
                'insurance' => array('Aetna', 'Cigna', 'Self-Pay', 'Out of Network'),
                'languages' => array('English'),
                'modalities' => array('Gottman Method', 'Emotionally Focused Therapy', 'Family Systems'),
                'phone' => '(919) 555-0104',
                'email' => 'dr.foster@example.com',
                'website' => 'https://example.com/drfoster',
                'booking_link' => 'https://example.com/book/foster',
                'address' => '',
                'zip' => '',
            ),
            array(
                'title' => 'Marcus Thompson',
                'credentials' => 'LPC, Licensed Professional Counselor',
                'short_bio' => 'Helping men navigate depression, career stress, and relationship challenges with practical, solution-focused therapy.',
                'full_bio' => 'Marcus specializes in men\'s mental health, understanding the unique pressures men face in today\'s society. He helps clients work through depression, career dissatisfaction, and relationship issues using a combination of cognitive-behavioral therapy and mindfulness techniques.',
                'session_type' => 'Hybrid',
                'verification_status' => 'Verified',
                'specialties' => array('Depression', 'Stress Management', 'Relationships'),
                'city' => 'Chapel Hill',
                'insurance' => array('Blue Cross Blue Shield of NC', 'UnitedHealthcare', 'Optum', 'Self-Pay'),
                'languages' => array('English'),
                'modalities' => array('CBT', 'Solution-Focused', 'Mindfulness-Based', 'Acceptance & Commitment Therapy (ACT)'),
                'phone' => '(919) 555-0105',
                'email' => 'm.thompson@example.com',
                'website' => 'https://example.com/thompson',
                'booking_link' => '',
                'address' => '100 Europa Dr, Suite 200',
                'zip' => '27517',
            ),
            array(
                'title' => 'Dr. Rachel Green',
                'credentials' => 'PsyD, Clinical Psychologist',
                'short_bio' => 'Specializing in eating disorders, body image, and women\'s mental health. Compassionate, evidence-based care for lasting recovery.',
                'full_bio' => 'Dr. Green has dedicated her career to helping individuals recover from eating disorders and body image issues. She takes a non-diet, Health at Every Size approach and works collaboratively with clients to develop a healthy relationship with food and their bodies.',
                'session_type' => 'In-Person',
                'verification_status' => 'Verified',
                'specialties' => array('Eating Disorders', 'Body Image', 'Women\'s Health'),
                'city' => 'Raleigh',
                'insurance' => array('Blue Cross Blue Shield of NC', 'Aetna', 'Cigna', 'Self-Pay'),
                'languages' => array('English'),
                'modalities' => array('CBT', 'Dialectical Behavior Therapy (DBT)', 'Acceptance & Commitment Therapy (ACT)'),
                'phone' => '(919) 555-0106',
                'email' => 'dr.green@example.com',
                'website' => 'https://example.com/drgreen',
                'booking_link' => 'https://example.com/book/green',
                'address' => '3100 Duraleigh Rd, Suite 200',
                'zip' => '27612',
            ),
            array(
                'title' => 'David Park',
                'credentials' => 'LCSW, Licensed Clinical Social Worker',
                'short_bio' => 'Trauma-informed therapist helping individuals heal from PTSD, anxiety, and difficult life experiences using EMDR and somatic approaches.',
                'full_bio' => 'David brings a compassionate, trauma-informed approach to his work with survivors of trauma, abuse, and adverse life experiences. He is EMDR certified and incorporates somatic and mindfulness-based techniques to help clients process and heal from past wounds.',
                'session_type' => 'Hybrid',
                'verification_status' => 'Verified',
                'specialties' => array('Trauma & PTSD', 'Anxiety', 'Grief'),
                'city' => 'Durham',
                'insurance' => array('UnitedHealthcare', 'Optum', 'Tricare', 'Sliding Scale'),
                'languages' => array('English', 'Korean'),
                'modalities' => array('EMDR', 'Trauma-Focused CBT', 'Somatic Experiencing', 'Mindfulness-Based'),
                'phone' => '(919) 555-0107',
                'email' => 'd.park@example.com',
                'website' => '',
                'booking_link' => 'https://example.com/book/park',
                'address' => '4709 Hope Valley Rd, Suite 102',
                'zip' => '27713',
            ),
            array(
                'title' => 'Dr. Lisa Hernandez',
                'credentials' => 'PhD, Licensed Psychologist',
                'short_bio' => 'Bilingual therapist specializing in multicultural issues, immigration-related stress, and Latino mental health.',
                'full_bio' => 'Dr. Hernandez provides culturally competent mental health services to the Latino community and others navigating multicultural identities. She specializes in immigration-related trauma, acculturation stress, and family dynamics in bicultural households.',
                'session_type' => 'Virtual',
                'verification_status' => 'Verified',
                'specialties' => array('Multicultural Issues', 'Trauma & PTSD', 'Family'),
                'city' => 'Raleigh',
                'insurance' => array('Blue Cross Blue Shield of NC', 'Medicaid', 'Sliding Scale', 'Self-Pay'),
                'languages' => array('English', 'Spanish'),
                'modalities' => array('Trauma-Focused CBT', 'Family Systems', 'Cultural Competency'),
                'phone' => '(919) 555-0108',
                'email' => 'dr.hernandez@example.com',
                'website' => 'https://example.com/drhernandez',
                'booking_link' => 'https://example.com/book/hernandez',
                'address' => '',
                'zip' => '',
            ),
            array(
                'title' => 'Katherine Brooks',
                'credentials' => 'LPC, NCC, Licensed Professional Counselor',
                'short_bio' => 'Helping young adults and professionals navigate anxiety, perfectionism, and life transitions with warmth and practical strategies.',
                'full_bio' => 'Katherine specializes in working with young adults and professionals dealing with anxiety, perfectionism, imposter syndrome, and major life transitions. She combines evidence-based techniques with a collaborative, non-judgmental approach.',
                'session_type' => 'Virtual',
                'verification_status' => 'Verified',
                'specialties' => array('Anxiety', 'Life Transitions', 'Young Adults'),
                'city' => 'Cary',
                'insurance' => array('Aetna', 'Cigna', 'UnitedHealthcare', 'Self-Pay'),
                'languages' => array('English'),
                'modalities' => array('CBT', 'Acceptance & Commitment Therapy (ACT)', 'Solution-Focused'),
                'phone' => '(919) 555-0109',
                'email' => 'k.brooks@example.com',
                'website' => 'https://example.com/brooks',
                'booking_link' => 'https://example.com/book/brooks',
                'address' => '',
                'zip' => '',
            ),
            array(
                'title' => 'Dr. James Richardson',
                'credentials' => 'PhD, Clinical Psychologist',
                'short_bio' => 'Specializing in OCD and anxiety disorders. Evidence-based treatment using Exposure and Response Prevention (ERP) therapy.',
                'full_bio' => 'Dr. Richardson is a specialist in obsessive-compulsive disorder and related anxiety conditions. He provides intensive ERP therapy and has helped hundreds of clients overcome OCD and regain control of their lives. His approach is direct yet compassionate.',
                'session_type' => 'In-Person',
                'verification_status' => 'Verified',
                'specialties' => array('OCD', 'Anxiety', 'Phobias'),
                'city' => 'Raleigh',
                'insurance' => array('Blue Cross Blue Shield of NC', 'Aetna', 'Self-Pay', 'Out of Network'),
                'languages' => array('English'),
                'modalities' => array('Exposure and Response Prevention (ERP)', 'CBT', 'Acceptance & Commitment Therapy (ACT)'),
                'phone' => '(919) 555-0110',
                'email' => 'dr.richardson@example.com',
                'website' => 'https://example.com/drrichardson',
                'booking_link' => '',
                'address' => '3800 Capital Blvd, Suite 120',
                'zip' => '27604',
            ),
            array(
                'title' => 'Emily Thompson',
                'credentials' => 'LCSW, Licensed Clinical Social Worker',
                'short_bio' => 'Compassionate grief counselor helping individuals and families navigate loss, life transitions, and find meaning after difficult times.',
                'full_bio' => 'Emily specializes in grief and loss, helping clients process the death of loved ones, divorce, job loss, and other significant life changes. She creates a supportive space for healing while helping clients develop coping strategies and find renewed purpose.',
                'session_type' => 'Hybrid',
                'verification_status' => 'Verified',
                'specialties' => array('Grief', 'Loss', 'Life Transitions'),
                'city' => 'Apex',
                'insurance' => array('Blue Cross Blue Shield of NC', 'UnitedHealthcare', 'Sliding Scale', 'Self-Pay'),
                'languages' => array('English'),
                'modalities' => array('Grief Counseling', 'CBT', 'Mindfulness-Based', 'Solution-Focused'),
                'phone' => '(919) 555-0111',
                'email' => 'e.thompson@example.com',
                'website' => '',
                'booking_link' => 'https://example.com/book/ethompson',
                'address' => '900 Town Hall Dr, Suite 105',
                'zip' => '27502',
            ),
            array(
                'title' => 'Dr. Patricia Wells',
                'credentials' => 'PsyD, Clinical Psychologist',
                'short_bio' => 'Specializing in depression and mood disorders. Helping clients find hope and build fulfilling lives through evidence-based treatment.',
                'full_bio' => 'Dr. Wells has extensive experience treating depression, bipolar disorder, and other mood conditions. She takes an integrative approach, combining medication consultation with therapy to provide comprehensive care. Her goal is to help clients achieve lasting wellness.',
                'session_type' => 'In-Person',
                'verification_status' => 'Verified',
                'specialties' => array('Depression', 'Bipolar Disorder', 'Mood Disorders'),
                'city' => 'Durham',
                'insurance' => array('Cigna', 'UnitedHealthcare', 'Medicare', 'Self-Pay'),
                'languages' => array('English'),
                'modalities' => array('CBT', 'Interpersonal Therapy', 'Mindfulness-Based'),
                'phone' => '(919) 555-0112',
                'email' => 'dr.wells@example.com',
                'website' => 'https://example.com/drwells',
                'booking_link' => '',
                'address' => '4321 Medical Park Dr, Suite 300',
                'zip' => '27704',
            ),
        );
    }
    
    /**
     * Seed sample providers
     */
    public function seed() {
        // Check if gd_place post type exists
        if (!post_type_exists('gd_place')) {
            return new WP_Error('missing_post_type', 'GeoDirectory post type "gd_place" does not exist.');
        }
        
        // Ensure taxonomies exist
        $this->ensure_taxonomies();
        
        $results = array(
            'created' => 0,
            'skipped' => 0,
            'errors' => array(),
        );
        
        foreach ($this->providers as $index => $provider) {
            // Check if provider already exists
            $existing = get_page_by_title($provider['title'], OBJECT, 'gd_place');
            if ($existing) {
                $results['skipped']++;
                continue;
            }
            
            // Create the listing
            $post_id = $this->create_provider($provider, $index);
            
            if (is_wp_error($post_id)) {
                $results['errors'][] = array(
                    'provider' => $provider['title'],
                    'error' => $post_id->get_error_message(),
                );
            } else {
                $results['created']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Create a single provider listing
     */
    private function create_provider($provider, $index) {
        // Insert post
        $post_id = wp_insert_post(array(
            'post_title'   => $provider['title'],
            'post_content' => $provider['full_bio'],
            'post_excerpt' => $provider['short_bio'],
            'post_status'  => 'publish',
            'post_type'    => 'gd_place',
            'post_author'  => 1,
        ));
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Set featured image
        $image_url = $this->images[$index % count($this->images)];
        $this->set_featured_image($post_id, $image_url, $provider['title']);
        
        // Set meta fields
        $meta_fields = array(
            'provider_credentials' => $provider['credentials'],
            'short_bio'            => $provider['short_bio'],
            'full_bio'             => $provider['full_bio'],
            'session_type'         => $provider['session_type'],
            'verification_status'  => $provider['verification_status'],
            'phone'                => $provider['phone'],
            'email'                => $provider['email'],
            'website'              => $provider['website'],
            'booking_link'         => $provider['booking_link'],
            'street'               => $provider['address'],
            'zip'                  => $provider['zip'],
            'insurance_accepted'   => $provider['insurance'],
            'languages_spoken'     => $provider['languages'],
            'modalities'           => $provider['modalities'],
            'post_status'          => 'publish',
        );
        
        foreach ($meta_fields as $key => $value) {
            if (!empty($value)) {
                update_post_meta($post_id, $key, $value);
            }
        }
        
        // Set GeoDirectory default fields
        update_post_meta($post_id, 'geodir_contact_name', $provider['title']);
        update_post_meta($post_id, 'geodir_email', $provider['email']);
        update_post_meta($post_id, 'geodir_phone', $provider['phone']);
        update_post_meta($post_id, 'geodir_website', $provider['website']);
        
        // Set specialties taxonomy
        if (!empty($provider['specialties'])) {
            $specialty_ids = array();
            foreach ($provider['specialties'] as $specialty) {
                $term = term_exists($specialty, 'myhh_specialty');
                if ($term) {
                    $specialty_ids[] = $term['term_id'];
                }
            }
            if (!empty($specialty_ids)) {
                wp_set_post_terms($post_id, $specialty_ids, 'myhh_specialty');
            }
        }
        
        // Set city taxonomy
        if (!empty($provider['city'])) {
            $city_term = term_exists($provider['city'], 'myhh_city');
            if ($city_term) {
                wp_set_post_terms($post_id, array($city_term['term_id']), 'myhh_city');
            }
            // Also set city meta
            update_post_meta($post_id, 'city', $provider['city']);
            
            // Set coordinates based on city
            $city_coords = array(
                'Raleigh' => array('lat' => 35.7796, 'lng' => -78.6382),
                'Durham' => array('lat' => 35.9949, 'lng' => -78.8986),
                'Chapel Hill' => array('lat' => 35.9132, 'lng' => -79.0558),
                'Cary' => array('lat' => 35.7915, 'lng' => -78.7811),
                'Apex' => array('lat' => 35.7327, 'lng' => -78.8503),
                'Morrisville' => array('lat' => 35.8329, 'lng' => -78.8242),
                'Holly Springs' => array('lat' => 35.6513, 'lng' => -78.8336),
                'Fuquay-Varina' => array('lat' => 35.5838, 'lng' => -78.8003),
                'Wake Forest' => array('lat' => 35.9729, 'lng' => -78.5097),
                'Garner' => array('lat' => 35.7113, 'lng' => -78.6142),
                'Knightdale' => array('lat' => 35.7877, 'lng' => -78.5053),
                'Carrboro' => array('lat' => 35.9102, 'lng' => -79.0695),
                'Pittsboro' => array('lat' => 35.7201, 'lng' => -79.1775),
                'Hillsborough' => array('lat' => 36.0754, 'lng' => -79.0997),
            );
            
            if (isset($city_coords[$provider['city']])) {
                // Add small random offset to prevent markers from overlapping
                $lat = $city_coords[$provider['city']]['lat'] + (rand(-100, 100) / 10000);
                $lng = $city_coords[$provider['city']]['lng'] + (rand(-100, 100) / 10000);
                update_post_meta($post_id, 'latitude', $lat);
                update_post_meta($post_id, 'longitude', $lng);
            }
        }
        
        return $post_id;
    }
    
    /**
     * Ensure taxonomies have terms
     */
    private function ensure_taxonomies() {
        // Specialty terms
        $specialties = array(
            'Anxiety', 'Depression', 'Trauma & PTSD', 'Relationships', 
            'Couples Therapy', 'Family', 'Grief', 'Stress Management',
            'OCD', 'Eating Disorders', 'Body Image', 'Life Transitions',
            'Young Adults', 'Multicultural Issues', 'Children & Adolescents',
            'Women\'s Health', 'Bipolar Disorder', 'Mood Disorders', 'Phobias', 'Loss'
        );
        
        foreach ($specialties as $specialty) {
            if (!term_exists($specialty, 'myhh_specialty')) {
                wp_insert_term($specialty, 'myhh_specialty');
            }
        }
        
        // City terms
        $cities = array('Raleigh', 'Durham', 'Cary', 'Chapel Hill', 'Apex');
        
        foreach ($cities as $city) {
            if (!term_exists($city, 'myhh_city')) {
                wp_insert_term($city, 'myhh_city');
            }
        }
    }
    
    /**
     * Set featured image from URL
     */
    private function set_featured_image($post_id, $image_url, $title) {
        // Check if function exists
        if (!function_exists('media_sideload_image')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        
        // Download and attach image
        $attachment_id = media_sideload_image($image_url, $post_id, $title, 'id');
        
        if (!is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
        }
        
        return $attachment_id;
    }
    
    /**
     * Delete all sample providers
     */
    public function clear() {
        $providers = get_posts(array(
            'post_type' => 'gd_place',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));
        
        foreach ($providers as $provider_id) {
            // Delete associated attachments
            $attachments = get_attached_media('image', $provider_id);
            foreach ($attachments as $attachment) {
                wp_delete_attachment($attachment->ID, true);
            }
            
            // Delete the post
            wp_delete_post($provider_id, true);
        }
        
        return count($providers);
    }
}
