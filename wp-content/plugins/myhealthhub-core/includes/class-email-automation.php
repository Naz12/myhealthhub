<?php
/**
 * Email Automation Class
 *
 * Handles automated email drip sequences for provider lifecycle events:
 * - Welcome email (immediate on claim/submission approval)
 * - First follow-up (3 days after approval)
 * - Second follow-up (7 days after approval)
 *
 * Uses David's updated Pulse Lite / Pulse Pro pricing and CTA copy.
 *
 * @package MyHealthHub
 * @subpackage Core
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class MyHH_Email_Automation {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Hook into claim approval
        add_action('update_post_meta', array($this, 'on_meta_update'), 10, 4);

        // WP-Cron hooks for scheduled follow-ups
        add_action('myhh_send_followup_1', array($this, 'send_followup_1'), 10, 2);
        add_action('myhh_send_followup_2', array($this, 'send_followup_2'), 10, 2);
    }

    /**
     * Detect approval status changes on claims and submissions.
     */
    public function on_meta_update($meta_id, $post_id, $meta_key, $meta_value) {
        if ($meta_key !== '_myhh_status' || $meta_value !== 'approved') {
            return;
        }

        $post_type = get_post_type($post_id);
        if (!in_array($post_type, array('myhh_claim', 'myhh_submission'), true)) {
            return;
        }

        // Prevent duplicate sends
        if (get_post_meta($post_id, '_myhh_welcome_email_sent', true)) {
            return;
        }

        $this->trigger_welcome_sequence($post_id, $post_type);
    }

    /**
     * Trigger the full welcome sequence: immediate welcome + schedule follow-ups.
     */
    private function trigger_welcome_sequence($post_id, $post_type) {
        $recipient = $this->get_recipient_data($post_id, $post_type);
        if (!$recipient || empty($recipient['email'])) {
            $this->log('skip', $post_id, 'No recipient email found');
            return;
        }

        // 1. Send welcome email immediately
        $sent = $this->send_welcome_email($recipient, $post_id, $post_type);
        if ($sent) {
            update_post_meta($post_id, '_myhh_welcome_email_sent', current_time('mysql'));
            $this->log('welcome_sent', $post_id, $recipient['email']);
        }

        // 2. Schedule follow-up 1 (3 days)
        $followup_1_time = time() + (3 * DAY_IN_SECONDS);
        wp_schedule_single_event($followup_1_time, 'myhh_send_followup_1', array($post_id, $post_type));
        $this->log('followup_1_scheduled', $post_id, date('Y-m-d H:i:s', $followup_1_time));

        // 3. Schedule follow-up 2 (7 days)
        $followup_2_time = time() + (7 * DAY_IN_SECONDS);
        wp_schedule_single_event($followup_2_time, 'myhh_send_followup_2', array($post_id, $post_type));
        $this->log('followup_2_scheduled', $post_id, date('Y-m-d H:i:s', $followup_2_time));
    }

    /**
     * Get recipient data from claim or submission post.
     */
    private function get_recipient_data($post_id, $post_type) {
        if ($post_type === 'myhh_claim') {
            return array(
                'name'         => get_post_meta($post_id, '_myhh_claimant_name', true),
                'email'        => get_post_meta($post_id, '_myhh_claimant_email', true),
                'listing_id'   => get_post_meta($post_id, '_myhh_listing_id', true),
                'listing_name' => get_the_title(get_post_meta($post_id, '_myhh_listing_id', true)),
                'type'         => 'claim',
            );
        }

        if ($post_type === 'myhh_submission') {
            return array(
                'name'         => get_post_meta($post_id, '_myhh_provider_name', true),
                'email'        => get_post_meta($post_id, '_myhh_email', true),
                'listing_id'   => $post_id,
                'listing_name' => get_post_meta($post_id, '_myhh_practice_name', true),
                'type'         => 'submission',
            );
        }

        return null;
    }

    // ──────────────────────────────────────────────
    //  EMAIL 1 — WELCOME (immediate)
    // ──────────────────────────────────────────────

    private function send_welcome_email($recipient, $post_id, $post_type) {
        $site_name = get_bloginfo('name');
        $site_url  = home_url();

        $subject = sprintf('Welcome to %s — Your Profile Is Live!', $site_name);

        $body  = "Hi {$recipient['name']},\n\n";
        $body .= "Congratulations! Your profile for \"{$recipient['listing_name']}\" is now live on {$site_name}.\n\n";
        $body .= "Thousands of people in the Raleigh/Triangle area are searching for wellness providers just like you. ";
        $body .= "Your listing is already working for you — here's how to get the most out of it.\n\n";

        $body .= "── WHAT'S INCLUDED WITH YOUR FREE LISTING ──\n";
        $body .= "- Your name, credentials, and contact info displayed\n";
        $body .= "- Visibility in category and city searches\n";
        $body .= "- Mia (our AI wellness concierge) can recommend you to visitors\n\n";

        $body .= "── READY TO STAND OUT? ──\n\n";

        $body .= "Pulse Lite — only \$97/month\n";
        $body .= "- Priority placement in search results\n";
        $body .= "- Enhanced profile with full bio and specialties\n";
        $body .= "- Direct booking link displayed\n";
        $body .= "- Monthly performance insights\n\n";

        $body .= "Pulse Pro — only \$197/month\n";
        $body .= "- Everything in Pulse Lite\n";
        $body .= "- Featured provider badge\n";
        $body .= "- Mia priority recommendations\n";
        $body .= "- Dedicated account support\n";
        $body .= "- Social media spotlight\n\n";

        $body .= ">> Upgrade Now: {$site_url}/contact/?upgrade=pulse-lite\n";
        $body .= ">> Upgrade to Pulse Pro: {$site_url}/contact/?upgrade=pulse-pro\n\n";

        $body .= "Questions? Just reply to this email — we're here to help.\n\n";
        $body .= "Welcome aboard,\nThe {$site_name} Team\n{$site_url}";

        return $this->send($recipient['email'], $subject, $body, $post_id, 'welcome');
    }

    // ──────────────────────────────────────────────
    //  EMAIL 2 — FIRST FOLLOW-UP (Day 3)
    // ──────────────────────────────────────────────

    public function send_followup_1($post_id, $post_type) {
        // Prevent duplicate
        if (get_post_meta($post_id, '_myhh_followup1_sent', true)) {
            return;
        }

        $recipient = $this->get_recipient_data($post_id, $post_type);
        if (!$recipient || empty($recipient['email'])) {
            return;
        }

        $site_name = get_bloginfo('name');
        $site_url  = home_url();

        $subject = sprintf('%s — 3 Quick Ways to Strengthen Your Profile', $recipient['name']);

        $body  = "Hi {$recipient['name']},\n\n";
        $body .= "Your listing on {$site_name} has been live for a few days now — great start!\n\n";
        $body .= "Here are 3 quick things you can do right now to attract more clients:\n\n";

        $body .= "1. COMPLETE YOUR PROFILE\n";
        $body .= "   Profiles with a full bio, specialties, and a professional photo get 3x more views. ";
        $body .= "   Make sure yours tells your story.\n\n";

        $body .= "2. ADD YOUR BOOKING LINK\n";
        $body .= "   Let potential clients book directly from your profile. ";
        $body .= "   The easier you make it, the more appointments you'll fill.\n\n";

        $body .= "3. UPGRADE TO PULSE LITE\n";
        $body .= "   For only \$97/month, Pulse Lite gives you priority search placement, ";
        $body .= "   enhanced profile features, and monthly performance insights.\n\n";

        $body .= ">> Upgrade Now: {$site_url}/contact/?upgrade=pulse-lite\n\n";

        $body .= "Already upgraded? Amazing — you're ahead of the curve.\n\n";
        $body .= "Need help with your profile? Just reply to this email.\n\n";
        $body .= "Best,\nThe {$site_name} Team\n{$site_url}";

        $sent = $this->send($recipient['email'], $subject, $body, $post_id, 'followup_1');
        if ($sent) {
            update_post_meta($post_id, '_myhh_followup1_sent', current_time('mysql'));
            $this->log('followup_1_sent', $post_id, $recipient['email']);
        }
    }

    // ──────────────────────────────────────────────
    //  EMAIL 3 — SECOND FOLLOW-UP (Day 7)
    // ──────────────────────────────────────────────

    public function send_followup_2($post_id, $post_type) {
        // Prevent duplicate
        if (get_post_meta($post_id, '_myhh_followup2_sent', true)) {
            return;
        }

        $recipient = $this->get_recipient_data($post_id, $post_type);
        if (!$recipient || empty($recipient['email'])) {
            return;
        }

        $site_name = get_bloginfo('name');
        $site_url  = home_url();

        $subject = sprintf('Your First Week on %s — What\'s Next?', $site_name);

        $body  = "Hi {$recipient['name']},\n\n";
        $body .= "It's been one week since your profile went live on {$site_name}. ";
        $body .= "We hope you're already seeing the benefits of being part of the Triangle's most trusted wellness directory.\n\n";

        $body .= "── HERE'S WHAT TOP PROVIDERS ARE DOING ──\n\n";

        $body .= "The providers getting the most visibility on {$site_name} have one thing in common: ";
        $body .= "they've upgraded to Pulse Pro.\n\n";

        $body .= "With Pulse Pro — only \$197/month — you get:\n";
        $body .= "- Featured provider badge on your profile\n";
        $body .= "- Priority recommendations from Mia, our AI concierge\n";
        $body .= "- Top placement in search results\n";
        $body .= "- Dedicated account support\n";
        $body .= "- Social media spotlight to your local audience\n\n";

        $body .= ">> Upgrade to Pulse Pro: {$site_url}/contact/?upgrade=pulse-pro\n\n";

        $body .= "Not ready for Pro? Pulse Lite at only \$97/month is a great starting point ";
        $body .= "with priority placement and enhanced profile features.\n\n";

        $body .= ">> Upgrade Now: {$site_url}/contact/?upgrade=pulse-lite\n\n";

        $body .= "We're building something special for wellness providers in the Triangle, ";
        $body .= "and we'd love for you to grow with us.\n\n";

        $body .= "Warmly,\nThe {$site_name} Team\n{$site_url}";

        $sent = $this->send($recipient['email'], $subject, $body, $post_id, 'followup_2');
        if ($sent) {
            update_post_meta($post_id, '_myhh_followup2_sent', current_time('mysql'));
            $this->log('followup_2_sent', $post_id, $recipient['email']);
        }
    }

    // ──────────────────────────────────────────────
    //  SEND HELPER
    // ──────────────────────────────────────────────

    private function send($to, $subject, $body, $post_id, $email_type) {
        $site_name = get_bloginfo('name');
        $headers = array(
            'From: ' . $site_name . ' <' . (defined('MYHH_SMTP_FROM') ? MYHH_SMTP_FROM : get_option('admin_email')) . '>',
        );

        $result = wp_mail($to, $subject, $body, $headers);

        $this->log(
            $result ? 'sent' : 'failed',
            $post_id,
            sprintf('%s | to:%s | type:%s', $subject, $to, $email_type)
        );

        return $result;
    }

    // ──────────────────────────────────────────────
    //  LOGGING
    // ──────────────────────────────────────────────

    private function log($event, $post_id, $detail = '') {
        $log_file = WP_CONTENT_DIR . '/myhh-email-automation.log';
        $entry = sprintf(
            "[%s] %s | post_id:%d | %s\n",
            current_time('Y-m-d H:i:s'),
            $event,
            $post_id,
            $detail
        );
        error_log($entry, 3, $log_file);
    }
}
