<?php
/**
 * Mia Wellness Concierge Chatbot
 *
 * Handles chat interactions via structured intent → retrieval → response pipeline.
 * Uses MyHH_Mia_Intent_Engine for intent routing, extraction, and ranking.
 *
 * @package MyHealthHub
 * @version 2.0.0 - Matching & Routing Upgrade
 */

if (!defined('ABSPATH')) {
    exit;
}

// ── [DEBUG] Backend debug helper — always writes to WP debug log, tagged [MiaDebug] ──
function mia_debug_log($task, $stage, $detail) {
    if (!defined('WP_DEBUG') || !WP_DEBUG) return;
    $entry = sprintf('[MiaDebug][%s][%s] %s', $task, $stage, is_scalar($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    error_log($entry);
}

class MyHH_Mia_Chatbot {
    
    private static $instance = null;
    private $api_key;
    private $api_url = 'https://api.x.ai/v1/chat/completions';
    private $tts_url = 'https://api.x.ai/v1/tts';
    private $realtime_secret_url = 'https://api.x.ai/v1/realtime/client_secrets';
    private $tts_voice = 'ara';
    private $_claim_disambiguation_providers = array();
    private $_claim_name_suggestions = array();
    private $_conversational_speech = '';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->api_key = $this->get_api_key();
        if ($this->uses_openai()) { $this->tts_voice = 'marin'; }
        add_action('wp_ajax_mia_chat', array($this, 'handle_chat'));
        add_action('wp_ajax_nopriv_mia_chat', array($this, 'handle_chat'));
        add_action('wp_ajax_mia_tts', array($this, 'handle_tts'));
        add_action('wp_ajax_nopriv_mia_tts', array($this, 'handle_tts'));
        add_action('wp_ajax_mia_realtime_session', array($this, 'handle_realtime_session'));
        add_action('wp_ajax_nopriv_mia_realtime_session', array($this, 'handle_realtime_session'));
        add_action('wp_ajax_mia_realtime_tool', array($this, 'handle_realtime_tool'));
        add_action('wp_ajax_nopriv_mia_realtime_tool', array($this, 'handle_realtime_tool'));
        add_action('wp_ajax_mia_voice_metric', array($this, 'handle_voice_metric'));
        add_action('wp_ajax_nopriv_mia_voice_metric', array($this, 'handle_voice_metric'));
        add_action('wp_ajax_mia_provider_outcome', array($this, 'handle_provider_outcome'));
        add_action('wp_ajax_nopriv_mia_provider_outcome', array($this, 'handle_provider_outcome'));
        add_action('wp_ajax_mia_dynamic_greeting', array($this, 'handle_dynamic_greeting'));
        add_action('wp_ajax_nopriv_mia_dynamic_greeting', array($this, 'handle_dynamic_greeting'));
        add_action('wp_ajax_mia_voice_worklet', array($this, 'serve_voice_worklet'));
        add_action('wp_ajax_nopriv_mia_voice_worklet', array($this, 'serve_voice_worklet'));
        add_action('wp_footer', array($this, 'render_widget'), 999);
    }
    
    /**
     * Check if Mia is enabled
     */
    private function is_enabled() {
        return (bool) get_option('myhh_mia_enabled', true); // Default to enabled
    }
    
    private function uses_openai() {
        require_once __DIR__ . '/class-mia-openai.php';
        return MyHH_Mia_OpenAI::provider() === 'openai';
    }

    private function request_chat_completion($args) {
        if ($this->uses_openai()) { return MyHH_Mia_OpenAI::completion($args); }
        return wp_remote_post($this->api_url, $args);
    }

    private function get_api_key() {
        if ($this->uses_openai()) { return MyHH_Mia_OpenAI::key(); }
        // Try to get from options first, then constants
        $key = get_option('myhh_grok_api_key');
        if ($key) {
            return $key;
        }
        // Check for constant via getenv for CLI/CLI-adjacent contexts
        $env_key = getenv('MYHH_GROK_API_KEY');
        if ($env_key) {
            return $env_key;
        }
        return '';
    }

    private function verify_mia_ajax_request() {
        $nonce_ok = check_ajax_referer('mia_chat_nonce', 'nonce', false);
        if ($nonce_ok) {
            return true;
        }

        if (is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Security check failed. Please refresh and try again.'), 403);
        }

        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ORIGIN'])) : '';
        $referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        $origin_host = $origin ? wp_parse_url($origin, PHP_URL_HOST) : '';
        $referer_host = $referer ? wp_parse_url($referer, PHP_URL_HOST) : '';

        if (($origin_host && $origin_host !== $site_host) || ($referer_host && $referer_host !== $site_host)) {
            wp_send_json_error(array('message' => 'Security check failed. Please refresh and try again.'), 403);
        }

        mia_debug_log('Security', 'public_nonce_bypass', array(
            'reason' => 'cached_or_stale_public_nonce',
            'origin_host' => $origin_host,
            'referer_host' => $referer_host,
        ));
        return true;
    }

    /**
     * Serve the AudioWorklet processor module for Mia voice.
     *
     * This endpoint returns the JS worklet file with the correct MIME type
     * so the browser can load it via audioWorklet.addModule().
     */
    public function serve_voice_worklet() {
        $file_path = plugin_dir_path(__FILE__) . '../assets/js/mia-voice-processor.js';
        if (!file_exists($file_path)) {
            wp_die('Worklet module not found.', 404);
        }

        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($file_path);
        exit;
    }

    /**
     * Neural TTS proxy for Mia hero voice replies.
     *
     * Keeps xAI credentials server-side and returns base64 audio to the hero UI.
     */
    public function handle_tts() {
        mia_debug_log('TTS', 'handler_start', ['text_len' => strlen($_POST['text'] ?? ''), 'language' => $_POST['language'] ?? 'en']);
        $this->verify_mia_ajax_request();

        if (!$this->is_enabled()) {
            mia_debug_log('TTS', 'disabled', 'Mia is disabled — returning 503');
            wp_send_json_error(array('message' => 'Mia is currently unavailable.'), 503);
        }

        if (empty($this->api_key)) {
            wp_send_json_error(array('message' => 'Voice service is not configured.'), 503);
        }

        $text = isset($_POST['text']) ? wp_unslash($_POST['text']) : '';
        $text = $this->normalize_tts_text($text);
        if ($text === '') {
            wp_send_json_error(array('message' => 'No speech text provided.'), 400);
        }

        $language = sanitize_text_field($_POST['language'] ?? 'en');
        $language = $this->normalize_tts_language($language);

        // Cache only Mia's short fixed acknowledgement clips. Never cache
        // arbitrary user-specific or health-related speech at this layer.
        $static_cache_key = $this->get_static_tts_cache_key($text, $language);
        if ($static_cache_key !== '') {
            $cached_audio = get_transient($static_cache_key);
            if (is_array($cached_audio) && !empty($cached_audio['mime']) && !empty($cached_audio['audio'])) {
                mia_debug_log('TTS', 'static_cache_hit', array('language' => $language));
                wp_send_json_success(array(
                    'provider' => $this->uses_openai() ? 'openai' : 'xai',
                    'voice_id' => $this->tts_voice,
                    'language' => $language,
                    'mime'     => $cached_audio['mime'],
                    'audio'    => $cached_audio['audio'],
                    'cached'   => true,
                ));
            }
        }

        $audio = $this->uses_openai() ? MyHH_Mia_OpenAI::speech($text, $language) : $this->request_xai_tts($text, $language);
        if (is_wp_error($audio)) {
            $error_data = $audio->get_error_data();
            $upstream_status = is_array($error_data) && isset($error_data['status']) ? (int) $error_data['status'] : 502;
            $retryable = $this->is_retryable_tts_status($upstream_status);
            mia_debug_log('TTS', 'xai_error', [
                'code' => $audio->get_error_code(),
                'message' => $audio->get_error_message(),
                'status' => $upstream_status,
                'retryable' => $retryable,
            ]);
            wp_send_json_error(array(
                'message' => $audio->get_error_message(),
                'code'    => $audio->get_error_code(),
                'status'  => $upstream_status,
                'retryable' => $retryable,
            ), $retryable ? 200 : 502);
        }

        $encoded_audio = base64_encode($audio['bytes']);
        if ($static_cache_key !== '') {
            set_transient($static_cache_key, array(
                'mime'  => $audio['mime'],
                'audio' => $encoded_audio,
            ), WEEK_IN_SECONDS);
        }

        mia_debug_log('TTS', 'success', ['mime' => $audio['mime'], 'bytes' => strlen($audio['bytes'])]);
        wp_send_json_success(array(
            'provider' => $this->uses_openai() ? 'openai' : 'xai',
            'voice_id' => $this->tts_voice,
            'language' => $language,
            'mime'     => $audio['mime'],
            'audio'    => $encoded_audio,
            'cached'   => false,
        ));
    }

    /**
     * Issue a short-lived xAI realtime client secret for browser voice sessions.
     */
    public function handle_realtime_session() {
        $this->verify_mia_ajax_request();

        if (!$this->is_enabled()) {
            wp_send_json_error(array('message' => 'Mia is currently unavailable.'), 503);
        }

        if (empty($this->api_key)) {
            wp_send_json_error(array('message' => 'Realtime voice is not configured.'), 503);
        }

        $rate_limit = $this->enforce_rate_limit('realtime_session');
        if (is_wp_error($rate_limit)) {
            wp_send_json_error(array('message' => $rate_limit->get_error_message()), 429);
        }

        if ($this->uses_openai()) {
            $sdp = wp_unslash($_POST['sdp'] ?? '');
            $history = json_decode(wp_unslash($_POST['history'] ?? '[]'), true);
            $live = MyHH_Mia_OpenAI::live($sdp, $history);
            if (is_wp_error($live)) { wp_send_json_error(array('message' => $live->get_error_message(), 'code' => $live->get_error_code()), 502); }
            wp_send_json_success($live);
            return;
        }

        $secret_payload = $this->request_xai_realtime_client_secret();
        if (is_wp_error($secret_payload)) {
            wp_send_json_error(array('message' => $secret_payload->get_error_message()), 502);
        }

        $client_secret = $this->extract_realtime_client_secret($secret_payload);
        if (empty($client_secret)) {
            wp_send_json_error(array('message' => 'Realtime voice token was not returned by xAI.'), 502);
        }

        wp_send_json_success(array(
            'provider'       => 'xai',
            'voice_metric_schema' => 2,
            'ws_url'         => 'wss://api.x.ai/v1/realtime?model=grok-voice-latest',
            'client_secret'  => $client_secret,
            'voice'          => $this->tts_voice,
            'realtime_v2'    => true,
            'session_config' => array(
                'instructions' => $this->build_realtime_voice_instructions(),
                'voice'        => $this->tts_voice,
                'reasoning'    => array(
                    // Directory intent and safety remain deterministic in PHP, so
                    // voice reasoning can stay off for the fastest first audio.
                    'effort' => 'none',
                ),
                'resumption'   => array(
                    'enabled' => true,
                ),
                'audio'        => array(
                    'input' => array(
                        'format' => array(
                            'type' => 'audio/pcm',
                            'rate' => 24000,
                        ),
                        'transport' => 'json',
                        'transcription' => array(
                            'model' => 'grok-transcribe',
                            'keyterms' => array('Mia', 'MyHealthHub', 'therapist', 'provider', 'Raleigh', 'Durham'),
                        ),
                    ),
                    'output' => array(
                        'format' => array(
                            'type' => 'audio/pcm',
                            'rate' => 24000,
                        ),
                        'transport' => 'json',
                    ),
                ),
                'turn_detection' => array(
                    'type'                 => 'server_vad',
                    // Keep the threshold inclusive for accented and softer speech.
                    'threshold'            => 0.70,
                    'silence_duration_ms'  => 700,
                    'prefix_padding_ms'    => 400,
                ),
                'tools' => array(
                    array(
                        'type'        => 'function',
                        'name'        => 'myhealthhub_lookup',
                        'description' => 'Use this for every MyHealthHub provider search, provider follow-up, platform question, claim/add/upgrade/sponsor/provider-signup flow, safety turn, or out-of-scope redirect. Do not use it only to define, compare, or explain an in-scope wellness category, service, modality, provider type, or license; answer that domain education directly from the supplied system knowledge. Supply the literal user words plus your structured interpretation. WordPress validates the action and remains the only source of provider records, prices, links, and policy.',
                        'parameters'  => array(
                            'type'       => 'object',
                            'properties' => array(
                                'message' => array(
                                    'type'        => 'string',
                                    'description' => 'The user\'s literal spoken words only. Do not add annotations, commentary, inferred facts, or prior context to this field.',
                                ),
                                'next_action' => array(
                                    'type' => 'string',
                                    'enum' => array('provider_search', 'provider_detail', 'provider_location', 'provider_insurance', 'provider_booking', 'provider_compare', 'provider_claim', 'claim_candidate_select', 'provider_add', 'provider_upgrade', 'sponsor_inquiry', 'platform_info', 'crisis', 'general', 'clarify'),
                                    'description' => 'The single best next platform action for this turn.',
                                ),
                                'intent' => array(
                                    'type' => 'string',
                                    'enum' => array('consumer_provider_search', 'provider_followup', 'provider_claim_listing', 'provider_add_listing', 'provider_upgrade_or_visibility', 'sponsor_or_company_inquiry', 'crisis_or_high_risk', 'general_unknown'),
                                ),
                                'confidence' => array(
                                    'type' => 'number',
                                    'minimum' => 0,
                                    'maximum' => 1,
                                ),
                                'navigation_state' => array(
                                    'type' => 'object',
                                    'description' => 'A privacy-safe description of how to continue the conversation. Use only these labels; never put diagnoses, symptoms, or a free-text personal summary here.',
                                    'properties' => array(
                                        'goal' => array(
                                            'type' => 'string',
                                            'enum' => array('learn', 'explore_support', 'find_provider', 'compare_providers', 'provider_claim', 'provider_add', 'provider_upgrade', 'unsure'),
                                        ),
                                        'stage' => array(
                                            'type' => 'string',
                                            'enum' => array('listening', 'clarifying', 'educating', 'search_ready', 'reviewing_results', 'action_ready', 'closing'),
                                        ),
                                        'readiness' => array(
                                            'type' => 'string',
                                            'enum' => array('not_ready', 'exploring', 'ready', 'unknown'),
                                        ),
                                        'support_topics' => array(
                                            'type' => 'array',
                                            'items' => array(
                                                'type' => 'string',
                                                'enum' => array('stress', 'sleep', 'relationships', 'grief', 'anxiety_support', 'mood_support', 'trauma_support', 'pain_support', 'movement_recovery', 'nutrition_support', 'financial_stress', 'work_life', 'social_support', 'general_wellness', 'other'),
                                            ),
                                            'maxItems' => 5,
                                        ),
                                        'open_question' => array(
                                            'type' => 'string',
                                            'enum' => array('none', 'location', 'service_type', 'modality', 'provider_language', 'insurance', 'session_type', 'provider_choice', 'user_readiness'),
                                        ),
                                        'next_best_action' => array(
                                            'type' => 'string',
                                            'enum' => array('listen', 'clarify', 'educate', 'search', 'compare', 'show_profile', 'platform_action', 'close'),
                                        ),
                                    ),
                                ),
                                'search_entities' => array(
                                    'type' => 'object',
                                    'properties' => array(
                                        'category' => array('type' => 'string'),
                                        'specialty' => array('type' => 'string'),
                                        'city' => array('type' => 'string'),
                                        'provider_type' => array('type' => 'string'),
                                        'modality' => array('type' => 'string'),
                                        'language_preference' => array('type' => 'string'),
                                        'insurance' => array('type' => 'string'),
                                        'session_type' => array('type' => 'string'),
                                    ),
                                ),
                                'entities' => array(
                                    'type' => 'object',
                                    'properties' => array(
                                        'provider_name' => array('type' => 'string'),
                                        'city' => array('type' => 'string'),
                                        'category' => array('type' => 'string'),
                                        'specialty' => array('type' => 'string'),
                                        'ordinal' => array('type' => 'integer'),
                                        'language' => array('type' => 'string', 'enum' => array('en', 'es', 'unknown')),
                                    ),
                                ),
                                'target_provider_rank' => array('type' => 'integer', 'minimum' => 0),
                                'target_provider_id' => array('type' => 'integer', 'minimum' => 0),
                                'target_provider_name' => array('type' => 'string'),
                                'change_dimension' => array(
                                    'type' => 'string',
                                    'enum' => array('none', 'category', 'specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type'),
                                ),
                                'preserve_context' => array(
                                    'type' => 'array',
                                    'items' => array('type' => 'string', 'enum' => array('category', 'specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type')),
                                ),
                                'clear_context' => array(
                                    'type' => 'array',
                                    'items' => array('type' => 'string', 'enum' => array('category', 'specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type')),
                                ),
                                'needs_clarification' => array('type' => 'boolean'),
                                'clarification_question' => array(
                                    'type' => 'string',
                                    'description' => 'One short question only when the user\'s intended platform action is genuinely unclear.',
                                ),
                            ),
                            'required' => array('message', 'next_action', 'confidence'),
                        ),
                    ),
                ),
            ),
        ));
    }

    /**
     * Execute the deterministic MyHealthHub grounding tool used by realtime voice.
     */
    public function handle_realtime_tool() {
        $request_started_at = microtime(true);
        $this->verify_mia_ajax_request();

        if (!$this->is_enabled()) {
            wp_send_json_error(array('message' => 'Mia is currently unavailable.'), 503);
        }

        $message = isset($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';
        $message = $this->normalize_spoken_search_terms($message);
        $history = isset($_POST['history']) ? json_decode(stripslashes($_POST['history']), true) : array();
        $prior_context = isset($_POST['prior_context']) ? json_decode(stripslashes($_POST['prior_context']), true) : array();
        $route_hint = isset($_POST['route_hint']) ? sanitize_text_field(wp_unslash($_POST['route_hint'])) : '';
        $predictive_prefetch_raw = isset($_POST['predictive_prefetch']) && is_scalar($_POST['predictive_prefetch'])
            ? wp_unslash($_POST['predictive_prefetch'])
            : '';
        $predictive_prefetch = filter_var($predictive_prefetch_raw, FILTER_VALIDATE_BOOLEAN);
        $predictive_commit_raw = isset($_POST['predictive_commit']) && is_scalar($_POST['predictive_commit'])
            ? wp_unslash($_POST['predictive_commit'])
            : '';
        $predictive_commit = filter_var($predictive_commit_raw, FILTER_VALIDATE_BOOLEAN);
        $realtime_action = array();
        if (isset($_POST['realtime_action'])) {
            $decoded_action = json_decode((string) wp_unslash($_POST['realtime_action']), true);
            if (is_array($decoded_action)) {
                $realtime_action = $decoded_action;
            }
        }
        $transcript_candidates = array();
        if (isset($_POST['transcript_candidates'])) {
            $decoded_candidates = json_decode((string) wp_unslash($_POST['transcript_candidates']), true);
            if (is_array($decoded_candidates)) {
                $transcript_candidates = $decoded_candidates;
            }
        }
        $transcript_candidates = $this->sanitize_transcript_candidates($transcript_candidates);

        if ($message === '') {
            wp_send_json_error(array('message' => 'No message provided.'), 400);
        }

        $rate_limit = $this->enforce_rate_limit(($predictive_prefetch || $predictive_commit) ? 'predictive_prefetch' : 'realtime_tool');
        if (is_wp_error($rate_limit)) {
            wp_send_json_error(array('message' => $rate_limit->get_error_message()), 429);
        }

        if ($predictive_commit) {
            $receipt = sanitize_key(wp_unslash($_POST['prediction_receipt'] ?? ''));
            $key = 'mia_preview_' . substr($receipt, 0, 48);
            $selection = $receipt !== '' ? get_transient($key) : false;
            if (!is_array($selection) || !hash_equals($selection['visitor'], $this->get_rate_limit_key('predictive_prefetch'))) {
                wp_send_json_success(array('accepted' => false, 'reason' => 'expired_or_consumed'));
            }
            // Consume the server-issued receipt once; never rerun the selection.
            if (!delete_transient($key)) wp_send_json_success(array('accepted' => false, 'reason' => 'consumed'));
            $rotation_key = $selection['rotation_key'];
            if (preg_match('/^myhh_mia_rotation_[a-f0-9]{32}$/', $rotation_key)) {
                $recent = get_transient($rotation_key . '_recent');
                $recent = is_array($recent) ? $recent : array();
                set_transient($rotation_key, intval(get_transient($rotation_key)) + 1, 15 * MINUTE_IN_SECONDS);
                set_transient($rotation_key . '_recent', array_slice(array_values(array_unique(array_merge($selection['ids'], $recent))), 0, 12), 45 * MINUTE_IN_SECONDS);
            }
            wp_send_json_success(array('accepted' => true, 'provider_ids' => $selection['ids']));
        }
        $payload = $this->build_realtime_tool_payload($message, $history, $prior_context, $route_hint, $transcript_candidates, $realtime_action, $predictive_prefetch);
        if ($predictive_prefetch && !empty($payload['providers'])) {
            $receipt = strtolower(wp_generate_password(40, false, false));
            set_transient('mia_preview_' . $receipt, array(
                'visitor' => $this->get_rate_limit_key('predictive_prefetch'),
                'rotation_key' => $payload['provider_exposure']['rotation']['rotation_key'] ?? '',
                'ids' => array_values(array_filter(array_map(static function($provider) { return absint($provider['id'] ?? 0); }, $payload['providers']))),
            ), 90);
            $payload['prediction_receipt'] = $receipt;
        }
        $payload['predictive_prefetch'] = $predictive_prefetch;
        $payload['predictive_commit'] = $predictive_commit;
        $payload['performance'] = array(
            'server_ms' => max(0, (int) round((microtime(true) - $request_started_at) * 1000)),
            'bootstrap_ms' => isset($_SERVER['REQUEST_TIME_FLOAT']) ? max(0, (int) round(($request_started_at - $_SERVER['REQUEST_TIME_FLOAT']) * 1000)) : null,
        );
        wp_send_json_success($payload);
    }

    /**
     * Store privacy-safe voice performance measurements for production QA.
     */
    public function handle_voice_metric() {
        $this->verify_mia_ajax_request();

        $rate_limit = $this->enforce_rate_limit('voice_metric');
        if (is_wp_error($rate_limit)) {
            wp_send_json_error(array('message' => 'Metric rate limit reached.'), 429);
        }

        $raw = isset($_POST['metric']) ? wp_unslash($_POST['metric']) : '';
        $metric = json_decode((string) $raw, true);
        if (!is_array($metric)) {
            wp_send_json_error(array('message' => 'Invalid metric.'), 400);
        }

        $allowed_events = array(
            'transcript_final',
            'first_audio',
            'useful_playback_scheduled',
            'acknowledgement_started',
            'lookup_complete',
            'lookup_recovered',
            'barge_in',
            'input_audio_timing',
        );
        $event = sanitize_key($metric['event'] ?? '');
        if (!in_array($event, $allowed_events, true)) {
            wp_send_json_error(array('message' => 'Unsupported metric.'), 400);
        }

        $bounded_int = static function($value, $max = 120000) {
            return max(0, min((int) $max, (int) round((float) $value)));
        };
        $browser = sanitize_key($metric['browser'] ?? 'other');
        if (!in_array($browser, array('chrome', 'safari', 'firefox', 'edge', 'other'), true)) {
            $browser = 'other';
        }

        $entry = array(
            'schema'                    => 2,
            'ts'                        => gmdate('Y-m-d\TH:i:s\Z'),
            'event'                     => $event,
            'route'                     => substr(sanitize_key($metric['route'] ?? ''), 0, 40),
            'status'                    => substr(sanitize_key($metric['status'] ?? ''), 0, 24),
            'response_type'             => substr(sanitize_key($metric['response_type'] ?? ''), 0, 48),
            'duration_ms'               => $bounded_int($metric['duration_ms'] ?? 0),
            'server_ms'                 => $bounded_int($metric['server_ms'] ?? 0),
            'bootstrap_ms'              => $bounded_int($metric['bootstrap_ms'] ?? 0),
            'queue_ms'                  => $bounded_int($metric['queue_ms'] ?? 0),
            'transcript_to_audio_ms'    => $bounded_int($metric['transcript_to_audio_ms'] ?? 0),
            'speech_stop_to_audio_ms'   => $bounded_int($metric['speech_stop_to_audio_ms'] ?? 0),
            'provider_count'            => $bounded_int($metric['provider_count'] ?? 0, 20),
            'browser'                   => $browser,
            'realtime_v2'               => !empty($metric['realtime_v2']),
            'recovered'                 => !empty($metric['recovered']),
            'timeout'                   => !empty($metric['timeout']),
        );

        foreach (array('local_signal_to_server_stop_ms', 'local_signal_to_audio_ms', 'capture_dispatch_max_ms',
            'capture_audio_ms', 'sent_audio_ms', 'capture_window_ms', 'ws_buffered_max_bytes') as $field) {
            $value = $metric[$field] ?? null;
            $entry[$field] = (is_int($value) || is_float($value)) && is_finite((float) $value)
                ? $bounded_int($value, $field === 'ws_buffered_max_bytes' ? 16777216 : 120000) : null;
        }

        $log_file = WP_CONTENT_DIR . '/mia-voice-metrics.jsonl';
        if (file_exists($log_file) && filesize($log_file) > 5 * MB_IN_BYTES) {
            @unlink($log_file . '.1');
            @rename($log_file, $log_file . '.1');
        }
        @file_put_contents($log_file, wp_json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

        wp_send_json_success(array('accepted' => true));
    }

    /**
     * Record a provider-profile click without storing the visitor's message,
     * transcript, health context, IP address, or other identifying data.
     */
    public function handle_provider_outcome() {
        $this->verify_mia_ajax_request();

        $rate_limit = $this->enforce_rate_limit('provider_outcome');
        if (is_wp_error($rate_limit)) {
            wp_send_json_error(array('message' => 'Outcome rate limit reached.'), 429);
        }

        $event = sanitize_key($_POST['event'] ?? '');
        if ($event !== 'profile_click') {
            wp_send_json_error(array('message' => 'Unsupported outcome.'), 400);
        }

        $provider_id = absint($_POST['provider_id'] ?? 0);
        $profile_url = esc_url_raw(wp_unslash($_POST['profile_url'] ?? ''));
        if ($provider_id <= 0 && $profile_url !== '') {
            $site_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
            $profile_host = strtolower((string) wp_parse_url($profile_url, PHP_URL_HOST));
            $profile_path = (string) wp_parse_url($profile_url, PHP_URL_PATH);
            if ($profile_host !== $site_host || strpos($profile_path, '/listings/') !== 0) {
                wp_send_json_error(array('message' => 'Invalid provider profile.'), 400);
            }
            $provider_id = absint(url_to_postid($profile_url));
        }

        if ($provider_id <= 0 || get_post_type($provider_id) !== 'gd_place' || get_post_status($provider_id) !== 'publish') {
            wp_send_json_error(array('message' => 'Provider profile not found.'), 404);
        }

        $search_key = strtolower(preg_replace('/[^a-f0-9]/', '', (string) ($_POST['search_key'] ?? '')));
        $search_key = substr($search_key, 0, 32);
        $rank = max(1, min(4, absint($_POST['rank'] ?? 1)));
        $surface = sanitize_key($_POST['surface'] ?? 'unknown');
        if (!in_array($surface, array('hero_text', 'hero_voice', 'widget', 'unknown'), true)) {
            $surface = 'unknown';
        }

        // A short dedupe window prevents double-click/navigation retries from
        // inflating the signal. The visitor key is used only in the transient.
        $dedupe_key = 'mia_outcome_' . md5($this->get_rate_limit_key('provider_outcome') . '|' . $provider_id . '|' . $search_key);
        if (get_transient($dedupe_key)) {
            wp_send_json_success(array('accepted' => true, 'deduplicated' => true, 'provider_id' => $provider_id));
        }
        set_transient($dedupe_key, 1, 20);

        $entry = array(
            'schema' => 1,
            'ts' => gmdate('Y-m-d\TH:i:s\Z'),
            'event' => 'profile_click',
            'provider_id' => $provider_id,
            'rank' => $rank,
            'search_key' => $search_key,
            'surface' => $surface,
        );
        $log_file = WP_CONTENT_DIR . '/mia-provider-outcomes.jsonl';
        if (file_exists($log_file) && filesize($log_file) > 5 * MB_IN_BYTES) {
            @unlink($log_file . '.1');
            @rename($log_file, $log_file . '.1');
        }
        @file_put_contents($log_file, wp_json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

        wp_send_json_success(array('accepted' => true, 'deduplicated' => false, 'provider_id' => $provider_id));
    }

    public function handle_dynamic_greeting() {
        $this->verify_mia_ajax_request();

        if (!$this->is_enabled()) {
            wp_send_json_error(array('message' => 'Mia is currently unavailable.'), 503);
        }

        $rate_limit = $this->enforce_rate_limit('dynamic_greeting');
        if (is_wp_error($rate_limit)) {
            wp_send_json_error(array('message' => $rate_limit->get_error_message()), 429);
        }

        $language = sanitize_text_field($_POST['language'] ?? 'en');
        $language = (strpos(strtolower($language), 'es') === 0) ? 'es' : 'en';
        $fallback = ($language === 'es')
            ? 'Hola, soy Mia. Cuentame que tipo de apoyo buscas y te guio hacia la mejor opcion.'
            : "Hi, I'm Mia. Tell me what kind of support you're looking for, and I'll guide you to the right fit.";

        $messages = array(
            array(
                'role' => 'system',
                // NOTE: This greeting-generation prompt is intentionally separate from the
                // identity section in build_identity_section(). That method defines Mia's
                // runtime persona for full conversations; this prompt is a one-shot instruction
                // that asks the API to produce a single opening greeting line.
                'content' => ($language === 'es')
                    ? "Eres Mia, una concierge de bienestar calida y moderna. Escribe una sola bienvenida breve y natural para iniciar una conversacion por voz en vivo. Usa 1 o 2 frases cortas. Invita a la persona a decir que tipo de apoyo busca. No menciones MyHealthHub salvo que sea necesario."
                    : "You are Mia, a warm modern wellness concierge. Write one short natural opening greeting for a live voice conversation. Use 1 or 2 short sentences. Invite the person to share what kind of support they want. Do not mention MyHealthHub unless necessary."
            ),
            array(
                'role' => 'user',
                'content' => ($language === 'es')
                    ? 'Genera el saludo ahora.'
                    : 'Generate the greeting now.'
            ),
        );

        $greeting = $fallback;
        $api_response = $this->call_grok_api($messages);

        if (!is_wp_error($api_response)) {
            $body = wp_remote_retrieve_body($api_response);
            $data = json_decode($body, true);
            if (!empty($data['choices'][0]['message']['content'])) {
                $candidate = wp_strip_all_tags((string) $data['choices'][0]['message']['content']);
                $candidate = preg_replace('/\s+/', ' ', $candidate);
                $candidate = trim($this->cleanup_brand_repetition($candidate));
                if ($candidate !== '') {
                    $greeting = $candidate;
                }
            }
        }

        wp_send_json_success(array(
            'greeting' => $greeting,
            'language' => $language,
            'provider' => is_wp_error($api_response) ? 'fallback' : 'xai',
        ));
    }

    private function normalize_tts_text($text) {
        $text = $this->cleanup_brand_repetition((string) $text);
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '$1', $text);
        $text = preg_replace('/[*_`>#-]+/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = $this->normalize_spoken_response($text);
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        // Keep latency tight for hero playback and avoid oversized TTS turns.
        // Truncate at the last sentence boundary before 1200 chars so TTS
        // never speaks a garbled half-sentence.
        $limit = 8000;
        if (function_exists('mb_strlen') && mb_strlen($text) > $limit) {
            $truncated = mb_substr($text, 0, $limit);
            // Find the last sentence-ending punctuation
            $lastPeriod   = mb_strrpos($truncated, '.');
            $lastExclaim  = mb_strrpos($truncated, '!');
            $lastQuestion = mb_strrpos($truncated, '?');
            $lastBoundary = max($lastPeriod, $lastExclaim, $lastQuestion);
            if ($lastBoundary !== false && $lastBoundary > $limit * 0.4) {
                $text = mb_substr($truncated, 0, $lastBoundary + 1);
            } else {
                $text = $truncated;
            }
        } elseif (strlen($text) > $limit) {
            $truncated = substr($text, 0, $limit);
            $lastPeriod   = strrpos($truncated, '.');
            $lastExclaim  = strrpos($truncated, '!');
            $lastQuestion = strrpos($truncated, '?');
            $lastBoundary = max($lastPeriod, $lastExclaim, $lastQuestion);
            if ($lastBoundary !== false && $lastBoundary > $limit * 0.4) {
                $text = substr($truncated, 0, $lastBoundary + 1);
            } else {
                $text = $truncated;
            }
        }

        return trim($text);
    }

    private function normalize_spoken_search_terms($message) {
        $message = trim((string) $message);
        if ($message === '') {
            return '';
        }

        // ── Strip xAI interpretive prefixes ──
        // The xAI realtime transcription model sometimes rephrases the
        // user's speech instead of transcribing it literally. For example,
        // when the user says "Ashley Almon" in a claim context, xAI may
        // produce "The profile name is Ashne Alman." — adding framing
        // words that make the transcript useless for name extraction.
        // Strip them so only the name/content portion remains.
        // (Debug log 1777325005623.)
        //
        // Two cases:
        //  1. Prefix at start: "The profile name is Ashne Alman." → "Ashne Alman."
        //  2. Combined (browser + xAI): "Ashley almond The profile name is Ashne Alman."
        //     → extract the xAI interpretation: "Ashne Alman."
        if (preg_match('/^.+?\b(?:the\s+)?(?:profile|provider|practice|listing|business)\s+(?:name\s+)?(?:is|was|would\s+be|should\s+be)\s+(?!(?:in|at|near|based|located)\b)(.+)$/i', $message, $xai_match)) {
            // Combined transcript: browser fragment + xAI re-interpretation.
            // Prefer the xAI portion (the name after "is").
            $message = trim($xai_match[1]);
        } else {
            // Simple prefix at start
            $message = preg_replace('/^(?:the\s+)?(?:profile|provider|practice|listing|business)\s+(?:name\s+)?(?:is|was|would\s+be|should\s+be)\s+/i', '', $message);
        }
        $message = preg_replace('/^my\s+(?:name\s+is|profile\s+name\s+is|practice\s+name\s+is|provider\s+name\s+is)\s+/i', 'My name is ', $message);
        $message = preg_replace('/^i\s+(?:said|mean|meant)\s+/i', '', $message);
        $message = preg_replace('/^it(?:\'s|\s+is|\s+was)\s+(?:called|named)\s+/i', '', $message);
        $message = trim($message);

        $message = preg_replace('/[;:]+/', ' ', $message);
        $message = preg_replace('/\b(?:um+|uh+|erm+|er+|ah+|eh+|hmm+|mm+|emm+|amm+|em+|este+|pues|o\s+sea|bueno|you know|ya know)\b/i', ' ', $message);
        $message = preg_replace('/\b(?:like)\b(?=\s+(?:i|can|could|need|want|my|claim|provider|profile|listing)\b)/i', ' ', $message);
        $message = preg_replace('/\b(i\s+need|i\s+want|i\s+am|i\'m|my\s+profile|my\s+listing|claim\s+my|add\s+my)(?:\s+\1\b)+/i', '$1', $message);
        $message = preg_replace('/\b(necesito|quiero|mi\s+perfil|mi\s+listado|reclamar\s+mi|agregar\s+mi|añadir\s+mi|anadir\s+mi)(?:\s+\1\b)+/iu', '$1', $message);
        $message = preg_replace('/\b(\w+)(?:\s+\1\b)+/i', '$1', $message);
        $message = preg_replace('/\s*([,;:])\s*(?:[,;:]\s*)+/', '$1 ', $message);
        $message = preg_replace('/^[,.;:\s]+(?=\w)/', '', $message);
        $message = preg_replace('/\b(tell me|show me|give me|find me|help me),\s+/i', '$1 ', $message);
        $message = preg_replace('/\s+([,.;:!?])/', '$1', $message);
        $message = preg_replace('/([,.;:!?]){2,}/', '$1', $message);
        $message = preg_replace('/\bi\s+need\s+the\s+my\b/i', 'I need my', $message);
        $message = preg_replace('/\bi\s+(?:need|want)\s+claim\s+my\b/i', 'I need to claim my', $message);
        $message = preg_replace('/\bneed\s+claim\s+my\b/i', 'need to claim my', $message);
        $message = preg_replace('/\bmy\s+(profile|listing|provider profile|provider page)\s+claim\b/i', 'claim my $1', $message);
        $message = preg_replace('/\b(profile|listing)\s+mine\b/i', 'my $1', $message);
        $message = preg_replace('/\bi\s+need\s+my\s+(profile|proof|listing|provider profile|provider proof|provider page)\s+i\s+need(?:\s+\w+)?\s*$/i', 'I need my $1', $message);
        $message = preg_replace('/\bi\s+need\s+(?:the\s+)?my\s+proof\b/i', 'I need my profile', $message);
        $message = preg_replace('/\bmy\s+provider\s+proof\b/i', 'my provider profile', $message);
        $message = preg_replace('/\bnecesito\s+el\s+mi\b/iu', 'necesito mi', $message);
        $message = preg_replace('/\b(?:necesito|quiero)\s+reclamar\s+(?:el\s+)?mi\b/iu', 'necesito reclamar mi', $message);
        $message = preg_replace('/\breclamo\s+mi\b/iu', 'reclamar mi', $message);
        $message = preg_replace('/\bclamar\s+mi\b/iu', 'reclamar mi', $message);
        $message = preg_replace('/\breclama\s+mi\b/iu', 'reclamar mi', $message);
        $message = preg_replace('/\bmi\s+(perfil|listado|práctica|practica|consulta|negocio)\s+reclamar\b/iu', 'reclamar mi $1', $message);
        $message = preg_replace('/\b(perfil|listado)\s+m[ií]o\b/iu', 'mi $1', $message);
        $message = preg_replace('/\bnecesito\s+mi\s+(perfil|perfile|listado|perfil de proveedor|p[áa]gina de proveedor)\s+necesito(?:\s+\w+)?\s*$/iu', 'necesito mi $1', $message);
        $message = preg_replace('/\bmi\s+perfile\b/iu', 'mi perfil', $message);
        $message = preg_replace('/\bmi\s+por\s+fin\b/iu', 'mi perfil', $message);
        $message = preg_replace('/\bmi\s+perf[ií]l\b/iu', 'mi perfil', $message);
        $message = preg_replace('/\bprovedor\b/iu', 'proveedor', $message);
        $message = preg_replace('/\bprove[eé]dor\b/iu', 'proveedor', $message);

        $message = preg_replace('/\btherapy\s+(?:doctor|person|people|provider)\b/i', 'therapist', $message);
        $message = preg_replace('/\b(?:find|fine|found)\s+(?:me\s+)?tv\s+providers?\b/i', 'find therapy providers', $message);
        $message = preg_replace('/\btv\s+(?:providers?|therapists?|therapy)\b/i', 'therapy providers', $message);
        $message = preg_replace('/\btherapist\s+(?:doctor|person|people)\b/i', 'therapist', $message);
        $message = preg_replace('/\bmental\s+(?:help|support|doctor|person|people)\b/i', 'mental health provider', $message);
        $message = preg_replace('/\b(?:stress|anxiety|depression|trauma|grief)\s+(?:doctor|person|people|provider)\b/i', '$1 therapist', $message);
        $message = preg_replace('/\bdoctor\s+for\s+(anxiety|depression|stress|trauma|grief)\b/i', '$1 therapist', $message);
        $message = preg_replace('/\b(?:counseling|counselling)\s+(?:people|person|doctor)\b/i', 'counseling providers', $message);
        $message = preg_replace('/\bshow\s+(?:counseling|therapy)\s+people\b/i', 'show counseling providers', $message);
        $message = preg_replace('/\bi\s+(?:need|want)\s+(?:mental\s+)?help\s+(?:for\s+)?(anxiety|depression|stress|trauma|grief)\b/i', 'I need $1 therapist', $message);
        $message = preg_replace('/\bi\s+(?:need|want)\s+(anxiety|depression|stress|trauma|grief)\s+help\b/i', 'I need $1 therapist', $message);
        $message = preg_replace('/\bfind\s+(?:me\s+)?(?:therapy|counseling|mental health)\s+(?:person|people)\b/i', 'find therapists', $message);
        $message = preg_replace('/\b(?:find|show|search)\s+(?:a\s+)?(?:dinner|diner|dinder|winner|thinner)\b/i', 'find a provider', $message);

        $message = preg_replace('/\bayuda\s+(ansiedad|depresi[oó]n|estres|estr[eé]s|trauma|duelo)\b/iu', 'ayuda con $1', $message);
        $message = preg_replace('/\bnecesito\s+ayuda\s+(ansiedad|depresi[oó]n|estres|estr[eé]s|trauma|duelo)\b/iu', 'necesito ayuda con $1', $message);
        $message = preg_replace('/\b(?:doctor|doctora|persona|gente)\s+(?:de|para)\s+(ansiedad|depresi[oó]n|estres|estr[eé]s|trauma|duelo)\b/iu', 'terapeuta para $1', $message);
        $message = preg_replace('/\b(?:persona|gente)\s+de\s+(?:terapia|consejer[ií]a|salud mental)\b/iu', 'proveedores de terapia', $message);
        $message = preg_replace('/\bmu[eé]strame\s+(?:personas|gente)\s+de\s+(?:terapia|consejer[ií]a)\b/iu', 'muestrame terapeutas', $message);
        $message = preg_replace('/\bbusco\s+(?:ayuda\s+)?(ansiedad|depresi[oó]n|estres|estr[eé]s|trauma|duelo)\b/iu', 'busco terapeuta para $1', $message);
        $message = preg_replace('/\bquiero\s+psic[oó]logo\s+([a-záéíóúñ ]+)\b/iu', 'quiero psicologo en $1', $message);

        $search_context = (bool) preg_match('/\b(find|show|search|looking|need|want|provider|providers|therapist|therapists|counselor|counselors|therapy|general)\b/i', $message);
        if ($search_context || preg_match('/^\s*(jenna|genna|jena)\s+(clarkson|clarkston|clarksen|clarkson\'s)\s*[?.!]*\s*$/i', $message)) {
            // Common ASR miss: "general counseling provider" can arrive as a person name.
            $message = preg_replace('/\b(jenna|genna|jena)\s+(clarkson|clarkston|clarksen|clarkson\'s)\b/i', 'general counseling providers', $message);
        }

        $message = preg_replace('/\bto\s+find\s+(?:regional|general)\s+accounts?\s+link\b/i', 'find general counseling providers', $message);
        $message = preg_replace('/\bfind\s+(?:in\s+)?(?:regional|general)\s+accounts?\s+link\b/i', 'find general counseling providers', $message);
        $message = preg_replace('/\b(?:in\s+)?(?:regional|general)\s+accounts?\s+link\b/i', 'general counseling', $message);
        $message = preg_replace('/\bgeneral\s+counsel(?:\s+(?:providers?|proveders?|proveder|provder))?\b/i', 'general counseling provider', $message);
        $message = preg_replace('/\bgeneral\s+councel(?:\s+(?:providers?|proveders?|proveder|provder))?\b/i', 'general counseling provider', $message);
        $message = preg_replace('/\bgeneral\s+council(?:\s+(?:providers?|proveders?|proveder|provder))?\b/i', 'general counseling provider', $message);
        $message = preg_replace('/\bgeneral\s+counselor(?:s)?\s+providers?\b/i', 'general counseling providers', $message);
        $message = preg_replace('/\bgeneral\s+counseling\s+provider\b/i', 'general counseling providers', $message);
        $message = preg_replace('/\bi\s+feel\s+nearby\s+what\s+should\s+i\s+do\b/i', 'I feel anxious and need someone nearby. What should I do', $message);
        $message = preg_replace('/\bi\s+feel\s+near\s+me\s+what\s+should\s+i\s+do\b/i', 'I feel anxious and need someone near me. What should I do', $message);
        $message = preg_replace('/\bi\s+feel\s+anxiet(?:y|ies)\b/i', 'I feel anxiety', $message);

        // ── Ordinal ASR mishears ──
        // Chrome ASR and xAI realtime frequently mishear ordinal words, especially
        // "fourth" as "Force"/"for" and "fifth" as "Fifth" (the avenue).  These are
        // phonetically identical but semantically very different in a provider-
        // selection context where the user is choosing from a numbered list.
        // Debug log 1777389975638: "I said the fourth one" heard as "I said
        // Force One" → backend received "for the one, the last one" → resolved
        // to 1st provider instead of 4th.
        $message = preg_replace('/\bforce\s+(?:one|1)\b/i', 'fourth one', $message);
        $message = preg_replace('/\bfor\s+the\s+one\b/i', 'fourth one', $message);
        $message = preg_replace('/\bfor\s+one\b/i', 'fourth one', $message);
        $message = preg_replace('/\bfor\s+(?:4|four|fourth)\s+force\b/i', 'fourth', $message);
        $message = preg_replace('/\b4\s*(?:th|one)\b/i', 'fourth one', $message);
        // "41" as standalone phrase → "fourth one" (user saying "fourth one" rapidly)
        if (preg_match('/^(?:41|4\s*1)\s*$/i', $message)) {
            $message = 'fourth one';
        }
        $message = preg_replace('/\bFifth\s+(?:one|1)\b/i', 'fifth one', $message);
        $message = preg_replace('/\b(?:sec|2nd)\s+one\b/i', 'second one', $message);
        $message = preg_replace('/\b(?:3rd|thirt)\s+one\b/i', 'third one', $message);
        // ── xAI Realtime ordinal misrecognition: "the third one" → "the Sartre" ──
        // Debug log 1777420050565: User said "what about the third one" but xAI
        // Realtime API transcribed it as "tell me more about the Sartre."
        // Phonetic similarity: "Sartre" (/sɑːrt/) ≈ "third" (/θɜːrd/).
        $message = preg_replace('/\bthe\s+Sartre\b/i', 'the third one', $message);
        $message = preg_replace('/\babout\s+the\s+Sartre\b/i', 'about the third one', $message);
        // "the third" misheard as "the searched" / "the surge" / "the surd" / "the sir"
        $message = preg_replace('/\bthe\s+(?:searched|surge|surd|sir|serge|sert)\b/i', 'the third', $message);
        $message = preg_replace('/\b1st\s+one\b/i', 'first one', $message);
        $message = preg_replace('/\bi\s+feel\s+anxiousness\b/i', 'I feel anxious', $message);
        // ── xAI Realtime hallucinated prefix: "I need me provider. Tell me more" ──
        // Debug log 1777539583535 turn 3: User said "tell me more about" but xAI
        // Realtime API garbled it as "I need me provider. Tell me more".  The prefix
        // triggers consumer_provider_search on the backend because "need"+"provider"
        // are search signals.  Strip the garbled prefix so the followup intent is
        // preserved.  The frontend also normalizes this, but the backend must handle
        // it too for cases where the frontend normalization doesn't fire (e.g. text
        // chat or the normalization is applied before the route_hint).
        $message = preg_replace('/^i\s+need\s+me\s+provider\s*\.?\s*tell\s+me\s+more\b/i', 'tell me more', $message);
        $message = preg_replace('/^i\s+need\s+(?:me\s+)?(?:a\s+)?provider\s*\.?\s*/i', 'find a provider ', $message);
        // "Melissa to one" / "Melissa at 1:00" → ordinal garble after "tell me"
        // Debug log 1777539583535 turn 4: User likely said "tell me about the second
        // one" but ASR heard "tell me Melissa to one" (62% confidence).
        $message = preg_replace('/\btell\s+me\s+melissa\s+(?:to|2|at)\s+(?:one|1)\b/i', 'tell me the second one', $message);
        $message = preg_replace('/\btell\s+me\s+(?:about\s+)?melissa\s+(?:to|2|at)\s+(?:one|1)\b/i', 'tell me about the second one', $message);
        // ── xAI Realtime hallucinated prefix: "I'm David" ──
        // Debug log 1777548040629: The Realtime API hallucinates "I'm David"
        // (and "I'm David from Britain") as a prefix on almost every turn.
        // This corrupts the transcript with a false speaker identity.
        $message = preg_replace('/^i\'?m\s+david\s+(?:from\s+\w+)?\s*[.,!]?\s*/i', '', $message);
        // ── Browser ASR "I need" bleed when barging in during TTS ──
        // Debug log 1777548040629: When the user barges in during TTS playback,
        // Browser ASR picks up "I need" or "I need to" which gets concatenated
        // with the Realtime transcript.  After stripping "I'm David", the
        // orphaned "I need" / "I need to" remains.  Strip it from the start.
        $message = preg_replace('/^i\s+need\s+(?:to\s+)?\s*(?=[A-Z])/', '', $message);
        $message = preg_replace('/^i\s+need\s*(?=[A-Z])/', '', $message);
        $message = preg_replace('/\s*([,;:])\s*(?:[,;:]\s*)+/', '$1 ', $message);
        $message = preg_replace('/^[,.;:\s]+(?=\w)/', '', $message);
        $message = preg_replace('/\b(tell me|show me|give me|find me|help me),\s+/i', '$1 ', $message);
        $message = preg_replace('/\s+([,.;:!?])/', '$1', $message);
        $message = preg_replace('/([,.;:!?]){2,}/', '$1', $message);
        $message = preg_replace('/\s{2,}/', ' ', $message);

        return trim($message);
    }

    private function normalize_spoken_response($text) {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\b(I(\'d| would)\s+be\s+happy\s+to\s+help\.?\s*)/i', '', $text);
        $text = preg_replace('/\b(Absolutely|Certainly|Of course)[,!\s]+/i', '', $text);
        $text = preg_replace('/\b(Here are some providers|Here are a few providers)\s*[:\-]?\s*/i', '', $text);
        $text = preg_replace('/\b(Visit their profile to learn more about their practice and how to connect\.?)\s*/i', '', $text);
        $text = preg_replace('/\b(Browse All Providers|Browse all providers|View all matches|View all providers)\.?\s*/i', '', $text);

        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($sentences) || empty($sentences)) {
            return trim($text);
        }

        $seen = array();
        $kept = array();

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }

            $fingerprint = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $sentence));
            $fingerprint = trim($fingerprint);
            if ($fingerprint === '' || isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $kept[] = $sentence;

            if (count($kept) >= 3) {
                break;
            }
        }

        $text = implode(' ', $kept);
        $text = preg_replace('/\s{2,}/', ' ', $text);

        return trim($text);
    }

    private function cleanup_brand_repetition($text, $message = '', $intent = '') {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }

        $message = strtolower((string) $message);
        $intent  = (string) $intent;
        if ($intent === MyHH_Mia_Intent_Engine::INTENT_GENERAL) {
            return trim($text);
        }

        // ── Protect URLs from the brand-repetition scrubber ──
        // The scrubber removes "myhealthub.com" from prose, but URLs MUST keep
        // their host intact.  Without this, [Claim](https://myhealthub.com/claim-listing/?id=1)
        // becomes [Claim](https:///claim-listing/?id=1) and the browser treats
        // "claim-listing" as the host.  Mask URLs → scrub → unmask.
        $url_stash = array();
        $text = preg_replace_callback('#(\]\()(https?://[^)\s]+)(\))#i', function($m) use (&$url_stash) {
            $idx = count($url_stash);
            $url_stash[] = $m[2];
            return $m[1] . 'MIAURL' . $idx . 'ENDMIAURL' . $m[3];
        }, $text);
        $text = preg_replace_callback('#(https?://[^\s<)]+)#i', function($m) use (&$url_stash) {
            $idx = count($url_stash);
            $url_stash[] = $m[1];
            return 'MIAURL' . $idx . 'ENDMIAURL';
        }, $text);

        $patterns = array(
            '/\bMy\s*Health\s*(?:Hub|Ub)(?:\.com)?\s*[,:\-–]*\s*/i',
            '/\bOn\s+My\s*Health\s*(?:Hub|Ub)(?:\.com)?\b\s*[,:\-–]*\s*/i',
            '/\bHere\s+on\s+My\s*Health\s*(?:Hub|Ub)(?:\.com)?\b\s*[,:\-–]*\s*/i',
            '/\bThrough\s+My\s*Health\s*(?:Hub|Ub)(?:\.com)?\b\s*[,:\-–]*\s*/i',
            '/\b(?:listed\s+)?(?:on|at|through|via|from)\s+My\s*Health\s*(?:Hub|Ub)(?:\.com)?\b\s*[,:\-–\.]*\s*/i',
            '/\b(?:myhealthub|myhealthhub)(?:\.com)?\b\s*[,:\-–\.]*\s*/i',
            '/\b(?:available\s+)?(?:on|at|through|via|from)\s+(?:our|the|this)\s+(?:platform|directory|site|website)\b\s*[,:\-–\.]*\s*/i',
            '/\bhere\s+(?:on|at)\s+(?:our|the|this)\s+(?:platform|directory|site|website)\b\s*[,:\-–\.]*\s*/i',
        );

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }

        if ($intent === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH) {
            $text = preg_replace('/\(?\b(?:my\s*health\s*ub|my\s*health\s*hub|myhealthub|myhealthhub)(?:\.com)?\b\)?\s*[,:\-–\.]*\s*/i', '', $text);
            $text = preg_replace('/\b(?:this|our)\s+(?:directory|platform|site|website)\b\s*[,:\-–]*\s*/i', '', $text);
            $text = preg_replace('/\b(?:here|right here)\s+(?:you can|you will|you\'ll|we can)\b\s*/i', '', $text);
        }

        $text = preg_replace('/\b(My\s*Health\s*(?:Hub|Ub)(?:\.com)?)(\s+\1)+\b/i', '$1', $text);
        $text = preg_replace('/(?:\bMy\s*Health\s*(?:Hub|Ub)(?:\.com)?\b[\s,.;:!?-]*){2,}/i', '', $text);
        $text = preg_replace('/\s+([,.;:!?])/', '$1', $text);
        $text = preg_replace('/\s{2,}/', ' ', $text);

        // Final safety for provider-result replies: never let a platform/domain
        // label survive as if it were a provider name.
        if ($intent === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH) {
            $text = preg_replace('/\(?\b(?:My\s*Health\s*(?:Hub|Ub)|myhealthub|myhealthhub)(?:\.com)?\b\)?\s*[,:\-–\.]*\s*/i', '', $text);
        } elseif (substr_count(strtolower($text), 'myhealthhub') > 1 || substr_count(strtolower($text), 'myhealthub') > 1) {
            $first = true;
            $text = preg_replace_callback('/(?:My\s*Health\s*(?:Hub|Ub)|myhealthub|myhealthhub)(?:\.com)?/i', function($m) use (&$first) {
                if ($first) { $first = false; return $m[0]; }
                return '';
            }, $text);
            $text = preg_replace('/\s{2,}/', ' ', $text);
        }

        // ── Restore protected URLs ──
        if (!empty($url_stash)) {
            $text = preg_replace_callback('/MIAURL(\d+)ENDMIAURL/', function($m) use ($url_stash) {
                $i = (int) $m[1];
                return isset($url_stash[$i]) ? $url_stash[$i] : '';
            }, $text);
        }

        return trim($text);
    }

    private function enforce_grounded_provider_reply($assistant_reply, $result, $message = '', $intent = '', $response_type = '') {
        $assistant_reply = (string) $assistant_reply;
        $intent = (string) $intent;
        $response_type = strtolower((string) $response_type);

        if ($intent !== MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH) {
            return $assistant_reply;
        }

        $understanding = (!empty($result['_grok_understanding']) && is_array($result['_grok_understanding']))
            ? $result['_grok_understanding']
            : array();
        $next_action = strtolower((string) ($understanding['next_action'] ?? $understanding['action'] ?? ''));
        $route_hint_override = strtolower((string) ($result['_route_hint_override'] ?? ''));
        $is_provider_followup = $response_type === 'deterministic_followup'
            || $route_hint_override === 'provider_followup'
            || in_array($next_action, array(
                'provider_detail',
                'provider_location',
                'provider_insurance',
                'provider_booking',
                'provider_compare',
            ), true);

        if ($is_provider_followup) {
            mia_debug_log('Chat', 'grounded_enforce_skipped_followup', array(
                'response_type' => $response_type,
                'route_hint_override' => $route_hint_override,
                'next_action' => $next_action,
                'preview' => substr(wp_strip_all_tags($assistant_reply), 0, 90),
            ));
            return $assistant_reply;
        }

        $providers = array_values($result['providers'] ?? array());
        if (empty($providers)) {
            if ($response_type === 'search_no_results' && empty($result['criteria']['needs_clarification'])) {
                return $this->build_grounded_no_results_reply($result['criteria'] ?? array(), $result['detected_language'] ?? 'en');
            }
            return $assistant_reply;
        }

        $is_platform_question = (bool) preg_match('/\b(my\s*health\s*ub|my\s*health\s*hub|myhealthub|myhealthhub|this site|the platform|website)\b/i', strtolower((string) $message));
        if ($is_platform_question) {
            return $assistant_reply;
        }

        $normalized_reply = strtolower(wp_strip_all_tags($assistant_reply));
        $has_brand_mention = (bool) preg_match('/\b(?:my\s*health\s*ub|my\s*health\s*hub|myhealthub|myhealthhub)(?:\.com)?\b/i', $assistant_reply);
        $mentions_provider_name = false;

        foreach (array_slice($providers, 0, 2) as $provider) {
            $provider_name = $this->get_safe_provider_display_name($provider);
            if ($provider_name !== '' && stripos($normalized_reply, strtolower($provider_name)) !== false) {
                $mentions_provider_name = true;
                break;
            }
        }

        if ($assistant_reply === '' || $has_brand_mention || !$mentions_provider_name) {
            return $this->format_providers_fallback($result, $message);
        }

        return $assistant_reply;
    }

    private function normalize_tts_language($language) {
        $language = strtolower((string) $language);

        if (strpos($language, 'es') === 0) {
            return 'es-MX';
        }

        return 'en';
    }

    private function get_static_tts_cache_key($text, $language) {
        $allowed_clips = array(
            'en' => array(
                'Let me look into that.',
                "I'll pull that up.",
                "One moment, I'm on it.",
            ),
            'es-MX' => array(
                'Déjame revisarlo.',
                'Voy a buscarlo.',
                'Dame un momento.',
            ),
        );

        if (!isset($allowed_clips[$language]) || !in_array($text, $allowed_clips[$language], true)) {
            return '';
        }

        return 'myhh_mia_static_tts_' . md5('v2|' . $this->tts_voice . '|' . $language . '|' . $text);
    }

    private function is_retryable_tts_status($status) {
        $status = (int) $status;
        return $status === 0 || $status === 408 || $status === 409 || $status === 425 || $status === 429 || $status >= 500;
    }

    private function request_xai_tts($text, $language) {
        $request_args = array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'text'        => $text,
                'voice_id'    => $this->tts_voice,
                'language'    => $language,
                'output_format' => array(
                    'codec'       => 'mp3',
                    'sample_rate' => 24000,
                    'bit_rate'    => 128000,
                ),
            )),
        );

        $max_attempts = 2;
        $last_error = null;

        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            $response = wp_remote_post($this->tts_url, $request_args);

            if (is_wp_error($response)) {
                $last_error = new WP_Error('tts_request_failed', 'The voice service request failed.', array('status' => 0));
                if ($attempt < $max_attempts) {
                    usleep(250000 * $attempt);
                    continue;
                }
                return $last_error;
            }

            $status = wp_remote_retrieve_response_code($response);
            $body   = wp_remote_retrieve_body($response);

            if ($status < 200 || $status >= 300 || empty($body)) {
                $error_message = 'The voice service could not generate audio right now.';
                $decoded = json_decode($body, true);
                if (!empty($decoded['error']['message'])) {
                    $error_message = sanitize_text_field($decoded['error']['message']);
                } elseif (!empty($decoded['message'])) {
                    $error_message = sanitize_text_field($decoded['message']);
                }

                // Log to help diagnose API failures (gated behind WP_DEBUG to avoid leaking sensitive data)
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Mia TTS] xAI TTS failed - status: ' . $status . ' attempt: ' . $attempt);
                }

                $last_error = new WP_Error('tts_bad_response', $error_message, array('status' => $status));
                if ($attempt < $max_attempts && $this->is_retryable_tts_status($status)) {
                    usleep(250000 * $attempt);
                    continue;
                }

                return $last_error;
            }

            $mime = wp_remote_retrieve_header($response, 'content-type');
            if (empty($mime)) {
                $mime = 'audio/mpeg';
            }

            return array(
                'mime'  => $mime,
                'bytes' => $body,
            );
        }

        return $last_error ?: new WP_Error('tts_bad_response', 'The voice service could not generate audio right now.', array('status' => 502));
    }

    private function request_xai_realtime_client_secret() {
        // Finish upstream work inside the browser's 6s abort, including PHP bootstrap.
        $started_at = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true);
        $remaining = 4.5 - max(0, microtime(true) - $started_at);
        if ($remaining < 0.5) {
            return new WP_Error('realtime_startup_budget', 'Voice startup took too long. Please try again.');
        }
        $response = wp_remote_post($this->realtime_secret_url, array(
            'timeout' => min(4.0, $remaining),
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'expires_after' => array(
                    'seconds' => 300,
                ),
            )),
        ));

        if (is_wp_error($response)) {
            return new WP_Error('realtime_secret_failed', 'Could not start realtime voice right now.');
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = wp_remote_retrieve_body($response);
        $data   = json_decode($body, true);

        if ($status < 200 || $status >= 300 || !is_array($data)) {
            $message = 'Could not start realtime voice right now.';
            if (!empty($data['error']['message'])) {
                $message = sanitize_text_field($data['error']['message']);
            } elseif (!empty($data['message'])) {
                $message = sanitize_text_field($data['message']);
            }
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Mia Realtime] client_secrets failed — status: ' . $status);
            }
            return new WP_Error('realtime_secret_bad_response', $message, array('status' => $status));
        }

        return $data;
    }

    private function extract_realtime_client_secret($payload) {
        if (!is_array($payload)) {
            return '';
        }

        $candidates = array(
            $payload['value'] ?? '',
            $payload['client_secret']['value'] ?? '',
            $payload['client_secret'] ?? '',
            $payload['secret'] ?? '',
            $payload['token'] ?? '',
        );

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private function build_realtime_voice_instructions() {
        $context = $this->get_directory_context();
        $base = $this->build_base_system_prompt($context);

        $base .= "\n\n## REALTIME VOICE OPERATING MODE\n";
        $base .= "You are speaking live as Mia.\n";
        $base .= "The latest literal user request takes priority over saved workflow state. If a user describes their own pain, symptoms, sleep problems, anxiety, or another personal concern, respond as a patient navigator. Never reinterpret that concern as claiming or adding a provider profile, and never ask whether they meant a claim unless their current words actually ask about ownership. Call myhealthhub_lookup with the literal concern before speaking so it can clear an old owner workflow.\n";
        $base .= "Detailed education about any recognized wellness category, service, modality, provider type, or license that fits MyHealthHub's service scope is in scope. Answer definitions, comparisons, common purposes, typical provider types, and natural educational follow-ups directly from the Category & Specialty Knowledge and your established domain knowledge. Do not require an exact keyword and do not call myhealthhub_lookup solely because the question names a service such as counseling, therapy, CBT, acupuncture, nutrition, massage, yoga, sleep coaching, financial wellness, or mindfulness.\n";
        $base .= "For an educational answer, lead with a plain-language definition, then explain what it commonly covers and which type of professional may provide it. Add distinctions or limitations only when useful. Never turn general education into personalized diagnosis, treatment advice, an efficacy promise, or a claim about a specific listing.\n";
        $base .= "General education is not evidence about directory inventory. Do not claim that many, most, some, or all listed providers offer a modality or service unless the tool result explicitly establishes that fact. Explain the concept without an unsolicited provider-search pitch.\n";
        $base .= "Use myhealthhub_lookup when the user asks for current providers, counts, availability, locations, links, profile facts, policies, prices, or another platform action. A prior provider search never turns a new educational question into another search.\n";
        $base .= "For any provider search, provider follow-up, claim/add/upgrade/sponsor/provider-signup question, crisis or safety question, or directory/platform-specific question, call the function myhealthhub_lookup before responding.\n";
        $base .= "These are backend-owned tasks. Do NOT answer them from memory, even if you think you know the answer.\n";
        $base .= "CRITICAL: Do NOT speak any introductory or greeting text before calling myhealthhub_lookup. Call the function first, then speak only after you have the result.\n";
        $base .= "CRITICAL: When calling myhealthhub_lookup, keep the \"message\" parameter exactly equal to the user\'s spoken words. Put your interpretation only in next_action, intent, confidence, entities, search_entities, provider references, and context-change fields. Never append commentary to message.\n";
        $base .= "Choose the structured next_action from the current conversation rather than waiting for a specific keyword. Resolve natural paraphrases, follow-ups, corrections, and language switches semantically.\n";
        $base .= "A question about why you chose a person, or whether a detail is actually listed, is a provider follow-up, not a new search. Preserve the named person and the requested fact. An explicit name outranks an ordinal such as first. Never replace the result list unless the user requests another search.\n";
        $base .= "CRITICAL: If the user asks about providers already shown on screen, the directory itself, trust signals like verified/claimed, pricing/free use, or safety/crisis help, call myhealthhub_lookup before answering.\n";
        $base .= "Use the function result as your source of truth.\n";
        $base .= "If the function result contains assistant_reply, treat it as an authoritative factual fallback. Never invent different providers, policies, prices, URLs, availability, or credentials.\n";
        $base .= "If natural_response.allowed is true, express the verified result freshly in your own warm conversational words. Preserve prices and policy facts exactly, and never rename or substitute a provider you mention. The fallback paragraph is not a checklist to read aloud.\n";
        $base .= "If natural_response.allowed is false, speak assistant_reply exactly because the turn requires deterministic wording.\n";
        $base .= "If providers are present, use only those providers, name the best one or two early, and let the on-screen cards carry the remaining detail.\n";
        $base .= "A requested provider category is a hard constraint. Never describe a provider from another category as a match. If no provider is returned, say so plainly and ask whether to keep the service while trying another location.\n";
        $base .= "Keep spoken replies warm and conversational. When the answer requires detail (e.g. multiple providers, explanations), speak as long as needed. Avoid repeating long disclaimers unless needed.\n";
        $base .= "In voice mode, be concise when a short answer suffices, but give thorough spoken responses when the user's question warrants it.\n";
        $base .= "Use natural spoken cadence: calm, direct, lightly warm, and never robotic or over-scripted.\n";
        $base .= "When speaking English, use clear neutral American English pronunciation.\n";
        $base .= "When speaking Spanish, use clear neutral Latin American Spanish pronunciation; do not speak Spanish with English phrasing or an English-accented rhythm.\n";
        $base .= "Do not mix English into a Spanish answer unless the user mixes languages first or a provider/business name requires it.\n";
        $base .= "Avoid repetitive helper phrases like 'I'd be happy to help' or 'absolutely' unless they sound genuinely natural in context.\n";
        $base .= "Prefer one clear answer and one brief reason. Offer a next step only when it follows from the user's stated goal and readiness.\n";
        $base .= "For a capabilities question, answer in at most two short sentences: you can explain wellness options, help find providers and check their listed details, and guide claiming or adding a practice. Do not recite categories or provider counts unless asked. Published listings do not establish verified licenses.\n";
        $base .= "When a user shares a meaningful concern, briefly reflect the main themes once, without parroting their wording or acting like a therapist. Explain relevant options neutrally before searching unless the user is already asking for providers.\n";
        $base .= "Ask at most one missing question at a time, and only when its answer will materially improve the help you can give. If the user wants education, to be heard, or is not ready to search, do not force a provider call to action.\n";
        $base .= "When a fact is unknown or unavailable, name that limit plainly. Never fill the gap with a plausible-sounding assumption.\n";
        $base .= "For provider searches, speak about the best one or two providers by name and one useful reason for each. Do not read every result card aloud unless the user asks for the full list.\n";
        $base .= "The on-screen cards preserve the remaining provider names, links, and details. Mention that more options are visible instead of narrating every card.\n";
        $base .= "STRICT LIVE BREVITY: spoken_provider_facts is the complete allowed spoken provider set. Never say a name from card_only_provider_names unless the user explicitly asks you to read the full list.\n";
        $base .= "When providers are available, mention one or two actual provider names early in the reply instead of talking about the platform in general.\n";
        $base .= "STRICT RULE: Never say 'MyHealthHub' or 'My Health Hub' in provider-result answers. Even if you would naturally say 'here on MyHealthHub' or 'on our platform', omit it entirely.\n";
        $base .= "WRONG: 'Here is Dr. Smith on MyHealthHub in Durham. Here is Dr. Jones on MyHealthHub in Raleigh.'\n";
        $base .= "RIGHT: 'Here is Dr. Smith in Durham. And Dr. Jones in Raleigh.'\n";
        $base .= "Provider-result answers should sound local and direct, as if you are personally recommending real people, not narrating a website.\n";
        $base .= "If the function result says crisis guidance or location limitation, honor it exactly.\n";
        $base .= "If a user interrupts, answer the interruption directly without restarting the conversation.\n";
        $base .= "Never restart with an opening greeting in the middle of a live conversation. After the first hello, do not say 'Hi, I am Mia', 'Hello, I am Mia', or re-explain your role unless the user explicitly asks who you are.\n";
        $base .= "If the user is only greeting you or making small talk (for example 'hi', 'hello', 'how are you', 'what\\'s up', or a short social check-in), answer briefly and naturally like a person. Do not pivot into a description of the directory or your role unless they ask who you are or what you do.\n";
        $base .= "If the transcript is partial but clearly sounds like a social check-in, such as ending in 'are you?', treat it as small talk and answer briefly rather than giving your concierge introduction.\n";
        $base .= "If the user says a short social acknowledgement or apology such as 'sorry', 'thanks', 'thank you', 'okay', 'alright', or 'my bad', answer briefly and naturally like a person. Do not answer with clipped fragments or vague follow-up pieces.\n";
        $base .= "If the transcript sounds partial, hesitant, or incomplete and you cannot tell the task with confidence, ask one short clarifying question instead of guessing.\n";
        $base .= "For provider-result voice answers, lead with the best next fit and a short reason, then leave the rest to the on-screen cards unless the user asks for comparison or more detail.\n";
        $base .= "Users may speak with native or non-native accents. Treat transcripts literally, do not correct or paraphrase the user's wording before deciding intent, and ask one short clarifying question if the wording is uncertain.\n";
        $base .= "\n## SCOPE ENFORCEMENT IN VOICE MODE\n";
        $base .= "You are a directory concierge — NOT a general assistant, NOT a clinician, NOT a therapist, NOT an entertainer.\n";
        $base .= "In voice mode, you are speaking directly to a person in real time. This makes boundary violations MORE harmful, not less. A user who hears therapeutic advice spoken aloud may trust it more than text on a screen.\n";
        $base .= "When the user asks for medical advice, coping strategies, diagnosis, medication guidance, or any out-of-scope request:\n";
        $base .= "1. Do NOT answer the question, even partially. Do NOT say 'I can't diagnose, but...'\n";
        $base .= "2. Briefly state: 'I'm not able to help with that — I'm a directory guide, not a clinician.'\n";
        $base .= "3. Mention that you can help find an appropriate professional, but ask whether to search only if the user appears ready.\n";
        $base .= "When the user asks for jokes, trivia, or entertainment, briefly explain that you are focused on wellness navigation. Do not force a search question.\n";
        $base .= "NEVER answer an out-of-scope question and then add a disclaimer at the end. The redirect IS the answer.\n";

        return $base;
    }

    private function requested_response_language($message) {
        if (!preg_match('/\b(?:reply|respond|answer|speak\s+to\s+me|talk\s+to\s+me|keep\s+replying)\b[\s\S]{0,25}\b(English|Spanish|espa[ñn]ol|ingl[eé]s)\b|\b(?:h[aá]blame|resp[oó]ndeme)\s+en\s+(espa[ñn]ol|ingl[eé]s)\b/iu', $message, $m)) return '';
        $lang = strtolower(remove_accents($m[1] ?: ($m[2] ?? '')));
        return in_array($lang, array('spanish', 'espanol'), true) ? 'es' : 'en';
    }

    /** Reply-language instructions never silently become a provider search constraint. */
    private function normalize_conversation_language_understanding($message, $understanding) {
        $language = $this->requested_response_language($message);
        if ($language === '') return $understanding;
        $understanding['entities']['language'] = $language;
        $provider_language = preg_match('/\b(?:Spanish|English)[-\s]+speaking\b|\b(?:speaks?|speaking)\s+(?:Spanish|English)\b|\b(?:que\s+hable|hable|habla)\s+(?:espa[ñn]ol|ingl[eé]s)\b|\b(?:provider|therapist|counselor|doctor|proveedor|terapeuta)\s+(?:who|that|should|must|needs?\s+to)\s+(?:speak|habl)/iu', $message);
        if ($provider_language) return $understanding;
        unset($understanding['search_entities']['language_preference'], $understanding['entities']['language_preference']);
        $understanding['clear_context'] = array_values(array_diff((array) ($understanding['clear_context'] ?? array()), array('language_preference')));
        $understanding['preserve_context'] = array_values(array_unique(array_merge((array) ($understanding['preserve_context'] ?? array()), array('language_preference'))));
        if (($understanding['change_dimension'] ?? '') === 'language_preference') $understanding['change_dimension'] = 'none';
        $care_request = preg_replace('/\b(?:please\s+)?(?:reply|respond|answer|speak\s+to\s+me|talk\s+to\s+me|keep\s+replying)\s+(?:in\s+)?(?:English|Spanish|espa[ñn]ol|ingl[eé]s)\b[.!;, ]*|\b(?:h[aá]blame|resp[oó]ndeme)\s+en\s+(?:espa[ñn]ol|ingl[eé]s)\b[.!;, ]*/iu', '', $message);
        if (trim($care_request) !== '') $understanding['clean_text'] = trim($care_request);
        return $understanding;
    }

    /** Shared turn state. Only bounded assistant questions and fixed recipient labels persist. */
    private function prepare_conversation_turn($message, $prior) {
        $response_language = $this->requested_response_language($message);
        if ($response_language !== '') $prior['language'] = $response_language;
        if (preg_match('/\bmy\s+(father|mother|daughter|son|child|wife|husband|partner|parent|friend)\b/iu', $message, $m)) {
            $recipient = strtolower($m[1]);
            if ($recipient !== ($prior['care_recipient'] ?? '')) unset($prior['care_age_group']);
            $prior['care_recipient'] = $recipient;
        } elseif (preg_match('/\bi\s+(?:have|feel|am|got|suffer)\b[\s\S]{0,55}\b(?:pain|hurts?|anxiety|depressed|symptoms?)\b|\bmy\s+(?:back|leg|arm|neck|knee|foot)\b[\s\S]{0,30}\b(?:pain|hurts?)\b/iu', $message)) {
            if (($prior['care_recipient'] ?? '') !== 'self') unset($prior['care_age_group']);
            $prior['care_recipient'] = 'self';
        }
        if (preg_match('/\b(?:she|he|they|child|daughter|son)\s+(?:is|are)\s+(\d{1,2})\b|\b(\d{1,2})[-\s]+year[-\s]+old\b/iu', $message, $m)) {
            $age = intval($m[1] ?: ($m[2] ?? 0));
            if ($age > 0) $prior['care_age_group'] = $age < 13 ? 'Children' : ($age < 18 ? 'Adolescents' : 'Adults');
        }
        $positive = $this->without_negated_owner_actions($message);
        if ($positive !== $message) {
            $prior = $this->apply_context_field_resets($prior, array('provider_owner_mode', 'provider_workflow_step', 'provider_workflow_city', 'provider_workflow_field', 'claim_disambiguation_providers', 'claim_name_suggestions'));
            $prior['navigation_state']['goal'] = 'find_provider';
        }
        return $prior;
    }

    private function without_negated_owner_actions($message) {
        return trim(preg_replace('/\b(?:i\s+(?:am\s+not|do\s+not|don[\x{2019}\x{27}]t)|i[\x{2019}\x{27}]m\s+not|not\s+trying\s+to)\s*(?:want\s+to|trying\s+to|going\s+to)?\s*(?:claim|book|add|register|manage)\b[^.!?;,]*?(?=\s+\b(?:but|instead|and\s+i)\b|[.!?;,]|$)[.!?;,]*/iu', '', (string) $message));
    }

    private function conversation_answer($message, $reply, $context, $action = 'general', $providers = array()) {
        $payload = array('assistant_reply' => $reply, 'response_type' => 'conversation_control', 'intent' => $action === 'provider_detail' ? 'provider_followup' : MyHH_Mia_Intent_Engine::INTENT_GENERAL,
            'prior_context' => $context, 'criteria' => array(), 'providers' => $providers, 'provider_exposure' => array(),
            'conversational_speech' => '', 'understanding' => array('raw_text' => $message, 'clean_text' => $message, 'intent' => $action === 'provider_detail' ? 'provider_followup' : MyHH_Mia_Intent_Engine::INTENT_GENERAL,
                'next_action' => $action, 'action' => $action, 'source' => 'deterministic_conversation_state', 'confidence' => 1, 'entities' => array('language' => $context['language'] ?? 'en')));
        return $this->finalize_conversation_answer($payload, $message);
    }

    /** Returns a grounded reply or prepares one contextual search; never guesses a service from a symptom. */
    private function resolve_conversation_control($message, &$prior, $history) {
        $lang = ($prior['language'] ?? 'en') === 'es' ? 'es' : 'en';
        $pending = $prior['pending_question']['type'] ?? ($prior['navigation_state']['open_question'] ?? 'none');
        if (preg_match('/^(?:never\s+mind[, ]*stop|never\s+mind|stop|stop\s+searching|cancel|no\s+more|para|cancelar)[.! ]*$/iu', trim($message))) {
            $prior['navigation_state'] = array_merge($prior['navigation_state'] ?? array(), array('stage' => 'closing', 'open_question' => 'none', 'next_best_action' => 'close'));
            if (!empty($prior['pending_question']['text'])) $prior['resume_question'] = $prior['pending_question'];
            else unset($prior['resume_question']);
            $prior['pending_question'] = array();
            return $this->conversation_answer($message, $lang === 'es' ? 'Claro, paramos aquí.' : 'Of course—we can stop here.', $prior);
        }
        if (($prior['navigation_state']['stage'] ?? '') === 'closing' && !empty($prior['resume_question']['text']) && preg_match('/\b(?:continue|resume|pick\s+up|continuar|sigamos)\b/iu', $message)) {
            $prior['pending_question'] = $prior['resume_question']; unset($prior['resume_question']);
            $prior['navigation_state']['stage'] = 'clarifying';
            return $this->conversation_answer($message, $prior['pending_question']['text'], $prior, 'clarify');
        }
        if (preg_match('/^(?:please\s+)?(?:reply|respond|answer|speak\s+to\s+me|h[aá]blame|resp[oó]ndeme)\s+(?:in|en)\s+(?:English|Spanish|espa[ñn]ol|ingl[eé]s)[.! ]*$/iu', trim($message))) {
            return $this->conversation_answer($message, $lang === 'es' ? 'Claro, seguimos en español.' : 'Of course, we’ll continue in English.', $prior);
        }
        $positive = $this->without_negated_owner_actions($message);
        if ($positive !== $message) {
            if (!empty($prior['recent_providers']) && preg_match('/\b(?:profile|provider|first|second|details|information)\b/iu', $positive)) {
                $pool = $this->get_provider_reference_pool($prior['recent_providers'], $prior);
                $target = $this->resolve_referenced_provider($positive, $pool, $prior);
                $lookup = $positive;
                if ($target && preg_match('/\b(?:what|which)\s+(?:information|details)\b[\s\S]{0,40}\bprofile\b/iu', $positive)) $lookup = 'Tell me about ' . $target['name'];
                $result = array('intent' => MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH, 'providers' => $pool, '_route_hint_override' => 'provider_followup', '_grok_understanding' => array('target_provider_name' => $target['name'] ?? ''));
                $reply = $this->get_deterministic_followup_response($lookup, $result, $prior, $prior);
                if ($reply) {
                    $prior['last_provider_focus'] = $this->get_answered_provider_focus($reply, $prior);
                    return $this->conversation_answer($message, $reply, $prior, 'provider_detail');
                }
            }
            if ($positive === '') return $this->conversation_answer($message, $lang === 'es' ? 'Entendido. ¿Qué te gustaría saber?' : 'Understood. What would you like to know?', $prior);
        }
        if (preg_match('/\b(?:tell\s+me\s+(?:more\s+)?about|details\s+(?:on|about)|profile\s+(?:for|of))\s+([\p{L}][\p{L} .\x{2019}\x{27}-]{2,80})/iu', $message, $named)) {
            $name = trim(preg_replace('/\s+(?:again|please|profile|provider)[.! ]*$/iu', '', $named[1]), '.! ');
            $reference = $this->resolve_named_provider_from_recent_list($name, $this->get_provider_reference_pool($prior['recent_providers'] ?? array(), $prior));
            $looks_named = preg_match('/^\p{Lu}[\p{L}\x{2019}\x{27}-]+(?:\s+\p{Lu}[\p{L}\x{2019}\x{27}.-]*)+/u', $name);
            if (!$reference && $looks_named && !$this->has_explicit_search_defer_signal($message)) {
                $directory = new MyHH_Mia_Intent_Engine();
                $matches = method_exists($directory, 'find_named_providers') ? $directory->find_named_providers($name) : array();
                $key = $this->normalize_provider_reference_text($name);
                $matches = array_values(array_filter($matches, function($provider) use ($key) { return $this->normalize_provider_reference_text($provider['name'] ?? '') === $key; }));
                if (count($matches) === 1) {
                    $reference = $matches[0];
                    $prior['last_provider_focus'] = $reference['id'];
                    if (empty($prior['recent_providers'])) $prior['recent_providers'] = array($reference);
                    else $prior['provider_history'] = array_slice(array_merge($prior['provider_history'] ?? array(), array($reference)), -8);
                    $prior['search_context_updated_at'] = time();
                    $prior['navigation_state'] = array_merge($prior['navigation_state'] ?? array(), array('goal'=>'find_provider','stage'=>'reviewing_results','readiness'=>'ready'));
                    $focused = array_merge($prior, array('recent_providers'=>array($reference)));
                    $result = array('intent'=>MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH,'providers'=>array($reference),'_route_hint_override'=>'provider_followup','_grok_understanding'=>array('target_provider_name'=>$reference['name']));
                    $reply = $this->get_deterministic_followup_response('Tell me about ' . $reference['name'], $result, $prior, $focused);
                    if ($reply) return $this->conversation_answer($message, $reply, $prior, 'provider_detail');
                } elseif (count($matches) > 1) {
                    return $this->conversation_answer($message, $lang === 'es' ? 'Hay varios perfiles con ese nombre. ¿En qué ciudad está el proveedor?' : 'Several profiles have that name. Which city is the provider in?', $prior, 'clarify', $matches);
                }
                if (!$reference && !preg_match('/\b(?:first|second|third|fourth|one|them|their|that|this|the|options|therapy|counseling|insurance|location|virtual|claim|booking)\b/iu', $name)) {
                    return $this->conversation_answer($message, $lang === 'es' ? 'No pude identificar un perfil con ese nombre exacto. ¿Cuál es el nombre completo y la ciudad?' : 'I couldn’t match that exact name in the directory. What is the full provider name and city?', $prior, 'clarify');
                }
            }
        }
        if (!empty($prior['discussed_provider_id']) && preg_match('/\b(?:provider|person|one)\b[\s\S]{0,70}\b(?:before|earlier|previous)|\b(?:before|earlier|previous)\b[\s\S]{0,40}\b(?:provider|person|one)\b/iu', $message)) {
            foreach ($this->get_provider_reference_pool($prior['recent_providers'] ?? array(), $prior) as $provider) {
                if (absint($provider['id'] ?? 0) !== absint($prior['discussed_provider_id'])) continue;
                $prior['last_provider_focus'] = $provider['id'];
                $result = array('intent' => MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH, 'providers' => array($provider), '_route_hint_override' => 'provider_followup', '_grok_understanding' => array('target_provider_name' => $provider['name']));
                $reply = $this->get_deterministic_followup_response('Tell me about ' . $provider['name'], $result, $prior, $prior);
                if ($reply) return $this->conversation_answer($message, $reply, $prior, 'provider_detail');
            }
        }
        if (!empty($prior['recent_providers']) && preg_match('/\b(?:none|neither|ninguno)\b[\s\S]{0,35}\b(?:confirm|confirms|confirmed|list|lists|confirma)\b/iu', $message)) {
            foreach (array_reverse($history) as $turn) {
                $previous = $turn['content'] ?? '';
                if (($turn['role'] ?? '') !== 'user' || !is_string($previous) || $previous === $message || !$this->is_existing_provider_fact_question($previous)) continue;
                $retry = $this->resolve_conversation_control($previous, $prior, array());
                if ($retry !== null) {
                    $retry['understanding']['raw_text'] = $message;
                    $retry['understanding']['clean_text'] = $message;
                    return $retry;
                }
            }
            return $this->conversation_answer($message, $lang === 'es' ? '¿Qué detalle de estos perfiles quieres que confirme?' : 'Which detail on these profiles would you like me to check?', $prior, 'clarify');
        }
        if (!empty($prior['recent_providers']) && $this->is_existing_provider_fact_question($message)) {
            $reply = $this->build_prior_provider_service_filter_response($message, $prior['recent_providers'], $lang);
            if ($reply !== null && preg_match('/\b(?:which|any|these|those|two|cu[aá]les|esos|estos)\b/iu', $message)) return $this->conversation_answer($message, $reply, $prior, 'provider_detail');
        }
        $contact_question = preg_match('/\b(?:phone|telephone|address|office|contact|located|website)\b|\bwhere\s+(?:is|are)\b/iu', $message);
        $profile_question = preg_match('/\b(?:what\s+(?:information|details)[\s\S]{0,35}(?:profile|provider)|(?:tell\s+me|show\s+me)[\s\S]{0,25}(?:their|his|her|that|this)\s+(?:profile|details))\b/iu', $message);
        if (!empty($prior['recent_providers']) && empty($prior['provider_owner_mode']) && !$this->has_explicit_search_activation_signal($message)
            && ($contact_question || $profile_question || $this->is_existing_provider_fact_question($message)) && !preg_match('/\b(?:which|these|those|compare|add|register|practice\s+form)\b/iu', $message)) {
            $pool = $this->get_provider_reference_pool($prior['recent_providers'], $prior);
            $target = $this->resolve_referenced_provider($message, $pool, $prior);
            if ($target) {
                $lookup = $profile_question ? 'Tell me about ' . $target['name'] : $message;
                $result = array('intent' => MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH, 'providers' => array($target), '_route_hint_override' => 'provider_followup', '_grok_understanding' => array('target_provider_name' => $target['name']));
                $focused_context = array_merge($prior, array('recent_providers' => array($target)));
                $reply = $this->get_deterministic_followup_response($lookup, $result, $prior, $focused_context);
                if ($reply) { $prior['last_provider_focus'] = $target['id']; return $this->conversation_answer($message, $reply, $prior, 'provider_detail'); }
            }
        }
        $short_yes = preg_match('/^(?:yes|yeah|yep|sure|okay|ok|go\s+ahead|si|sí|claro)[.! ]*$/iu', trim($message));
        $ready = $this->has_explicit_search_activation_signal($message);
        $choose_search = $pending === 'user_readiness' && preg_match('/\b(?:second(?:\s+option)?|look\s+for\s+care|find\s+(?:nearby\s+)?care|find\s+(?:a\s+)?provider|search)\b/iu', $message) && !$this->has_explicit_search_defer_signal($message);
        $has_direction = in_array($prior['navigation_state']['goal'] ?? '', array('find_provider', 'explore_support', 'learn'), true);
        $selection = $this->extract_explicit_category_selection($message);
        if ($selection === '' && preg_match('/^(?:therapy|counseling|massage|physical\s+therapy|primary\s+care|coaching)[.! ]*$/iu', trim($message))) {
            $selection = $this->infer_fast_provider_search_entities_from_turn($message)['primary_category_name'] ?? '';
        }
        if ($selection !== '' && $has_direction) {
            list($slug, $name) = $this->normalize_grok_category_entity($selection, $prior);
            $prior['primary_category'] = $slug; $prior['primary_category_name'] = $name;
            $chosen_service = $this->infer_fast_provider_search_entities_from_turn($message)['specialty'] ?? '';
            if ($chosen_service !== '') $prior['specialty'] = $chosen_service;
            else unset($prior['specialty']);
        }
        if ($pending === 'location' && !$ready && !$short_yes && preg_match('/^[\p{L}][\p{L} .\x{2019}\x{27}-]{1,45}[.!]?$/u', trim($message)) && $selection === '' && !$this->is_search_preference_recap_request($message) && str_word_count($message) <= 4 && !preg_match('/^(?:tell|what|where|which|how|why|explain|remember|continue|resume|I|my|can|could|please)\b|\b(?:virtual|person|English|Spanish|thanks|no|options|stop)\b/iu', $message)) {
            $prior['city'] = sanitize_text_field(trim($message, '.! '));
            $ready = true;
        }
        if ($selection !== '' && $pending === 'service_type') $ready = true;
        if ($choose_search || ($ready && $has_direction && preg_match('/\b(?:ready|ahead)\b/iu', $message)) || ($short_yes && in_array($pending, array('service_type', 'location'), true)) || ($pending === 'location' && $ready)) {
            $prior['navigation_state'] = array_merge($prior['navigation_state'] ?? array(), array('goal' => 'find_provider', 'readiness' => 'ready', 'stage' => 'search_ready'));
            // An explicit new request outranks the old pending question's search slots.
            $current = $this->infer_fast_provider_search_entities_from_turn($message);
            if (!empty($current['primary_category_name'])) {
                if ($current['primary_category_name'] !== ($prior['primary_category_name'] ?? '')) unset($prior['specialty']);
                list($slug, $name) = $this->normalize_grok_category_entity($current['primary_category_name'], $prior);
                $prior['primary_category'] = $slug; $prior['primary_category_name'] = $name;
            }
            foreach (array('specialty', 'city', 'insurance', 'session_type', 'language_preference') as $field) if (!empty($current[$field])) $prior[$field] = $current[$field];
            $missing = empty($prior['primary_category_name']) && empty($prior['specialty']) ? 'service_type' : (empty($prior['city']) ? 'location' : 'none');
            if ($missing !== 'none') {
                $reply = $missing === 'location'
                    ? ($lang === 'es' ? '¿En qué ciudad quieres buscar?' : 'Which city should I search in?')
                    : ($lang === 'es' ? '¿Qué tipo de proveedor quieres buscar?' : 'What kind of provider would you like to find?');
                $prior['pending_question'] = array('type' => $missing, 'text' => $reply, 'options' => array());
                $prior['navigation_state']['open_question'] = $missing;
                return $this->conversation_answer($message, $reply, $prior, 'clarify');
            }
            $prior['_conversation_search'] = true;
            $prior['pending_question'] = array();
        } elseif ($short_yes && $pending !== 'none' && $pending !== '') {
            $reply = $prior['pending_question']['text'] ?? ($lang === 'es' ? '¿Prefieres explorar las opciones o buscar un proveedor?' : 'Would you like to explore the options or find a provider?');
            return $this->conversation_answer($message, $reply, $prior, 'clarify');
        }
        return null;
    }

    /** Commit the question actually asked and one factual speech representation. */
    private function finalize_conversation_answer($payload, $message) {
        $context = $payload['prior_context'] ?? array();
        unset($context['_conversation_search']);
        $reply = (string) ($payload['assistant_reply'] ?? '');
        $navigation = $context['navigation_state'] ?? array();
        $type = $context['pending_question']['type'] ?? 'none';
        $plain = preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '$1', $reply);
        $plain = trim(wp_strip_all_tags(str_replace(array('**', '\\'), '', $plain)));
        if (preg_match('/([^.!?\n]*[?¿][^.!?\n]*[?]?|[^.!?\n]+\?)\s*$/u', $plain, $m)) {
            $question = trim($m[0]);
            if (preg_match('/\b(?:city|ciudad)\b/iu', $question)) $type = 'location';
            elseif (preg_match('/\b(?:kind|type|tipo)\b[\s\S]{0,40}\b(?:provider|care|support|proveedor)\b/iu', $question)) $type = 'service_type';
            elseif (preg_match('/\b(?:options\s+first|explore\s+the\s+options|understand\s+the\s+options|opciones)\b[\s\S]{0,90}\b(?:or|o)\b/iu', $question)) $type = 'user_readiness';
            elseif (preg_match('/\b(?:insurance|seguro)\b/iu', $question)) $type = 'insurance';
            elseif (preg_match('/\b(?:compare|profile|location details|which one|comparar|perfil)\b/iu', $question)) $type = 'provider_choice';
            else $type = 'none';
            $context['pending_question'] = array('type' => $type, 'text' => mb_substr($question, 0, 300), 'options' => $type === 'user_readiness' ? array('education', 'provider_search') : array());
        } elseif (($navigation['stage'] ?? '') !== 'closing') {
            $context['pending_question'] = array(); $type = 'none';
        } else { $type = 'none'; }
        $navigation['open_question'] = $type;
        if ($type !== 'none') { $navigation['stage'] = 'clarifying'; $navigation['next_best_action'] = 'clarify'; }
        $context['navigation_state'] = $this->sanitize_navigation_state($navigation);
        if (in_array($payload['response_type'] ?? '', array('deterministic_followup', 'conversation_control'), true) && ($payload['understanding']['next_action'] ?? '') !== 'clarify') {
            $focus = $this->get_answered_provider_focus($reply, $context);
            if ($focus && preg_match('/\b(?:profile|specializes|located|phone|office|insurance|perfil)\b/iu', $reply)) $context['discussed_provider_id'] = $focus;
        }
        $payload['prior_context'] = $context; $payload['navigation_state'] = $context['navigation_state'];
        if (strpos($payload['response_type'] ?? '', 'search') === 0 && !empty($payload['providers'])) {
            $lang = ($context['language'] ?? 'en') === 'es' ? 'es' : 'en';
            $speech = $this->build_search_results_conversational_speech($payload['providers'], $payload['criteria'] ?? array(), $lang);
            // Written qualifications and speech must use the same directory evidence.
            $caveats = array();
            foreach (array('insurance' => 'insurance_accepted', 'language_preference' => 'languages', 'session_type' => 'session_type', 'care_age_group' => 'age_groups') as $criterion => $field) {
                $requested = $context[$criterion] ?? ''; if ($requested === '') continue;
                $confirmed_count = 0;
                foreach ($payload['providers'] as $provider) {
                    foreach ((array) ($provider[$field] ?? array()) as $value) {
                        if (strcasecmp((string) $value, (string) $requested) === 0) { $confirmed_count++; break; }
                    }
                }
                if ($confirmed_count > 0 && $confirmed_count < count($payload['providers'])) $caveats[] = $lang === 'es' ? "No todos estos perfiles confirman {$requested}; verifica los detalles con cada proveedor." : "Not all these profiles confirm {$requested}; please check directly with each provider.";
                elseif (!$confirmed_count) $caveats[] = $lang === 'es' ? "Estos perfiles no confirman {$requested}; verifica los detalles con el proveedor." : "These profiles do not confirm {$requested}; please check directly with the provider.";
            }
            $broader_request = sanitize_text_field($payload['criteria']['requested_specialty'] ?? '');
            if ($broader_request !== '') $caveats[] = $lang === 'es'
                ? "Son opciones mas amplias; {$broader_request} no esta confirmado en todos los perfiles."
                : "These are broader options; {$broader_request} isn't confirmed in every profile.";
            foreach ($caveats as $caveat) if (strpos($reply, $caveat) === false) $reply .= "\n\n" . $caveat;
            $payload['assistant_reply'] = $reply;
            $payload['required_facts'] = $caveats;
            $speech_prefix = array_filter($caveats, function($caveat) use ($speech) { return strpos($speech, $caveat) === false; });
            $payload['conversational_speech'] = implode(' ', $speech_prefix) . ($speech_prefix ? ' ' : '') . $speech;
        } else {
            $payload['conversational_speech'] = $plain;
        }
        return $payload;
    }

    private function sanitize_prior_context($prior_context) {
        $safe_prior = array();
        $ctx_keys = array(
            'primary_category',
            'primary_category_name',
            'specialty',
            'city',
            'provider_type',
            'modality',
            'language_preference',
            'insurance',
            'session_type',
            'language',
            'care_recipient',
            'care_age_group',
            'provider_owner_mode',
            'provider_workflow_step',
            'provider_workflow_city',
            'provider_workflow_field',
        );
        if (is_array($prior_context)) {
            foreach ($ctx_keys as $k) {
                if (!empty($prior_context[$k])) {
                    $safe_prior[$k] = sanitize_text_field($prior_context[$k]);
                }
            }
            if (!empty($prior_context['recent_providers']) && is_array($prior_context['recent_providers'])) {
                $safe_prior['recent_providers'] = $this->sanitize_provider_memory($prior_context['recent_providers']);
            }
            if (!empty($prior_context['provider_history']) && is_array($prior_context['provider_history'])) {
                $safe_prior['provider_history'] = $this->sanitize_provider_memory($prior_context['provider_history'], 8);
            }
            if (!empty($prior_context['claim_disambiguation_providers']) && is_array($prior_context['claim_disambiguation_providers'])) {
                $safe_prior['claim_disambiguation_providers'] = $this->sanitize_provider_memory($prior_context['claim_disambiguation_providers']);
            }
            if (!empty($prior_context['claim_name_suggestions']) && is_array($prior_context['claim_name_suggestions'])) {
                $safe_prior['claim_name_suggestions'] = $this->sanitize_provider_memory($prior_context['claim_name_suggestions']);
            }
            if (!empty($prior_context['discussed_provider_id'])) $safe_prior['discussed_provider_id'] = absint($prior_context['discussed_provider_id']);
            if (!empty($prior_context['last_provider_focus'])) {
                $safe_prior['last_provider_focus'] = absint($prior_context['last_provider_focus']);
            }
            if (!empty($prior_context['search_context_updated_at'])) {
                $safe_prior['search_context_updated_at'] = absint($prior_context['search_context_updated_at']);
            }
            if (!empty($prior_context['navigation_state']) && is_array($prior_context['navigation_state'])) {
                $safe_prior['navigation_state'] = $this->sanitize_navigation_state($prior_context['navigation_state']);
            }
        }

        foreach (array('pending_question', 'resume_question') as $question_field) {
            if (empty($prior_context[$question_field]) || !is_array($prior_context[$question_field])) continue;
            $q = $prior_context[$question_field];
            $question_type = sanitize_key($q['type'] ?? 'none');
            $allowed_types = array('none', 'location', 'service_type', 'modality', 'provider_language', 'insurance', 'session_type', 'provider_choice', 'user_readiness');
            $safe_prior[$question_field] = array('type' => in_array($question_type, $allowed_types, true) ? $question_type : 'none', 'text' => mb_substr(sanitize_text_field($q['text'] ?? ''), 0, 300), 'options' => array_slice(array_map('sanitize_key', (array) ($q['options'] ?? array())), 0, 4));
        }
        return $this->maybe_expire_search_memory($safe_prior);
    }

    /**
     * Keep conversational memory useful without retaining a diagnosis or a
     * free-text health narrative. Every value is selected from a fixed label set.
     */
    private function sanitize_navigation_state($state, $fallback = array()) {
        $state = is_array($state) ? $state : array();
        $fallback = is_array($fallback) ? $fallback : array();
        $allowed = array(
            'goal' => array('learn', 'explore_support', 'find_provider', 'compare_providers', 'provider_claim', 'provider_add', 'provider_upgrade', 'unsure'),
            'stage' => array('listening', 'clarifying', 'educating', 'search_ready', 'reviewing_results', 'action_ready', 'closing'),
            'readiness' => array('not_ready', 'exploring', 'ready', 'unknown'),
            'open_question' => array('none', 'location', 'service_type', 'modality', 'provider_language', 'insurance', 'session_type', 'provider_choice', 'user_readiness'),
            'next_best_action' => array('listen', 'clarify', 'educate', 'search', 'compare', 'show_profile', 'platform_action', 'close'),
        );
        $clean = array();
        foreach ($allowed as $field => $values) {
            $value = sanitize_key((string) ($state[$field] ?? ($fallback[$field] ?? '')));
            if (in_array($value, $values, true)) {
                $clean[$field] = $value;
            }
        }

        $topic_values = array('stress', 'sleep', 'relationships', 'grief', 'anxiety_support', 'mood_support', 'trauma_support', 'pain_support', 'movement_recovery', 'nutrition_support', 'financial_stress', 'work_life', 'social_support', 'general_wellness', 'other');
        $topics = array_merge(
            is_array($fallback['support_topics'] ?? null) ? $fallback['support_topics'] : array(),
            is_array($state['support_topics'] ?? null) ? $state['support_topics'] : array()
        );
        $clean['support_topics'] = array_values(array_slice(array_unique(array_filter(array_map(function($topic) use ($topic_values) {
            $topic = sanitize_key((string) $topic);
            return in_array($topic, $topic_values, true) ? $topic : '';
        }, $topics))), 0, 5));

        return $clean;
    }

    private function infer_navigation_support_topics($message) {
        $message = strtolower(remove_accents((string) $message));
        $patterns = array(
            'stress' => '/\b(?:stress|stressed|overwhelm|burnout|tension|estres|agobiad)\w*\b/i',
            'sleep' => '/\b(?:sleep|insomnia|rest|tired|fatigue|dormir|duerm|sueno|insomnio)\w*\b/i',
            'relationships' => '/\b(?:relationship|marriage|couple|partner|family conflict|relacion|pareja|matrimonio)\w*\b/i',
            'grief' => '/\b(?:grief|bereavement|loss|grieving|duelo|perdida)\w*\b/i',
            'anxiety_support' => '/\b(?:anxiety|anxious|panic|worry|ansiedad|panico)\w*\b/i',
            'mood_support' => '/\b(?:mood|depress|sadness|low mood|estado de animo|depres)\w*\b/i',
            'trauma_support' => '/\b(?:trauma|ptsd|post traumatic|traumatico)\w*\b/i',
            'pain_support' => '/\b(?:pain|chronic pain|ache|dolor)\w*\b/i',
            'movement_recovery' => '/\b(?:movement|fitness|physical therapy|recovery|injury|exercise|movimiento|recuperacion|ejercicio)\w*\b/i',
            'nutrition_support' => '/\b(?:nutrition|diet|dietitian|food|nutricion|dieta|alimentacion)\w*\b/i',
            'financial_stress' => '/\b(?:financial|money|debt|budget|financiero|dinero|deuda|presupuesto)\w*\b/i',
            'work_life' => '/\b(?:work|career|job|work-life|work life|trabajo|carrera|empleo)\w*\b/i',
            'social_support' => '/\b(?:lonely|isolation|support group|social connection|soledad|aislamiento|grupo de apoyo)\w*\b/i',
        );
        $topics = array();
        foreach ($patterns as $topic => $pattern) {
            if (preg_match($pattern, $message)) {
                $topics[] = $topic;
            }
        }
        return array_slice($topics, 0, 5);
    }

    private function has_explicit_search_defer_signal($message) {
        $value = strtolower(remove_accents((string) $message));
        return (bool) preg_match(
            '/\b(?:not ready(?:\s+to\s+(?:search|find|choose|see\s+providers?))?|don[\'’]?t\s+want\s+to\s+(?:search|find|choose)|do\s+not\s+want\s+to\s+(?:search|find|choose)|don[\'’]?t\s+want\s+(?:provider\s+)?(?:suggestions?|recommendations?|results?)|do\s+not\s+want\s+(?:provider\s+)?(?:suggestions?|recommendations?|results?)|(?:do\s+not|don[\'’]?t|please\s+don[\'’]?t)\s+(?:search|find|show|recommend|suggest)(?:\s+(?:yet|now))?|(?:no|without)\s+(?:provider\s+)?(?:suggestions?|recommendations?|results?)\s+(?:yet|now)|not\s+looking\s+for\s+(?:a\s+)?provider|just\s+want\s+to\s+(?:understand|learn|talk|explain)|first\s+(?:explain|describe|compare|help\s+me\s+understand|tell\s+me\s+about)|ask\s+me\s+(?:one|a)\b[\s\S]{0,70}\bbefore\s+(?:you\s+)?search(?:ing)?|no\s+quiero\s+(?:buscar|elegir|recomendaciones)|no\s+estoy\s+list[oa]|no\s+busques|todavia\s+no|solo\s+quiero\s+(?:entender|aprender|hablar)|primero\s+(?:explica|describe|compara|ayudame\s+a\s+entender))\b/i',
            $value
        );
    }

    private function has_explicit_search_activation_signal($message) {
        if ($this->has_explicit_search_defer_signal($message)) {
            return false;
        }
        $value = strtolower(remove_accents((string) $message));
        if ($this->extract_explicit_category_selection($message) !== '') {
            return true;
        }
        return (bool) preg_match(
            '/\b(?:now\s+)?(?:find|search|show|recommend|suggest)\b[\s\S]{0,90}\b(?:provider|therapist|counselor|doctor|specialist|practice|clinic|option|match|care)|\b(?:i(?:\s+am|[\'’]?m)\s+ready|ready\s+now|go\s+ahead)\b|\b(?:ahora\s+)?(?:busca|encuentra|muestra|recomienda)\b|\b(?:estoy\s+list[oa]|lista?\s+ahora)\b/i',
            $value
        );
    }

    private function wants_pre_search_clarification($message) {
        return (bool) preg_match(
            '/\bask\s+me\s+(?:one|a)\b[\s\S]{0,70}\bbefore\s+(?:you\s+)?search(?:ing)?|\b(?:before\s+(?:you\s+)?search|antes\s+de\s+buscar)\b[\s\S]{0,70}\b(?:ask|question|pregunta)/iu',
            (string) $message
        );
    }

    private function should_hold_provider_search_for_readiness($message, $safe_prior) {
        if ($this->has_explicit_search_defer_signal($message)) {
            return true;
        }
        $navigation = $this->sanitize_navigation_state($safe_prior['navigation_state'] ?? array());
        if (($navigation['readiness'] ?? '') === 'not_ready' && $this->extract_explicit_category_selection($message) !== '') return true;
        return ($navigation['readiness'] ?? '') === 'not_ready'
            && !$this->has_explicit_search_activation_signal($message);
    }

    private function looks_like_contextual_education_followup($message, $safe_prior) {
        if ($this->patient_request_kind($message) === 'personal_concern') {
            return false;
        }
        // Learning about services must not trap the next explicit search in
        // education merely because it is phrased as a question.
        if ($this->has_explicit_search_activation_signal($message) || (!empty($safe_prior['recent_providers']) && $this->is_existing_provider_fact_question($message))) {
            return false;
        }
        $navigation = $this->sanitize_navigation_state($safe_prior['navigation_state'] ?? array());
        if (($navigation['stage'] ?? '') !== 'educating') {
            return false;
        }
        $value = trim((string) $message);
        if ($value === '' || !preg_match('/\?|\b(?:how|what|why|which|explain|compare|difference|different|structured|trauma[-\s]?focused|como|que|cual|explica|compara|diferencia)\b/iu', $value)) {
            return false;
        }
        return !preg_match('/\b(?:profile|listing|provider|option|result|availability|insurance|address|phone|website|book|perfil|listado|proveedor|opcion|resultado|disponibilidad|seguro|direccion|telefono|reservar)\b/iu', $value);
    }

    private function looks_like_platform_service_overview_request($message, $history = array(), $safe_prior = array()) {
        $value = trim((string) $message);
        if ($value === '') {
            return false;
        }

        if (preg_match('/\bwhat\s+(?:(?:kind|kinds|type|types)\s+of\s+)?(?:care|health\s*care|healthcare|services?|treatments?)\s+(?:(?:do|would|can|could)\s+you\s+|does\s+mia\s+|you\s+)?(?:have|offer|provide|cover)\b|\bwhat\s+(?:care|services?)\s+(?:is|are)\s+available\b|\bqu[eé]\s+(?:tipo\s+de\s+)?(?:atenci[oó]n|cuidados?|servicios?)\s+(?:tienes|ofreces|hay)\b/iu', $value)) {
            return true;
        }

        if (preg_match('/\bwhat\s+(?:kind|kinds|type|types)\s+of\s+services?\s+(?:(?:do\s+you|does\s+mia)\s+)?(?:have|offer|provide)\b|\bwhat\s+services?\s+(?:(?:do\s+you|does\s+mia)\s+)?(?:have|offer|provide)\b|\bqu[eé]\s+(?:tipo|tipos)\s+de\s+servicios?\s+(?:tienes|ofreces|brindas|proporcionas)\b|\bqu[eé]\s+servicios?\s+(?:tienes|ofreces|brindas|proporcionas)\b/iu', $value)) {
            return true;
        }

        if (!preg_match('/^(?:(?:can|could|would|will)\s+you\s+)?(?:list|name|show)\s*(?:me|them|those|all|the\s+(?:services|categories)|(?:them|those)\s+for\s+me)?[?.!]*$|^(?:puedes|podr[ií]as?)?\s*(?:listar|enumerar|mencionar|mostrar)\s*(?:me|los|las|esos|esas|todos|todas|los\s+servicios|las\s+categor[ií]as)?[?.!]*$/iu', $value)) {
            return false;
        }

        $navigation = $this->sanitize_navigation_state($safe_prior['navigation_state'] ?? array());
        if (($navigation['goal'] ?? '') !== 'learn' || ($navigation['stage'] ?? '') !== 'educating') {
            return false;
        }

        $previous_assistant = '';
        if (is_array($history)) {
            foreach (array_reverse($history) as $turn) {
                if (is_array($turn) && ($turn['role'] ?? '') === 'assistant') {
                    $previous_assistant = sanitize_text_field(wp_strip_all_tags((string) ($turn['content'] ?? '')));
                    break;
                }
            }
        }

        return (bool) preg_match('/12\s+(?:primary|main)?\s*wellness\s+categories|12\s+categor[ií]as|find\s+providers[\s\S]{0,220}(?:add|claim)\s+(?:a\s+)?(?:listing|profile)|encontrar\s+proveedores[\s\S]{0,220}(?:agregar|reclamar)\s+(?:un\s+)?perfil/iu', $previous_assistant);
    }

    private function looks_like_provider_set_fact_followup($message, $safe_prior) {
        if (empty($safe_prior['recent_providers']) || !is_array($safe_prior['recent_providers'])) {
            return false;
        }
        return (bool) preg_match(
            '/\b(?:which|who)(?:\s+of\s+(?:those|these|the))?\s*(?:providers?|options?|matches?|results?)?\s*(?:specifically\s+|actually\s+|explicitly\s+)?(?:offers?|provides?|lists?|documents?|confirms?|shows?|states?|specializes?\s+in|works?\s+(?:in|with))\b|\b(?:cu[aá]l(?:es)?|qui[eé]n(?:es)?)(?:\s+de\s+(?:esos|esas|estos|estas))?\s*(?:proveedores?|opciones?|resultados?)?\s*(?:ofrece|brinda|incluye|documenta|confirma|muestra)/iu',
            (string) $message
        );
    }

    private function build_deferred_search_criteria($message) {
        $entities = $this->infer_fast_provider_search_entities_from_turn($message);
        $criteria = array();
        foreach (array('primary_category_name', 'specialty', 'city', 'provider_type') as $field) {
            if (!empty($entities[$field])) {
                if ($field === 'city' && preg_match('/^(?:work|home|school|the\s+office)$/i', trim((string) $entities[$field]))) {
                    continue;
                }
                $criteria[$field] = sanitize_text_field($entities[$field]);
            }
        }
        return $criteria;
    }

    private function build_pre_search_clarification_question($safe_prior, $lang = 'en') {
        $navigation = $this->sanitize_navigation_state($safe_prior['navigation_state'] ?? array());
        $topics = $navigation['support_topics'] ?? array();
        if (in_array('work_life', $topics, true)) {
            return $lang === 'es'
                ? 'Antes de buscar, una pregunta: ¿prefieres apoyo clínico de consejería para el estrés o coaching profesional no clínico para metas y decisiones laborales?'
                : 'Before I search, one useful question: would you prefer licensed counseling for the stress itself, or non-clinical career coaching for work goals and decisions?';
        }
        if (empty($safe_prior['primary_category_name']) && empty($safe_prior['specialty'])) {
            return $lang === 'es'
                ? 'Antes de buscar, ¿qué tipo de apoyo quieres explorar primero?'
                : 'Before I search, what kind of support would you like to explore first?';
        }
        if (empty($safe_prior['city'])) {
            return $lang === 'es'
                ? 'Antes de buscar, ¿qué ciudad o zona debo usar?'
                : 'Before I search, which city or area should I use?';
        }
        return $lang === 'es'
            ? 'Antes de buscar, ¿prefieres atención presencial, virtual o cualquiera de las dos?'
            : 'Before I search, do you prefer in-person support, virtual support, or either?';
    }

    private function update_navigation_support_topics($current_topics, $message) {
        $current_topics = is_array($current_topics) ? $current_topics : array();
        $inferred = $this->infer_navigation_support_topics($message);
        $value = strtolower(remove_accents((string) $message));
        if (preg_match('/\b(?:main|primary)\s+concern\s+is\s+(.+)|\bfocus\s+(?:only|just)\s+on\s+(.+)/i', $value, $match)) {
            $focus = trim((string) (($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? '')));
            $focused_topics = $this->infer_navigation_support_topics($focus);
            if (!empty($focused_topics)) {
                $current_topics = $focused_topics;
            }
        } else {
            $current_topics = array_merge($current_topics, $inferred);
        }
        $negative_patterns = array(
            'sleep' => '/\b(?:not\s+sleep|sleep\s+is\s+(?:less\s+important|not\s+(?:the\s+)?(?:focus|priority)))\b/i',
            'work_life' => '/\b(?:not\s+(?:work|career)|(?:work|career)\s+is\s+(?:less\s+important|not\s+(?:the\s+)?(?:focus|priority)))\b/i',
        );
        foreach ($negative_patterns as $topic => $pattern) {
            if (preg_match($pattern, $value)) {
                $current_topics = array_values(array_diff($current_topics, array($topic)));
            }
        }
        return array_values(array_slice(array_unique($current_topics), 0, 5));
    }

    private function looks_like_consultative_exploration_turn($message) {
        $message_key = strtolower(remove_accents((string) $message));
        $not_ready = $this->has_explicit_search_defer_signal($message_key);
        if (!$not_ready) {
            return false;
        }

        return !empty($this->infer_navigation_support_topics($message))
            || MyHH_Mia_Intent_Engine::is_basic_education_question($message)
            || (bool) preg_match('/\b(?:what kind of support|what might fit|help me understand|que tipo de apoyo|ayudame a entender)\b/i', $message_key);
    }

    private function derive_navigation_state($message, $result, $safe_prior, $suggested = array()) {
        $prior = $this->sanitize_navigation_state($safe_prior['navigation_state'] ?? array());
        $state = $this->sanitize_navigation_state($suggested, $prior);
        $intent = sanitize_key((string) ($result['intent'] ?? ''));
        $action = sanitize_key((string) ($result['_grok_understanding']['next_action'] ?? ($result['_grok_understanding']['action'] ?? '')));
        $message_key = strtolower(remove_accents((string) $message));
        $providers = is_array($result['providers'] ?? null) ? $result['providers'] : array();
        $criteria = is_array($result['criteria'] ?? null) ? $result['criteria'] : array();

        $state += array(
            'goal' => 'unsure',
            'stage' => 'clarifying',
            'readiness' => 'unknown',
            'support_topics' => array(),
            'open_question' => 'none',
            'next_best_action' => 'clarify',
        );
        $state['support_topics'] = $this->update_navigation_support_topics($state['support_topics'], $message);

        $is_closing = (bool) preg_match('/^(?:okay|ok|alright|thanks|thank you|appreciate it|that is all|thats all|bye|gracias|eso es todo|adios)[\s.!,-]*$/i', trim($message_key));
        $not_ready = $this->has_explicit_search_defer_signal($message)
            || (($prior['readiness'] ?? '') === 'not_ready' && !$this->has_explicit_search_activation_signal($message));
        $clarify_before_search = $this->wants_pre_search_clarification($message);
        $explicit_search = $this->has_explicit_search_activation_signal($message)
            || ((bool) preg_match('/\b(?:find|show|search|recommend|looking for|need|ready to find|buscar|muestr|recomiend|necesito|list[oa] para buscar)\w*\b/i', $message_key)
                && (bool) preg_match('/\b(?:provider|therapist|counselor|doctor|specialist|practice|clinic|care|support|proveedor|terapeuta|consejero|medico|especialista|clinica|atencion)\w*\b/i', $message_key)
                && !$not_ready);
        $contextual_provider_followup = !empty($safe_prior['recent_providers']) && (
            in_array($action, array('provider_detail', 'provider_location', 'provider_insurance', 'provider_booking', 'provider_compare'), true)
            || $this->looks_like_provider_set_fact_followup($message, $safe_prior)
            || (bool) preg_match('/\b(?:which|who)\s+of\s+(?:those|these)|\b(?:those|these|the first|the second|the third|that provider|this provider)\b[\s\S]{0,70}\b(?:insurance|location|address|language|modality|booking|available|compare)|\b(?:cu[aá]l|qui[eé]n)\s+de\s+(?:esos|estos)|\b(?:esos|estos|el primero|el segundo|ese proveedor)\b[\s\S]{0,70}\b(?:seguro|ubicacion|direccion|idioma|modalidad|reserva|disponible|compar)/i', $message_key)
        );

        if ($intent === MyHH_Mia_Intent_Engine::INTENT_CRISIS || $action === 'crisis') {
            $state['goal'] = 'explore_support';
            $state['stage'] = 'action_ready';
            $state['readiness'] = 'ready';
            $state['open_question'] = 'none';
            $state['next_best_action'] = 'platform_action';
        } elseif ($intent === MyHH_Mia_Intent_Engine::INTENT_PROVIDER_ADD || $action === 'provider_add') {
            $state = array_merge($state, array('goal' => 'provider_add', 'stage' => 'action_ready', 'readiness' => 'ready', 'open_question' => 'none', 'next_best_action' => 'platform_action', 'support_topics' => array()));
        } elseif (in_array($intent, array(MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM, MyHH_Mia_Intent_Engine::INTENT_PROVIDER_OWNER), true) || in_array($action, array('provider_claim', 'claim_candidate_select'), true)) {
            $state = array_merge($state, array('goal' => 'provider_claim', 'stage' => 'action_ready', 'readiness' => 'ready', 'open_question' => 'none', 'next_best_action' => 'platform_action', 'support_topics' => array()));
        } elseif (in_array($intent, array(MyHH_Mia_Intent_Engine::INTENT_PROVIDER_UPGRADE, MyHH_Mia_Intent_Engine::INTENT_SPONSOR), true) || in_array($action, array('provider_upgrade', 'sponsor_inquiry'), true)) {
            $state = array_merge($state, array('goal' => 'provider_upgrade', 'stage' => 'action_ready', 'readiness' => 'ready', 'open_question' => 'none', 'next_best_action' => 'platform_action', 'support_topics' => array()));
        } elseif ($clarify_before_search) {
            $state['goal'] = 'find_provider';
            $state['stage'] = 'clarifying';
            $state['readiness'] = 'exploring';
            $state['open_question'] = empty($criteria['primary_category_name']) && empty($criteria['specialty']) ? 'service_type' : (empty($criteria['city']) ? 'location' : 'session_type');
            $state['next_best_action'] = 'clarify';
        } elseif (!empty($result['_contextual_education']) || MyHH_Mia_Intent_Engine::is_basic_education_question($message)) {
            $state['goal'] = 'learn';
            $state['stage'] = 'educating';
            $state['readiness'] = ($not_ready || ($prior['readiness'] ?? '') === 'not_ready') ? 'not_ready' : 'exploring';
            $state['open_question'] = 'none';
            $state['next_best_action'] = 'educate';
        } elseif ($not_ready && !empty($state['support_topics'])) {
            $state['goal'] = 'explore_support';
            $state['stage'] = 'listening';
            $state['readiness'] = 'not_ready';
            $state['open_question'] = 'none';
            $state['next_best_action'] = 'listen';
        } elseif ($contextual_provider_followup) {
            $is_compare = $action === 'provider_compare' || (bool) preg_match('/\b(?:compare|difference|versus|better|comparar|diferencia|mejor)\b/i', $message_key);
            $state['goal'] = $is_compare ? 'compare_providers' : 'find_provider';
            $state['stage'] = 'reviewing_results';
            $state['readiness'] = 'ready';
            $state['open_question'] = 'none';
            $state['next_best_action'] = $is_compare ? 'compare' : 'show_profile';
        } elseif ($intent === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH || strpos($action, 'provider_') === 0) {
            $is_compare = $action === 'provider_compare' || (bool) preg_match('/\b(?:compare|difference|versus|better|comparar|diferencia|mejor)\b/i', $message_key);
            $state['goal'] = $is_compare ? 'compare_providers' : 'find_provider';
            $state['readiness'] = 'ready';
            $state['stage'] = !empty($providers) ? 'reviewing_results' : (!empty($criteria) ? 'search_ready' : 'clarifying');
            $state['next_best_action'] = $is_compare ? 'compare' : (!empty($providers) ? 'show_profile' : 'search');
            $state['open_question'] = 'none';
            if (empty($providers) && empty($criteria['primary_category_name']) && empty($criteria['specialty'])) {
                $state['open_question'] = 'service_type';
            } elseif (empty($providers) && !empty($result['near_me_detected']) && empty($criteria['city'])) {
                $state['open_question'] = 'location';
            }
        } elseif ($is_closing) {
            $state['stage'] = 'closing';
            $state['open_question'] = 'none';
            $state['next_best_action'] = 'close';
        } elseif (!empty($state['support_topics']) && !$explicit_search) {
            $state['goal'] = 'explore_support';
            $state['stage'] = $not_ready ? 'listening' : 'clarifying';
            $state['readiness'] = $not_ready ? 'not_ready' : 'exploring';
            $state['open_question'] = $not_ready ? 'none' : 'user_readiness';
            $state['next_best_action'] = $not_ready ? 'listen' : 'clarify';
        }

        return $this->sanitize_navigation_state($state);
    }

    private function normalize_context_reset_fields($fields) {
        $allowed = array(
            'primary_category',
            'primary_category_name',
            'specialty',
            'city',
            'provider_type',
            'modality',
            'language_preference',
            'insurance',
            'session_type',
            'recent_providers',
            'provider_history',
            'claim_disambiguation_providers',
            'claim_name_suggestions',
            'last_provider_focus',
            'discussed_provider_id',
            'provider_owner_mode',
            'provider_workflow_step',
            'provider_workflow_city',
            'provider_workflow_field',
            'search_context_updated_at',
        );
        $normalized = array();
        if (!is_array($fields)) {
            return $normalized;
        }

        foreach ($fields as $field) {
            $key = sanitize_key((string) $field);
            if ($key && in_array($key, $allowed, true)) {
                $normalized[$key] = true;
            }
        }

        return array_keys($normalized);
    }

    private function apply_context_field_resets($context, $fields) {
        $reset_fields = $this->normalize_context_reset_fields($fields);
        if (empty($reset_fields)) {
            return $context;
        }

        foreach ($reset_fields as $field) {
            switch ($field) {
                case 'recent_providers':
                case 'provider_history':
                case 'claim_disambiguation_providers':
                case 'claim_name_suggestions':
                    $context[$field] = array();
                    break;
                case 'discussed_provider_id':
                case 'last_provider_focus':
                case 'search_context_updated_at':
                    $context[$field] = 0;
                    break;
                default:
                    $context[$field] = '';
                    break;
            }
        }

        return $context;
    }

    private function maybe_expire_search_memory($context) {
        $updated_at = absint($context['search_context_updated_at'] ?? 0);
        $ttl = 20 * MINUTE_IN_SECONDS;
        if ($updated_at > 0 && (time() - $updated_at) > $ttl) {
            $context = $this->apply_context_field_resets($context, array(
                'recent_providers',
                'provider_history',
                'last_provider_focus',
                'discussed_provider_id',
                'search_context_updated_at',
            ));
            mia_debug_log('Chat', 'search_memory_expired', array('age_seconds' => time() - $updated_at));
        }

        return $context;
    }

    private function build_context_reset_fields_from_result($result, $negative_search_refinement = false) {
        $fields = array();
        if (!empty($result['criteria']['reset_fields']) && is_array($result['criteria']['reset_fields'])) {
            $fields = array_merge($fields, $result['criteria']['reset_fields']);
        }

        if ($negative_search_refinement) {
            $fields = array_merge($fields, array(
                'primary_category',
                'primary_category_name',
                'specialty',
                'provider_type',
                'recent_providers',
                'last_provider_focus',
                'provider_owner_mode',
                'provider_workflow_step',
                'provider_workflow_city',
                'provider_workflow_field',
                'search_context_updated_at',
            ));
        }

        // Provider-owner intents that need recent_providers preserved so the
        // claim guidance can match the user's name against previously shown
        // providers.  (Debug log 1777280880936: user said "My name is Ashley
        // Almon" after seeing Ashley Almon in a prior search, but the context
        // reset had already cleared recent_providers, so the name could not be
        // matched and the WP_Query search also failed.)
        $claim_owner_intents = array(
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_OWNER,
        );
        $add_owner_intents = array(
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_ADD,
        );
        $other_owner_intents = array(
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_UPGRADE,
            MyHH_Mia_Intent_Engine::INTENT_SPONSOR,
        );
        if (!empty($result['intent']) && in_array($result['intent'], $claim_owner_intents, true)) {
            // Claim/owner: keep recent_providers + last_provider_focus for
            // name matching, but reset search context fields.
            $fields = array_merge($fields, array(
                'primary_category',
                'primary_category_name',
                'specialty',
                'city',
                'provider_type',
                'modality',
                'language_preference',
                'insurance',
                'session_type',
                'search_context_updated_at',
                'provider_workflow_step',
                'provider_workflow_city',
                'provider_workflow_field',
            ));
        } elseif (!empty($result['intent']) && in_array($result['intent'], $add_owner_intents, true)) {
            // Adding a practice is a distinct workflow. Remove consumer-search
            // memory so provider cards and search criteria cannot leak into it.
            $fields = array_merge($fields, array(
                'primary_category',
                'primary_category_name',
                'specialty',
                'city',
                'provider_type',
                'modality',
                'language_preference',
                'insurance',
                'session_type',
                'recent_providers',
                'last_provider_focus',
                'search_context_updated_at',
                'claim_disambiguation_providers',
                'claim_name_suggestions',
            ));
        } elseif (!empty($result['intent']) && in_array($result['intent'], $other_owner_intents, true)) {
            // Upgrade/sponsor is often a brief side question during a
            // consumer search. Clear active search filters so they cannot
            // contaminate the owner flow, but retain the ranked provider
            // memory so "going back to those therapists" resumes the same
            // conversation instead of silently starting a new search.
            $fields = array_merge($fields, array(
                'primary_category',
                'primary_category_name',
                'specialty',
                'city',
                'provider_type',
                'modality',
                'language_preference',
                'insurance',
                'session_type',
                'provider_workflow_step',
                'provider_workflow_city',
                'provider_workflow_field',
            ));
        } elseif (!empty($result['intent']) && $result['intent'] === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH) {
            $fields = array_merge($fields, array(
                'provider_owner_mode',
                'provider_workflow_step',
                'provider_workflow_city',
                'provider_workflow_field',
                'claim_disambiguation_providers',
                'claim_name_suggestions',
            ));
        } else {
            $fields = array_merge($fields, array(
                'provider_owner_mode',
                'provider_workflow_step',
                'provider_workflow_city',
                'provider_workflow_field',
            ));
        }

        return $this->normalize_context_reset_fields($fields);
    }

    private function has_claim_selection_context($context) {
        if (!is_array($context)) {
            return false;
        }
        $owner_mode = $context['provider_owner_mode'] ?? '';
        if (in_array($owner_mode, array(
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_OWNER,
        ), true)) {
            return true;
        }

        return !empty($context['claim_disambiguation_providers']) && is_array($context['claim_disambiguation_providers']);
    }

    /** The current patient request outranks an earlier provider-owner workflow. */
    private function patient_request_kind($message) {
        $raw = $this->without_negated_owner_actions($message);
        if ($this->is_existing_provider_fact_question($raw)
            || preg_match('/\b(?:this|that|his|her|their|the)\s+(?:profile|listing)\b/iu', $raw)) {
            return '';
        }
        // An owner may have a practice whose name includes a health concern.
        if (preg_match('/\b(?:claim|reclaim|add|list|register|submit|manage|upgrade)\b[\s\S]{0,60}\b(?:my|our|profile|listing|practice|clinic|business)\b|\b(?:my|our)\s+(?:practice|clinic|business|profile|listing)\b|\b(?:reclamar|agregar|registrar|gestionar)\b[\s\S]{0,60}\b(?:perfil|listado|pr[aá]ctica|negocio)\b/iu', $raw)) {
            return '';
        }
        $concern = preg_match('/\b(?:pain|ache|aching|hurts?|injur(?:y|ed)|swollen|swelling|numb(?:ness)?|symptoms?|sick|unwell|anxious|anxiety|depressed|depression|stressed|stress|panic|grief|sleep|insomnia|dolor|duele|ansiedad|depresi[oó]n|dormir)\b/iu', $raw);
        $personal = preg_match('/\b(?:my\s+(?:father|mother|daughter|son|child|wife|husband|partner|parent|friend)|(?:his|her|their)\s+(?:leg|arm|back|neck|head|chest|knee|foot|stomach|body)|mi\s+(?:padre|madre|hija|hijo)|i\s+(?:have|got|feel|am|need|suffer|struggle|experience|can[\x{2019}\x{27}]?t|cannot)|i[\x{2019}\x{27}]m|my\s+(?:leg|arm|back|neck|head|chest|knee|foot|stomach|body|pain|symptoms?|sleep)|me\s+(?:duele|siento)|tengo|no\s+puedo\s+dormir)\b/iu', $raw);
        $search = preg_match('/\b(?:find|search|looking\s+for|look\s+for|need|want|buscar|busco|necesito)\b[\s\S]{0,80}\b(?:therapists?|counselors?|doctors?|medical\s+(?:care|providers?)|care|psychiatrists?|psychologists?|physiotherapists?|physical\s+therapists?|terapeutas?|m[eé]dicos?|atenci[oó]n)\b/iu', $raw);
        if ($search) {
            return 'provider_search';
        }
        if ($concern && $personal) {
            return 'personal_concern';
        }
        return ($this->looks_like_platform_service_overview_request($raw) || MyHH_Mia_Intent_Engine::is_basic_education_question($raw)) ? 'education' : '';
    }

    private function prepare_patient_request_context($message, $safe_prior) {
        if ($this->patient_request_kind($message) === '' || (empty($safe_prior['provider_owner_mode'])
            && empty($safe_prior['claim_disambiguation_providers']) && empty($safe_prior['claim_name_suggestions'])
            && !in_array($safe_prior['navigation_state']['goal'] ?? '', array('provider_claim', 'provider_add', 'provider_upgrade'), true))) {
            return $safe_prior;
        }
        $safe_prior = $this->apply_context_field_resets($safe_prior, array(
            'provider_owner_mode', 'provider_workflow_step', 'provider_workflow_city', 'provider_workflow_field',
            'claim_disambiguation_providers', 'claim_name_suggestions', 'recent_providers', 'provider_history',
            'last_provider_focus', 'search_context_updated_at', 'primary_category', 'primary_category_name',
            'specialty', 'city', 'provider_type', 'modality', 'insurance', 'session_type',
        ));
        $safe_prior['navigation_state'] = $this->sanitize_navigation_state(array(
            'goal' => 'explore_support', 'stage' => 'listening', 'readiness' => 'exploring',
            'open_question' => 'none', 'next_best_action' => 'listen',
            'support_topics' => $this->infer_navigation_support_topics($message),
        ));
        return $safe_prior;
    }

    private function build_patient_concern_response($message, $lang = 'en', $prior = array()) {
        $pain = preg_match('/\b(?:pain|ache|aching|hurts?|dolor|duele)\b/iu', (string) $message);
        $body_parts = array(
            'back' => array('back', 'espalda'), 'leg' => array('leg', 'pierna'),
            'neck' => array('neck', 'cuello'), 'knee' => array('knee', 'rodilla'),
            'arm' => array('arm', 'brazo'), 'foot' => array('foot', 'pie'),
        );
        $body_part = '';
        if ($pain) {
            foreach ($body_parts as $key => $names) {
                if (preg_match('/\b(?:' . implode('|', array_map(static function($name) { return preg_quote($name, '/'); }, $names)) . ')\b/iu', (string) $message)) {
                    $body_part = $key;
                    break;
                }
            }
        }
        if ($lang === 'es') {
            $opening = $body_part !== '' ? 'Siento que tengas dolor en ' . (in_array($body_part, array('arm', 'foot'), true) ? 'el ' : 'la ') . $body_parts[$body_part][1] . '.' : 'Siento que estes pasando por eso.';
            if (!empty($prior['care_recipient']) && $prior['care_recipient'] !== 'self') $opening = 'Siento que tu familiar este pasando por eso.';
            $reply = $opening . ' Puedo ayudarte a explorar opciones de atencion o encontrar un profesional. Prefieres entender las opciones primero o buscar proveedores cerca de ti?';
        } else {
            $opening = $body_part !== '' ? "I'm sorry your {$body_part} is hurting." : "I'm sorry you're dealing with that.";
            if (!empty($prior['care_recipient']) && $prior['care_recipient'] !== 'self') {
                $recipient = sanitize_text_field($prior['care_recipient']);
                $opening = $body_part !== '' ? "I'm sorry your {$recipient} has {$body_part} pain." : "I'm sorry your {$recipient} is dealing with that.";
            }
            $reply = $opening . ' I can help you explore care options or find a provider. Would you like to understand the options first, or look for care near you?';
        }
        $this->_conversational_speech = $reply;
        return $reply;
    }

    private function build_patient_concern_understanding($message, $safe_prior) {
        return array(
            'raw_text' => $message, 'clean_text' => $message,
            'intent' => MyHH_Mia_Intent_Engine::INTENT_GENERAL, 'next_action' => 'general', 'action' => 'general',
            'confidence' => 1, 'entities' => array('language' => $safe_prior['language'] ?? 'en'),
            'search_entities' => array(), 'target_provider_id' => 0, 'target_provider_rank' => 0,
            'target_provider_name' => '', 'needs_clarification' => false, 'clarification_question' => '',
            'source' => 'deterministic_patient_concern',
            'navigation_state' => array(
                'goal' => 'explore_support', 'stage' => 'clarifying', 'readiness' => 'exploring',
                'open_question' => 'readiness', 'next_best_action' => 'clarify',
                'support_topics' => $this->infer_navigation_support_topics($message),
            ),
        );
    }

    private function has_provider_add_workflow_context($context) {
        return is_array($context)
            && ($context['provider_owner_mode'] ?? '') === MyHH_Mia_Intent_Engine::INTENT_PROVIDER_ADD;
    }

    private function looks_like_claim_to_add_transition($message) {
        if (preg_match('/\b(?:add|list|submit|register|create|agregar|a[ñn]adir|registrar|crear)\b[\s\S]{0,40}\b(?:practice|business|clinic|listing|profile|provider|pr[aá]ctica|negocio|cl[ií]nica|listado|perfil|proveedor)\b/iu', (string) $message)
            && !preg_match('/\b(?:do\s+not|don[\x{2019}\x{27}]?t|not|no)\s+(?:want\s+to\s+)?(?:add|create|agregar|crear)\b/iu', (string) $message)) {
            return true;
        }
        return (bool) preg_match(
            '/\b(?:it|my\s+(?:practice|business|clinic|office|profile|listing))\s+(?:isn[\'’]?t|is\s+not|wasn[\'’]?t|was\s+not|doesn[\'’]?t|does\s+not)\s+(?:already\s+)?(?:listed|there|in\s+the\s+directory)\b|\b(?:couldn[\'’]?t|could\s+not|can[\'’]?t|cannot|didn[\'’]?t|did\s+not)\s+find\s+(?:it|my\s+(?:practice|business|profile|listing))\b|\b(?:no\s+aparece|no\s+est[aá]\s+(?:listad[oa]|en\s+el\s+directorio)|no\s+(?:lo|la)\s+encontr[eé])\b/iu',
            (string) $message
        );
    }

    /**
     * Resolve active provider-add turns before Grok or provider retrieval.
     */
    private function build_provider_workflow_continuation_understanding($message, $safe_prior) {
        $owner_mode = is_array($safe_prior) ? ($safe_prior['provider_owner_mode'] ?? '') : '';
        $provider_add_active = $owner_mode === MyHH_Mia_Intent_Engine::INTENT_PROVIDER_ADD;
        $provider_upgrade_active = $owner_mode === MyHH_Mia_Intent_Engine::INTENT_PROVIDER_UPGRADE;
        $provider_claim_active = in_array($owner_mode, array(
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_OWNER,
        ), true);
        if ($provider_claim_active && !$this->looks_like_claim_to_add_transition($message)) {
            return array();
        }
        if (!$provider_add_active && !$provider_upgrade_active && !$provider_claim_active) {
            return array();
        }

        $raw = trim((string) $message);
        $value = strtolower($raw);
        if ($value === '') {
            return array();
        }
        $language = preg_match('/\b(?:qu[eé]|c[oó]mo|cu[aá]l|necesito|quiero|pr[aá]ctica|proveedor|perfil|listado|ciudad|informaci[oó]n|gracias|ayuda)\b/iu', $value)
            ? 'es'
            : (($safe_prior['language'] ?? 'en') === 'es' ? 'es' : 'en');
        $workflow_closing = $this->is_provider_workflow_closing_acknowledgement($raw);
        if ($provider_upgrade_active && !$workflow_closing && !preg_match('/\b(?:price|prices|pricing|cost|monthly|month|plan|plans|tier|tiers|their|they|them|difference|compare|benefit|benefits|feature|features|visibility|visible|placement|action|actions|pulse|lite|pro|upgrade|upgrading)\b|\bhow\s+much\b/iu', $value)) {
            return array();
        }

        $action = $provider_upgrade_active ? 'provider_upgrade' : 'provider_add';
        $intent = $provider_upgrade_active
            ? MyHH_Mia_Intent_Engine::INTENT_PROVIDER_UPGRADE
            : MyHH_Mia_Intent_Engine::INTENT_PROVIDER_ADD;

        if ($workflow_closing || preg_match('/^\s*(?:cancel|stop|never\s*mind|nevermind|forget\s+it|exit|end|new\s+chat|start\s+over|cancelar|detener|olvida(?:lo)?|salir|terminar|nuevo\s+chat|empezar\s+de\s+nuevo)\s*[.!?]*\s*$/iu', $value)) {
            $action = 'general';
            $intent = MyHH_Mia_Intent_Engine::INTENT_GENERAL;
        } elseif (preg_match('/\b(?:find|search|show|looking\s+for|help\s+me\s+find|buscar|busco|encuentra|mu[eé]strame)\b[\s\S]{0,80}\b(?:provider|providers|therapist|therapists|counselor|counselors|doctor|doctors|psychiatrist|psychologist|massage|nutrition|dietitian|acupuncture|proveedor|proveedores|terapeuta|terapeutas|consejero|consejeros|m[eé]dico|psic[oó]logo|psiquiatra)\b/iu', $value)) {
            $action = 'provider_search';
            $intent = MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH;
        } elseif (preg_match('/\b(?:add|list|submit|register|create|set\s+up|agregar|a[ñn]adir|listar|registrar|crear)\b[\s\S]{0,40}\b(?:practice|business|clinic|office|listing|profile|provider|pr[aá]ctica|negocio|cl[ií]nica|oficina|listado|perfil|proveedor)\b/iu', $value)) {
            $action = 'provider_add';
            $intent = MyHH_Mia_Intent_Engine::INTENT_PROVIDER_ADD;
        } elseif (preg_match('/\b(?:claim|reclaim|take\s+over|access)\b[\s\S]{0,50}\b(?:profile|listing|practice|provider|business)\b|\b(?:reclamar|gestionar)\b[\s\S]{0,50}\b(?:perfil|listado|pr[aá]ctica|proveedor)\b/iu', $value)) {
            $action = 'provider_claim';
            $intent = MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM;
        } elseif (preg_match('/\b(?:upgrade|premium|visibility|pulse\s*(?:lite|pro)|mejorar|actualizar)\b/iu', $value)) {
            $action = 'provider_upgrade';
            $intent = MyHH_Mia_Intent_Engine::INTENT_PROVIDER_UPGRADE;
        } elseif (preg_match('/\b(?:sponsor|sponsorship|advertis|company\s+partnership|patrocinar|patrocinio|publicidad)\b/iu', $value)) {
            $action = 'sponsor_inquiry';
            $intent = MyHH_Mia_Intent_Engine::INTENT_SPONSOR;
        }

        return array(
            'raw_text' => sanitize_text_field($raw),
            'clean_text' => sanitize_text_field($raw),
            'intent' => $intent,
            'next_action' => $action,
            'action' => $action,
            'confidence' => 1,
            'entities' => array('language' => $language),
            'search_entities' => array(),
            'change_dimension' => '',
            'preserve_context' => array(),
            'clear_context' => array(),
            'needs_clarification' => false,
            'clarification_question' => '',
            'source' => 'provider_workflow_fast_path',
            'status' => 'fast_path',
        );
    }

    private function is_provider_workflow_closing_acknowledgement($message) {
        $value = strtolower(trim(wp_strip_all_tags((string) $message)));
        if ($value === '' || strlen($value) > 180) {
            return false;
        }

        if (preg_match('/^\s*(?:that(?:\'s|\s+is)\s+all|nothing\s+else|never\s*mind|cancel|stop|done|bye|goodbye|eso\s+es\s+todo|nada\s+m[aá]s|cancelar|terminamos|adi[oó]s)\s*[.!?]*\s*$/iu', $value)) {
            return true;
        }
        if (preg_match('/(?:^|[.!?]\s*)(?:that(?:\'s|\s+is)\s+all|nothing\s+else|i(?:\'m|\s+am)\s+done|eso\s+es\s+todo|nada\s+m[aá]s|terminamos)\s*[.!?]*\s*$/iu', $value)) {
            return true;
        }

        $normalized = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value));
        $gratitude = '(?:thank\s+you(?:\s+(?:so|very)\s+much)?(?:\s+for\s+(?:your\s+)?(?:time|help|support|assistance))?|thanks(?:\s+(?:so|very)\s+much|\s+a\s+lot)?(?:\s+for\s+(?:your\s+)?(?:time|help|support|assistance))?|i\s+appreciate(?:\s+(?:it|that|you|your\s+(?:time|help|support|assistance)))?|appreciate(?:\s+(?:it|that|you|your\s+(?:time|help|support|assistance)))?|much\s+appreciated|many\s+thanks|gracias(?:\s+por\s+(?:tu\s+)?(?:tiempo|ayuda|apoyo))?|muchas\s+gracias(?:\s+por\s+(?:tu\s+)?(?:tiempo|ayuda|apoyo))?)';
        $connector = '(?:ok|okay|alright|all\s+right|well|and|also|again|really|so|bueno|bien|y|tambien|tambi[eé]n)';

        return (bool) preg_match('/^(?:' . $gratitude . '|' . $connector . ')(?:\s+(?:' . $gratitude . '|' . $connector . '))*$/iu', $normalized);
    }

    private function is_search_preference_recap_request($message) {
        if (preg_match('/\b(?:what|which)\s+are\s+my\s+(?:search\s+)?(?:preferences|criteria)|\b(?:tell|remind)\s+me\b[\s\S]{0,50}\b(?:preferences|criteria)\b|\b(?:preferences|criteria)\b[\s\S]{0,35}\b(?:remember|recall)\b/iu', (string) $message)) return true;
        return (bool) preg_match(
            '/\b(?:what|which)\s+(?:preferences?|details?|criteria|things?)\s+did\s+i\s+(?:give|tell|mention|ask)|\bwhat\s+did\s+i\s+(?:say|ask)\s+(?:i\s+)?(?:wanted|needed|preferred)|\bwhat\s+(?:city|location|service|support)\b[\s\S]{0,45}\b(?:did\s+i|i\s+did)\s+(?:mention|give|say|ask)\b|\bwhat\s+city\s+and\s+(?:service|support)\s+did\s+i\s+mention|\b(?:remind|tell)\s+me\s+(?:what|which)\s+(?:preferences?|criteria)|\bqu[eé]\s+(?:preferencias?|detalles?|criterios?|ciudad|servicio)\s+(?:te\s+)?(?:di|dije|mencion[eé])|\brecu[eé]rdame\s+(?:mis|qu[eé])\s+(?:preferencias?|criterios?|ciudad|servicio)/iu',
            (string) $message
        );
    }

    private function build_search_preference_recap_response($safe_prior, $lang = 'en') {
        $parts = array();
        $specialty = sanitize_text_field($safe_prior['specialty'] ?? '');
        $category = sanitize_text_field($safe_prior['primary_category_name'] ?? '');
        if ($specialty !== '') {
            $parts[] = $specialty;
        } elseif ($category !== '') {
            $parts[] = $category;
        }
        if (!empty($safe_prior['city'])) {
            $parts[] = ($lang === 'es' ? 'en ' : 'in ') . sanitize_text_field($safe_prior['city']);
        }
        if (!empty($safe_prior['modality'])) {
            $parts[] = ($lang === 'es' ? 'con ' : 'using ') . sanitize_text_field($safe_prior['modality']);
        }
        if (!empty($safe_prior['language_preference'])) {
            $parts[] = ($lang === 'es' ? 'un proveedor que hable ' : 'a provider who speaks ') . sanitize_text_field($safe_prior['language_preference']);
        }

        foreach (array('insurance' => $lang === 'es' ? 'seguro: ' : 'insurance: ', 'session_type' => $lang === 'es' ? 'sesiones: ' : 'sessions: ', 'care_recipient' => $lang === 'es' ? 'para: ' : 'care for: ', 'care_age_group' => $lang === 'es' ? 'edad: ' : 'age group: ') as $key => $label) {
            if (!empty($safe_prior[$key])) $parts[] = $label . sanitize_text_field($safe_prior[$key]);
        }
        if (empty($parts)) {
            return $lang === 'es'
                ? 'No tengo preferencias de búsqueda confirmadas en esta conversación todavía.'
                : "I don't have any confirmed search preferences in this conversation yet.";
        }

        return $lang === 'es'
            ? 'Antes de buscar, me pediste: **' . implode('**, **', $parts) . '**. Esas siguen siendo tus preferencias actuales.'
            : 'Before the search, you asked for **' . implode('**, **', $parts) . '**. Those are still your current search preferences.';
    }

    private function clear_active_provider_workflow_context($context) {
        return $this->apply_context_field_resets($context, array(
            'provider_owner_mode',
            'claim_disambiguation_providers',
            'claim_name_suggestions',
        ));
    }

    private function looks_like_claim_list_selection($message) {
        $msg = strtolower(trim((string) $message));
        if ($msg === '') {
            return false;
        }

        $number_words = '(?:one|two|three|four|five|six|seven|eight|nine|ten)';
        return preg_match('/\b(?:number|option|profile|listing)\s+(?:\d+|' . $number_words . ')\b/i', $msg) ||
            preg_match('/\b(?:that\'?s|that\s+is|this\s+is|it\'?s|it\s+is|ok(?:ay)?|alright|all\s+right|yes|yeah|yep|sure)\s+(?:the\s+)?(?:number\s+)?(?:\d+|' . $number_words . '|first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|1st|2nd|3rd|4th|5th|6th|7th|8th|9th|10th)\b/i', $msg) ||
            preg_match('/\b(?:the\s+)?(?:first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|1st|2nd|3rd|4th|5th|6th|7th|8th|9th|10th)\s*(?:one|profile|listing)?\b/i', $msg) ||
            preg_match('/^\s*\d+\s*$/', $msg) ||
            preg_match('/\b(?:that|this|the)\s+(?:one|profile|listing|account)\b/i', $msg);
    }

    private function force_claim_selection_result_if_needed($message, $safe_prior, $result, $source = '') {
        if (empty($result) || !is_array($result)) {
            return $result;
        }
        if (($result['intent'] ?? '') === MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM) {
            return $result;
        }
        if (!$this->has_claim_selection_context($safe_prior) || !$this->looks_like_claim_list_selection($message)) {
            return $result;
        }

        mia_debug_log('ProviderOwner', 'claim_selection_forced', array(
            'source' => $source,
            'previous_intent' => $result['intent'] ?? '',
            'message_preview' => substr((string) $message, 0, 80),
            'has_candidates' => !empty($safe_prior['claim_disambiguation_providers']),
        ));

        $result['intent'] = MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM;
        $result['response'] = $result['response'] ?? '';
        $result['providers'] = array();
        $result['criteria'] = is_array($result['criteria'] ?? null) ? $result['criteria'] : array();
        return $result;
    }

    private function get_answered_provider_focus($reply, $prior_context) {
        $mentioned = array();
        foreach (array_merge((array) ($prior_context['recent_providers'] ?? array()), (array) ($prior_context['provider_history'] ?? array())) as $provider) {
            $name = trim((string) ($provider['name'] ?? ''));
            $id = absint($provider['id'] ?? 0);
            if ($id && $name !== '' && preg_match('/(?<![\p{L}\p{N}])' . preg_quote($name, '/') . '(?![\p{L}\p{N}])/iu', $reply)) {
                $mentioned[$id] = $id;
            }
        }
        // A comparison/list has no single new focus; a single-provider answer does.
        return count($mentioned) === 1 ? reset($mentioned) : absint($prior_context['last_provider_focus'] ?? 0);
    }

    private function build_updated_context($safe_prior, $result, $message, $negative_search_refinement = false) {
        $ctx_keys = array(
            'primary_category',
            'primary_category_name',
            'specialty',
            'city',
            'provider_type',
            'modality',
            'language_preference',
            'insurance',
            'session_type',
            'language',
            'provider_workflow_step',
            'provider_workflow_city',
            'provider_workflow_field',
        );
        $reset_fields = $this->build_context_reset_fields_from_result($result, $negative_search_refinement);
        $updated_context = $this->apply_context_field_resets($safe_prior, $reset_fields);
        if (!empty($reset_fields)) {
            mia_debug_log('Chat', 'context_reset', array(
                'intent' => $result['intent'] ?? '',
                'fields' => $reset_fields,
            ));
        }

        if (!empty($result['criteria']) && is_array($result['criteria'])) {
            $criteria = $result['criteria'];
            foreach ($ctx_keys as $k) {
                if (!empty($criteria[$k])) {
                    $updated_context[$k] = $criteria[$k];
                }
            }
        }

        if (!empty($result['detected_language'])) {
            $updated_context['language'] = $result['detected_language'];
        }

        $owner_intents = array(
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_ADD,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_OWNER,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_UPGRADE,
            MyHH_Mia_Intent_Engine::INTENT_SPONSOR,
        );
        if (!empty($result['intent']) && in_array($result['intent'], $owner_intents, true)) {
            $updated_context['provider_owner_mode'] = $result['intent'];
        } else {
            $updated_context['provider_owner_mode'] = '';
        }
        if (($result['intent'] ?? '') === MyHH_Mia_Intent_Engine::INTENT_PROVIDER_ADD) {
            $workflow_step = sanitize_key((string) ($result['criteria']['provider_workflow_step'] ?? ($updated_context['provider_workflow_step'] ?? 'start')));
            $updated_context['provider_workflow_step'] = $workflow_step;
            if (!empty($result['criteria']['provider_workflow_city'])) {
                $updated_context['provider_workflow_city'] = sanitize_text_field($result['criteria']['provider_workflow_city']);
            }
            if (!empty($result['criteria']['provider_workflow_field'])) {
                $updated_context['provider_workflow_field'] = sanitize_key((string) $result['criteria']['provider_workflow_field']);
            } elseif ($workflow_step !== 'field_help') {
                $updated_context['provider_workflow_field'] = '';
            }
        }

        if (!empty($result['providers']) && is_array($result['providers'])) {
            // Keep a bounded earlier-page history for explicit names, never ordinals.
            $history = array();
            foreach (array_merge($safe_prior['recent_providers'] ?? array(), $safe_prior['provider_history'] ?? array()) as $provider) {
                $id = absint($provider['id'] ?? 0);
                if ($id && !isset($history[$id])) $history[$id] = $provider;
            }
            $updated_context['provider_history'] = $this->sanitize_provider_memory(array_values($history), 8);
            $updated_context['recent_providers'] = $this->build_provider_memory($result['providers']);
            if (!empty($updated_context['recent_providers'][0]['id'])) {
                $updated_context['last_provider_focus'] = absint($updated_context['recent_providers'][0]['id']);
            }
            $updated_context['search_context_updated_at'] = time();
        }

        if (!empty($updated_context['recent_providers'])) {
            $referenced_provider = $this->resolve_referenced_provider($message, $updated_context['recent_providers'], $updated_context);
            if (!empty($referenced_provider['id'])) {
                $updated_context['last_provider_focus'] = absint($referenced_provider['id']);
            }
        }

        $suggested_navigation = $result['_navigation_state'] ?? ($result['_grok_understanding']['navigation_state'] ?? array());
        $updated_context['navigation_state'] = $this->derive_navigation_state($message, $result, $updated_context, $suggested_navigation);
        // Explicit empty values survive frontend merges; omission resurrects stale
        // service fields from the previous client state after a category change.
        foreach ($reset_fields as $field) {
            if (!array_key_exists($field, $updated_context)) {
                $updated_context[$field] = $field === 'recent_providers' ? array() : '';
            }
        }
        return $updated_context;
    }

    /**
     * Tell the active Realtime session which backend facts it may phrase
     * naturally and which details must remain exact.
     */
    private function build_realtime_natural_response_contract($assistant_reply, $result, $response_type, $providers = array(), $navigation_state = array()) {
        $intent = sanitize_key((string) ($result['intent'] ?? ''));
        $response_type = sanitize_key((string) $response_type);
        $exact_business_intents = array(
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_ADD,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_OWNER,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_UPGRADE,
            MyHH_Mia_Intent_Engine::INTENT_SPONSOR,
        );
        $is_exact_safety = !empty($result['crisis_override'])
            || $intent === MyHH_Mia_Intent_Engine::INTENT_CRISIS
            || strpos($response_type, 'crisis') !== false;
        $is_exact_business = in_array($intent, $exact_business_intents, true);
        $is_empty_provider_search = $intent === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH
            && strpos($response_type, 'search') !== false
            && empty($providers);
        $is_exact_provider_fact = $response_type === 'deterministic_followup'
            && $this->is_existing_provider_fact_question($result['_grok_understanding']['raw_text'] ?? $result['_grok_understanding']['clean_text'] ?? '');
        $broadened = sanitize_text_field($result['broadened'] ?? '');
        if (!empty($result['criteria']['requested_specialty']) && !empty($providers) && strpos($response_type, 'search') !== false) {
            $broadened = 'Broader category options; the requested specialty is not confirmed for every returned profile.';
        }

        $required_links = array();
        if (preg_match_all('/\[[^\]]+\]\((https?:\/\/[^)]+|\/[^)]+)\)/i', (string) $assistant_reply, $matches)) {
            foreach ($matches[1] as $url) {
                $required_links[] = esc_url_raw($url);
            }
        }
        if (preg_match_all('/href=["\']([^"\']+)["\']/i', (string) $assistant_reply, $html_matches)) {
            foreach ($html_matches[1] as $url) {
                $required_links[] = esc_url_raw($url);
            }
        }
        $required_links = array_values(array_unique(array_filter($required_links)));

        $provider_facts = array();
        foreach (array_slice(is_array($providers) ? $providers : array(), 0, 2) as $provider) {
            $provider_facts[] = array(
                'id' => absint($provider['id'] ?? 0),
                'name' => sanitize_text_field($provider['name'] ?? ''),
                'city' => sanitize_text_field($provider['city'] ?? ''),
                'categories' => array_values(array_slice(array_map('sanitize_text_field', $provider['categories'] ?? array()), 0, 3)),
                'specialties' => array_values(array_slice(array_map('sanitize_text_field', $provider['specialties'] ?? array()), 0, 3)),
                'session_type' => sanitize_text_field($provider['session_type'] ?? ''),
                'insurance_accepted' => array_values(array_slice(array_map('sanitize_text_field', $provider['insurance_accepted'] ?? array()), 0, 8)),
                'url' => $this->sanitize_provider_url($provider['url'] ?? ''),
            );
        }

        $navigation_state = $this->sanitize_navigation_state($navigation_state);
        $readiness = $navigation_state['readiness'] ?? 'unknown';
        $stage = $navigation_state['stage'] ?? 'clarifying';
        $next_step_rule = in_array($stage, array('listening', 'educating', 'closing'), true) || $readiness === 'not_ready'
            ? 'Do not force a provider search, profile link, or promotional call to action. Finish the answer naturally.'
            : 'Offer one relevant next step only when it helps advance the user\'s stated goal.';

        return array(
            'allowed' => !$is_exact_safety && !$is_exact_business && !$is_empty_provider_search && !$is_exact_provider_fact,
            'source_of_truth' => 'wordpress',
            'fallback_reply_field' => 'assistant_reply',
            'broadened' => preg_match('/^Adjusted search by /i', $broadened) ? '' : $broadened,
            'required_links' => $required_links,
            'spoken_provider_facts' => $provider_facts,
            'spoken_provider_limit' => 2,
            'card_only_provider_names' => array_values(array_filter(array_map(function($provider) {
                return sanitize_text_field($provider['name'] ?? '');
            }, array_slice(is_array($providers) ? $providers : array(), 2)))),
            'preserve_all_provider_names' => in_array($intent, array(
                MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM,
                MyHH_Mia_Intent_Engine::INTENT_PROVIDER_OWNER,
            ), true),
            'navigation_state' => $navigation_state,
            'rules' => array(
                'Use only the returned facts and providers.',
                'Preserve prices, URLs, free/paid status, and safety instructions exactly.',
                'Respond in the user language for this turn.',
                'Do not repeat an introduction or the platform name unnecessarily.',
                'Lead with the answer. Briefly reflect meaningful concerns once, but do not parrot or role-play as a therapist.',
                'Ask no more than one clarifying question, and only if it unlocks materially better guidance.',
                'State unknown or unverified facts plainly instead of guessing.',
                $next_step_rule,
                'If broadened is non-empty, say these are broader options rather than exact specialty matches.',
                'Never call a provider a match for the requested category unless WordPress returned that provider under the category-constrained search.',
                'STRICT: Speak at most the first two provider names unless the user explicitly asks for the full list. Never read card_only_provider_names aloud.',
            ),
        );
    }

    private function build_realtime_tool_payload($message, $history = array(), $prior_context = array(), $route_hint = '', $transcript_candidates = array(), $realtime_action = array(), $predictive_prefetch = false) {
        $safe_prior = $this->prepare_conversation_turn($message, $this->sanitize_prior_context($prior_context));
        $had_owner_workflow = !empty($safe_prior['provider_owner_mode']) || $this->has_claim_selection_context($safe_prior)
            || in_array($safe_prior['navigation_state']['goal'] ?? '', array('provider_claim', 'provider_add', 'provider_upgrade'), true);
        $safe_prior = $this->prepare_patient_request_context($message, $safe_prior);
        $patient_request_kind = $this->patient_request_kind($message);
        $is_patient_concern = $patient_request_kind === 'personal_concern';
        if ($is_patient_concern || ($had_owner_workflow && $patient_request_kind !== '')) {
            $route_hint = '';
            $realtime_action = array();
        }
        $engine  = new MyHH_Mia_Intent_Engine();
        $engine->set_predictive_preview_mode($predictive_prefetch);
        $context = $this->get_directory_context();
        $transcript_candidates = $this->sanitize_transcript_candidates($transcript_candidates);
        $is_platform_service_overview = $this->looks_like_platform_service_overview_request($message, $history, $safe_prior);
        $is_provider_fact = !empty($safe_prior['recent_providers']) && $this->is_existing_provider_fact_question($message);
        $is_basic_education = !$is_provider_fact && ($is_platform_service_overview
            || MyHH_Mia_Intent_Engine::is_basic_education_question($message)
            || $this->looks_like_contextual_education_followup($message, $safe_prior));
        $suppress_provider_action = $is_patient_concern || $this->should_hold_provider_search_for_readiness($message, $safe_prior);
        $pre_search_clarification = $this->wants_pre_search_clarification($message);
        if ($is_basic_education || $suppress_provider_action) {
            // The literal current turn outranks a stale provider-search hint.
            // Otherwise the continuation guard below can undo the corrected
            // Realtime action and rotate providers for an education question.
            $route_hint = '';
        }

        $workflow_closing = ($this->has_claim_selection_context($safe_prior) || $this->has_provider_add_workflow_context($safe_prior))
            && $this->is_provider_workflow_closing_acknowledgement($message);
        if ($workflow_closing) {
            $updated_context = $this->clear_active_provider_workflow_context($safe_prior);
            $updated_context['navigation_state'] = $this->sanitize_navigation_state(array(
                'goal' => 'unsure',
                'stage' => 'closing',
                'readiness' => 'unknown',
                'support_topics' => array(),
                'open_question' => 'none',
                'next_best_action' => 'close',
            ));
            $language = ($updated_context['language'] ?? 'en') === 'es' ? 'es' : 'en';
            $assistant_reply = $language === 'es'
                ? 'De nada. Que estés bien.'
                : "You're very welcome. Take care.";

            return array(
                'assistant_reply'       => $assistant_reply,
                'response_type'         => 'social_ack',
                'intent'                => MyHH_Mia_Intent_Engine::INTENT_GENERAL,
                'criteria'              => array(),
                'prior_context'         => $updated_context,
                'detected_language'     => $language,
                'providers'             => array(),
                'provider_exposure'     => array(),
                'near_me_detected'      => false,
                'conversational_speech' => '',
                'understanding'         => array(
                    'intent'              => 'general_unknown',
                    'next_action'         => 'general',
                    'clean_text'          => $message,
                    'confidence'          => 1,
                    'needs_clarification' => false,
                    'source'              => 'deterministic_workflow_close',
                    'entities'            => array('language' => $language),
                    'navigation_state'    => $updated_context['navigation_state'],
                ),
                'navigation_state'      => $updated_context['navigation_state'],
            );
        }

        $crisis_result = $engine->get_crisis_override($message, $safe_prior);
        if (is_array($crisis_result)) {
            $updated_context = $this->build_updated_context($safe_prior, $crisis_result, $message);
            $language = $crisis_result['detected_language'] ?? ($updated_context['language'] ?? 'en');
            $understanding = array(
                'intent'                 => 'crisis_or_high_risk',
                'next_action'            => 'crisis',
                'clean_text'             => $message,
                'confidence'             => 1,
                'needs_clarification'    => false,
                'source'                 => 'deterministic_crisis_gate',
                'selected_transcript_source' => 'realtime',
                'entities'               => array('language' => $language),
            );

            return array(
                'assistant_reply'       => $crisis_result['response'],
                'response_type'         => 'crisis_override',
                'intent'                => MyHH_Mia_Intent_Engine::INTENT_CRISIS,
                'criteria'              => $crisis_result['criteria'] ?? array(),
                'prior_context'         => $updated_context,
                'detected_language'     => $language,
                'providers'             => array(),
                'provider_exposure'     => array(),
                'near_me_detected'      => false,
                'conversational_speech' => '',
                'understanding'         => $understanding,
                'crisis_override'       => true,
            );
        }

        $conversation_reply = $this->resolve_conversation_control($message, $safe_prior, $history);
        if ($conversation_reply !== null) return $conversation_reply;

        if ($this->is_search_preference_recap_request($message)) {
            $language = ($safe_prior['language'] ?? 'en') === 'es' ? 'es' : 'en';
            $updated_context = $safe_prior;
            $prior_navigation = $this->sanitize_navigation_state($safe_prior['navigation_state'] ?? array());
            $recap_is_deferred = ($prior_navigation['readiness'] ?? '') === 'not_ready';
            $updated_context['navigation_state'] = $this->sanitize_navigation_state(array_merge(
                $safe_prior['navigation_state'] ?? array(),
                array(
                    'goal' => $recap_is_deferred ? 'learn' : 'find_provider',
                    'stage' => $recap_is_deferred ? 'educating' : (!empty($safe_prior['recent_providers']) ? 'reviewing_results' : 'search_ready'),
                    'readiness' => $recap_is_deferred ? 'not_ready' : 'ready',
                    'open_question' => 'none',
                    'next_best_action' => $recap_is_deferred ? 'educate' : (!empty($safe_prior['recent_providers']) ? 'show_profile' : 'search'),
                )
            ));
            $assistant_reply = $this->build_search_preference_recap_response($safe_prior, $language);
            $understanding = array(
                'intent' => 'provider_followup',
                'next_action' => 'provider_detail',
                'clean_text' => $message,
                'confidence' => 1,
                'needs_clarification' => false,
                'source' => 'deterministic_preference_recap',
                'entities' => array('language' => $language),
                'navigation_state' => $updated_context['navigation_state'],
            );
            $recap_result = array('intent' => MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH, 'criteria' => array(), 'providers' => array());
            return array(
                'assistant_reply' => $assistant_reply,
                'response_type' => 'deterministic_context_recap',
                'intent' => 'provider_followup',
                'criteria' => array(),
                'prior_context' => $updated_context,
                'detected_language' => $language,
                'providers' => array(),
                'provider_exposure' => array(),
                'near_me_detected' => false,
                'conversational_speech' => '',
                'navigation_state' => $updated_context['navigation_state'],
                'natural_response' => $this->build_realtime_natural_response_contract($assistant_reply, $recap_result, 'deterministic_context_recap', array(), $updated_context['navigation_state']),
                'understanding' => $understanding,
            );
        }

        $understanding = $is_platform_service_overview ? array(
            'intent' => MyHH_Mia_Intent_Engine::INTENT_GENERAL,
            'next_action' => 'platform_info',
            'action' => 'platform_info',
            'clean_text' => $message,
            'confidence' => 1.0,
            'search_entities' => array(),
            'preserve_context' => array(),
            'clear_context' => array(),
            'target_provider_rank' => 0,
            'target_provider_id' => 0,
            'target_provider_name' => '',
            'needs_clarification' => false,
            'clarification_question' => '',
            'source' => 'deterministic_platform_service_overview',
        ) : $this->build_provider_workflow_continuation_understanding($message, $safe_prior);
        if ($is_patient_concern) {
            $understanding = $this->build_patient_concern_understanding($message, $safe_prior);
        }
        if (!empty($safe_prior['_conversation_search'])) {
            $search = array_intersect_key($safe_prior, array_flip(array('primary_category_name', 'specialty', 'city', 'modality', 'insurance', 'session_type', 'language_preference')));
            $understanding = array('raw_text' => $message, 'clean_text' => $message, 'intent' => MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH, 'next_action' => 'provider_search', 'action' => 'provider_search', 'search_entities' => $search, 'entities' => array('language' => $safe_prior['language'] ?? 'en'), 'confidence' => 1, 'source' => 'deterministic_conversation_state');
            $suppress_provider_action = false; $is_basic_education = false; $route_hint = 'provider_search';
        }

        if (empty($understanding)) {
            $understanding = $this->sanitize_realtime_action_contract($realtime_action, $message, $route_hint, $safe_prior);
        }
        if (empty($understanding)) {
            $understanding = $this->understand_turn_with_grok($message, $route_hint, $safe_prior, $transcript_candidates);
        }
        $understanding = $this->apply_openai_decision($message, $understanding, $safe_prior, $route_hint);
        $understanding = $this->normalize_conversation_language_understanding($message, $understanding);
        $engine_message = $this->get_grok_understood_text($message, $understanding);
        $engine_message = $this->build_realtime_action_engine_message($engine_message, $understanding);
        if ($is_platform_service_overview) {
            $engine_message = ($safe_prior['language'] ?? 'en') === 'es'
                ? 'Que servicios cubres?'
                : 'What services do you cover?';
        }
        $grok_intent = $understanding['intent'] ?? null;

        $forced_followup_provider = $this->resolve_recent_provider_name_followup($engine_message, $safe_prior)
            ?: $this->resolve_recent_provider_name_followup($message, $safe_prior);
        if ($forced_followup_provider) {
            $providers = $this->sanitize_provider_result_names(
                $this->get_provider_reference_pool($safe_prior['recent_providers'], $safe_prior),
                $safe_prior['language'] ?? 'en'
            );
            $understanding = $this->merge_forced_provider_followup_understanding($understanding, $message, $engine_message, $forced_followup_provider);
            mia_debug_log('ProviderFollowup', 'recent_provider_name_forced', array(
                'message' => substr($engine_message, 0, 80),
                'provider' => $forced_followup_provider['name'] ?? '',
                'provider_id' => absint($forced_followup_provider['id'] ?? 0),
            ));
            $result = array(
                'intent'           => MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH,
                'criteria'         => array(),
                'providers'        => $providers,
                'response'         => null,
                'fallback_used'    => false,
                'broadened'        => null,
                'near_me_detected' => false,
                'detected_language' => sanitize_key($understanding['entities']['language'] ?? ($safe_prior['language'] ?? 'en')),
                '_route_hint_override' => 'provider_followup',
                '_forced_recent_provider_followup' => true,
            );
        } elseif ($this->grok_understanding_is_provider_followup($understanding) || $this->looks_like_provider_set_fact_followup($message, $safe_prior)) {
            // Build a minimal result that mimics the route_hint override.
            // The followup resolution code (get_deterministic_followup_response)
            // handles the actual response generation using the providers
            // the user actually saw.
            $providers = !empty($safe_prior['recent_providers'])
                ? $this->sanitize_provider_result_names(
                    $safe_prior['recent_providers'],
                    $safe_prior['language'] ?? 'en'
                )
                : array();
            $result = array(
                'intent'           => MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH,
                'criteria'         => array(),
                'providers'        => $providers,
                'response'         => null,
                'fallback_used'    => false,
                'broadened'        => null,
                'near_me_detected' => false,
                'detected_language' => sanitize_key($understanding['entities']['language'] ?? ($safe_prior['language'] ?? 'en')),
            );
            $result['_route_hint_override'] = 'provider_followup';
        } elseif (in_array($understanding['next_action'] ?? '', array('general', 'clarify', 'platform_info'), true) || $is_basic_education || $suppress_provider_action) {
            $result = array('intent' => MyHH_Mia_Intent_Engine::INTENT_GENERAL, 'criteria' => array(), 'providers' => array(), 'response' => null, 'detected_language' => $safe_prior['language'] ?? 'en', '_suppress_provider_followup' => true);
        } else {
            // Fall through to the PHP engine for grounded action execution.
            // Grok only normalizes/chooses the likely action; the engine and
            // database remain the source of truth.
            if (!$is_basic_education && !$suppress_provider_action && $route_hint !== 'provider_followup') {
                $location_question = $this->build_realtime_search_location_question($understanding, $safe_prior, $message);
                if (is_array($location_question)) {
                    return $this->finalize_conversation_answer($location_question, $message);
                }
            }
            $direct_grok_result = $this->build_grok_direct_provider_search_result($understanding, $safe_prior, $engine, $context, $engine_message);
            $result  = is_array($direct_grok_result)
                ? $direct_grok_result
                : $engine->process($engine_message, $context, $safe_prior);
        }
        if (in_array($understanding['next_action'] ?? '', array('general', 'clarify', 'platform_info'), true) && ($result['intent'] ?? '') !== MyHH_Mia_Intent_Engine::INTENT_CRISIS) {
            $result = array('intent' => MyHH_Mia_Intent_Engine::INTENT_GENERAL, 'criteria' => array(), 'providers' => array(), 'response' => null, 'detected_language' => $safe_prior['language'] ?? 'en', '_suppress_provider_followup' => true);
        }
        $result['_grok_understanding'] = $understanding;

        $understood_intent = $this->intent_from_grok_next_action($understanding['next_action'] ?? $understanding['action'] ?? '');
        $claim_turn_is_explicit = (bool) preg_match('/\b(?:claim|claiming|reclaim|take\s+over)\b|\bexisting\s+(?:profile|listing)\b/iu', $engine_message);
        $claim_turn_is_active = $this->has_claim_selection_context($safe_prior);
        if ($understood_intent === MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM &&
            ($claim_turn_is_explicit || $claim_turn_is_active) &&
            !in_array($result['intent'] ?? '', array(MyHH_Mia_Intent_Engine::INTENT_CRISIS, MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM, MyHH_Mia_Intent_Engine::INTENT_PROVIDER_OWNER), true)) {
            $result['intent'] = MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM;
            $result['criteria'] = is_array($result['criteria'] ?? null) ? $result['criteria'] : array();
            $result['providers'] = array();
            $result['response'] = '';
            $result['fallback_used'] = false;
        }

        $result  = $this->force_claim_selection_result_if_needed($engine_message, $safe_prior, $result, 'realtime_tool');
        $effective_route_hint = $is_basic_education
            ? ''
            : ($route_hint ?: ($this->grok_understanding_is_provider_search($understanding) ? 'provider_search' : ''));
        $result  = $this->force_provider_search_continuation_result_if_needed($engine_message, $effective_route_hint, $safe_prior, $result, $engine, $context);
        $result  = $this->apply_grok_action_contract_to_result($result, $understanding, $safe_prior, $engine, $context);
        $result  = $this->broaden_empty_specialty_search_result($result, $engine, $context);
        if ($this->grok_understanding_is_provider_search($understanding) && $route_hint !== 'provider_followup') {
            // A search preference such as "accepts Aetna" belongs to the
            // result set, not to one arbitrarily focused provider.
            $result['_suppress_provider_followup'] = true;
        }
        if (($is_basic_education || $suppress_provider_action || $this->looks_like_consultative_exploration_turn($message))
            && ($result['intent'] ?? '') !== MyHH_Mia_Intent_Engine::INTENT_CRISIS) {
            $result['intent'] = MyHH_Mia_Intent_Engine::INTENT_GENERAL;
            $result['providers'] = array();
            $result['criteria'] = $this->has_explicit_search_defer_signal($message)
                ? $this->build_deferred_search_criteria($message)
                : array();
            $result['response'] = null;
            $result['_suppress_provider_followup'] = true;
            $result['_contextual_education'] = $is_basic_education;
            $understanding['intent'] = MyHH_Mia_Intent_Engine::INTENT_GENERAL;
            $understanding['next_action'] = 'general';
            $understanding['action'] = 'general';
            if ($pre_search_clarification) {
                $language = ($safe_prior['language'] ?? 'en') === 'es' ? 'es' : 'en';
                $understanding['needs_clarification'] = true;
                $understanding['confidence'] = 0.5;
                $understanding['clarification_question'] = $this->build_pre_search_clarification_question($safe_prior, $language);
            }
            $result['_grok_understanding'] = $understanding;
        }

        // ── Route hint override: provider_followup ──
        // When the frontend has already classified a turn as provider_followup
        // (e.g. "tell me more about" detected by hasHeroProviderFollowupSignal),
        // but the intent engine classifies it as consumer_provider_search because
        // the garbled Realtime transcript contains search signals like "need" +
        // "provider" (debug log 1777539583535 turn 3: "I need me provider. Tell
        // me more" → fresh search instead of followup about the first provider),
        // override the intent so the followup resolution code runs instead of
        // a fresh search with rotation.
        //
        // IMPORTANT: Always set _route_hint_override when route_hint is
        // provider_followup, even when safe_prior has no recent_providers.
        // A prior deterministic_followup response (e.g. contact details)
        // can clear recent_providers (because it returned providers:0),
        // making this check fail on the very next turn (debug log
        // 1777548040629 turn 4 barge-in: first lookup returned providers:0,
        // so safe_prior.recent_providers was empty, the override didn't
        // trigger, and "tell me more about insurance location" fell through
        // to a fresh search listing instead of a followup).
        if ($route_hint === 'provider_followup'
            && empty($result['_suppress_provider_followup'])
            && in_array($result['intent'], array(
                MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH,
                MyHH_Mia_Intent_Engine::INTENT_GENERAL,
            ), true)) {
            $result['_route_hint_override'] = 'provider_followup';
            // Replace the fresh search providers with the ones the user
            // actually saw, so resolve_referenced_provider works against
            // the correct list — but only when we have them.
            if (!empty($safe_prior['recent_providers'])) {
                $result['providers'] = $this->sanitize_provider_result_names(
                    $safe_prior['recent_providers'],
                    $result['detected_language'] ?? ($safe_prior['language'] ?? 'en')
                );
            }
        }

        if (($result['intent'] ?? '') === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH && ($result['_route_hint_override'] ?? '') !== 'provider_followup') {
            $service = ($this->infer_fast_provider_search_entities_from_turn($message)['specialty'] ?? '') ?: ($result['criteria']['requested_specialty'] ?? ($result['criteria']['specialty'] ?? ''));
            if (in_array($service, array('Physical Therapy', 'Massage Therapy'), true)) {
                $criteria = $result['criteria'] ?? array();
                $criteria['specialty'] = $service; unset($criteria['requested_specialty']);
                $exact = $criteria; unset($exact['primary_category'], $exact['primary_category_name']);
                $candidates = $engine->get_ranked_providers_from_context($exact, $context);
                $pattern = $service === 'Physical Therapy' ? '/\b(?:physical\s+therap|physiotherap|fisioterap)/iu' : '/\b(?:massage|masaj)/iu';
                $result['providers'] = array_values(array_filter($candidates, static function($p) use ($pattern) {
                    return (bool) preg_match($pattern, implode(' ', array_merge((array) ($p['specialties'] ?? array()), array($p['name'] ?? '', $p['license_type'] ?? ''))));
                }));
                $result['criteria'] = $criteria; $result['response'] = null; $result['broadened'] = null;
            }
        }

        $result['providers'] = $this->sanitize_provider_result_names($result['providers'] ?? array(), $result['detected_language'] ?? ($safe_prior['language'] ?? 'en'));
        $result['provider_exposure'] = $this->build_provider_exposure_payload(
            $engine->get_last_provider_exposure(),
            $result['providers'] ?? array(),
            $result['criteria'] ?? array(),
            $result['intent'] ?? '',
            'grounded'
        );
        $negative_search_refinement = $this->looks_like_negative_search_refinement($engine_message, $safe_prior);
        $updated_context = $this->build_updated_context($safe_prior, $result, $message, $negative_search_refinement);

        $assistant_reply = '';
        $response_type = 'grounded';
        $this->_conversational_speech = ''; // reset per-request
        $provider_owner_fragment = $this->looks_like_provider_owner_fragment($engine_message);
        $vague_search_fragment = $this->looks_like_vague_search_fragment($engine_message);

        $deterministic = array(
            MyHH_Mia_Intent_Engine::INTENT_CRISIS,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_ADD,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_OWNER,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_UPGRADE,
            MyHH_Mia_Intent_Engine::INTENT_SPONSOR,
        );

        if ($this->should_answer_with_grok_clarification($understanding, $result)) {
            $assistant_reply = $this->build_grok_clarification_reply($understanding);
            $response_type = 'clarify';
        } elseif (in_array($result['intent'], $deterministic, true)) {
            $assistant_reply = $result['response'];
            if ($result['intent'] === MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM) {
                $claim_guidance = $this->build_provider_claim_guidance_response($message, $updated_context, $result, $safe_prior);
                if ($claim_guidance) {
                    $assistant_reply = $claim_guidance;
                }
                // Persist claim disambiguation providers to context for next-turn narrowing
                if (!empty($this->_claim_disambiguation_providers)) {
                    $updated_context['claim_disambiguation_providers'] = $this->sanitize_provider_memory($this->_claim_disambiguation_providers);
                    if (count($updated_context['claim_disambiguation_providers']) === 1) {
                        $updated_context['last_provider_focus'] = absint($updated_context['claim_disambiguation_providers'][0]['id'] ?? 0);
                    }
                    $this->_claim_disambiguation_providers = array(); // reset
                }
                // Persist name suggestions to context for next-turn selection
                if (!empty($this->_claim_name_suggestions)) {
                    $updated_context['claim_name_suggestions'] = $this->sanitize_provider_memory($this->_claim_name_suggestions);
                    $this->_claim_name_suggestions = array(); // reset
                }
            }
            $response_type = 'deterministic';
        } elseif ($result['intent'] === MyHH_Mia_Intent_Engine::INTENT_GENERAL
            && empty($result['_route_hint_override'])) {
            // Skip the general handler when route_hint says provider_followup —
            // let get_deterministic_followup_response handle it instead.
            // (debug log 1777548040629: "tell me more about the first one"
            // classified as general_unknown but should be a followup.)
            $general_reply = $this->get_deterministic_general_response(
                $message,
                $context,
                $updated_context,
                $safe_prior
            );
            if (!$general_reply && $engine_message !== $message) {
                $general_reply = $this->get_deterministic_general_response($engine_message, $context, $updated_context, $safe_prior);
            }
            if ($general_reply) {
                $assistant_reply = $general_reply;
                $response_type = 'deterministic_general';
            }
        }

        $followup_result = $result;
        // Do not backfill stale provider cards for deterministic provider-side intents
        // (claim, add, upgrade, sponsor, crisis). These return guided text responses
        // and must never carry a previous search's provider list — doing so causes the
        // frontend to speak provider-first text and append cards instead of the guidance.
        $provider_owner_context = !empty($updated_context['provider_owner_mode']);
        $search_needs_clarification = !empty($result['criteria']['needs_clarification']);
        $can_backfill_providers = $this->should_backfill_search_providers(
            $engine_message,
            $followup_result,
            $updated_context,
            $negative_search_refinement
        ) && !$provider_owner_fragment && !$provider_owner_context && !$vague_search_fragment && !$search_needs_clarification;

        // When route_hint_override is active (provider_followup), never
        // generate a fresh rotated search set — the user is interacting
        // with previously shown providers, and a new search rotation
        // would break ordinal/deictic references on the next turn.
        // (Audit 2026-04-30: provider context can rotate during followup.)
        $is_followup_override = !empty($result['_route_hint_override']) && $result['_route_hint_override'] === 'provider_followup';
        if ($is_followup_override) {
            $can_backfill_providers = false;
        }
        $followup_message = $is_followup_override ? $message : $engine_message;

        if ($can_backfill_providers && empty($followup_result['providers']) && !empty($updated_context['recent_providers'])) {
            $followup_result['providers'] = $updated_context['recent_providers'];
        }
        if ($can_backfill_providers && empty($followup_result['providers']) && $followup_result['intent'] === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH && (!empty($updated_context['specialty']) || !empty($updated_context['city']) || !empty($updated_context['primary_category']))) {
            $followup_result['providers'] = $engine->get_ranked_providers_from_context($updated_context, $context);
        }

        if ($assistant_reply === '' && empty($result['_suppress_provider_followup']) && $result['intent'] !== MyHH_Mia_Intent_Engine::INTENT_GENERAL) {
            // A Realtime action can normalize a contextual question into a
            // generic search sentence. Once the frontend has positively
            // identified a provider follow-up, resolve ordinals, pronouns and
            // "what you recommended earlier" from the user's actual words.
            $deterministic_followup = $this->get_deterministic_followup_response($followup_message, $followup_result, $updated_context, $safe_prior);
            if ($deterministic_followup) {
                $assistant_reply = $deterministic_followup;
                $response_type = 'deterministic_followup';
                if ($negative_search_refinement) {
                    $followup_result['providers'] = array();
                } elseif (!empty($safe_prior['recent_providers'])) {
                    $followup_result['providers'] = array_values($safe_prior['recent_providers']);
                    $updated_context['recent_providers'] = array_values($safe_prior['recent_providers']);
                    $updated_context['last_provider_focus'] = $this->get_answered_provider_focus($deterministic_followup, $safe_prior);
                }
            }
        }

        if ($assistant_reply === '' && $result['intent'] === MyHH_Mia_Intent_Engine::INTENT_GENERAL && $this->uses_openai()) {
            $prompt = $this->build_enhanced_system_prompt($context, $result, count(is_array($history) ? $history : array()), $safe_prior, is_array($history) ? $history : array());
            $prompt .= "\nAnswer the actual question concisely. General education is not a directory fact or provider search. Do not invent provider facts or claim website actions were executed. Do not diagnose or prescribe.\n";
            $messages = array(array('role' => 'system', 'content' => $prompt));
            foreach (array_slice(is_array($history) ? $history : array(), -10) as $item) {
                if (in_array($item['role'] ?? '', array('user', 'assistant'), true) && is_string($item['content'] ?? null)) {
                    $messages[] = array('role' => $item['role'], 'content' => mb_substr(wp_strip_all_tags($item['content']), 0, 1500));
                }
            }
            $messages[] = array('role' => 'user', 'content' => $message);
            $response = $this->call_grok_api($messages); // Selected-provider transport routes this to Responses.
            if (!is_wp_error($response)) {
                $answer = json_decode(wp_remote_retrieve_body($response), true);
                $assistant_reply = trim((string) ($answer['choices'][0]['message']['content'] ?? ''));
                if ($assistant_reply !== '') {
                    if ($is_basic_education) $assistant_reply = $this->enforce_education_inventory_boundary($assistant_reply, $updated_context['language'] ?? 'en');
                    $response_type = 'openai_general';
                }
            }
        }

        if ($assistant_reply === '') {
            if ($result['intent'] === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH) {
                if (!empty($followup_result['providers'])) {
                    $assistant_reply = $this->format_providers_fallback($followup_result, $engine_message);
                    $detected_lang = $result['detected_language'] ?? ($updated_context['language'] ?? 'en');
                    $this->_conversational_speech = $this->build_search_results_conversational_speech(
                        $followup_result['providers'], $followup_result['criteria'] ?? array(), $detected_lang
                    );
                } else {
                    // Fallback: use intent engine response or smart followup
                    $assistant_reply = $result['response'] ?: $this->build_grounded_no_results_reply(
                        $followup_result['criteria'] ?? ($result['criteria'] ?? array()),
                        $result['detected_language'] ?? ($updated_context['language'] ?? 'en')
                    );
                    if (!$assistant_reply) {
                        $assistant_reply = $this->smart_followup_fallback($engine_message, $history, $updated_context, $result);
                    }
                }
                $response_type = !empty($followup_result['providers']) ? 'search_grounded' : 'search_no_results';
            } else {
                $assistant_reply = $this->smart_followup_fallback($engine_message, $history, $updated_context, $result);
                if (!$assistant_reply) {
                    $assistant_reply = $result['response'] ?: $this->get_general_fallback();
                }
                $response_type = 'general_fallback';
            }
        }

        // A grounded tool result must always contain a safe sentence. An empty
        // zero-result reply gives the Realtime model no factual fallback and can
        // otherwise produce silence or tempt it to invent a provider.
        if (trim((string) $assistant_reply) === '' && $result['intent'] === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH) {
            $criteria = is_array($followup_result['criteria'] ?? null) ? $followup_result['criteria'] : array();
            $assistant_reply = $this->build_grounded_no_results_reply(
                $criteria,
                $result['detected_language'] ?? ($updated_context['language'] ?? 'en')
            );
            $response_type = 'search_no_results';
        }

        $assistant_reply = $this->enforce_grounded_provider_reply($assistant_reply, $followup_result, $engine_message, $result['intent'], $response_type);
        $assistant_reply = $this->cleanup_brand_repetition($assistant_reply, $engine_message, $result['intent']);
        if (($result['intent'] ?? '') === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH && preg_match('/\band\s+(?:explain|tell\s+me)\b[\s\S]{0,45}\b(?:next|after|happens)\b/iu', $message)) {
            $assistant_reply .= "\n\nOpen a relevant profile to review its listed services and contact or booking links. Confirm availability and any insurance details directly with the practice; Mia has not booked an appointment.";
        }
        $response_providers = ($result['intent'] ?? '') === MyHH_Mia_Intent_Engine::INTENT_GENERAL
            ? array()
            : array_values($followup_result['providers'] ?? array());

        return $this->finalize_conversation_answer(array(
            'assistant_reply' => $assistant_reply,
            'response_type'   => $response_type,
            'intent'          => $result['intent'],
            'criteria'        => $result['criteria'] ?? array(),
            'prior_context'   => $updated_context,
            'detected_language' => $result['detected_language'] ?? ($updated_context['language'] ?? 'en'),
            'providers'       => $response_providers,
            'provider_exposure' => $this->build_provider_exposure_payload(
                $result['provider_exposure'] ?? array(),
                $followup_result['providers'] ?? array(),
                $result['criteria'] ?? array(),
                $result['intent'] ?? '',
                $response_type
            ),
            'near_me_detected' => !empty($result['near_me_detected']),
            'conversational_speech' => $this->_conversational_speech,
            'navigation_state' => $updated_context['navigation_state'] ?? array(),
            'natural_response' => $this->build_realtime_natural_response_contract($assistant_reply, $result, $response_type, $response_providers, $updated_context['navigation_state'] ?? array()),
            'understanding'    => $understanding,
        ), $message);
    }
    
    /**
     * Get directory context for Mia
     */
    private function get_directory_context() {
        $cached_context = get_transient('mia_directory_context_v2');
        if (is_array($cached_context) && isset($cached_context['categories'], $cached_context['total_providers'])) {
            return $cached_context;
        }
        $context = array();
        
        // Get all primary categories with counts
        $categories = get_terms(array(
            'taxonomy' => 'myhh_primary_category',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ));
        
        $category_counts = array();
        if ($categories && !is_wp_error($categories)) {
            foreach ($categories as $cat) {
                $category_counts[$cat->slug] = intval($cat->count);
            }
        }
        $category_data = array();
        foreach ($this->get_myhealthhub_category_knowledge_catalog() as $slug => $item) {
            $count = intval($category_counts[$slug] ?? 0);
            $category_data[] = array(
                'name' => $item['name'],
                'slug' => $slug,
                'count' => $count,
                'has_providers' => $count > 0,
            );
        }
        $context['categories'] = $category_data;
        
        // Get total provider count
        $context['total_providers'] = wp_count_posts('gd_place')->publish;
        
        // Get cities/locations
        $cities = get_terms(array(
            'taxonomy' => 'myhh_city',
            'hide_empty' => true,
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => 10,
        ));
        
        $city_data = array();
        if ($cities && !is_wp_error($cities)) {
            foreach ($cities as $city) {
                $city_data[] = $city->name;
            }
        }
        $context['locations'] = $city_data;
        
        // Get top specialties
        $specialties = get_terms(array(
            'taxonomy' => 'myhh_specialty',
            'hide_empty' => true,
            'parent' => 0,
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => 15,
        ));
        
        $specialty_data = array();
        if ($specialties && !is_wp_error($specialties)) {
            foreach ($specialties as $spec) {
                $specialty_data[] = array(
                    'name' => $spec->name,
                    'count' => $spec->count,
                );
            }
        }
        $context['top_specialties'] = $specialty_data;
        
        // Get license types
        $licenses = get_terms(array(
            'taxonomy' => 'myhh_license_type',
            'hide_empty' => true,
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => 10,
        ));
        
        $license_data = array();
        if ($licenses && !is_wp_error($licenses)) {
            foreach ($licenses as $lic) {
                $license_data[] = $lic->name;
            }
        }
        $context['license_types'] = $license_data;
        // Cache public aggregate metadata briefly, never personal conversation data.
        set_transient('mia_directory_context_v2', $context, 30);
        return $context;
    }
    
    // Provider search, ranking, city/specialty helpers, crisis detection,
    // and fallback responses have been moved to MyHH_Mia_Intent_Engine.
    
    /**
     * Build the base system prompt with directory data.
     *
     * Composed from three modular sections so each concern is isolated:
     *   1. Identity — personality and voice
     *   2. Boundaries — scope and safety rails
     *   3. Context — live directory data, response rules, and links
     */
    private function build_base_system_prompt($context) {
        $prompt  = $this->build_identity_section();
        $prompt .= $this->build_boundaries_section();
        $prompt .= $this->build_context_section($context);
        return $prompt;
    }

    /**
     * Identity section: who Mia is, her personality, and vocal style.
     */
    private function build_identity_section() {
        return <<<SECTION
You are Mia, a warm and caring wellness concierge. You speak like a trusted friend who happens to know everything about finding the right care provider.

## Who You Are

You're a knowledgeable guide who genuinely cares about helping people find the support they need. Think of yourself as the concierge at a premium wellness center: warm but efficient, personal but professional.

**Your personality:**
- Warm and approachable — never clinical or transactional
- Proactively helpful — anticipate what someone might need next
- Genuinely empathetic — acknowledge emotions before solving problems
- Honest and transparent — if something isn't available, say so kindly

**How you sound:**
- Sound like a premium concierge: calm, clear, warm, and lightly confident
- Keep your phrasing natural and modern, not scripted, chirpy, or overly formal
- Use short, well-shaped sentences instead of long paragraphs when speaking live
- Vary your openings so you don't keep sounding repetitive
- Frame suggestions warmly: "Based on what you've shared, I'd start with..." rather than "Here are some providers."
- Avoid filler like "absolutely," "certainly," or "I'd be happy to" unless it adds real warmth
- Do not over-explain the platform when the user really wants guidance

SECTION;
    }

    /**
     * Boundaries section: what Mia does and doesn't do, safety rails.
     */
    private function build_boundaries_section() {
        return <<<SECTION
## What You Do (And Don't Do)

**You ARE a wellness guide:** Your core jobs are:
- Help users search for and find the right providers
- Help providers claim or add their listings
- Give clear, detailed education about the wellness categories, services, modalities, provider types, credentials, trust signals, and business flows represented by MyHealthHub

Stay centered on those three jobs. Do NOT expand beyond them, even if the user's question seems loosely related.

**Domain knowledge rule:** Within MyHealthHub's service scope, do not behave like a keyword FAQ. Use your established wellness knowledge to explain recognized categories and services naturally, including reasonable follow-up questions, comparisons, common purposes, typical provider types, and credential distinctions. This educational freedom does not authorize personalized advice, diagnosis, treatment instructions, efficacy claims, or invented facts about a specific provider.

**You are NOT a clinician:** You don't diagnose, prescribe, or provide medical/therapeutic advice. You guide people TO professionals who can help.

**Boundaries (non-negotiable):**
- Never diagnose, prescribe, or act like a clinician
- Never ask for or store personal health information (PHI)
- Never invent providers or listings that don't exist
- Always use ACTUAL directory data when making recommendations
- If you don't know something, say so honestly

## Out-of-Scope Rejection Protocol

You are a directory concierge, not a general-purpose assistant. When a request falls outside your three core jobs, you MUST redirect — never attempt to answer it yourself.

**What is OUT of scope (always redirect):**
- Medical diagnosis or treatment advice ("What medication should I take?", "Do I have anxiety?", "How do I treat depression?")
- Therapeutic guidance ("What should I do about my panic attacks?", "Give me coping strategies")
- Dosage, medication, or supplement recommendations
- Crisis counseling or emergency intervention (instead: provide crisis hotline and direct to a professional)
- Legal, financial, or insurance advice beyond basic directory info
- General knowledge, trivia, jokes, riddles, or entertainment
- Personal opinions on politics, religion, or controversial topics
- Technical support for non-platform websites or apps
- Meal plans, exercise routines, or wellness coaching

**How to set the boundary:**
1. Briefly acknowledge the request
2. State your limitation in one sentence
3. Mention the relevant help you can provide; ask to search only when the user appears ready

**Examples:**
- User: "What dose of sertraline should I take?" → "I can't advise on medication dosages; your prescribing clinician is the right person to answer that. I can explain which provider types handle medication management or help you find one when you're ready."
- User: "Tell me a joke" → "I'm focused on wellness navigation rather than entertainment."
- User: "What are some coping strategies for anxiety?" → "I'm not able to provide therapeutic guidance. I can explain the kinds of professionals who support anxiety or help you find one if you'd like."
- User: "Do I have ADHD?" → "I'm not qualified to diagnose conditions. A licensed professional can provide a proper evaluation, and I can explain those provider types if useful."

**CRITICAL: Do NOT hedge.** Do not say "I can't diagnose, but generally..." or "While I'm not a doctor, some people find..." Any medical guidance after the boundary is a scope violation. State the boundary clearly, then offer only in-scope navigation help without forcing a search.

SECTION;
    }

    /**
     * Context section: live directory data, response guidelines, and reference links.
     */
    private function build_context_section($context) {
        $categories_list = '';
        foreach ($context['categories'] as $cat) {
            $status = $cat['has_providers'] ? "({$cat['count']} providers)" : "(Coming Soon)";
            $categories_list .= "- {$cat['name']} {$status}\n";
        }

        $specialties_list = '';
        foreach ($context['top_specialties'] as $spec) {
            $specialties_list .= "- {$spec['name']} ({$spec['count']} providers)\n";
        }

        $locations_list = implode(', ', $context['locations']);

        $license_types_list = '';
        if (!empty($context['license_types'])) {
            foreach ($context['license_types'] as $lic) {
                $license_types_list .= "- {$lic}\n";
            }
        }

        // Build dynamic category descriptions from DB term descriptions
        // Falls back to hardcoded descriptions when DB description is empty
        $category_descriptions = $this->build_category_knowledge($context['categories']);
        $specialty_descriptions = $this->build_specialty_knowledge($context['top_specialties']);
        $license_descriptions = $this->build_license_knowledge($context['license_types'] ?? array());

        return <<<SECTION
## Current Directory Data (USE THIS — DO NOT MAKE UP DATA)

### Provider Count
Total published provider listings (not a count of verified licenses): {$context['total_providers']}

### Primary Categories (12 total)
{$categories_list}

### Top Specialties
{$specialties_list}

### License Types in Directory
{$license_types_list}

### Service Areas
{$locations_list}

## Category & Specialty Knowledge (What MyHealthHub Covers)

Use this reference whenever a user asks what a represented category, service, modality, provider role, or credential means, covers, commonly supports, or how it differs from another option. You may apply established domain knowledge to natural follow-ups, but never infer live listing facts from this educational reference:

### Category Descriptions
{$category_descriptions}

### Specialty Descriptions (most common)
{$specialty_descriptions}

### License Type Descriptions
{$license_descriptions}

## Important Context
- Most providers are currently in "Mental Health & Therapy"
- Other categories may have few or zero providers — be honest about this
- Basic listings are FREE for providers
- Users can browse listings without signup

## How to Respond

**Be a guide, not a search engine:**
- When someone shares a meaningful concern, briefly reflect the main themes once; do not parrot their words or act like a therapist
- Explain relevant service or provider-type options neutrally before searching unless the user is already asking for providers
- If a request is vague, ask at most ONE clarifying question, and only when the answer will materially improve the guidance
- Search only when the user asks for options or indicates they are ready

**Presenting providers:** Always frame with a recommendation:
- "Based on what you've shared, I'd suggest starting with [Provider Name] — they specialize in [X]."
- Highlight the most relevant 2-3 first; don't just list names
- Format as clickable links: [Provider Name](profile-url)

**Use a contextual next step, not a mandatory call to action:**
- After listing providers, offer one useful refinement only when it fits the user's stated goal
- After education or a listening turn, finish naturally unless the user appears ready to search
- Never turn uncertainty, emotion, or a personal concern into a sales pitch
- If information is unknown or not verified, say so plainly rather than filling the gap

**Response length:** Keep responses conversational. Speak as long as needed to fully answer the question. Use bullet points for multiple options, but wrap them in context.

**Cadence and polish:**
- Lead with the answer, then give the reason
- In voice, one crisp recommendation beats a long explanation
- Sound reassuring without sounding salesy
- Prefer plain, elegant language over generic support-bot phrases

**Conversation continuity:**
- Reference what the user said earlier: "Since you mentioned wanting CBT..."
- Avoid generic transitions — be a conversational partner, not a question-answer machine
- Do not keep repeating the site or brand name unless the user is explicitly asking about the platform itself

## Conversation Greeting Rule
- ONLY introduce yourself ("Hi, I'm Mia...") on the very FIRST message of a conversation
- After the first message, respond naturally as if mid-conversation — never re-introduce yourself
- Do NOT say "Hi!" or "Hello!" again after the conversation has started

## Links to Reference
- Listings: /listings/
- Add Practice: /add-your-practice/
- Get Matched: /get-matched/
- Categories: /wellness-category/
- Contact: /contact/
SECTION;
    }

    /**
     * Build a conversation summary for longer conversations (10+ messages).
     * This helps prevent context drift by reminding Grok of the overall context.
     *
     * @param array $history        Full conversation history.
     * @param array $prior_context  Prior context (city, specialty, category).
     * @return string Summary text for the system prompt.
     */
    private function build_conversation_summary($history, $prior_context) {
        $summary_parts = array();

        // Extract key topics from conversation history
        $topics = array();
        $user_messages = array_filter($history, function($msg) {
            return isset($msg['role']) && $msg['role'] === 'user';
        });

        // Look for key terms in user messages
        $key_terms = array(
            'anxiety' => 'anxiety support',
            'depression' => 'depression support',
            'therapy' => 'therapy',
            'counseling' => 'counseling',
            'CBT' => 'CBT',
            'medication' => 'medication management',
            'psychiatrist' => 'psychiatry',
            'nutrition' => 'nutrition',
            'fitness' => 'fitness',
            'massage' => 'massage therapy',
            'acupuncture' => 'acupuncture',
            'price' => 'pricing questions',
            'cost' => 'cost questions',
            'insurance' => 'insurance questions',
            'appointment' => 'booking appointments',
            'availability' => 'provider availability',
            'spanish' => 'Spanish language preference',
            'español' => 'Spanish language preference',
        );

        foreach ($user_messages as $msg) {
            $content = strtolower($msg['content'] ?? '');
            foreach ($key_terms as $term => $label) {
                if (stripos($content, $term) !== false && !in_array($label, $topics, true)) {
                    $topics[] = $label;
                }
            }
        }

        // Build summary
        if (!empty($prior_context['primary_category_name'])) {
            $summary_parts[] = "User is looking for {$prior_context['primary_category_name']}";
        } elseif (!empty($prior_context['specialty'])) {
            $summary_parts[] = "User is interested in {$prior_context['specialty']}";
        }

        if (!empty($prior_context['city'])) {
            $summary_parts[] = "located in {$prior_context['city']}";
        }

        if (!empty($topics)) {
            $summary_parts[] = "Topics discussed: " . implode(', ', array_slice($topics, 0, 5));
        }

        $navigation = $this->sanitize_navigation_state($prior_context['navigation_state'] ?? array());
        if (!empty($navigation['goal'])) {
            $summary_parts[] = "Current navigation goal: {$navigation['goal']}";
        }
        if (!empty($navigation['stage'])) {
            $summary_parts[] = "Conversation stage: {$navigation['stage']}";
        }
        if (!empty($navigation['readiness'])) {
            $summary_parts[] = "Search readiness: {$navigation['readiness']}";
        }

        if (empty($summary_parts)) {
            return '';
        }

        return "## CONVERSATION SUMMARY\n" . implode('. ', $summary_parts) . ".";
    }

    /**
     * Build an enhanced system prompt that includes real provider search results
     * from the intent engine so Grok's response is grounded in actual data.
     *
     * @param array $context        Directory context data.
     * @param array $engine_result  Intent engine result.
     * @param int   $history_count  Number of prior conversation turns (0 = first message).
     * @param array $prior_context  Prior context (city, specialty, category, language).
     * @param array $history        Full conversation history for summary generation.
     */
    private function build_enhanced_system_prompt($context, $engine_result, $history_count = 0, $prior_context = array(), $history = array()) {
        $base = $this->build_base_system_prompt($context);
        $memory_providers = $this->get_provider_memory_from_context($prior_context);

        // Add vulnerability handling instructions (always present)
        $base .= "\n\n## HANDLING VULNERABILITY\n";
        $base .= "When someone shares something personal or difficult (anxiety, low mood, relationship issues, stress, poor sleep, etc.):\n";
        $base .= "1. Briefly reflect what you understood in one natural sentence. Do not repeat every detail or use canned validation.\n";
        $base .= "2. Identify at most one genuinely useful missing detail without interrogating the user.\n";
        $base .= "3. Explain relevant support options neutrally before searching unless the user already asked for providers.\n";
        $base .= "4. Search only when the user says they are ready or asks for options.\n";
        $base .= "5. Explain why a provider may fit only from verified profile facts and the user's stated preferences. Clearly label unknown information.\n";
        $base .= "6. If the user only wants education or to be heard, answer that need and do not add a provider call to action.\n";
        $base .= "7. Keep the tone warm but not clinical. You are a wellness navigation consultant, not a counselor, clinician, or substitute for care.\n";

        // Build conversation summary for longer conversations
        $conversation_summary = '';
        if ($history_count >= 10 && !empty($history)) {
            $conversation_summary = $this->build_conversation_summary($history, $prior_context);
        }

        // Append conversation continuation block when this is NOT the first message
        if ($history_count > 0) {
            $base .= "\n\n## CONVERSATION CONTINUATION\n";
            $base .= "You are continuing an ongoing conversation with this user. DO NOT introduce yourself again.\n";
            $base .= "The user has already heard your introduction. Continue naturally from where you left off.\n";
            $base .= "Be concise and direct — skip pleasantries and introductory language.\n";
            $base .= "Reference previous context naturally: 'Since you mentioned wanting CBT...' or 'Going back to your interest in...'\n";
            $base .= "If the user asks a follow-up, answer it directly without re-establishing who you are.\n";
            $base .= "Use the supplied recent dialogue and structured session context. Never claim to remember a fact that is not present.\n";

            // Add conversation summary for longer conversations (prevents context drift)
            if (!empty($conversation_summary)) {
                $base .= "\n" . $conversation_summary . "\n";
            }

            // Add prior context reference if available (reduces "fresh start" feeling)
            if (!empty($prior_context)) {
                $context_parts = array();
                if (!empty($prior_context['primary_category_name'])) {
                    $context_parts[] = "looking for {$prior_context['primary_category_name']} providers";
                } elseif (!empty($prior_context['specialty'])) {
                    $context_parts[] = "interested in {$prior_context['specialty']}";
                }
                if (!empty($prior_context['city'])) {
                    $context_parts[] = "in {$prior_context['city']}";
                }
                if (!empty($prior_context['language']) && $prior_context['language'] !== 'en') {
                    $context_parts[] = "preferring {$prior_context['language']} language support";
                }

                if (!empty($context_parts)) {
                    $base .= "\n## PRIOR CONTEXT\n";
                    $base .= "The user has been " . implode(', ', $context_parts) . ". Pick up naturally where you left off.\n";
                }

                $navigation = $this->sanitize_navigation_state($prior_context['navigation_state'] ?? array());
                if (!empty($navigation)) {
                    $base .= "\n## CONVERSATION NAVIGATION STATE\n";
                    $base .= "These are privacy-safe navigation labels, not a diagnosis or a complete personal history: " . wp_json_encode($navigation) . "\n";
                    $base .= "Respect readiness. If it is not_ready, listen or educate without offering a provider CTA. If it is exploring, explain options before searching. If it is ready, advance the grounded workflow directly.\n";
                }

                if (!empty($memory_providers)) {
                    $base .= "\n## RECENTLY MENTIONED PROVIDERS\n";
                    $base .= "If the user says 'that one', 'the first one', 'the second one', or asks about what you just said, anchor to these providers before inventing a new list.\n";
                    foreach ($memory_providers as $i => $provider) {
                        $n = $i + 1;
                        $base .= "{$n}. {$provider['name']}";
                        if (!empty($provider['city'])) {
                            $base .= " — {$provider['city']}";
                        }
                        if (!empty($provider['license_type'])) {
                            $base .= " — {$provider['license_type']}";
                        }
                        if (!empty($provider['specialties'])) {
                            $base .= ' — ' . implode(', ', array_slice($provider['specialties'], 0, 2));
                        }
                        if (!empty($provider['url'])) {
                            $p_url = $this->sanitize_provider_url($provider['url']);
                            $base .= "\n   Profile: {$p_url}";
                        }
                        $base .= "\n";
                    }
                }
            }
        }

        // Inject matched provider data for consumer search
        if ($engine_result['intent'] === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH
            && !empty($engine_result['providers'])) {

            $criteria  = $engine_result['criteria'];
            $providers = $engine_result['providers'];

            $section = "\n\n## CURRENT SEARCH CONTEXT\n";
            $section .= 'The user appears to be searching for: ';
            $parts = array();
            if (!empty($criteria['primary_category_name'])) $parts[] = $criteria['primary_category_name'];
            if (!empty($criteria['specialty']))             $parts[] = $criteria['specialty'] . ' specialist';
            if (!empty($criteria['city']))                  $parts[] = 'in ' . $criteria['city'];
            $section .= (!empty($parts) ? implode(', ', $parts) : 'a provider') . "\n\n";

            $section .= "### REAL MATCHED PROVIDERS (present ONLY these — do NOT invent others):\n";
            foreach ($providers as $i => $p) {
                $n = $i + 1;
                $section .= "{$n}. {$p['name']}";
                if ($p['city'])         $section .= " — {$p['city']}";
                if ($p['license_type']) $section .= " — {$p['license_type']}";
                if (!empty($p['specialties'])) $section .= ' — ' . implode(', ', array_slice($p['specialties'], 0, 3));
                $provider_url = $this->sanitize_provider_url($p['url'] ?? '');
                $section .= "\n   Profile: {$provider_url}\n";
            }

            if ($engine_result['broadened']) {
                $section .= "\nNote: {$engine_result['broadened']}\n";
            }

            $section .= "\n**HOW TO PRESENT THESE PROVIDERS:**\n";
            $section .= "- Start by naming one or two actual provider matches early in the reply\n";
            $section .= "- Don't just list them — explain fit using only the user's stated preferences and verified profile facts\n";
            $section .= "- Example: 'For your stated preference for [X] in [city], [Provider Name] is the closest documented match.'\n";
            $section .= "- Speak as if you are recommending real people directly. NEVER say 'MyHealthHub', 'My Health Hub', 'on our platform', 'on our directory', or 'here on' in provider result answers.\n";
            $section .= "- Offer one next step only if it advances the user's stated goal; do not force a call to action\n";
            $section .= "- Say when insurance, availability, modality, language, or another comparison fact is not documented; never infer it\n";
            $section .= "- Format provider names as clickable links: [Provider Name](profile-url)\n";
            $section .= "- IMPORTANT: Present ONLY these real providers. Do NOT invent additional providers, booking links, or availability information.\n";
            $base .= $section;
        }

        // Sparse category notice
        if ($engine_result['intent'] === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH
            && empty($engine_result['providers'])
            && !empty($engine_result['criteria']['primary_category_name'])) {

            $cat_name = $engine_result['criteria']['primary_category_name'];
            $base .= "\n\n## SPARSE CATEGORY NOTICE\n";
            $base .= "The user is looking for providers in '{$cat_name}' but we have very few or no providers in this category yet.\n";
            $base .= "Be honest AND helpful: acknowledge the limitation warmly, then suggest alternatives.\n";
            $base .= "Example: 'I'd love to help you find {$cat_name} providers! We're actively expanding that category. In the meantime, you might find what you need in [related category], or check back soon as we're adding new providers weekly.'\n";
            $base .= "Do NOT invent providers in this category.\n";
        }

        // Near-me / no-city notice
        if ($engine_result['intent'] === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH
            && !empty($engine_result['criteria'])
            && empty($engine_result['criteria']['city'])
            && isset($engine_result['near_me_detected'])
            && $engine_result['near_me_detected']) {

            $base .= "\n\n## NEAR-ME NOTICE\n";
            $base .= "The user said 'near me' or 'nearby' but we don't have their location.\n";
            $base .= "Ask warmly which city or area they're in. Our service areas include: Raleigh, Durham, Chapel Hill, Cary, Apex, Morrisville, and surrounding Triangle-area cities.\n";
            $base .= "Example: 'I can't use your location right now, but I can continue this search if you tell me your city or area. I cover the Raleigh-Durham Triangle area.'\n";
            $base .= "Do NOT guess their location.\n";
        }

        // Language instruction for Grok
        if (!empty($engine_result['detected_language'])) {
            $lang = $engine_result['detected_language'];
            if ($lang === 'es') {
                $base .= "\n\n## LANGUAGE INSTRUCTION\n";
                $base .= "The user wrote in Spanish or mixed Spanish-English (Spanglish). RESPOND ENTIRELY IN SPANISH.\n";
                $base .= "Use warm, natural Spanish. Be conversational and helpful.\n";
                $base .= "Apply the same warmth and vulnerability-handling guidelines in Spanish.\n";
                $base .= "If the user uses English words mixed into Spanish (Spanglish), that is fine — keep responding in Spanish.\n";
                $base .= "If the user switches fully to English later, follow their lead.\n";
            } elseif ($lang === 'en') {
                $base .= "\n\n## LANGUAGE INSTRUCTION\n";
                $base .= "The user wrote in English. Respond in English.\n";
                $base .= "The user may use imperfect or broken English — do not correct their grammar. Understand their intent and respond warmly.\n";
                $base .= "If the user mixes in Spanish words (Spanglish), acknowledge it naturally and continue in English unless they switch fully to Spanish.\n";
                $base .= "If the user switches to Spanish later, follow their lead.\n";
            }
        } else {
            // No language detected — give Grok a graceful fallback instruction
            $base .= "\n\n## LANGUAGE INSTRUCTION\n";
            $base .= "The user's language could not be determined with confidence. Respond in English.\n";
            $base .= "If the user writes in Spanish or mixes Spanish into English, switch to Spanish naturally.\n";
            $base .= "Always understand intent even when grammar is broken or imperfect.\n";
        }

        return $base;
    }
    
    /**
     * Handle chat AJAX request — structured intent → retrieval → response pipeline.
     *
     * Flow:
     *   1. Crisis check (hard override — deterministic, no API call)
     *   2. Intent classification
     *   3. For provider-add / claim / upgrade / sponsor → deterministic response
     *   4. For consumer_search → extract, query DB, rank, inject into Grok prompt
     *   5. For general → pass to Grok with base prompt
     *   6. Attach debug trace for admin users
     */
    public function handle_chat() {
        mia_debug_log('Chat', 'handler_start', ['message_preview' => substr(sanitize_text_field($_POST['message'] ?? ''), 0, 60)]);
        $this->verify_mia_ajax_request();

        $message = isset($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';
        $message = $this->normalize_spoken_search_terms($message);
        $history = isset($_POST['history']) ? json_decode(stripslashes($_POST['history']), true) : array();
        $prior_context = isset($_POST['prior_context']) ? json_decode(stripslashes($_POST['prior_context']), true) : array();

        // Support for session_context from localStorage (lightweight persistence)
        // This allows the frontend to restore context after page refresh
        $session_context = isset($_POST['session_context']) ? json_decode(stripslashes($_POST['session_context']), true) : array();

        // Sanitise prior_context — only allow known keys
        $safe_prior = $this->sanitize_prior_context($prior_context);
        $ctx_keys = array(
            'primary_category',
            'primary_category_name',
            'specialty',
            'city',
            'provider_type',
            'modality',
            'language_preference',
            'insurance',
            'session_type',
            'language',
            'provider_owner_mode',
            'provider_workflow_step',
            'provider_workflow_city',
            'provider_workflow_field',
            'navigation_state',
        );

        // If session_context is provided and history is empty (fresh page load),
        // use session_context to initialize prior context for continuity
        if (empty($history) && is_array($session_context) && !empty($session_context)) {
            $safe_session = $this->sanitize_prior_context($session_context);
            foreach ($ctx_keys as $k) {
                if (empty($safe_prior[$k]) && !empty($safe_session[$k])) {
                    $safe_prior[$k] = $safe_session[$k];
                }
            }
            if (empty($safe_prior['recent_providers']) && !empty($safe_session['recent_providers'])) {
                $safe_prior['recent_providers'] = $safe_session['recent_providers'];
            }
            if (empty($safe_prior['last_provider_focus']) && !empty($safe_session['last_provider_focus'])) {
                $safe_prior['last_provider_focus'] = $safe_session['last_provider_focus'];
            }
            if (empty($safe_prior['search_context_updated_at']) && !empty($safe_session['search_context_updated_at'])) {
                $safe_prior['search_context_updated_at'] = $safe_session['search_context_updated_at'];
            }
        }

        $safe_prior = $this->prepare_patient_request_context($message, $safe_prior);
        $is_patient_concern = $this->patient_request_kind($message) === 'personal_concern';

        if (empty($message)) {
            mia_debug_log('Chat', 'empty_message', 'Rejecting empty message');
            wp_send_json_error(array('message' => 'No message provided'));
        }

        $is_admin = current_user_can('manage_options');

        // Apply lightweight abuse protection before any expensive processing.
        $rate_limit = $this->enforce_rate_limit('chat');
        if (is_wp_error($rate_limit)) {
            wp_send_json_error(array('message' => $rate_limit->get_error_message()), 429);
        }

        if ($this->uses_openai()) {
            $payload = $this->build_realtime_tool_payload($message, is_array($history) ? $history : array(), $safe_prior);
            $payload['message'] = $payload['assistant_reply'];
            wp_send_json_success($payload);
            return;
        }

        // Social ack fast exit — short-circuit all Grok processing for "ok / thanks / got it" etc.
        // This prevents 8–12 second waits for one-word acknowledgments.
        $msg_lower_ack = strtolower(trim($message));
        $workflow_closing = ($this->has_claim_selection_context($safe_prior) || $this->has_provider_add_workflow_context($safe_prior))
            && $this->is_provider_workflow_closing_acknowledgement($message);
        $short_social_ack = strlen($message) <= 60 && preg_match('/^(?:(?:ok|okay|got it|sounds good|great|perfect|thanks|thank you|thx|ty|alright|sure|cool|nice|yep|yup|yes|no problem|appreciate it|gracias|entendido|perfecto|bien|claro|dale)[,!.]?\s*)+$/iu', $msg_lower_ack);
        if ($workflow_closing || $short_social_ack) {
            $ack_context = $workflow_closing ? $this->clear_active_provider_workflow_context($safe_prior) : $safe_prior;
            if ($workflow_closing) {
                $ack_context['navigation_state'] = $this->sanitize_navigation_state(array(
                    'goal' => 'unsure',
                    'stage' => 'closing',
                    'readiness' => 'unknown',
                    'support_topics' => array(),
                    'open_question' => 'none',
                    'next_best_action' => 'close',
                ));
            }
            $provider_add_active = $this->has_provider_add_workflow_context($ack_context);
            $ack_providers = $provider_add_active ? array() : array_values($ack_context['recent_providers'] ?? array());
            $ack_lang = $safe_prior['language'] ?? 'en';
            if ($workflow_closing) {
                $ack_response = $ack_lang === 'es'
                    ? 'De nada. Que estés bien.'
                    : "You're very welcome. Take care.";
            } elseif ($provider_add_active) {
                $ack_response = $ack_lang === 'es'
                    ? 'De nada. Seguimos con tu nueva práctica. Puedo mostrarte los requisitos, ayudarte con un campo o abrir el formulario.'
                    : "You're welcome. We are still working on your new practice. I can show the requirements, help with a field, or open the form.";
            } elseif (!empty($ack_providers)) {
                $focus_id = absint($safe_prior['last_provider_focus'] ?? 0);
                $ack_provider = $ack_providers[0];
                foreach ($ack_providers as $_p) {
                    if (absint($_p['id'] ?? 0) === $focus_id) { $ack_provider = $_p; break; }
                }
                $ack_name = sanitize_text_field($ack_provider['name'] ?? '');
                $ack_response = $ack_lang === 'es'
                    ? "¡Perfecto! ¿Quieres saber más sobre **{$ack_name}**, revisar ubicación, seguro o buscar otra opción?"
                    : "Glad that helped! Want more details on **{$ack_name}**, check location, insurance, or find a different option?";
            } else {
                $ack_response = $ack_lang === 'es'
                    ? "¡Perfecto! ¿En qué más te puedo ayudar — buscar un proveedor, explorar especialidades o algo más?"
                    : "Glad I could help! What would you like to do next — search for a provider, explore specialties, or something else?";
            }
            wp_send_json_success(array(
                'message'          => $ack_response,
                'intent'           => MyHH_Mia_Intent_Engine::INTENT_GENERAL,
                'response_type'    => 'social_ack',
                'prior_context'    => $ack_context,
                'providers'        => $ack_providers,
                'provider_exposure' => array(),
                'understanding'    => array('intent' => 'general_unknown', 'action' => 'social_ack', 'source' => $workflow_closing ? 'workflow_close_fast_path' : 'fast_path', 'status' => 'fast_path'),
            ));
            return;
        }

        // Run the intent engine (with prior conversation context)
        $engine  = new MyHH_Mia_Intent_Engine();
        $context = $this->get_directory_context();
        $crisis_result = $engine->get_crisis_override($message, $safe_prior);
        if (is_array($crisis_result)) {
            $updated_context = $this->build_updated_context($safe_prior, $crisis_result, $message);
            $language = $crisis_result['detected_language'] ?? ($updated_context['language'] ?? 'en');
            wp_send_json_success(array(
                'message'           => $crisis_result['response'],
                'intent'            => MyHH_Mia_Intent_Engine::INTENT_CRISIS,
                'response_type'     => 'crisis_override',
                'prior_context'     => $updated_context,
                'providers'         => array(),
                'provider_exposure' => array(),
                'crisis_override'   => true,
                'understanding'     => array(
                    'intent'              => 'crisis_or_high_risk',
                    'next_action'         => 'crisis',
                    'clean_text'          => $message,
                    'confidence'          => 1,
                    'needs_clarification' => false,
                    'source'              => 'deterministic_crisis_gate',
                    'entities'            => array('language' => $language),
                    'navigation_state'    => $updated_context['navigation_state'] ?? array(),
                ),
            ));
            return;
        }
        if ($this->is_search_preference_recap_request($message)) {
            $language = ($safe_prior['language'] ?? 'en') === 'es' ? 'es' : 'en';
            $updated_context = $safe_prior;
            $prior_navigation = $this->sanitize_navigation_state($safe_prior['navigation_state'] ?? array());
            $recap_is_deferred = ($prior_navigation['readiness'] ?? '') === 'not_ready';
            $updated_context['navigation_state'] = $this->sanitize_navigation_state(array_merge(
                $safe_prior['navigation_state'] ?? array(),
                array(
                    'goal' => $recap_is_deferred ? 'learn' : 'find_provider',
                    'stage' => $recap_is_deferred ? 'educating' : (!empty($safe_prior['recent_providers']) ? 'reviewing_results' : 'search_ready'),
                    'readiness' => $recap_is_deferred ? 'not_ready' : 'ready',
                    'open_question' => 'none',
                    'next_best_action' => $recap_is_deferred ? 'educate' : (!empty($safe_prior['recent_providers']) ? 'show_profile' : 'search'),
                )
            ));
            wp_send_json_success(array(
                'message' => $this->build_search_preference_recap_response($safe_prior, $language),
                'intent' => 'provider_followup',
                'response_type' => 'deterministic_context_recap',
                'prior_context' => $updated_context,
                'providers' => array(),
                'provider_exposure' => array(),
                'understanding' => array(
                    'intent' => 'provider_followup',
                    'next_action' => 'provider_detail',
                    'confidence' => 1,
                    'source' => 'deterministic_preference_recap',
                    'entities' => array('language' => $language),
                    'navigation_state' => $updated_context['navigation_state'],
                ),
            ));
            return;
        }
        $is_provider_fact = !empty($safe_prior['recent_providers']) && $this->is_existing_provider_fact_question($message);
        $is_platform_service_overview = $this->looks_like_platform_service_overview_request($message, $history, $safe_prior);
        $is_basic_education = $is_platform_service_overview || !$is_provider_fact && (MyHH_Mia_Intent_Engine::is_basic_education_question($message)
            || $this->looks_like_contextual_education_followup($message, $safe_prior));
        $suppress_provider_action = $is_patient_concern || $this->should_hold_provider_search_for_readiness($message, $safe_prior);
        $pre_search_clarification = $this->wants_pre_search_clarification($message);
        $understanding = $is_platform_service_overview ? array(
            'raw_text' => $message, 'clean_text' => $message,
            'intent' => MyHH_Mia_Intent_Engine::INTENT_GENERAL, 'next_action' => 'platform_info', 'action' => 'platform_info',
            'confidence' => 1, 'entities' => array('language' => $safe_prior['language'] ?? 'en'),
            'source' => 'deterministic_platform_service_overview',
        ) : ($is_patient_concern
            ? $this->build_patient_concern_understanding($message, $safe_prior)
            : $this->build_provider_workflow_continuation_understanding($message, $safe_prior));
        if (empty($understanding)) {
            $understanding = $this->understand_turn_with_grok($message, '', $safe_prior);
        }
        $understanding = $this->apply_openai_decision($message, $understanding, $safe_prior);
        $engine_message = $this->get_grok_understood_text($message, $understanding);
        $forced_followup_provider = $this->resolve_recent_provider_name_followup($engine_message, $safe_prior)
            ?: $this->resolve_recent_provider_name_followup($message, $safe_prior);
        if ($forced_followup_provider) {
            $providers = $this->sanitize_provider_result_names(
                $safe_prior['recent_providers'],
                $safe_prior['language'] ?? 'en'
            );
            $understanding = $this->merge_forced_provider_followup_understanding($understanding, $message, $engine_message, $forced_followup_provider);
            mia_debug_log('ProviderFollowup', 'recent_provider_name_forced_chat', array(
                'message' => substr($engine_message, 0, 80),
                'provider' => $forced_followup_provider['name'] ?? '',
                'provider_id' => absint($forced_followup_provider['id'] ?? 0),
            ));
            $result = array(
                'intent'           => MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH,
                'criteria'         => array(),
                'providers'        => $providers,
                'response'         => null,
                'fallback_used'    => false,
                'broadened'        => null,
                'near_me_detected' => false,
                'detected_language' => sanitize_key($understanding['entities']['language'] ?? ($safe_prior['language'] ?? 'en')),
                '_route_hint_override' => 'provider_followup',
                '_forced_recent_provider_followup' => true,
            );
        } elseif ($this->grok_understanding_is_provider_followup($understanding) || $this->looks_like_provider_set_fact_followup($message, $safe_prior)) {
            $providers = !empty($safe_prior['recent_providers'])
                ? $this->sanitize_provider_result_names(
                    $safe_prior['recent_providers'],
                    $safe_prior['language'] ?? 'en'
                )
                : array();
            $result = array(
                'intent'           => MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH,
                'criteria'         => array(),
                'providers'        => $providers,
                'response'         => null,
                'fallback_used'    => false,
                'broadened'        => null,
                'near_me_detected' => false,
                'detected_language' => sanitize_key($understanding['entities']['language'] ?? ($safe_prior['language'] ?? 'en')),
                '_route_hint_override' => 'provider_followup',
            );
        } else {
            $direct_grok_result = $this->build_grok_direct_provider_search_result($understanding, $safe_prior, $engine, $context, $engine_message);
            $result  = is_array($direct_grok_result)
                ? $direct_grok_result
                : $engine->process($engine_message, $context, $safe_prior);
        }
        $result['_grok_understanding'] = $understanding;
        $understood_intent = $this->intent_from_grok_next_action($understanding['next_action'] ?? $understanding['action'] ?? '');
        $claim_turn_is_explicit = (bool) preg_match('/\b(?:claim|claiming|reclaim|take\s+over)\b|\bexisting\s+(?:profile|listing)\b/iu', $engine_message);
        $claim_turn_is_active = $this->has_claim_selection_context($safe_prior);
        if ($understood_intent === MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM &&
            ($claim_turn_is_explicit || $claim_turn_is_active) &&
            !in_array($result['intent'] ?? '', array(MyHH_Mia_Intent_Engine::INTENT_CRISIS, MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM, MyHH_Mia_Intent_Engine::INTENT_PROVIDER_OWNER), true)) {
            $result['intent'] = MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM;
            $result['criteria'] = is_array($result['criteria'] ?? null) ? $result['criteria'] : array();
            $result['providers'] = array();
            $result['response'] = '';
            $result['fallback_used'] = false;
        }
        $result  = $this->force_claim_selection_result_if_needed($engine_message, $safe_prior, $result, 'chat');
        $effective_route_hint = ($is_basic_education || $suppress_provider_action)
            ? ''
            : ($this->grok_understanding_is_provider_search($understanding) ? 'provider_search' : '');
        $result  = $this->force_provider_search_continuation_result_if_needed($engine_message, $effective_route_hint, $safe_prior, $result, $engine, $context);
        $result  = $this->apply_grok_action_contract_to_result($result, $understanding, $safe_prior, $engine, $context);
        $result  = $this->broaden_empty_specialty_search_result($result, $engine, $context);
        if ($this->grok_understanding_is_provider_search($understanding)) {
            // Keep explicit search preferences out of the provider-detail
            // parser; otherwise an insurance term can hijack the whole set.
            $result['_suppress_provider_followup'] = true;
        }
        if (($is_basic_education || $suppress_provider_action || $this->looks_like_consultative_exploration_turn($message))
            && ($result['intent'] ?? '') !== MyHH_Mia_Intent_Engine::INTENT_CRISIS) {
            $result['intent'] = MyHH_Mia_Intent_Engine::INTENT_GENERAL;
            $result['providers'] = array();
            $result['criteria'] = $this->has_explicit_search_defer_signal($message)
                ? $this->build_deferred_search_criteria($message)
                : array();
            $result['response'] = null;
            $result['_suppress_provider_followup'] = true;
            $result['_contextual_education'] = $is_basic_education;
            $understanding['intent'] = MyHH_Mia_Intent_Engine::INTENT_GENERAL;
            $understanding['next_action'] = 'general';
            $understanding['action'] = 'general';
            if ($pre_search_clarification) {
                $language = ($safe_prior['language'] ?? 'en') === 'es' ? 'es' : 'en';
                $understanding['needs_clarification'] = true;
                $understanding['confidence'] = 0.5;
                $understanding['clarification_question'] = $this->build_pre_search_clarification_question($safe_prior, $language);
            }
            $result['_grok_understanding'] = $understanding;
        }
        $result['providers'] = $this->sanitize_provider_result_names($result['providers'] ?? array(), $result['detected_language'] ?? ($safe_prior['language'] ?? 'en'));
        $result['provider_exposure'] = $this->build_provider_exposure_payload(
            $engine->get_last_provider_exposure(),
            $result['providers'] ?? array(),
            $result['criteria'] ?? array(),
            $result['intent'] ?? '',
            ''
        );
        $negative_search_refinement = $this->looks_like_negative_search_refinement($engine_message, $safe_prior);
        mia_debug_log('Chat', 'intent_result', ['intent' => $result['intent'], 'provider_count' => count($result['providers'] ?? []), 'language' => $result['detected_language'] ?? 'en', 'criteria' => $result['criteria'] ?? []]);

        // Build updated conversation context to return to client
        $updated_context = $this->build_updated_context($safe_prior, $result, $message, $negative_search_refinement);

        // Deterministic intents — no Grok API call needed
        $deterministic = array(
            MyHH_Mia_Intent_Engine::INTENT_CRISIS,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_ADD,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_OWNER,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_UPGRADE,
            MyHH_Mia_Intent_Engine::INTENT_SPONSOR,
        );

        if ($this->should_answer_with_grok_clarification($understanding, $result)) {
            $out = array('message' => $this->build_grok_clarification_reply($understanding));
            if ($is_admin) $out['debug'] = $engine->get_debug_trace();
            $out['prior_context'] = $updated_context;
            $out['providers'] = !empty($result['_suppress_provider_followup'])
                ? array()
                : array_values($updated_context['recent_providers'] ?? array());
            $out['provider_exposure'] = $result['provider_exposure'] ?? array();
            $out['understanding'] = $understanding;
            wp_send_json_success($out);
            return;
        }

        if (in_array($result['intent'], $deterministic, true)) {
            $message_out = $result['response'];
            if ($result['intent'] === MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM) {
                $claim_guidance = $this->build_provider_claim_guidance_response($message, $updated_context, $result, $safe_prior);
                if ($claim_guidance) {
                    $message_out = $claim_guidance;
                }
                // Persist claim disambiguation providers to context for next-turn narrowing
                if (!empty($this->_claim_disambiguation_providers)) {
                    $updated_context['claim_disambiguation_providers'] = $this->sanitize_provider_memory($this->_claim_disambiguation_providers);
                    if (count($updated_context['claim_disambiguation_providers']) === 1) {
                        $updated_context['last_provider_focus'] = absint($updated_context['claim_disambiguation_providers'][0]['id'] ?? 0);
                    }
                    $this->_claim_disambiguation_providers = array();
                }
                // Persist name suggestions to context for next-turn selection
                if (!empty($this->_claim_name_suggestions)) {
                    $updated_context['claim_name_suggestions'] = $this->sanitize_provider_memory($this->_claim_name_suggestions);
                    $this->_claim_name_suggestions = array();
                }
            }
            mia_debug_log('Chat', 'deterministic_response', ['intent' => $result['intent'], 'response_preview' => substr($message_out, 0, 60)]);
            $out = array(
                'message'       => $message_out,
                'intent'        => $result['intent'],
                'response_type' => 'deterministic',
            );
            if ($is_admin) $out['debug'] = $engine->get_debug_trace();
            $out['prior_context'] = $updated_context;
            $out['providers'] = array();
            $out['provider_exposure'] = $result['provider_exposure'] ?? array();
            $out['understanding'] = $understanding;
            wp_send_json_success($out);
            return;
        }

        // Deterministic general support responses reduce unnecessary model usage
        // for common informational questions about Mia and the directory.
        if ($result['intent'] === MyHH_Mia_Intent_Engine::INTENT_GENERAL) {
            $deterministic_general = $this->get_deterministic_general_response($message, $context, $updated_context, $safe_prior);
            if (!$deterministic_general && $engine_message !== $message) {
                $deterministic_general = $this->get_deterministic_general_response($engine_message, $context, $updated_context, $safe_prior);
            }
            if ($deterministic_general) {
                $out = array(
                    'message' => $deterministic_general,
                    'intent' => $result['intent'],
                    'response_type' => 'deterministic_general',
                );
                if ($is_admin) $out['debug'] = $engine->get_debug_trace();
                $out['prior_context'] = $updated_context;
                // Keep search memory for later follow-ups, but do not render
                // stale provider cards beneath a conceptual education answer.
                $out['providers'] = array();
                $out['provider_exposure'] = $result['provider_exposure'] ?? array();
                $out['understanding'] = $understanding;
                $this->log_interaction(array(
                    'input'           => $message,
                    'intent'          => $result['intent'],
                    'criteria'        => $result['criteria'],
                    'provider_count'  => count($result['providers']),
                    'provider_exposure' => $result['provider_exposure'] ?? array(),
                    'crisis_override' => false,
                    'response_type'   => 'deterministic_general',
                    'fallback_used'   => false,
                    'near_me'         => $result['near_me_detected'] ?? false,
                ));
                wp_send_json_success($out);
                return;
            }
        }

        $followup_result = $result;
        $provider_owner_fragment = $this->looks_like_provider_owner_fragment($engine_message);
        $vague_search_fragment = $this->looks_like_vague_search_fragment($engine_message);
        $provider_owner_context = !empty($updated_context['provider_owner_mode']);
        $search_needs_clarification = !empty($result['criteria']['needs_clarification']);
        $can_backfill_providers = $this->should_backfill_search_providers(
            $engine_message,
            $followup_result,
            $updated_context,
            $negative_search_refinement
        ) && !$provider_owner_fragment && !$provider_owner_context && !$vague_search_fragment && !$search_needs_clarification;

        // When route_hint_override is active (provider_followup), never
        // generate a fresh rotated search set — the user is interacting
        // with previously shown providers, and a new search rotation
        // would break ordinal/deictic references on the next turn.
        // (Audit 2026-04-30: provider context can rotate during followup.)
        $is_followup_override = !empty($result['_route_hint_override']) && $result['_route_hint_override'] === 'provider_followup';
        if ($is_followup_override) {
            $can_backfill_providers = false;
        }

        if ($can_backfill_providers && empty($followup_result['providers']) && !empty($updated_context['recent_providers'])) {
            $followup_result['providers'] = $updated_context['recent_providers'];
        }
        if ($can_backfill_providers && empty($followup_result['providers']) && $followup_result['intent'] === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH && (!empty($updated_context['specialty']) || !empty($updated_context['city']) || !empty($updated_context['primary_category']))) {
            $followup_result['providers'] = $engine->get_ranked_providers_from_context($updated_context, $context);
        }

        $deterministic_followup = empty($result['_suppress_provider_followup'])
            ? $this->get_deterministic_followup_response($engine_message, $followup_result, $updated_context, $safe_prior)
            : null;
        if ($deterministic_followup) {
            if (!empty($safe_prior['recent_providers'])) {
                $followup_result['providers'] = array_values($safe_prior['recent_providers']);
                $updated_context['recent_providers'] = array_values($safe_prior['recent_providers']);
                $updated_context['last_provider_focus'] = $this->get_answered_provider_focus($deterministic_followup, $safe_prior);
            }
            $out = array(
                'message' => $deterministic_followup,
                'intent' => 'provider_followup',
                'response_type' => 'deterministic_followup',
            );
            if ($is_admin) $out['debug'] = $engine->get_debug_trace();
            $out['prior_context'] = $updated_context;
            $out['providers'] = $negative_search_refinement
                ? array()
                : array_values($updated_context['recent_providers'] ?? array());
            $out['provider_exposure'] = $result['provider_exposure'] ?? array();
            $out['understanding'] = $understanding;
            $this->log_interaction(array(
                'input'           => $message,
                'intent'          => $result['intent'],
                'criteria'        => $result['criteria'],
                'provider_count'  => count($followup_result['providers']),
                'provider_exposure' => $result['provider_exposure'] ?? array(),
                'crisis_override' => false,
                'response_type'   => 'deterministic_followup',
                'fallback_used'   => false,
                'near_me'         => $result['near_me_detected'] ?? false,
            ));
            wp_send_json_success($out);
            return;
        }

        // Empty searches are factual outcomes, not an invitation to invent
        // inventory, offer a different service, or reuse previous cards.
        if ($result['intent'] === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH
            && empty($result['providers']) && !$search_needs_clarification && !$is_followup_override) {
            $updated_context['recent_providers'] = array();
            $updated_context['last_provider_focus'] = 0;
            wp_send_json_success(array(
                'message' => $this->build_grounded_no_results_reply($result['criteria'] ?? array(), $updated_context['language'] ?? 'en'),
                'intent' => $result['intent'], 'response_type' => 'search_no_results',
                'prior_context' => $updated_context, 'providers' => array(),
                'provider_exposure' => array(), 'understanding' => $understanding,
            ));
            return;
        }

        // Consumer search or general → call Grok with enhanced context
        // BUT: if we already have provider results, use deterministic response directly.
        // Grok sometimes ignores the provider data and asks clarifying questions instead.
        if ($result['intent'] === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH && !empty($result['providers'])) {
            $out = array();
            $out['message'] = $this->format_providers_fallback($result, $engine_message);
            $out['intent'] = $result['intent'];
            $out['response_type'] = 'search_grounded';
            if ($is_admin) $out['debug'] = $engine->get_debug_trace();
            $out['prior_context'] = $updated_context;
            $out['providers'] = array_values($updated_context['recent_providers'] ?? array());
            $out['provider_exposure'] = $result['provider_exposure'] ?? array();
            $out['understanding'] = $understanding;

            // Persistent QA logging
            $this->log_interaction(array(
                'input'           => $message,
                'intent'          => $result['intent'],
                'criteria'        => $result['criteria'],
                'provider_count'  => count($result['providers']),
                'provider_exposure' => $result['provider_exposure'] ?? array(),
                'crisis_override' => ($result['intent'] === MyHH_Mia_Intent_Engine::INTENT_CRISIS),
                'response_type'   => 'search_grounded',
                'fallback_used'   => $result['fallback_used'] ?? false,
                'near_me'         => $result['near_me_detected'] ?? false,
            ));

            wp_send_json_success($out);
            return;
        }
        // Determine conversation state: 0 = first message, >0 = continuation
        $history_count = is_array($history) ? count($history) : 0;

        // Expand history window to 20 messages (increased from 10)
        // Keep full history for summary generation, slice to last 20 for API
        $full_history = $history;
        if (!empty($history)) {
            $history = array_slice($history, -20);
        }

        // Build enhanced system prompt with prior context and full history for summary
        $system_prompt = $this->build_enhanced_system_prompt($context, $result, $history_count, $safe_prior, $full_history);
        if ($is_basic_education && $result['intent'] === MyHH_Mia_Intent_Engine::INTENT_GENERAL) {
            $system_prompt .= "\nThis is conceptual education, not a provider search. Answer every concept in the question. General professional knowledge does not establish what any listed providers offer. Do not claim many, most, some, or all listed providers offer a modality without explicit profile evidence. Do not append a search invitation unless requested.\n";
        }

        $messages = array(
            array('role' => 'system', 'content' => $system_prompt),
        );

        // Conversation history (last 20 messages — expanded from 10)
        if (!empty($history)) {
            foreach ($history as $msg) {
                $messages[] = array(
                    'role'    => $msg['role'],
                    'content' => $msg['content'],
                );
            }
        }

        $messages[] = array('role' => 'user', 'content' => $engine_message);

        // Call Grok API
        mia_debug_log('Chat', 'grok_api_call', ['message_count' => count($messages), 'intent' => $result['intent']]);
        $api_response = $this->call_grok_api($messages);

        $out = array();

        if (is_wp_error($api_response)) {
            mia_debug_log('Chat', 'grok_api_error', ['code' => $api_response->get_error_code(), 'message' => $api_response->get_error_message()]);
            // Fallback: use engine's deterministic response or smart search fallback
            if ($result['intent'] === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH && !empty($result['providers'])) {
                $out['message'] = $this->format_providers_fallback($result, $engine_message);
            } else {
                // Try smart follow-up fallback using conversation history + prior context
                $smart = $this->smart_followup_fallback($engine_message, $history, $safe_prior, $result);
                $out['message'] = $smart ?: ($result['response'] ?: $this->get_general_fallback());
            }
        } else {
            $body = wp_remote_retrieve_body($api_response);
            $data = json_decode($body, true);

            if (isset($data['choices'][0]['message']['content'])) {
                $out['message'] = $data['choices'][0]['message']['content'];
                mia_debug_log('Chat', 'grok_api_success', ['response_preview' => substr($out['message'], 0, 60)]);
            } else {
                mia_debug_log('Chat', 'grok_api_empty', ['raw_body_preview' => substr($body ?? '', 0, 80)]);
                // Smart fallback for search queries
                if ($result['intent'] === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH && !empty($result['providers'])) {
                    $out['message'] = $this->format_providers_fallback($result, $engine_message);
                } else {
                    $smart = $this->smart_followup_fallback($engine_message, $history, $safe_prior, $result);
                    $out['message'] = $smart ?: ($result['response'] ?: $this->get_general_fallback());
                }
            }
        }

        if ($is_basic_education && $result['intent'] === MyHH_Mia_Intent_Engine::INTENT_GENERAL) {
            $out['message'] = $this->enforce_education_inventory_boundary($out['message'], $updated_context['language'] ?? 'en');
        }
        if ($is_admin) $out['debug'] = $engine->get_debug_trace();
        $out['intent'] = $result['intent'];
        $out['response_type'] = $result['intent'] === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH ? 'search_grounded' : ($this->uses_openai() ? 'openai_general' : 'grok_general');
        $out['prior_context'] = $updated_context;
        $out['providers'] = $result['intent'] === MyHH_Mia_Intent_Engine::INTENT_GENERAL
            ? array()
            : array_values($updated_context['recent_providers'] ?? array());
        $out['provider_exposure'] = $result['provider_exposure'] ?? array();
        $out['understanding'] = $understanding;

        // Persistent QA logging
        $this->log_interaction(array(
            'input'           => $message,
            'intent'          => $result['intent'],
            'criteria'        => $result['criteria'],
            'provider_count'  => count($result['providers']),
            'provider_exposure' => $result['provider_exposure'] ?? array(),
            'crisis_override' => ($result['intent'] === MyHH_Mia_Intent_Engine::INTENT_CRISIS),
            'response_type'   => in_array($result['intent'], $deterministic, true) ? 'deterministic' : ($result['intent'] === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH ? 'search_grounded' : ($this->uses_openai() ? 'openai_general' : 'grok_general')),
            'fallback_used'   => $result['fallback_used'] ?? false,
            'near_me'         => $result['near_me_detected'] ?? false,
        ));

        wp_send_json_success($out);
    }
    
    /**
     * Call Grok API
     */
    private function call_grok_api($messages) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'Grok API key not configured');
        }
        
        $body = array(
            'model' => 'grok-3-mini',
            'messages' => $messages,
            'max_tokens' => 500,
            'temperature' => 0.7,
        );
        
        $response = $this->request_chat_completion(array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body),
        ));
        
        return $response;
    }

    private function should_use_grok_understanding($message, $route_hint, $safe_prior) {
        $msg = strtolower(trim((string) $message));
        if ($msg === '' || strlen($msg) < 4) {
            return false;
        }

        if ($route_hint !== '' || !empty($safe_prior['recent_providers']) || $this->has_claim_selection_context($safe_prior)) {
            return true;
        }

        if (preg_match('/\b(?:um|uh|umm|hmm|ah|like|you know|i mean|kind of|sort of)\b/i', $msg)) {
            return true;
        }

        if (preg_match('/\b(?:find|looking for|provider|providers|therapist|therapy|counselor|counseling|psychiatrist|psychologist|anxiety|depression|mental health|near me|raleigh|durham|charlotte|other|different|another|except|outside|not in|city|cities|area|areas|specialty|specialties|service|services|claim|listing|profile|practice|manage|verify|add my|upgrade|sponsor|first one|second one|third one|tell me more|what about|location|address|compare|insurance|booking)\b/i', $msg)) {
            return true;
        }

        if (preg_match('/\b(?:buscar|busco|proveedor|proveedores|terapeuta|terapia|consejero|psicologo|psicólogo|psiquiatra|ansiedad|depresion|depresión|reclamar|perfil|listado|practica|práctica|gestionar|verificar|primero|segundo|tercero|cuentame|cuéntame|ubicacion|ubicación|direccion|dirección|seguro|cita)\b/iu', $msg)) {
            return true;
        }

        return false;
    }

    private function sanitize_transcript_candidates($candidates) {
        if (empty($candidates) || !is_array($candidates)) {
            return array();
        }

        $allowed_text_keys = array(
            'canonical_text',
            'chosen_text',
            'browser_transcript',
            'realtime_transcript',
            'final_assist_transcript',
            'assist_transcript',
            'manager_transcript',
        );
        $safe = array();
        foreach ($allowed_text_keys as $key) {
            if (!isset($candidates[$key])) {
                continue;
            }
            $value = $this->normalize_spoken_search_terms(sanitize_text_field((string) $candidates[$key]));
            if ($value !== '') {
                $safe[$key] = substr($value, 0, 260);
            }
        }

        $source = sanitize_key((string) ($candidates['source'] ?? ''));
        if ($source !== '') {
            $safe['source'] = $source;
        }
        $reason = sanitize_key((string) ($candidates['reason'] ?? ''));
        if ($reason !== '') {
            $safe['reason'] = $reason;
        }
        $route_hint = sanitize_key((string) ($candidates['route_hint'] ?? ''));
        if ($route_hint !== '') {
            $safe['route_hint'] = $route_hint;
        }
        if (isset($candidates['browser_confidence'])) {
            $safe['browser_confidence'] = max(0.0, min(1.0, floatval($candidates['browser_confidence'])));
        }
        if (isset($candidates['browser_final'])) {
            $safe['browser_final'] = !empty($candidates['browser_final']);
        }

        return $safe;
    }

    private function candidate_has_provider_action_signal($text) {
        $text = trim((string) $text);
        if ($text === '') {
            return false;
        }

        return (bool) preg_match('/\b(?:find|search|show|need|looking|look for|provider|providers|therapist|therapy|counselor|counseling|psychiatrist|psychologist|mental health|anxiety|depression|claim|listing|profile|practice|manage|verify|first one|second one|third one|tell me more|how about|what about|more about|location|address|compare|insurance|booking|buscar|busco|proveedor|proveedores|terapeuta|terapia|consejero|reclamar|perfil|listado|gestionar|verificar|primero|segundo|tercero|ubicaci[oó]n|direcci[oó]n)\b/iu', $text);
    }

    private function candidate_looks_garbled_against_browser($message, $browser_text) {
        $message = strtolower(trim((string) $message));
        $browser_text = strtolower(trim((string) $browser_text));
        if ($message === '' || $browser_text === '') {
            return false;
        }
        if (preg_match('/\b(?:weather|whether|pro weather|prove weather|tv providers?|me providers?)\b/i', $message)
            && preg_match('/\b(?:provider|providers|profile|listing|claim|therapist|counselor)\b/i', $browser_text)) {
            return true;
        }
        if (!preg_match('/\b(?:provider|providers|profile|listing|claim|therapist|therapy|counselor|counseling)\b/i', $message)
            && preg_match('/\b(?:provider|providers|profile|listing|claim|therapist|therapy|counselor|counseling)\b/i', $browser_text)) {
            return true;
        }
        return false;
    }

    private function select_best_transcript_candidate($message, $transcript_candidates) {
        $message = trim((string) $message);
        $candidates = $this->sanitize_transcript_candidates($transcript_candidates);
        $selected = array(
            'text' => $message,
            'source' => 'message',
            'reason' => 'default_message',
            'candidates' => $candidates,
        );

        $candidate_route_hint = sanitize_key((string) ($candidates['route_hint'] ?? ''));
        $canonical = trim((string) ($candidates['canonical_text'] ?? ''));
        if ($canonical !== ''
            && ($this->candidate_has_provider_action_signal($canonical)
                || $candidate_route_hint !== '')) {
            $selected['text'] = $canonical;
            $selected['source'] = sanitize_key((string) ($candidates['source'] ?? 'canonical'));
            $selected['reason'] = sanitize_key((string) ($candidates['reason'] ?? 'canonical_arbiter'));
            return $selected;
        }

        $chosen = trim((string) ($candidates['chosen_text'] ?? ''));
        if ($message === '' && $chosen !== '') {
            $selected['text'] = $chosen;
            $selected['source'] = sanitize_key((string) ($candidates['source'] ?? 'chosen'));
            $selected['reason'] = 'message_empty';
            return $selected;
        }

        $browser = trim((string) ($candidates['browser_transcript'] ?? ''));
        $browser_confidence = floatval($candidates['browser_confidence'] ?? 0);
        $browser_final = !empty($candidates['browser_final']);
        if ($browser !== ''
            && $browser_final
            && $browser_confidence >= 0.45
            && preg_match('/\b(?:how\s+about|what\s+about|tell\s+me\s+about|more\s+about|and\s+the)\b/iu', $browser)) {
            $selected['text'] = $browser;
            $selected['source'] = 'browser';
            $selected['reason'] = 'trusted_browser_named_followup';
            return $selected;
        }
        if ($browser !== ''
            && ($browser_confidence >= 0.82 || $browser_final)
            && $this->candidate_has_provider_action_signal($browser)
            && ($this->candidate_looks_garbled_against_browser($message, $browser) || !$this->candidate_has_provider_action_signal($message))) {
            $selected['text'] = $browser;
            $selected['source'] = 'browser';
            $selected['reason'] = 'trusted_browser_candidate';
            return $selected;
        }

        if ($chosen !== ''
            && $this->candidate_has_provider_action_signal($chosen)
            && (!$this->candidate_has_provider_action_signal($message) || $this->candidate_looks_garbled_against_browser($message, $chosen))) {
            $selected['text'] = $chosen;
            $selected['source'] = sanitize_key((string) ($candidates['source'] ?? 'chosen'));
            $selected['reason'] = 'trusted_chosen_candidate';
            return $selected;
        }

        return $selected;
    }

    private function build_grok_understanding_context($safe_prior, $route_hint = '', $transcript_candidates = array()) {
        return $this->build_grok_state_packet($safe_prior, $route_hint, $transcript_candidates);
    }

    private function build_grok_provider_context_item($provider, $rank = 0) {
        $specialties = array();
        foreach ((array) ($provider['specialties'] ?? $provider['specialty'] ?? array()) as $specialty) {
            $specialty = sanitize_text_field($specialty);
            if ($specialty !== '') {
                $specialties[] = $specialty;
            }
            if (count($specialties) >= 3) {
                break;
            }
        }

        return array(
            'rank' => absint($rank),
            'id' => absint($provider['id'] ?? 0),
            'name' => sanitize_text_field($provider['name'] ?? ''),
            'city' => sanitize_text_field($provider['city'] ?? ''),
            'specialties' => $specialties,
            'session_type' => sanitize_text_field($provider['session_type'] ?? ''),
            'insurance_accepted' => array_map('sanitize_text_field', array_slice((array) ($provider['insurance_accepted'] ?? array()), 0, 8)),
        );
    }

    private function build_grok_taxonomy_vocabulary() {
        $context = $this->get_directory_context();

        $categories = array();
        foreach (array_slice($context['categories'] ?? array(), 0, 20) as $category) {
            $name = sanitize_text_field($category['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $categories[] = array(
                'name' => $name,
                'slug' => sanitize_title($category['slug'] ?? $name),
                'count' => absint($category['count'] ?? 0),
            );
        }

        $specialties = array();
        foreach (array_slice($context['top_specialties'] ?? array(), 0, 30) as $specialty) {
            $name = sanitize_text_field($specialty['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $specialties[] = array(
                'name' => $name,
                'count' => absint($specialty['count'] ?? 0),
            );
        }

        return array(
            'known_categories' => $categories,
            'known_specialties' => $specialties,
            'common_mappings' => array(
                'therapy' => 'Mental Health & Therapy',
                'therapist' => 'Mental Health & Therapy',
                'counseling' => 'General Counseling',
                'counselor' => 'General Counseling',
                'anxiety help' => 'Anxiety',
                'depression help' => 'Depression',
                'marriage help' => 'Couples Therapy',
                'relationship help' => 'Couples Therapy',
                'diet' => 'Nutrition',
                'nutritionist' => 'Nutrition',
                'exercise coach' => 'Fitness',
                'fitness coach' => 'Fitness',
                'massage' => 'Massage Therapy',
                'terapia' => 'Mental Health & Therapy',
                'consejeria' => 'General Counseling',
                'consejería' => 'General Counseling',
                'ansiedad' => 'Anxiety',
                'depresion' => 'Depression',
                'depresión' => 'Depression',
                'pareja' => 'Couples Therapy',
                'nutricion' => 'Nutrition',
                'nutrición' => 'Nutrition',
            ),
        );
    }

    private function build_grok_state_packet($safe_prior, $route_hint = '', $transcript_candidates = array(), $include_taxonomy = true) {
        $recent_providers = array();
        $rank = 1;
        foreach (array_slice($safe_prior['recent_providers'] ?? array(), 0, 6) as $provider) {
            $recent_providers[] = $this->build_grok_provider_context_item($provider, $rank++);
        }

        $claim_candidates = array();
        $rank = 1;
        foreach (array_slice($safe_prior['claim_disambiguation_providers'] ?? array(), 0, 6) as $provider) {
            $claim_candidates[] = $this->build_grok_provider_context_item($provider, $rank++);
        }

        $claim_name_suggestions = array();
        $rank = 1;
        foreach (array_slice($safe_prior['claim_name_suggestions'] ?? array(), 0, 6) as $provider) {
            $claim_name_suggestions[] = $this->build_grok_provider_context_item($provider, $rank++);
        }

        $active_task = 'general';
        if (($safe_prior['provider_owner_mode'] ?? '') === MyHH_Mia_Intent_Engine::INTENT_PROVIDER_ADD) {
            $active_task = 'provider_add';
        } elseif (!empty($safe_prior['provider_owner_mode'])) {
            $active_task = 'provider_owner';
        } elseif (!empty($claim_candidates) || !empty($claim_name_suggestions)) {
            $active_task = 'claim_disambiguation';
        } elseif (!empty($recent_providers)) {
            $active_task = 'provider_results';
        }

        $allowed_next_actions = array(
            'provider_search',
            'provider_detail',
            'provider_location',
            'provider_insurance',
            'provider_booking',
            'provider_compare',
            'provider_claim',
            'claim_candidate_select',
            'provider_add',
            'provider_upgrade',
            'sponsor_inquiry',
            'platform_info',
            'crisis',
            'general',
            'clarify',
        );

        $taxonomy_vocabulary = $include_taxonomy ? $this->build_grok_taxonomy_vocabulary() : array();

        return array(
            'pending_question' => $safe_prior['pending_question'] ?? array(),
            'care_recipient' => $safe_prior['care_recipient'] ?? '',
            'care_age_group' => $safe_prior['care_age_group'] ?? '',
            'language' => $safe_prior['language'] ?? '',
            'route_hint' => sanitize_key((string) $route_hint),
            'active_task' => $active_task,
            'provider_owner_mode' => sanitize_key((string) ($safe_prior['provider_owner_mode'] ?? '')),
            'provider_workflow_step' => sanitize_key((string) ($safe_prior['provider_workflow_step'] ?? '')),
            'provider_workflow_city' => sanitize_text_field($safe_prior['provider_workflow_city'] ?? ''),
            'provider_workflow_field' => sanitize_key((string) ($safe_prior['provider_workflow_field'] ?? '')),
            'navigation_state' => $this->sanitize_navigation_state($safe_prior['navigation_state'] ?? array()),
            'has_recent_providers' => !empty($recent_providers),
            'has_claim_candidates' => !empty($claim_candidates) || !empty($claim_name_suggestions),
            'search' => array(
                'category' => $safe_prior['primary_category_name'] ?? '',
                'specialty' => $safe_prior['specialty'] ?? '',
                'city' => $safe_prior['city'] ?? '',
                'provider_type' => $safe_prior['provider_type'] ?? '',
                'modality' => $safe_prior['modality'] ?? '',
                'language_preference' => $safe_prior['language_preference'] ?? '',
                'insurance' => $safe_prior['insurance'] ?? '',
                'session_type' => $safe_prior['session_type'] ?? '',
                'contract_fields' => array(
                    'change_dimension' => 'city|specialty|category|provider_type|modality|language_preference|insurance|session_type|none',
                    'preserve_context' => array('category', 'specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type'),
                    'clear_context' => array('category', 'specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type'),
                    'search_entities' => array(
                        'category' => 'string|null',
                        'specialty' => 'string|null',
                        'city' => 'string|null',
                        'provider_type' => 'string|null',
                        'modality' => 'string|null',
                        'language_preference' => 'string|null',
                        'insurance' => 'string|null',
                        'session_type' => 'Virtual|In-Person|Hybrid|null',
                    ),
                ),
            ),
            'last_provider_focus' => !empty($safe_prior['last_provider_focus']) ? absint($safe_prior['last_provider_focus']) : 0,
            'recent_providers' => $recent_providers,
            'claim_candidates' => $claim_candidates,
            'claim_name_suggestions' => $claim_name_suggestions,
            'taxonomy_vocabulary' => $taxonomy_vocabulary,
            'transcript_candidates' => $this->sanitize_transcript_candidates($transcript_candidates),
            'allowed_next_actions' => $allowed_next_actions,
        );
    }

    private function normalize_grok_understanding_intent($intent) {
        $intent = strtolower(trim((string) $intent));
        $map = array(
            'provider_search' => MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH,
            'search_providers' => MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH,
            'consumer_provider_search' => MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH,
            'provider_claim' => MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM,
            'claim' => MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM,
            'claim_provider_profile' => MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM,
            'provider_claim_listing' => MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM,
            'provider_add' => MyHH_Mia_Intent_Engine::INTENT_PROVIDER_ADD,
            'add_listing' => MyHH_Mia_Intent_Engine::INTENT_PROVIDER_ADD,
            'provider_add_listing' => MyHH_Mia_Intent_Engine::INTENT_PROVIDER_ADD,
            'provider_upgrade' => MyHH_Mia_Intent_Engine::INTENT_PROVIDER_UPGRADE,
            'provider_upgrade_or_visibility' => MyHH_Mia_Intent_Engine::INTENT_PROVIDER_UPGRADE,
            'sponsor' => MyHH_Mia_Intent_Engine::INTENT_SPONSOR,
            'sponsor_or_company_inquiry' => MyHH_Mia_Intent_Engine::INTENT_SPONSOR,
            'crisis' => MyHH_Mia_Intent_Engine::INTENT_CRISIS,
            'crisis_or_high_risk' => MyHH_Mia_Intent_Engine::INTENT_CRISIS,
            'general' => MyHH_Mia_Intent_Engine::INTENT_GENERAL,
            'general_unknown' => MyHH_Mia_Intent_Engine::INTENT_GENERAL,
            'provider_followup' => 'provider_followup',
            'followup' => 'provider_followup',
            'provider_detail' => 'provider_followup',
            'provider_claim_selection' => MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM,
        );

        return $map[$intent] ?? '';
    }

    private function normalize_grok_next_action($action) {
        $action = strtolower(trim((string) $action));
        $action = str_replace(array('-', ' '), '_', $action);
        $map = array(
            'search' => 'provider_search',
            'search_provider' => 'provider_search',
            'search_providers' => 'provider_search',
            'find_provider' => 'provider_search',
            'find_providers' => 'provider_search',
            'new_search' => 'provider_search',
            'more_results' => 'provider_search',
            'show_provider_detail' => 'provider_detail',
            'provider_details' => 'provider_detail',
            'details' => 'provider_detail',
            'tell_me_more' => 'provider_detail',
            'show_provider_location' => 'provider_location',
            'location' => 'provider_location',
            'address' => 'provider_location',
            'show_location' => 'provider_location',
            'insurance' => 'provider_insurance',
            'show_provider_insurance' => 'provider_insurance',
            'booking' => 'provider_booking',
            'appointment' => 'provider_booking',
            'compare' => 'provider_compare',
            'compare_providers' => 'provider_compare',
            'claim' => 'provider_claim',
            'claim_provider_profile' => 'provider_claim',
            'claim_profile' => 'provider_claim',
            'provider_claim_listing' => 'provider_claim',
            'claim_provider_listing' => 'provider_claim',
            'claim_selection' => 'claim_candidate_select',
            'select_claim_candidate' => 'claim_candidate_select',
            'add_provider_listing' => 'provider_add',
            'add_listing' => 'provider_add',
            'upgrade_listing' => 'provider_upgrade',
            'visibility' => 'provider_upgrade',
            'sponsor' => 'sponsor_inquiry',
            'sponsor_inquiry' => 'sponsor_inquiry',
            'crisis_response' => 'crisis',
            'general_reply' => 'general',
        );

        return $map[$action] ?? sanitize_key($action);
    }

    private function intent_from_grok_next_action($action) {
        $action = $this->normalize_grok_next_action($action);
        if (in_array($action, array('provider_detail', 'provider_location', 'provider_insurance', 'provider_booking', 'provider_compare'), true)) {
            return 'provider_followup';
        }
        if (in_array($action, array('provider_claim', 'claim_candidate_select'), true)) {
            return MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM;
        }
        if ($action === 'provider_search') {
            return MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH;
        }
        if ($action === 'provider_add') {
            return MyHH_Mia_Intent_Engine::INTENT_PROVIDER_ADD;
        }
        if ($action === 'provider_upgrade') {
            return MyHH_Mia_Intent_Engine::INTENT_PROVIDER_UPGRADE;
        }
        if ($action === 'sponsor_inquiry') {
            return MyHH_Mia_Intent_Engine::INTENT_SPONSOR;
        }
        if ($action === 'crisis') {
            return MyHH_Mia_Intent_Engine::INTENT_CRISIS;
        }
        if (in_array($action, array('general', 'platform_info', 'clarify'), true)) {
            return MyHH_Mia_Intent_Engine::INTENT_GENERAL;
        }
        return '';
    }

    private function grok_understanding_is_provider_followup($understanding) {
        if (empty($understanding) || !is_array($understanding)) {
            return false;
        }
        if (($understanding['intent'] ?? '') === 'provider_followup') {
            return true;
        }
        return in_array($understanding['next_action'] ?? $understanding['action'] ?? '', array(
            'provider_detail',
            'provider_location',
            'provider_insurance',
            'provider_booking',
            'provider_compare',
        ), true);
    }

    private function grok_understanding_is_provider_search($understanding) {
        if (empty($understanding) || !is_array($understanding)) {
            return false;
        }
        if (($understanding['intent'] ?? '') === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH) {
            return true;
        }
        return ($understanding['next_action'] ?? $understanding['action'] ?? '') === 'provider_search';
    }

    private function normalize_grok_context_dimension($value) {
        $value = strtolower(trim((string) $value));
        $value = str_replace(array('-', ' '), '_', $value);
        $map = array(
            'none' => '',
            'no_change' => '',
            'service' => 'specialty',
            'services' => 'specialty',
            'therapy_style' => 'specialty',
            'support_type' => 'specialty',
            'location' => 'city',
            'area' => 'city',
            'place' => 'city',
            'provider_type' => 'provider_type',
            'license' => 'provider_type',
            'credential' => 'provider_type',
            'therapy_modality' => 'modality',
            'approach' => 'modality',
            'provider_language' => 'language_preference',
            'spoken_language' => 'language_preference',
            'coverage' => 'insurance',
            'insurance_plan' => 'insurance',
            'visit_type' => 'session_type',
            'appointment_type' => 'session_type',
            'delivery_mode' => 'session_type',
        );
        $value = $map[$value] ?? $value;
        return in_array($value, array('category', 'specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type'), true) ? $value : '';
    }

    private function sanitize_grok_context_field_list($value) {
        $items = is_array($value) ? $value : preg_split('/[,|]+/', (string) $value);
        $clean = array();
        foreach ($items as $item) {
            $raw_field = sanitize_key((string) $item);
            if ($raw_field === 'recent_providers') {
                $clean[] = 'recent_providers';
                continue;
            }
            $field = $this->normalize_grok_context_dimension($item);
            if ($field !== '') {
                $clean[] = $field;
            }
        }
        return array_values(array_unique($clean));
    }

    private function sanitize_grok_search_entities($value) {
        $entities = array();
        if (!is_array($value)) {
            return $entities;
        }
        $map = array(
            'category' => 'primary_category_name',
            'primary_category' => 'primary_category_name',
            'primary_category_name' => 'primary_category_name',
            'specialty' => 'specialty',
            'city' => 'city',
            'location' => 'city',
            'provider_type' => 'provider_type',
            'modality' => 'modality',
            'language_preference' => 'language_preference',
            'provider_language' => 'language_preference',
            'insurance' => 'insurance',
            'insurance_plan' => 'insurance',
            'coverage' => 'insurance',
            'session_type' => 'session_type',
            'visit_type' => 'session_type',
            'delivery_mode' => 'session_type',
        );
        foreach ($map as $source_key => $target_key) {
            if (isset($value[$source_key]) && trim((string) $value[$source_key]) !== '') {
                $entities[$target_key] = sanitize_text_field($value[$source_key]);
            }
        }
        if (!empty($entities['session_type'])) {
            $normalized_session = $this->extract_session_type_entity_from_turn($entities['session_type']);
            if ($normalized_session !== '') {
                $entities['session_type'] = $normalized_session;
            } else {
                unset($entities['session_type']);
            }
        }
        if (!empty($entities['insurance'])) {
            $normalized_insurance = $this->extract_insurance_entity_from_turn($entities['insurance']);
            if ($normalized_insurance !== '') {
                $entities['insurance'] = $normalized_insurance;
            }
        }
        return $entities;
    }

    private function has_provider_search_context($safe_prior) {
        if (!is_array($safe_prior)) {
            return false;
        }
        foreach (array('recent_providers', 'primary_category', 'primary_category_name', 'specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type') as $field) {
            if (!empty($safe_prior[$field])) {
                return true;
            }
        }
        return false;
    }

    private function normalize_grok_category_entity($name, $safe_prior = array()) {
        $name = sanitize_text_field(html_entity_decode(wp_strip_all_tags((string) $name), ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8'));
        if ($name === '') {
            return array('', '');
        }

        $normalized_name = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $name)));
        $prior_name = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', (string) ($safe_prior['primary_category_name'] ?? ''))));
        if ($prior_name !== '' && ($normalized_name === $prior_name || strpos($normalized_name, $prior_name) !== false || strpos($prior_name, $normalized_name) !== false)) {
            return array(
                sanitize_title($safe_prior['primary_category'] ?? $name),
                sanitize_text_field($safe_prior['primary_category_name'] ?? $name),
            );
        }

        // The curated catalog is the stable source for the milestone's 12
        // categories. Resolve it before the dynamic taxonomy vocabulary so a
        // transient/cache-limited term lookup cannot turn a valid category
        // name into a name-only criterion that matches no directory records.
        foreach ($this->get_myhealthhub_category_knowledge_catalog() as $category_slug => $category) {
            $category_name = sanitize_text_field($category['name'] ?? '');
            $category_norm = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $category_name)));
            if ($category_norm !== '' && ($normalized_name === $category_norm || strpos($normalized_name, $category_norm) !== false || strpos($category_norm, $normalized_name) !== false)) {
                return array(sanitize_title($category_slug), $category_name);
            }
        }

        foreach ($this->build_grok_taxonomy_vocabulary()['known_categories'] ?? array() as $category) {
            $category_name = sanitize_text_field(html_entity_decode(wp_strip_all_tags((string) ($category['name'] ?? '')), ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8'));
            $category_norm = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $category_name)));
            if ($category_norm === '') {
                continue;
            }
            if ($normalized_name === $category_norm || strpos($normalized_name, $category_norm) !== false || strpos($category_norm, $normalized_name) !== false) {
                return array(
                    sanitize_title($category['slug'] ?? $category_name),
                    $category_name,
                );
            }
        }

        return array('', $name);
    }

    private function normalize_grok_search_entities_for_directory($search_entities) {
        $search_entities = is_array($search_entities) ? $search_entities : array();
        $generic_provider_type = strtolower(trim((string) ($search_entities['provider_type'] ?? '')));
        if ($generic_provider_type !== '' && preg_match('/^(?:therap(?:y|ist)|counsel(?:or|ing)|psychologist|mental health provider|adult|adults|adult therapy|adult therapist)$/i', $generic_provider_type)) {
            unset($search_entities['provider_type']);
            if (empty($search_entities['primary_category_name'])) {
                $search_entities['primary_category_name'] = 'Mental Health & Therapy';
            }
        }

        // A newly stated specialty is stronger evidence than a category carried
        // from an older search. Grok normally sends only newly extracted values
        // in search_entities, so infer the parent before preserved context is
        // merged. This prevents anxiety from searching an old Massage category.
        if (empty($search_entities['primary_category_name']) && !empty($search_entities['specialty'])) {
            $specialty = strtolower(trim((string) $search_entities['specialty']));
            $category_patterns = array(
                'Fitness, Yoga & Movement' => '/\b(?:physical\s+therap(?:y|ist)|physiotherap(?:y|ist)|yoga|pilates|fitness|movement|personal\s+train(?:er|ing))\b/i',
                'Integrative & Holistic Therapies' => '/\b(?:acupuncture|acupuncturist|reiki|ayurved(?:a|ic)|naturopath|holistic|integrative)\b/i',
                'Mental Health & Therapy' => '/\b(?:anxiety|panic|depress(?:ion|ed)|trauma|grief|stress|counsel(?:ing|ling)|therap(?:y|ist)|psycholog(?:y|ist)|psychiatr(?:y|ist)|cbt|dbt|emdr|mental\s+health)\b/i',
                'Nutrition & Dietary Support' => '/\b(?:nutrition|diet(?:ary|itian|ician)|meal\s+planning|weight\s+management)\b/i',
                'Massage, Spa & Recovery' => '/\b(?:massage|bodywork|spa|recovery|med\s*spa)\b/i',
                'Sleep & Rest Wellness' => '/\b(?:sleep|insomnia|rest\s+wellness)\b/i',
                'Financial Wellness & Planning' => '/\b(?:financial\s+(?:therapy|planning|planner|wellness)|money\s+coach)\b/i',
                'Career & Work-Life Coaching' => '/\b(?:career|work[-\s]?life|executive\s+coach|job\s+coach)\b/i',
                'Meditation, Mindfulness & Spirituality' => '/\b(?:meditation|mindfulness|spiritual(?:ity)?|breathwork)\b/i',
                'Personal Growth & Learning' => '/\b(?:personal\s+growth|life\s+coach|learning|self[-\s]?development)\b/i',
                'Social Connections & Support Groups' => '/\b(?:support\s+group|peer\s+support|social\s+connection|community\s+support)\b/i',
                'Primary Care & Medical Services' => '/\b(?:primary\s+care|family\s+medicine|medical\s+service|physician|doctor)\b/i',
            );
            foreach ($category_patterns as $category_name => $pattern) {
                if (preg_match($pattern, $specialty)) {
                    $search_entities['primary_category_name'] = $category_name;
                    break;
                }
            }
        }
        return $search_entities;
    }

    private function build_grok_provider_search_criteria($understanding, $safe_prior) {
        if (!$this->grok_understanding_is_provider_search($understanding) || !is_array($understanding)) {
            return array();
        }
        if (!empty($understanding['needs_clarification'])) {
            return array();
        }

        $change_dimension = $this->normalize_grok_context_dimension($understanding['change_dimension'] ?? '');
        $clear_context = $this->sanitize_grok_context_field_list($understanding['clear_context'] ?? array());
        $preserve_context = $this->sanitize_grok_context_field_list($understanding['preserve_context'] ?? array());
        // Generic role words describe a directory category, not a license.
        $search_entities = $this->normalize_grok_search_entities_for_directory(
            $this->sanitize_grok_search_entities($understanding['search_entities'] ?? array())
        );

        if ($change_dimension !== '' && !in_array($change_dimension, $clear_context, true)) {
            $clear_context[] = $change_dimension;
        }
        if ($change_dimension === 'specialty' && !in_array('provider_type', $clear_context, true)) {
            $clear_context[] = 'provider_type';
        }
        if ($change_dimension === 'category') {
            foreach (array('specialty', 'provider_type', 'modality') as $field) {
                if (!in_array($field, $clear_context, true)) {
                    $clear_context[] = $field;
                }
            }
        }

        $has_prior_context = $this->has_provider_search_context($safe_prior);
        if (empty($preserve_context) && $has_prior_context) {
            $preserve_context = array_values(array_diff(array('category', 'specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type'), $clear_context));
        }

        $has_actionable_contract = !empty($search_entities) || !empty($clear_context) || !empty($preserve_context) || $change_dimension !== '';
        if (!$has_actionable_contract) {
            return array();
        }

        $criteria = array();
        if (in_array('category', $preserve_context, true) && !empty($safe_prior['primary_category'])) {
            $criteria['primary_category'] = sanitize_title($safe_prior['primary_category']);
            $criteria['primary_category_name'] = sanitize_text_field($safe_prior['primary_category_name'] ?? '');
        }
        foreach (array('specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type') as $field) {
            if (in_array($field, $preserve_context, true) && !empty($safe_prior[$field])) {
                $criteria[$field] = sanitize_text_field($safe_prior[$field]);
            }
        }

        if (!empty($search_entities['primary_category_name'])) {
            list($category_slug, $category_name) = $this->normalize_grok_category_entity($search_entities['primary_category_name'], $safe_prior);
            if ($category_slug !== '') {
                $criteria['primary_category'] = $category_slug;
            } else {
                unset($criteria['primary_category']);
            }
            $criteria['primary_category_name'] = $category_name;
            $criteria['explicit_primary_category'] = true;
        }
        foreach (array('specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type') as $field) {
            if (!empty($search_entities[$field])) {
                $criteria[$field] = sanitize_text_field($search_entities[$field]);
                $criteria['explicit_' . $field] = true;
            }
        }

        $reset_fields = array();
        foreach ($clear_context as $field) {
            if ($field === 'category') {
                $reset_fields[] = 'primary_category';
                $reset_fields[] = 'primary_category_name';
            } elseif ($field !== 'recent_providers') {
                $reset_fields[] = $field;
            }
        }
        if (!empty($clear_context)) {
            $reset_fields[] = 'recent_providers';
            $reset_fields[] = 'last_provider_focus';
            $reset_fields[] = 'search_context_updated_at';
        }
        if (!empty($reset_fields)) {
            $criteria['reset_fields'] = array_values(array_unique($reset_fields));
            $criteria['context_shifted'] = true;
        }

        foreach (array('primary_category_name', 'specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type') as $field) {
            if (!empty($criteria[$field])) {
                return $criteria;
            }
        }

        return array();
    }

    private function build_realtime_search_location_question($understanding, $safe_prior, $message) {
        $criteria = $this->build_grok_provider_search_criteria($understanding, $safe_prior);
        if (empty($criteria) || !empty($criteria['city']) ||
            (empty($criteria['primary_category_name']) && empty($criteria['specialty']) && empty($criteria['provider_type']))) {
            return null;
        }
        // A city is not required for an explicitly unrestricted or online search.
        $location_optional = preg_match('/\b(?:online|virtual|telehealth|remote)\b/i', (string) ($criteria['session_type'] ?? ''))
            || preg_match('/\b(?:anywhere|any city|all cities|no location preference|location does not matter|location doesn.t matter|cualquier ciudad|todas las ciudades|sin preferencia de ubicacion)\b/i', remove_accents($message));
        if ($location_optional) {
            return null;
        }

        $language = $this->resolve_response_language($message, $safe_prior);
        $reply = $language === 'es'
            ? 'Claro. En que ciudad quieres que busque?'
            : 'Sure. Which city should I search?';
        $criteria['needs_clarification'] = true;
        $criteria['clarification_field'] = 'city';
        $criteria['reset_fields'] = array_values(array_unique(array_merge($criteria['reset_fields'] ?? array(),
            array('recent_providers', 'last_provider_focus', 'search_context_updated_at'))));
        $result = array(
            'intent' => MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH,
            'criteria' => $criteria,
            'providers' => array(),
            'detected_language' => $language,
            '_grok_understanding' => $understanding,
        );
        $updated_context = $this->build_updated_context($safe_prior, $result, $message);
        $updated_context['navigation_state'] = array_merge($updated_context['navigation_state'], array(
            'goal' => 'find_provider', 'stage' => 'clarifying', 'readiness' => 'ready',
            'open_question' => 'location', 'next_best_action' => 'clarify',
        ));
        $understanding['needs_clarification'] = true;
        $understanding['clarification_question'] = $reply;
        $understanding['navigation_state'] = $updated_context['navigation_state'];
        return array(
            'assistant_reply' => $reply,
            'response_type' => 'clarify_city',
            'intent' => $result['intent'],
            'criteria' => $criteria,
            'prior_context' => $updated_context,
            'detected_language' => $language,
            'providers' => array(),
            'provider_exposure' => array(),
            'near_me_detected' => false,
            'conversational_speech' => $reply,
            'navigation_state' => $updated_context['navigation_state'],
            'natural_response' => array('allowed' => false),
            'understanding' => $understanding,
        );
    }

    private function build_grok_direct_provider_search_result($understanding, $safe_prior, $engine = null, $context = array(), $message = '') {
        if (!$engine instanceof MyHH_Mia_Intent_Engine) {
            return null;
        }

        $criteria = $this->build_grok_provider_search_criteria($understanding, $safe_prior);
        if (empty($criteria)) {
            return null;
        }

        $providers = $engine->get_ranked_providers_from_context($criteria, is_array($context) ? $context : array());
        $broadened = null;
        if (!empty($criteria['specialty']) && empty($providers)) {
            $criteria['requested_specialty'] = $criteria['specialty'];
            unset($criteria['specialty'], $criteria['explicit_specialty']);
            $providers = $engine->get_ranked_providers_from_context($criteria, is_array($context) ? $context : array());
            $broadened = 'No exact specialty match; showing broader category options in the requested location.';
        }
        if (!empty($criteria['specialty']) && !empty($providers)) {
            $requested_specialty = strtolower(trim((string) $criteria['specialty']));
            $has_exact_specialty = false;
            foreach ($providers as $provider) {
                foreach ((array) ($provider['specialties'] ?? array()) as $provider_specialty) {
                    if (strtolower(trim((string) $provider_specialty)) === $requested_specialty) {
                        $has_exact_specialty = true;
                        break 2;
                    }
                }
            }
            if (!$has_exact_specialty) {
                $criteria['requested_specialty'] = $criteria['specialty'];
                unset($criteria['specialty'], $criteria['explicit_specialty']);
                $broadened = 'No exact specialty match; showing broader category options in the requested location.';
            }
        }
        $excluded_city = sanitize_text_field($understanding['excluded_city'] ?? '');
        if ($excluded_city === '') {
            $excluded_city = $this->extract_excluded_city_from_refinement($understanding['raw_text'] ?? $message, $safe_prior, is_array($context) ? $context : array());
        }
        if ($excluded_city !== '' && !empty($providers)) {
            $providers = array_values(array_filter($providers, function($provider) use ($excluded_city) {
                return strcasecmp((string) ($provider['city'] ?? ''), $excluded_city) !== 0;
            }));
        }

        mia_debug_log('GrokBrain', 'direct_search_executed', array(
            'message' => substr((string) $message, 0, 90),
            'next_action' => $understanding['next_action'] ?? '',
            'source' => $understanding['source'] ?? '',
            'confidence' => $understanding['confidence'] ?? null,
            'search_entities' => $this->sanitize_grok_search_entities($understanding['search_entities'] ?? array()),
            'change_dimension' => $this->normalize_grok_context_dimension($understanding['change_dimension'] ?? ''),
            'preserve_context' => $this->sanitize_grok_context_field_list($understanding['preserve_context'] ?? array()),
            'clear_context' => $this->sanitize_grok_context_field_list($understanding['clear_context'] ?? array()),
            'excluded_city' => $excluded_city,
            'criteria' => $criteria,
            'providers' => count($providers),
        ));

        return array(
            'intent'           => MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH,
            'criteria'         => $criteria,
            'providers'        => array_values($providers),
            'response'         => null,
            'fallback_used'    => false,
            'broadened'        => $broadened,
            'near_me_detected' => false,
            'detected_language' => sanitize_key($understanding['entities']['language'] ?? ($safe_prior['language'] ?? 'en')),
            '_grok_direct_search' => true,
            '_route_hint_override' => 'provider_search',
        );
    }

    private function extract_excluded_city_from_refinement($message, $safe_prior = array(), $context = array()) {
        $text = preg_replace('/\bin[-\s]?person(?:\s+only)?\b|\ben\s+persona\b/iu', '', trim((string) $message));
        $text = preg_replace('/\b(?:for|para)\s+(?:my|our|his|her|their|mi)\s+(?:father|mother|daughter|son|child|wife|husband|partner|parent|friend|padre|madre|hija|hijo)\b/iu', '', $text);
        if ($text === '') {
            return '';
        }

        $candidate = '';
        if (preg_match('/\b(?:except|besides|outside(?:\s+of)?|not\s+in|not)\s+([a-z][a-z .\'-]{2,45})(?:[?.!,]|$)/i', $text, $m)) {
            $candidate = trim($m[1]);
        }
        if ($candidate === '') {
            return '';
        }

        $candidate = preg_replace('/\b(?:city|area|location|please|providers?|options?|results?|matches?)\b.*$/i', '', $candidate);
        $candidate = trim($candidate);
        if (preg_match('/\b(?:virtual|online|person|hybrid|ready|insurance|claim|booking)\b/iu', $candidate)) return '';
        if ($candidate === '') {
            return '';
        }

        $known = array();
        foreach ((array) ($context['locations'] ?? array()) as $city) {
            $city = trim((string) $city);
            if ($city !== '') {
                $known[strtolower($city)] = $city;
            }
        }
        if (!empty($safe_prior['city'])) {
            $known[strtolower((string) $safe_prior['city'])] = (string) $safe_prior['city'];
        }

        $candidate_lower = strtolower($candidate);
        foreach ($known as $lower => $proper) {
            if ($candidate_lower === $lower || strpos($candidate_lower, $lower) !== false || strpos($lower, $candidate_lower) !== false) {
                return sanitize_text_field($proper);
            }
        }

        return sanitize_text_field(ucwords(strtolower($candidate)));
    }

    private function provider_followup_phrase_pattern() {
        return '/\b(?:tell\s+me\s+(?:more\s+)?(?:about|on)|more\s+(?:about|on)|what\s+about|how\s+about|details?\s+(?:on|about|for)|info(?:rmation)?\s+(?:on|about|for)|who\s+(?:is|was)|which\s+(?:of\s+)?(?:those|these|the)\s+(?:providers?|options?|matches?|results?)|which\s+(?:person|provider|one)\s+did\s+you\s+(?:recommend|suggest|mention)\s+(?:first|earlier|before)|(?:going\s+back\s+to|go\s+back\s+to|back\s+to).{0,90}(?:which|who|recommended?|suggested?|mentioned?|first)|nearest|closest|show\s+me\s+(?:more\s+)?details?\s+(?:on|about)?|can\s+you\s+tell\s+me\s+(?:more\s+)?(?:about|on)|could\s+you\s+tell\s+me\s+(?:more\s+)?(?:about|on)|quiero\s+saber\s+(?:m[aá]s\s+)?(?:sobre|de)|cu[eé]ntame\s+(?:m[aá]s\s+)?(?:sobre|de)|qu[eé]\s+tal\s+(?:con\s+)?|informaci[oó]n\s+(?:sobre|de))\b/iu';
    }

    private function normalize_provider_reference_text($text) {
        $text = strtolower((string) $text);
        $text = preg_replace('/[^a-z0-9áéíóúñü\s]+/iu', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    private function resolve_named_provider_from_recent_list($message, $providers) {
        if (empty($providers) || !is_array($providers)) {
            return null;
        }

        $msg = $this->normalize_provider_reference_text($message);
        if ($msg === '') {
            return null;
        }

        $stop = array_fill_keys(array(
            'dr', 'mr', 'mrs', 'ms', 'jr', 'sr', 'phd', 'lcsw', 'lpc', 'lmft', 'psyd',
            'tell', 'more', 'about', 'what', 'how', 'details', 'detail', 'info', 'information',
            'provider', 'providers', 'profile', 'listing', 'the', 'this', 'that', 'one',
            'can', 'could', 'you', 'me', 'please', 'show', 'who', 'was', 'is', 'on', 'for',
            'quiero', 'saber', 'mas', 'más', 'sobre', 'cuentame', 'cuéntame', 'informacion',
            'información', 'proveedor', 'proveedora'
        ), true);

        $best_provider = null;
        $best_score = 0;
        $tie = false;

        foreach (array_values($providers) as $provider) {
            $name = $this->normalize_provider_reference_text($provider['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $score = 0;
            if (strpos(' ' . $msg . ' ', ' ' . $name . ' ') !== false) {
                $score += 20;
            }

            $parts = preg_split('/\s+/u', $name);
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '' || strlen($part) < 3 || isset($stop[$part])) {
                    continue;
                }
                if (preg_match('/\b' . preg_quote($part, '/') . '\b/iu', $msg)) {
                    $score += strlen($part) >= 5 ? 4 : 2;
                }
            }

            if ($score > $best_score) {
                $best_provider = $provider;
                $best_score = $score;
                $tie = false;
            } elseif ($score > 0 && $score === $best_score) {
                $tie = true;
            }
        }

        return ($best_score >= 2 && !$tie) ? $best_provider : null;
    }

    private function resolve_recent_provider_name_followup($message, $safe_prior) {
        if (empty($safe_prior['recent_providers']) || !is_array($safe_prior['recent_providers'])) {
            return null;
        }
        if (!preg_match($this->provider_followup_phrase_pattern(), (string) $message) && !$this->is_existing_provider_fact_question($message)) {
            return null;
        }
        return $this->resolve_named_provider_from_recent_list($message, $this->get_provider_reference_pool($safe_prior['recent_providers'], $safe_prior));
    }

    private function get_provider_reference_pool($providers, $prior_context) {
        $pool = array();
        foreach (array_merge($providers, $prior_context['provider_history'] ?? array()) as $provider) {
            $key = absint($provider['id'] ?? 0) ?: $this->normalize_provider_reference_text($provider['name'] ?? '');
            if ($key !== '' && !isset($pool[$key])) $pool[$key] = $provider;
        }
        return array_values($pool);
    }

    private function infer_provider_followup_action($message) {
        $msg = strtolower((string) $message);
        if (preg_match('/\b(?:phone|telephone|call|contact|address|location|located|where|website|site|directions|map|office|nearest|closest|distance|how\s+far|ubicaci[oó]n|direcci[oó]n|donde|tel[eé]fono|contacto)\b/iu', $msg)) {
            return 'provider_location';
        }
        if (preg_match('/\b(?:insurance|insur|accept|coverage|seguro)\b/iu', $msg)) {
            return 'provider_insurance';
        }
        if (preg_match('/\b(?:book|booking|appointment|schedule|availability|available|cita|reservar|agenda)\b/iu', $msg)) {
            return 'provider_booking';
        }
        if (preg_match('/\b(?:compare|comparison|difference|differ|better|best|which one|recommend|comparar|diferencia|mejor)\b/iu', $msg)) {
            return 'provider_compare';
        }
        return 'provider_detail';
    }

    private function merge_forced_provider_followup_understanding($understanding, $raw_message, $clean_message, $provider) {
        $understanding = is_array($understanding) ? $understanding : array();
        $action = $this->infer_provider_followup_action($clean_message);
        $understanding['raw_text'] = sanitize_text_field($understanding['raw_text'] ?? $raw_message);
        $understanding['clean_text'] = sanitize_text_field($understanding['clean_text'] ?? $clean_message);
        $understanding['intent'] = 'provider_followup';
        $understanding['next_action'] = $action;
        $understanding['action'] = $action;
        $understanding['confidence'] = max((float) ($understanding['confidence'] ?? 0), 0.93);
        $understanding['target_provider_id'] = absint($provider['id'] ?? 0);
        $understanding['target_provider_name'] = sanitize_text_field($provider['name'] ?? '');
        $understanding['needs_clarification'] = false;
        $understanding['clarification_question'] = '';
        $understanding['source'] = $understanding['source'] ?? 'recent_provider_name_fast_path';
        $understanding['forced_recent_provider_followup'] = true;
        $understanding['entities'] = is_array($understanding['entities'] ?? null) ? $understanding['entities'] : array();
        $understanding['entities']['provider_name'] = sanitize_text_field($provider['name'] ?? '');
        $understanding['entities']['city'] = sanitize_text_field($provider['city'] ?? '');
        return $understanding;
    }

    private function build_fast_provider_search_refinement_understanding($message, $safe_prior) {
        $raw = trim((string) $message);
        $msg = strtolower($raw);
        if ($raw === '' || !is_array($safe_prior)) {
            return array();
        }

        $has_search_context = !empty($safe_prior['recent_providers'])
            || !empty($safe_prior['primary_category'])
            || !empty($safe_prior['primary_category_name'])
            || !empty($safe_prior['specialty'])
            || !empty($safe_prior['city'])
            || !empty($safe_prior['provider_type'])
            || !empty($safe_prior['modality'])
            || !empty($safe_prior['language_preference'])
            || !empty($safe_prior['insurance'])
            || !empty($safe_prior['session_type']);
        if (!$has_search_context) {
            return array();
        }

        $change_dimension = '';
        $clear_context = array();
        $preserve_context = array();
        $search_entities = array();

        // A service selection is not a location change, even after an earlier search.
        $selected_category = $this->extract_explicit_category_selection($raw);
        if ($selected_category !== '') {
            $change_dimension = 'category';
            $search_entities['category'] = $selected_category;
            $clear_context = array('category', 'specialty', 'provider_type', 'modality');
            $preserve_context = array('city', 'language_preference', 'insurance', 'session_type');
        }
        $explicit_city = $this->extract_explicit_search_city_from_turn($raw);
        $remove_city = preg_match('/\b(?:remove|clear|drop|forget|without|no)\s+(?:the\s+)?(?:city|location|area)\b/iu', $msg);
        if ($explicit_city !== '') {
            $change_dimension = 'city';
            $clear_context[] = 'city';
            $preserve_context = array_values(array_diff(array('category', 'specialty', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type'), $clear_context));
            $search_entities['city'] = $explicit_city;
        } elseif ($remove_city) {
            $change_dimension = 'city';
            $clear_context = array('city');
            $preserve_context = array('category', 'specialty', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type');
        }

        if (preg_match('/\b(?:cbt|cognitive\s+behavio(?:u)?ral\s+therapy)\b/iu', $msg)) {
            $search_entities['modality'] = 'CBT';
            $clear_context[] = 'modality';
        } elseif (preg_match('/\b(?:dbt|dialectical\s+behavio(?:u)?ral\s+therapy)\b/iu', $msg)) {
            $search_entities['modality'] = 'DBT';
            $clear_context[] = 'modality';
        } elseif (preg_match('/\b(?:emdr|eye\s+movement\s+desensitization)\b/iu', $msg)) {
            $search_entities['modality'] = 'EMDR';
            $clear_context[] = 'modality';
        }
        if (preg_match('/\b(?:spanish[-\s]?speaking|speaks?\s+spanish|bilingual|(?:alguien|persona|proveedor|terapeuta)\b[\s\S]{0,35}\bespa[ñn]ol|(?:hable|habla|que\s+hable)\s+espa[ñn]ol)\b/iu', $msg)) {
            $search_entities['language_preference'] = 'Spanish';
            $clear_context[] = 'language_preference';
        }
        $insurance = $this->extract_insurance_entity_from_turn($msg);
        if ($insurance !== '') {
            $search_entities['insurance'] = $insurance;
            $clear_context[] = 'insurance';
        }
        $session_type = $this->extract_session_type_entity_from_turn($msg);
        if ($session_type !== '') {
            $search_entities['session_type'] = $session_type;
            $clear_context[] = 'session_type';
        }
        if (preg_match('/\b(?:any\s+insurance|no\s+insurance\s+preference)\b|\b(?:remove|clear|forget)\b[\s\S]{0,24}\b(?:insurance|coverage|plan)\b/iu', $msg)) {
            unset($search_entities['insurance']);
            $change_dimension = 'insurance';
            $clear_context[] = 'insurance';
        }
        if (preg_match('/\b(?:any\s+(?:session|visit)\s+type|no\s+(?:session|visit)\s+preference)\b|\beither\b[\s\S]{0,40}\b(?:works|fine|okay|ok)\b|\b(?:remove|clear|forget)\b[\s\S]{0,24}\b(?:virtual|in[-\s]?person|session|visit)\b/iu', $msg)) {
            unset($search_entities['session_type']);
            $change_dimension = 'session_type';
            $clear_context[] = 'session_type';
        }
        if (!empty($search_entities['modality']) || !empty($search_entities['language_preference']) || !empty($search_entities['insurance']) || !empty($search_entities['session_type'])) {
            $preserve_context = array_values(array_unique(array_merge($preserve_context, array('category', 'specialty', 'city', 'provider_type'))));
        }

        if ($change_dimension === '' && !empty($search_entities['session_type'])) $change_dimension = 'session_type';
        if ($change_dimension === '' && preg_match('/\b(?:other|different|another|new)\s+(?:city|cities|area|areas|location|locations|place|places)\b|\b(?:except|besides|outside|not\s+in|not)\s+[a-z][a-z .\'-]{2,45}\b|\b(?:otra|otro|otras|otros|diferente|diferentes)\s+(?:ciudad|ciudades|zona|zonas|ubicaci[oó]n|ubicaciones)\b/iu', $msg)) {
            $change_dimension = 'city';
            $clear_context = array('city');
            $preserve_context = array('category', 'specialty', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type');
        } elseif ($change_dimension === '' && preg_match('/\b(?:other|different|another|new)\s+(?:specialt(?:y|ies)|service|services|therapy\s+style|therapy\s+styles|support\s+type|support\s+types)\b|\b(?:otra|otro|otras|otros|diferente|diferentes)\s+(?:especialidad|especialidades|servicio|servicios)\b/iu', $msg)) {
            $change_dimension = 'specialty';
            $clear_context = array('specialty', 'provider_type');
            $preserve_context = array('category', 'city', 'language_preference', 'insurance', 'session_type');
        } elseif ($change_dimension === '' && preg_match('/\b(?:show|give|find|get)\s+(?:me\s+)?(?:more|other|different|another|similar|strongest|best|top)\s+(?:available\s+)?(?:options?|providers?|matches?|results?)\b|\b(?:any|do\s+you\s+have)\s+(?:more|other|different|another)\s+(?:options?|providers?|matches?|results?)\b/iu', $msg)) {
            $change_dimension = 'none';
            $clear_context = array('recent_providers');
            $preserve_context = array('category', 'specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type');
        }

        $excluded_city = $this->extract_excluded_city_from_refinement($raw, $safe_prior);
        if ($excluded_city !== '') {
            $change_dimension = 'city';
            $clear_context = array_values(array_unique(array_merge($clear_context, array('city'))));
            $preserve_context = array_values(array_unique(array_merge($preserve_context, array('category', 'specialty', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type'))));
        }

        $clear_context = array_values(array_unique($clear_context));
        if ($change_dimension === '' && empty($clear_context) && empty($search_entities)) {
            return array();
        }

        mia_debug_log('GrokBrain', 'fast_refinement_contract', array(
            'message' => substr($raw, 0, 90),
            'change_dimension' => $change_dimension,
            'preserve_context' => $preserve_context,
            'clear_context' => $clear_context,
            'excluded_city' => $excluded_city,
        ));

        return array(
            'raw_text' => sanitize_text_field($raw),
            'clean_text' => sanitize_text_field($raw),
            'intent' => MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH,
            'next_action' => 'provider_search',
            'action' => 'provider_search',
            'confidence' => 0.91,
            'entities' => array('language' => $safe_prior['language'] ?? 'en'),
            'search_entities' => $search_entities,
            'excluded_city' => $excluded_city,
            'change_dimension' => $change_dimension,
            'preserve_context' => $preserve_context,
            'clear_context' => $clear_context,
            'target_provider_rank' => 0,
            'target_provider_id' => 0,
            'target_provider_name' => '',
            'needs_clarification' => false,
            'clarification_question' => '',
            'source' => 'grok_state_fast_path',
            'status' => 'fast_path',
        );
    }

    private function extract_explicit_category_selection($message) {
        if (!preg_match('/\b(?:switch|change|instead|find|search|show|use|looking\s+for|i\s+(?:need|want|said|meant)|cambia|cambiar|busca|busco|buscar|muestra|necesito|encuentra|quise\s+decir)\b/iu', (string) $message)
            || $this->has_explicit_search_defer_signal($message)
            || MyHH_Mia_Intent_Engine::is_basic_education_question($message)) {
            return '';
        }
        $best = '';
        $length = 0;
        foreach ($this->get_myhealthhub_category_knowledge_catalog() as $item) {
            foreach (array_merge(array($item['name']), $item['aliases']) as $alias) {
                if (strlen($alias) > $length && preg_match('/\b' . preg_quote($alias, '/') . '\b/iu', $message)) {
                    $best = $item['name'];
                    $length = strlen($alias);
                }
            }
        }
        if (preg_match('/\batenci[oó]n\s+primaria\b/iu', $message)) {
            $best = 'Primary Care & Medical Services';
        }
        return $best;
    }

    private function extract_explicit_search_city_from_turn($message) {
        $text = preg_replace('/\bin[-\s]?person(?:\s+only)?\b|\ben\s+persona\b/iu', '', trim((string) $message));
        $text = preg_replace('/\b(?:for|para)\s+(?:my|our|his|her|their|mi)\s+(?:father|mother|daughter|son|child|wife|husband|partner|parent|friend|padre|madre|hija|hijo)\b/iu', '', $text);
        if ($text === '') {
            return '';
        }
        $candidate = '';
        if (preg_match('/\b(?:i(?:\'m|\s+am)\s+(?:located\s+)?in|i\s+live\s+in)\s+([a-z][a-z .\'-]{1,55})(?:[?.!,]|$)/i', $text, $match)) {
            $candidate = $match[1];
        } elseif (preg_match('/\b(?:use|switch|change|move)(?:\s+(?:the\s+)?(?:city|location|area))?\s+(?:to\s+)?([a-z][a-z .\'-]{1,55}?)(?:\s+instead)?(?:[?.!,]|$)/i', $text, $match)) {
            $candidate = $match[1];
        } elseif (preg_match('/\b(?:in|near|around|from|at)\s+([a-z][a-z .\'-]{1,55}?)(?:\s+(?:later|eventually|when\s+i(?:\'m|\s+am)\s+ready)|[?.!,]|$)/i', $text, $match)) {
            $candidate = $match[1];
        }
        $candidate = preg_replace('/\b(?:north\s+carolina|nc)\b/i', '', (string) $candidate);
        $candidate = preg_replace('/\b(?:later|eventually|but|and|while|with|keeping|keep|same|instead)\b[\s\S]*$/i', '', (string) $candidate);
        $candidate = trim((string) preg_replace('/\s+/', ' ', (string) $candidate), " ,.");
        if (preg_match('/\b(?:providers?|services?|primary\s+care|therapy|therapists?|counseling|insurance|virtual|online|in\s+person|atenci[oó]n|terapia|proveedores?)\b|^(?:english|spanish|espa[ñn]ol|ingl[eé]s)$/iu', $candidate)) {
            return '';
        }
        foreach ($this->get_myhealthhub_category_knowledge_catalog() as $item) {
            if (in_array(strtolower($candidate), array_map('strtolower', array_merge(array($item['name']), $item['aliases'])), true)) return '';
        }
        return $candidate === '' ? '' : sanitize_text_field(ucwords(strtolower($candidate)));
    }

    private function extract_city_from_provider_search_fragment($message) {
        $text = preg_replace('/\bin[-\s]?person(?:\s+only)?\b|\ben\s+persona\b/iu', '', trim((string) $message));
        $text = preg_replace('/\b(?:for|para)\s+(?:my|our|his|her|their|mi)\s+(?:father|mother|daughter|son|child|wife|husband|partner|parent|friend|padre|madre|hija|hijo)\b/iu', '', $text);
        if ($text === '') {
            return '';
        }

        if (preg_match('/\b(?:in|near|around|from|at|en|cerca\s+de)\s+([\p{L}][\p{L} .\'’-]{1,45})(?:\s+i\s+need|\s+need|\s+for|\s+provider|\s+providers|\s+therapist|\s+counselor|\s+para|\s+proveedor|\s+terapeuta|[?.!,]|$)/iu', $text, $m)) {
            $city = trim((string) $m[1], " \t\n\r\0\x0B,.;:!?");
            $city = preg_replace('/\b(?:later|eventually|but|and|while|with|keeping|keep|same|instead)\b[\s\S]*$/iu', '', $city);
            $city = trim((string) $city, " \t\n\r\0\x0B,.;:!?");
            if (!preg_match('/^(?:english|spanish|espa[ñn]ol|ingl[eé]s)$/iu', $city)) {
                return sanitize_text_field(ucwords(strtolower($city)));
            }
        }

        return '';
    }

    private function force_provider_search_continuation_result_if_needed($message, $route_hint, $safe_prior, $result, $engine = null, $context = array()) {
        if (!is_array($result)) {
            return $result;
        }

        $intent = $result['intent'] ?? '';
        $is_general = $intent === MyHH_Mia_Intent_Engine::INTENT_GENERAL || $intent === 'general_unknown' || $intent === '';
        if ($route_hint !== 'provider_search' || !$is_general) {
            return $result;
        }

        $msg = strtolower(trim((string) $message));
        if ($msg === '' || preg_match('/\b(?:claim|claimed|claiming|manage|verify|my\s+profile|my\s+listing|reclamar|gestionar|verificar)\b/iu', $msg)) {
            return $result;
        }

        $city = $this->extract_city_from_provider_search_fragment($message);

        $continuation_result = null;
        $forced_message = 'find providers ' . trim((string) $message);
        if ($engine instanceof MyHH_Mia_Intent_Engine) {
            $continuation_result = $engine->process($forced_message, is_array($context) ? $context : array(), $safe_prior);
        }

        if (is_array($continuation_result)
            && ($continuation_result['intent'] ?? '') === MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH
            && !empty($continuation_result['criteria'])
            && empty($continuation_result['criteria']['needs_clarification'])) {
            mia_debug_log('GrokNextAction', 'provider_search_continuation_forced', array(
                'message' => substr((string) $message, 0, 90),
                'forced_message' => substr($forced_message, 0, 100),
                'criteria' => $continuation_result['criteria'] ?? array(),
                'providers' => count($continuation_result['providers'] ?? array()),
                'previous_intent' => $intent,
            ));
            $continuation_result['_route_hint_override'] = 'provider_search';
            $continuation_result['_forced_provider_search_continuation'] = true;
            $continuation_result['_grok_understanding'] = $result['_grok_understanding'] ?? array();
            return $continuation_result;
        }

        $lang = $safe_prior['language'] ?? 'en';
        $city_label = $city ? ucwords(strtolower($city)) : '';
        if ($lang === 'es') {
            $response = $city_label
                ? "Entendido, {$city_label}. Que tipo de proveedor necesitas: terapia, consejeria, psiquiatria u otro servicio?"
                : "Claro. Que tipo de proveedor necesitas y en que ciudad estas?";
        } else {
            $response = $city_label
                ? "Got it, {$city_label}. What kind of provider do you need: therapy, counseling, psychiatry, or another service?"
                : "I can help with that. What kind of provider do you need, and which city are you in?";
        }

        mia_debug_log('GrokNextAction', 'provider_search_continuation_clarify', array(
            'message' => substr((string) $message, 0, 90),
            'forced_message' => substr($forced_message, 0, 100),
            'city' => $city_label,
            'previous_intent' => $intent,
        ));

        $result['intent'] = MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH;
        $result['criteria'] = is_array($result['criteria'] ?? null) ? $result['criteria'] : array();
        if ($city_label) {
            $result['criteria']['city'] = $city_label;
        }
        $result['criteria']['needs_clarification'] = true;
        $result['providers'] = array();
        $result['response'] = $response;
        $result['fallback_used'] = false;
        $result['broadened'] = null;
        $result['near_me_detected'] = false;
        $result['detected_language'] = $lang;
        $result['_route_hint_override'] = 'provider_search';
        return $result;
    }

    private function apply_grok_action_contract_to_result($result, $understanding, $safe_prior, $engine = null, $context = array()) {
        if (!is_array($result) || !$this->grok_understanding_is_provider_search($understanding)) {
            return $result;
        }
        if (!empty($understanding['needs_clarification']) || (($result['intent'] ?? '') !== MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH)) {
            return $result;
        }

        $change_dimension = $this->normalize_grok_context_dimension($understanding['change_dimension'] ?? '');
        $clear_context = $this->sanitize_grok_context_field_list($understanding['clear_context'] ?? array());
        $preserve_context = $this->sanitize_grok_context_field_list($understanding['preserve_context'] ?? array());
        $search_entities = $this->normalize_grok_search_entities_for_directory(
            $this->sanitize_grok_search_entities($understanding['search_entities'] ?? array())
        );
        $excluded_city = sanitize_text_field($understanding['excluded_city'] ?? '');
        if ($excluded_city === '') {
            $excluded_city = $this->extract_excluded_city_from_refinement($understanding['raw_text'] ?? '', $safe_prior, $context);
        }
        $criteria = is_array($result['criteria'] ?? null) ? $result['criteria'] : array();
        if (!empty($criteria['needs_clarification']) && empty($change_dimension) && empty($clear_context) && empty($search_entities)) {
            return $result;
        }
        if (!empty($criteria['needs_clarification']) && (!empty($change_dimension) || !empty($clear_context) || !empty($search_entities))) {
            unset($criteria['needs_clarification']);
        }
        $original_criteria = $criteria;

        if ($change_dimension !== '' && !in_array($change_dimension, $clear_context, true)) {
            $clear_context[] = $change_dimension;
        }
        if ($change_dimension === 'specialty' && !in_array('provider_type', $clear_context, true)) {
            $clear_context[] = 'provider_type';
        }
        if ($change_dimension === 'category') {
            foreach (array('specialty', 'provider_type', 'modality') as $field) {
                if (!in_array($field, $clear_context, true)) {
                    $clear_context[] = $field;
                }
            }
        }
        if (empty($preserve_context)) {
            $preserve_context = array_values(array_diff(array('category', 'specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type'), $clear_context));
        }

        foreach ($clear_context as $field) {
            switch ($field) {
                case 'category':
                    unset($criteria['primary_category'], $criteria['primary_category_name'], $criteria['explicit_primary_category']);
                    break;
                case 'specialty':
                    unset($criteria['specialty'], $criteria['explicit_specialty']);
                    break;
                case 'city':
                    unset($criteria['city'], $criteria['explicit_city']);
                    break;
                case 'provider_type':
                    unset($criteria['provider_type'], $criteria['explicit_provider_type']);
                    break;
                case 'modality':
                    unset($criteria['modality'], $criteria['explicit_modality']);
                    break;
                case 'language_preference':
                    unset($criteria['language_preference'], $criteria['explicit_language_preference']);
                    break;
                case 'insurance':
                    unset($criteria['insurance'], $criteria['explicit_insurance']);
                    break;
                case 'session_type':
                    unset($criteria['session_type'], $criteria['explicit_session_type']);
                    break;
            }
        }

        if (!empty($search_entities['primary_category_name'])) {
            list($category_slug, $category_name) = $this->normalize_grok_category_entity($search_entities['primary_category_name'], $safe_prior);
            if ($category_slug !== '') {
                $criteria['primary_category'] = $category_slug;
            } else {
                unset($criteria['primary_category']);
            }
            $criteria['primary_category_name'] = $category_name;
            $criteria['explicit_primary_category'] = true;
        }
        foreach (array('specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type') as $field) {
            if (!empty($search_entities[$field])) {
                $criteria[$field] = $search_entities[$field];
                $criteria['explicit_' . $field] = true;
            }
        }

        if (in_array('category', $preserve_context, true) && empty($criteria['primary_category']) && !empty($safe_prior['primary_category'])) {
            $criteria['primary_category'] = $safe_prior['primary_category'];
            $criteria['primary_category_name'] = $safe_prior['primary_category_name'] ?? ($criteria['primary_category_name'] ?? '');
        }
        foreach (array('specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type') as $field) {
            if (in_array($field, $preserve_context, true) && empty($criteria[$field]) && !empty($safe_prior[$field])) {
                $criteria[$field] = $safe_prior[$field];
            }
        }

        $reset_fields = is_array($criteria['reset_fields'] ?? null) ? $criteria['reset_fields'] : array();
        foreach ($clear_context as $field) {
            if ($field === 'category') {
                $reset_fields[] = 'primary_category';
                $reset_fields[] = 'primary_category_name';
            } else {
                $reset_fields[] = $field;
            }
        }
        if (!empty($clear_context)) {
            $reset_fields[] = 'recent_providers';
            $reset_fields[] = 'last_provider_focus';
            $reset_fields[] = 'search_context_updated_at';
        }
        $criteria['reset_fields'] = array_values(array_unique($reset_fields));
        $criteria['context_shifted'] = !empty($criteria['reset_fields']);

        $criteria_changed = wp_json_encode($criteria) !== wp_json_encode($original_criteria);
        if (!$criteria_changed && empty($change_dimension) && empty($search_entities)) {
            return $result;
        }

        $providers = array();
        if ($engine instanceof MyHH_Mia_Intent_Engine) {
            $providers = $engine->get_ranked_providers_from_context($criteria, is_array($context) ? $context : array());
        }
        if (!empty($criteria['specialty']) && empty($providers) && $engine instanceof MyHH_Mia_Intent_Engine) {
            $criteria['requested_specialty'] = $criteria['specialty'];
            unset($criteria['specialty'], $criteria['explicit_specialty']);
            $providers = $engine->get_ranked_providers_from_context($criteria, is_array($context) ? $context : array());
            $result['broadened'] = 'No exact specialty match; showing broader category options in the requested location.';
        }
        if ($excluded_city !== '' && !empty($providers)) {
            $providers = array_values(array_filter($providers, function($provider) use ($excluded_city) {
                return strcasecmp((string) ($provider['city'] ?? ''), $excluded_city) !== 0;
            }));
        }
        if (!empty($criteria['specialty']) && !empty($providers)) {
            $requested_specialty = strtolower(trim((string) $criteria['specialty']));
            $has_exact_specialty = false;
            foreach ($providers as $provider) {
                foreach ((array) ($provider['specialties'] ?? array()) as $provider_specialty) {
                    if (strtolower(trim((string) $provider_specialty)) === $requested_specialty) {
                        $has_exact_specialty = true;
                        break 2;
                    }
                }
            }
            if (!$has_exact_specialty) {
                $criteria['requested_specialty'] = $criteria['specialty'];
                unset($criteria['specialty'], $criteria['explicit_specialty']);
                $result['broadened'] = 'No exact specialty match; showing broader category options in the requested location.';
            }
        }
        if (!empty($providers) || $criteria_changed) {
            $result['criteria'] = $criteria;
            $result['providers'] = array_values($providers);
            $result['response'] = null;
            $result['fallback_used'] = false;
            $result['broadened'] = !empty($change_dimension) ? ('Adjusted search by ' . $change_dimension) : ($result['broadened'] ?? null);
            $result['_grok_action_contract_applied'] = true;
            mia_debug_log('GrokBrain', 'action_contract_applied', array(
                'next_action' => $understanding['next_action'] ?? '',
                'change_dimension' => $change_dimension,
                'preserve_context' => $preserve_context,
                'clear_context' => $clear_context,
                'search_entities' => $search_entities,
                'excluded_city' => $excluded_city,
                'criteria' => $criteria,
                'providers' => count($providers),
            ));
        }

        return $result;
    }

    private function broaden_empty_specialty_search_result($result, $engine, $context = array()) {
        if (!is_array($result)
            || !$engine instanceof MyHH_Mia_Intent_Engine
            || ($result['intent'] ?? '') !== MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH
            || !empty($result['providers'])) {
            return $result;
        }

        $criteria = is_array($result['criteria'] ?? null) ? $result['criteria'] : array();
        $specialty = sanitize_text_field($criteria['specialty'] ?? '');
        if ($specialty === '') {
            return $result;
        }

        $criteria['requested_specialty'] = sanitize_text_field($criteria['requested_specialty'] ?? $specialty);
        unset($criteria['specialty'], $criteria['explicit_specialty']);
        $providers = $engine->get_ranked_providers_from_context($criteria, is_array($context) ? $context : array());
        $result['criteria'] = $criteria;
        $result['broadened'] = 'No exact specialty match; showing broader category options in the requested location.';
        if (!empty($providers)) {
            $result['providers'] = array_values($providers);
            $result['response'] = null;
            $result['fallback_used'] = false;
        }

        return $result;
    }

    /**
     * Extract high-confidence entities for the no-model search fast path.
     */
    private function extract_insurance_entity_from_turn($message) {
        $message = strtolower(trim((string) $message));
        $plans = array(
            'Blue Cross Blue Shield' => '/\b(?:blue\s+cross(?:\s+blue\s+shield)?|blue\s+shield|bcbs)\b/iu',
            'UnitedHealthcare' => '/\b(?:united\s*health\s*care|unitedhealthcare|uhc)\b/iu',
            'Aetna' => '/\baetna\b/iu',
            'Cigna' => '/\bcigna\b/iu',
            'Humana' => '/\bhumana\b/iu',
            'Medicaid' => '/\bmedicaid\b/iu',
            'Medicare' => '/\bmedicare\b/iu',
            'TRICARE' => '/\btricare\b/iu',
            'Self-Pay' => '/\b(?:self[-\s]?pay|private\s+pay|cash\s+pay|pago\s+particular)\b/iu',
            'Sliding Scale' => '/\b(?:sliding\s+scale|escala\s+m[oó]vil)\b/iu',
        );
        foreach ($plans as $name => $pattern) {
            if (preg_match($pattern, $message)) {
                return $name;
            }
        }
        return '';
    }

    private function extract_session_type_entity_from_turn($message) {
        $message = strtolower(trim((string) $message));
        if (preg_match('/\b(?:any\s+(?:session|visit)\s+type|no\s+(?:session|visit)\s+preference)\b|\beither\b[\s\S]{0,40}\b(?:works|fine|okay|ok)\b/iu', $message)) return '';
        // Remove negated alternatives before selecting the affirmative value.
        $positive = preg_replace('/\b(?:not|no|without|do\s+not\s+want|don[\x{2019}\x{27}]t\s+want)\s+(?:virtual|online|telehealth|teletherapy|in[-\s]?person|hybrid)\b/iu', '', $message);
        if (preg_match('/\b(?:hybrid|h[ií]brid[oa])\b/iu', $positive)) return 'Hybrid';
        if (preg_match('/\b(?:in[-\s]?person|face[-\s]?to[-\s]?face|at\s+(?:their|the)\s+office|presencial|en\s+persona)\b/iu', $positive)) return 'In-Person';
        if (preg_match('/\b(?:virtual|online|telehealth|teletherapy|video\s+(?:visit|session)|remote|telesalud|en\s+l[ií]nea|por\s+video)\b/iu', $positive)) return 'Virtual';
        if (preg_match('/\b(?:not|no)\s+(?:virtual|online|telehealth)\b/iu', $message)) return 'In-Person';
        if (preg_match('/\b(?:not|no)\s+in[-\s]?person\b/iu', $message)) return 'Virtual';
        return '';
    }

    private function infer_fast_provider_search_entities_from_turn($message) {
        $msg = strtolower(trim((string) $message));
        $entities = array();

        $specialties = array(
            'Physical Therapy' => '/\b(?:physical\s+therap(?:y|ists?)|physiotherap(?:y|ists?)|fisioterapia)\b/iu',
            'Anxiety' => '/\b(?:anxiety|anxious|panic|ansiedad|ansioso|ansiosa|p[aá]nico)\b/iu',
            'Depression' => '/\b(?:depression|depressed|depresi[oó]n|deprimid[oa])\b/iu',
            'Trauma' => '/\b(?:trauma|ptsd|tept)\b/iu',
            'General Counseling' => '/\b(?:general\s+counsel(?:ing|ling)|consejer[ií]a\s+general)\b/iu',
            'Massage Therapy' => '/\b(?:massage\s+therapy|massage|terapia\s+de\s+masaje|masaje)\b/iu',
            'Acupuncture' => '/\b(?:acupuncture|acupuncturist|acupuntura|acupunturista)\b/iu',
            'Nutrition' => '/\b(?:nutrition|dietitian|nutritionist|nutrici[oó]n|dietista|nutricionista)\b/iu',
        );
        foreach ($specialties as $specialty => $pattern) {
            if (preg_match($pattern, $msg)) {
                $entities['specialty'] = $specialty;
                break;
            }
        }

        if (preg_match('/\b(?:physical\s+therap(?:y|ists?)|physiotherap(?:y|ists?)|fisioterapia)\b/iu', $msg)) {
            $entities['primary_category_name'] = 'Fitness, Yoga & Movement';
        } elseif (preg_match('/\b(?:massage|masaje|bodywork)\b/iu', $msg)) {
            $entities['primary_category_name'] = 'Massage, Spa & Recovery';
        } elseif (preg_match('/\b(?:therapist|therapy|counselor|counseling|psychologist|psychiatrist|mental\s+health|terapeuta|terapia|consejer[oa]|consejer[ií]a|psic[oó]log[oa]|psiquiatra|salud\s+mental|ansiedad|depresi[oó]n|trauma)\b/iu', $msg)) {
            $entities['primary_category_name'] = 'Mental Health & Therapy';
        } elseif (preg_match('/\b(?:massage|masaje|spa|bodywork)\b/iu', $msg)) {
            $entities['primary_category_name'] = 'Massage, Spa & Recovery';
        } elseif (preg_match('/\b(?:acupuncture|acupuntura|reiki|holistic|integrative|hol[ií]stic[oa]|integrativ[oa])\b/iu', $msg)) {
            $entities['primary_category_name'] = 'Integrative & Holistic Therapies';
        } elseif (preg_match('/\b(?:nutrition|dietitian|nutritionist|nutrici[oó]n|dietista|nutricionista)\b/iu', $msg)) {
            $entities['primary_category_name'] = 'Nutrition & Dietary Support';
        }

        $city = $this->extract_city_from_provider_search_fragment($message);
        if ($city !== '') {
            $entities['city'] = $city;
        }
        if (preg_match('/\b(?:cbt|cognitive\s+behavio(?:u)?ral\s+therapy|terapia\s+cognitivo[-\s]?conductual)\b/iu', $msg)) {
            $entities['modality'] = 'CBT';
        } elseif (preg_match('/\b(?:dbt|dialectical\s+behavio(?:u)?ral\s+therapy)\b/iu', $msg)) {
            $entities['modality'] = 'DBT';
        } elseif (preg_match('/\b(?:emdr|eye\s+movement\s+desensitization)\b/iu', $msg)) {
            $entities['modality'] = 'EMDR';
        }
        if (preg_match('/\b(?:spanish[-\s]?speaking|speaks?\s+spanish|(?:hable|habla|que\s+hable)\s+espa[ñn]ol|bilingual|biling[uü]e)\b/iu', $msg)) {
            $entities['language_preference'] = 'Spanish';
        }
        $insurance = $this->extract_insurance_entity_from_turn($msg);
        if ($insurance !== '') {
            $entities['insurance'] = $insurance;
        }
        $session_type = $this->extract_session_type_entity_from_turn($msg);
        if ($session_type !== '') {
            $entities['session_type'] = $session_type;
        }

        return $this->normalize_grok_search_entities_for_directory($entities);
    }

    private function build_fast_state_understanding($message, $route_hint, $safe_prior) {
        $raw = trim((string) $message);
        $msg = strtolower($raw);
        if ($raw === '') {
            return array();
        }

        if (preg_match('/\b(?:switch|change|go|reply|respond)(?:\s+back)?\s+to\s+english\b|\bin\s+english\b/iu', $msg)) {
            $safe_prior['language'] = 'en';
        } elseif (preg_match('/\b(?:switch|change|go|reply|respond|vuelve|cambia)(?:\s+back)?\s+(?:to|a|al)\s+(?:spanish|espa[ñn]ol)\b|\ben\s+espa[ñn]ol\b/iu', $msg) ||
            preg_match('/[¿¡]/u', $raw) ||
            preg_match('/\b(?:busco|buscar|terapeuta|ansiedad|prefiero|alguien|hable|proveedor|proveedores|recomendaste|vuelve|dame|enlaces|perfil|perfiles)\b/iu', $msg)) {
            $safe_prior['language'] = 'es';
        }

        $is_provider_fact = !empty($safe_prior['recent_providers']) && $this->is_existing_provider_fact_question($raw);
        if (MyHH_Mia_Intent_Engine::is_basic_education_question($raw) && !$is_provider_fact) {
            return array(
                'raw_text'               => sanitize_text_field($raw),
                'clean_text'             => sanitize_text_field($raw),
                'intent'                 => MyHH_Mia_Intent_Engine::INTENT_GENERAL,
                'next_action'            => 'general',
                'action'                 => 'general',
                'confidence'             => 0.98,
                'entities'               => array('language' => $safe_prior['language'] ?? 'en'),
                'target_provider_rank'   => 0,
                'target_provider_id'     => 0,
                'target_provider_name'   => '',
                'needs_clarification'    => false,
                'clarification_question' => '',
                'source'                 => 'basic_education_fast_path',
                'status'                 => 'fast_path',
            );
        }

        if ($is_provider_fact) {
            return array(
                'raw_text' => $raw, 'clean_text' => $raw,
                'intent' => 'provider_followup', 'next_action' => 'provider_detail', 'action' => 'provider_detail',
                'confidence' => 0.97, 'entities' => array('language' => $safe_prior['language'] ?? 'en'),
                'source' => 'existing_provider_facts', 'status' => 'fast_path',
                'forced_recent_provider_followup' => true, 'needs_clarification' => false,
            );
        }
        $refinement_understanding = $this->build_fast_provider_search_refinement_understanding($raw, $safe_prior);
        if (!empty($refinement_understanding)) {
            return $refinement_understanding;
        }

        $named_followup_provider = $this->resolve_recent_provider_name_followup($raw, $safe_prior);
        if ($named_followup_provider) {
            $action = $this->infer_provider_followup_action($raw);
            mia_debug_log('GrokNextAction', 'fast_named_provider_followup', array(
                'message' => substr($raw, 0, 80),
                'provider' => $named_followup_provider['name'] ?? '',
                'provider_id' => absint($named_followup_provider['id'] ?? 0),
                'action' => $action,
            ));
            return array(
                'raw_text' => sanitize_text_field($raw),
                'clean_text' => sanitize_text_field($raw),
                'intent' => 'provider_followup',
                'next_action' => $action,
                'action' => $action,
                'confidence' => 0.93,
                'entities' => array(
                    'provider_name' => sanitize_text_field($named_followup_provider['name'] ?? ''),
                    'city' => sanitize_text_field($named_followup_provider['city'] ?? ''),
                    'language' => $safe_prior['language'] ?? 'en',
                ),
                'target_provider_id' => absint($named_followup_provider['id'] ?? 0),
                'target_provider_name' => sanitize_text_field($named_followup_provider['name'] ?? ''),
                'needs_clarification' => false,
                'clarification_question' => '',
                'source' => 'recent_provider_name_fast_path',
                'status' => 'fast_path',
                'forced_recent_provider_followup' => true,
            );
        }

        if ($route_hint === 'provider_followup' && !empty($safe_prior['recent_providers'])) {
            // ── Guard against route_hint pollution ──
            // route_hint='provider_followup' can survive across turns.  When the
            // CURRENT message clearly signals a NEW search ("find me X", "looking
            // for Y", "I need a provider", "show me more options"), do NOT take
            // the followup path — fall through so the search fast-path or Grok
            // can handle it correctly.
            // (Debug CSV 1778551826587 T9: "Find the media Find immediate general
            //  call Council counseling" was forced to provider_location and
            //  surfaced ALEXIA ALI's contact info despite being a search request.)
            $is_clear_search = preg_match('/\b(?:find|search|show|looking\s+for|i\s+need|i\s+want|can\s+you\s+find|could\s+you\s+find|buscar|busco|mu[eé]strame|necesito|encuentra)\b/iu', $msg) &&
                !preg_match('/\b(?:that\s+one|this\s+one|the\s+(?:first|second|third|fourth|fifth|last|one|provider|profile|listing)|first\s+one|second\s+one|tell\s+me\s+more|more\s+about|what\s+about|this\s+provider|that\s+provider)\b/iu', $msg);
            if ($is_clear_search) {
                // Fall through — let search fast-path or Grok handle.
            } else {
                $action = 'provider_detail';
                if (preg_match('/\b(?:phone|telephone|call|contact|address|location|located|where|website|site|directions|map|office|ubicaci[oó]n|direcci[oó]n|donde|tel[eé]fono|contacto)\b/iu', $msg)) {
                    $action = 'provider_location';
                } elseif (preg_match('/\b(?:insurance|insur|accept|coverage|seguro)\b/iu', $msg)) {
                    $action = 'provider_insurance';
                } elseif (preg_match('/\b(?:book|booking|appointment|schedule|availability|available|cita|reservar|agenda)\b/iu', $msg)) {
                    $action = 'provider_booking';
                } elseif (preg_match('/\b(?:compare|comparison|difference|differ|better|best|which one|recommend|comparar|diferencia|mejor)\b/iu', $msg)) {
                    $action = 'provider_compare';
                }

            $rank = 0;
            $rank_map = array(
                '/\b(?:first|1st|number one|#1|uno|primero|primera)\b/iu' => 1,
                '/\b(?:second|2nd|number two|#2|dos|segundo|segunda)\b/iu' => 2,
                '/\b(?:third|3rd|number three|#3|tres|tercero|tercera)\b/iu' => 3,
                '/\b(?:fourth|4th|number four|#4|cuatro|cuarto|cuarta)\b/iu' => 4,
            );
            foreach ($rank_map as $pattern => $value) {
                if (preg_match($pattern, $msg)) {
                    $rank = $value;
                    break;
                }
            }

            $target = array();
            if ($rank > 0 && !empty($safe_prior['recent_providers'][$rank - 1])) {
                $target = $safe_prior['recent_providers'][$rank - 1];
            } elseif (!empty($safe_prior['last_provider_focus'])) {
                $focus_id = absint($safe_prior['last_provider_focus']);
                foreach ($safe_prior['recent_providers'] as $provider) {
                    if (absint($provider['id'] ?? 0) === $focus_id) {
                        $target = $provider;
                        break;
                    }
                }
            }

                return array(
                    'raw_text' => sanitize_text_field($raw),
                    'clean_text' => sanitize_text_field($raw),
                    'intent' => 'provider_followup',
                    'next_action' => $action,
                    'action' => $action,
                    'confidence' => 0.88,
                    'entities' => array(
                        'ordinal' => $rank,
                        'provider_name' => sanitize_text_field($target['name'] ?? ''),
                        'city' => sanitize_text_field($target['city'] ?? ''),
                        'language' => $safe_prior['language'] ?? 'en',
                    ),
                    'target_provider_rank' => $rank,
                    'target_provider_id' => absint($target['id'] ?? 0),
                    'target_provider_name' => sanitize_text_field($target['name'] ?? ''),
                    'needs_clarification' => false,
                    'clarification_question' => '',
                    'source' => 'grok_state_fast_path',
                    'status' => 'fast_path',
                );
            } // close else (route_hint followup path)
        }

        if (!empty($safe_prior['recent_providers']) &&
            preg_match('/\b(?:why\s+(?:did\s+you\s+)?recommend(?:ed)?|why\s+was\s+.+recommended|por\s+qu[eé]\s+recomend(?:aste|[oó])|profile\s+links?|links?\s+to\s+(?:the\s+)?profiles?|enlaces?\s+de\s+(?:los\s+)?perfiles?|enlaces?\s+de\s+perfil)\b/iu', $msg)) {
            $target = $safe_prior['recent_providers'][0];
            return array(
                'raw_text' => sanitize_text_field($raw),
                'clean_text' => sanitize_text_field($raw),
                'intent' => 'provider_followup',
                'next_action' => 'provider_detail',
                'action' => 'provider_detail',
                'confidence' => 0.94,
                'entities' => array(
                    'provider_name' => sanitize_text_field($target['name'] ?? ''),
                    'city' => sanitize_text_field($target['city'] ?? ''),
                    'language' => $safe_prior['language'] ?? 'en',
                ),
                'target_provider_rank' => 1,
                'target_provider_id' => absint($target['id'] ?? 0),
                'target_provider_name' => sanitize_text_field($target['name'] ?? ''),
                'needs_clarification' => false,
                'clarification_question' => '',
                'source' => 'provider_followup_fast_path',
                'status' => 'fast_path',
            );
        }

        if (preg_match('/\b(?:claim|claimed|claiming|manage|verify|own|my)\b.*\b(?:profile|listing|practice|provider|business)\b|\b(?:profile|listing|practice|provider|business)\b.*\b(?:claim|manage|verify|mine|my)\b|\b(?:reclamar|gestionar|verificar)\b.*\b(?:perfil|listado|pr[aá]ctica)\b/iu', $msg)) {
            return array(
                'raw_text' => sanitize_text_field($raw),
                'clean_text' => sanitize_text_field($raw),
                'intent' => MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM,
                'next_action' => 'provider_claim',
                'action' => 'provider_claim',
                'confidence' => 0.90,
                'entities' => array('language' => $safe_prior['language'] ?? 'en'),
                'target_provider_rank' => 0,
                'target_provider_id' => 0,
                'target_provider_name' => '',
                'needs_clarification' => false,
                'clarification_question' => '',
                'source' => 'grok_state_fast_path',
                'status' => 'fast_path',
            );
        }

        // Claim name-confirmation fast path.
        // When we are already in a claim/owner flow (provider_owner_mode set or
        // claim_disambiguation_providers present), the user is telling us which
        // provider they own — e.g. "the profiles Jamie", "yes that's Jeremy Elizabeth",
        // "yeah Jamie Jameson", "it's the third one", "my name is Dr. Smith".
        // These short replies do NOT contain new-search vocabulary, so Grok
        // almost always times out or returns empty intent (causing 12s+ delays).
        // Route them directly to provider_claim so the PHP handler can do name
        // matching against claim_disambiguation_providers / claim_name_suggestions.
        if ($this->has_claim_selection_context($safe_prior)) {
            // Greetings & social check-ins should NOT inherit stale claim context
            // from a previous session.  When the user says "hi", "hello", "hey Mia",
            // or "how are you" at the start of an interaction, treat as a fresh
            // turn — not a claim continuation.
            // (Debug CSV 1778551826587 T1: "Hello, Mia. hello Mia" returned the
            //  claim intro because provider_owner_mode was set from an earlier session.)
            $is_pure_greeting = preg_match('/^(?:(?:hi|hello|hey|hiya|howdy|good\s+(?:morning|afternoon|evening)|hola|buenos\s+d[ií]as|buenas\s+(?:tardes|noches))[\s,.!?]*(?:mia)?[\s,.!?]*)+$/iu', $msg) ||
                preg_match('/^(?:hey|hi|hello)\s+mia[\s,.!?]+(?:hey|hi|hello)\s+mia[\s,.!?]*$/iu', $msg) ||
                preg_match('/^(?:how\s+are\s+you|what\'?s\s+up|how\'?s\s+it\s+going|c[oó]mo\s+est[aá]s)[\s,.!?]*$/iu', $msg);
            $has_new_search = preg_match('/\b(?:find|search|show|looking for|buscar|busco|encuentra|mostrar|mu[eé]strame)\b.*\b(?:therapist|counselor|provider|psychiatrist|psychologist|massage|nutrition|dietitian|fitness|coach|terapeuta|consejero|proveedor|psicologo|psiquiatra)\b/iu', $msg);
            if (!$has_new_search && !$is_pure_greeting) {
                return array(
                    'raw_text'               => sanitize_text_field($raw),
                    'clean_text'             => sanitize_text_field($raw),
                    'intent'                 => MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM,
                    'next_action'            => 'provider_claim',
                    'action'                 => 'provider_claim',
                    'confidence'             => 0.88,
                    'entities'               => array('language' => $safe_prior['language'] ?? 'en'),
                    'target_provider_rank'   => 0,
                    'target_provider_id'     => 0,
                    'target_provider_name'   => '',
                    'needs_clarification'    => false,
                    'clarification_question' => '',
                    'source'                 => 'grok_state_fast_path',
                    'status'                 => 'fast_path',
                );
            }
        }

        // Social acknowledgments — skip Grok entirely, no action needed.
        // Allows single words ("ok", "thanks") AND compound phrases ("ok thanks", "great thanks", "yep got it").
        if (strlen($raw) <= 60 && preg_match('/^(?:(?:ok|okay|got it|sounds good|great|perfect|thanks|thank you|thx|ty|alright|sure|cool|nice|yep|yup|yes|no problem|appreciate it|gracias|entendido|perfecto|bien|claro|dale)[,!.]?\s*)+$/iu', $msg)) {
            return array(
                'raw_text'               => sanitize_text_field($raw),
                'clean_text'             => sanitize_text_field($raw),
                'intent'                 => MyHH_Mia_Intent_Engine::INTENT_GENERAL,
                'next_action'            => 'social_ack',
                'action'                 => 'social_ack',
                'confidence'             => 0.95,
                'entities'               => array('language' => $safe_prior['language'] ?? 'en'),
                'target_provider_rank'   => 0,
                'target_provider_id'     => 0,
                'target_provider_name'   => '',
                'needs_clarification'    => false,
                'clarification_question' => '',
                'source'                 => 'grok_state_fast_path',
                'status'                 => 'fast_path',
            );
        }

        // Comparison fast-path — when providers are in memory and user asks to compare or get a recommendation.
        // This prevents comparison questions from falling through to a fresh search (e.g. "which one is better for anxiety").
        if (!empty($safe_prior['recent_providers']) && count($safe_prior['recent_providers']) >= 2) {
            if (preg_match('/\b(?:compare|comparison|difference|differ|better|best|which one|which is|recommend|vs\.?|versus|comparar|diferencia|mejor|cu[aá]l es mejor|cu[aá]l)\b/iu', $msg)) {
                return array(
                    'raw_text'               => sanitize_text_field($raw),
                    'clean_text'             => sanitize_text_field($raw),
                    'intent'                 => 'provider_followup',
                    'next_action'            => 'provider_compare',
                    'action'                 => 'provider_compare',
                    'confidence'             => 0.91,
                    'entities'               => array('language' => $safe_prior['language'] ?? 'en'),
                    'target_provider_rank'   => 0,
                    'target_provider_id'     => 0,
                    'target_provider_name'   => '',
                    'needs_clarification'    => false,
                    'clarification_question' => '',
                    'source'                 => 'grok_state_fast_path',
                    'status'                 => 'fast_path',
                );
            }
        }

        $has_search_verb = preg_match('/\b(?:find|search|show|need|looking|look for|looking for|help me find|buscar|busco|mu[eé]strame|necesito)\b/iu', $msg);
        $has_provider_term = preg_match('/\b(?:provider|providers|therapist|therapy|counselor|counseling|psychiatrist|psychologist|mental health|anxiety|depression|massage|nutrition|dietitian|fitness|coach|proveedor|proveedores|terapeuta|terapia|consejero|ansiedad|depresi[oó]n)\b/iu', $msg);
        $has_direct_search_shape = preg_match('/\b(?:therapist|counselor|psychiatrist|psychologist|massage|nutrition|dietitian|fitness|coach|terapeuta|consejero)\b.*\b(?:in|near|for|around|en|cerca|para)\b|\b(?:in|near|around|en|cerca)\b.*\b(?:therapist|counselor|psychiatrist|psychologist|massage|nutrition|dietitian|fitness|coach|terapeuta|consejero)\b/iu', $msg);
        if ((($has_search_verb && $has_provider_term) || $has_direct_search_shape)
            && !preg_match('/\b(?:claim|claimed|claiming|manage|verify|own|my profile|my listing|reclamar|gestionar|verificar)\b/iu', $msg)) {
            $search_entities = $this->infer_fast_provider_search_entities_from_turn($raw);
            $clear_context = array('recent_providers');
            foreach (array('primary_category_name' => 'category', 'specialty' => 'specialty', 'city' => 'city', 'provider_type' => 'provider_type', 'modality' => 'modality', 'language_preference' => 'language_preference', 'insurance' => 'insurance', 'session_type' => 'session_type') as $entity_key => $dimension) {
                if (!empty($search_entities[$entity_key])) {
                    $clear_context[] = $dimension;
                }
            }
            if (!empty($search_entities['primary_category_name'])) {
                $new_category = strtolower(trim((string) $search_entities['primary_category_name']));
                $prior_category = strtolower(trim((string) ($safe_prior['primary_category_name'] ?? '')));
                $clear_context = array_merge($clear_context, array('category', 'specialty', 'provider_type'));
                if ($prior_category !== '' && $new_category !== $prior_category) {
                    $clear_context[] = 'modality';
                }
            } elseif (!empty($search_entities['specialty'])) {
                $clear_context = array_merge($clear_context, array('specialty', 'provider_type'));
            }
            $clear_context = array_values(array_unique($clear_context));
            return array(
                'raw_text' => sanitize_text_field($raw),
                'clean_text' => sanitize_text_field($raw),
                'intent' => MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH,
                'next_action' => 'provider_search',
                'action' => 'provider_search',
                'confidence' => 0.84,
                'entities' => array('language' => $safe_prior['language'] ?? 'en'),
                'search_entities' => $search_entities,
                'change_dimension' => !empty($search_entities['specialty']) ? 'specialty' : (!empty($search_entities['primary_category_name']) ? 'category' : ''),
                'preserve_context' => array_values(array_diff(array('category', 'specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type'), $clear_context)),
                'clear_context' => $clear_context,
                'target_provider_rank' => 0,
                'target_provider_id' => 0,
                'target_provider_name' => '',
                'needs_clarification' => false,
                'clarification_question' => '',
                'source' => 'grok_state_fast_path',
                'status' => 'fast_path',
            );
        }

        // ── Short search-continuation fast path ──
        // When Mia asked "What city?" or "What kind of support?" and the user
        // replies with 1–3 words (e.g. "Raleigh", "anxiety", "near me",
        // "CBT therapy"), Grok is not needed — route directly to provider_search
        // and let the PHP intent engine extract the city/specialty/category.
        // Guard rails: skip ordinals, follow-up signals, claim signals, and plain
        // acks so those paths still reach their own handlers.
        if (!empty($safe_prior['primary_category']) || !empty($safe_prior['primary_category_name'])) {
            $wc = str_word_count($msg);
            if ($wc >= 1 && $wc <= 3) {
                $is_ordinal       = (bool) preg_match('/\b(?:first|second|third|fourth|1st|2nd|3rd|4th|number\s*\d+|\d)\b/iu', $msg);
                $is_followup_sig  = (bool) preg_match('/\b(?:tell\s+me|more\s+about|compare|better|insurance|booking|address|location|website|contact|hours|phone)\b/iu', $msg);
                $is_claim_sig     = (bool) preg_match('/\b(?:claim|manage|verify|my\s+profile|my\s+listing|reclamar|gestionar)\b/iu', $msg);
                $is_bare_ack      = (bool) preg_match('/^\s*(?:yes|yeah|yep|no|nope|ok|okay|sure|alright|got\s+it|thanks|thank\s+you)\s*[!.?]*\s*$/iu', $msg);
                if (!$is_ordinal && !$is_followup_sig && !$is_claim_sig && !$is_bare_ack) {
                    mia_debug_log('GrokNextAction', 'fast_search_continuation', array(
                        'message' => substr($raw, 0, 80),
                        'word_count' => $wc,
                    ));
                    return array(
                        'raw_text'               => sanitize_text_field($raw),
                        'clean_text'             => sanitize_text_field($raw),
                        'intent'                 => MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH,
                        'next_action'            => 'provider_search',
                        'action'                 => 'provider_search',
                        'confidence'             => 0.80,
                        'entities'               => array('language' => $safe_prior['language'] ?? 'en'),
                        'change_dimension'       => 'none',
                        'preserve_context'       => array('category', 'specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type'),
                        'clear_context'          => array(),
                        'target_provider_rank'   => 0,
                        'target_provider_id'     => 0,
                        'target_provider_name'   => '',
                        'needs_clarification'    => false,
                        'clarification_question' => '',
                        'source'                 => 'grok_state_fast_path',
                        'status'                 => 'fast_path',
                    );
                }
            }
        }

        return array();
    }

    private function parse_grok_json_object($raw) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array();
        }

        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false || $end <= $start) {
            return array();
        }

        $json = substr($raw, $start, $end - $start + 1);
        $data = json_decode($json, true);
        return is_array($data) ? $data : array();
    }

    /**
     * Reuse the active Realtime model's semantic action instead of invoking a
     * second model. The contract may route work, but it can never provide the
     * provider records or business facts returned by WordPress.
     */
    private function sanitize_realtime_action_contract($data, $message, $route_hint, $safe_prior) {
        if (!empty($safe_prior['recent_providers']) && $this->is_existing_provider_fact_question($message)) {
            $facts = $this->build_fast_state_understanding($message, 'provider_followup', $safe_prior);
            if (!empty($data['target_provider_name']) && !preg_match('/\b(?:which|who|those|these)\b/iu', $message)) {
                $facts['target_provider_name'] = sanitize_text_field($data['target_provider_name']);
            }
            return $facts;
        }
        if (empty($data) || !is_array($data)) {
            return array();
        }

        $data['clean_text'] = sanitize_text_field($message);
        $understanding = $this->sanitize_grok_understanding($data, $message);
        if (empty($understanding)) {
            return array();
        }

        // Realtime can over-weight prior provider context and request a search
        // for a fresh educational question. Keep the literal turn authoritative.
        if (MyHH_Mia_Intent_Engine::is_basic_education_question($message)) {
            $understanding['intent'] = MyHH_Mia_Intent_Engine::INTENT_GENERAL;
            $understanding['next_action'] = 'general';
            $understanding['action'] = 'general';
            $understanding['confidence'] = 1.0;
            $understanding['search_entities'] = array();
            $understanding['change_dimension'] = 'none';
            $understanding['preserve_context'] = array();
            $understanding['clear_context'] = array();
            $understanding['target_provider_rank'] = 0;
            $understanding['target_provider_id'] = 0;
            $understanding['target_provider_name'] = '';
            $understanding['needs_clarification'] = false;
            $understanding['clarification_question'] = '';
        }

        // Realtime occasionally identifies the correct search action while
        // returning only one entity (commonly the city). Fill missing facts
        // from the literal transcript so the canonical engine message cannot
        // discard an explicitly spoken specialty, category, or preference.
        // This is deterministic and adds no model or network round trip.
        if ($this->grok_understanding_is_provider_search($understanding)) {
            $search_entities = $this->normalize_grok_search_entities_for_directory(
                $this->sanitize_grok_search_entities($understanding['search_entities'] ?? array())
            );
            $literal_entities = $this->infer_fast_provider_search_entities_from_turn($message);
            $literal_category = $this->extract_explicit_category_selection($message);
            if ($literal_category !== '' && preg_match('/\b(?:switch|change|instead|cambia|cambiar)\b/iu', $message)) {
                $search_entities['primary_category_name'] = $literal_category;
                $understanding['change_dimension'] = 'category';
                $understanding['clear_context'] = array('category', 'specialty', 'provider_type', 'modality');
                $understanding['preserve_context'] = array('city', 'language_preference', 'insurance', 'session_type');
                if ($this->extract_explicit_search_city_from_turn($message) === '') unset($search_entities['city']);
            }
            $filled_dimensions = array();
            $dimension_map = array(
                'primary_category_name' => 'category',
                'specialty' => 'specialty',
                'city' => 'city',
                'provider_type' => 'provider_type',
                'modality' => 'modality',
                'language_preference' => 'language_preference',
                'insurance' => 'insurance',
                'session_type' => 'session_type',
            );
            foreach ($dimension_map as $entity_key => $dimension) {
                if (empty($search_entities[$entity_key]) && !empty($literal_entities[$entity_key])) {
                    $search_entities[$entity_key] = sanitize_text_field($literal_entities[$entity_key]);
                    $filled_dimensions[] = $dimension;
                }
            }
            $understanding['search_entities'] = $search_entities;

            if (!empty($filled_dimensions)) {
                $clear_context = $this->sanitize_grok_context_field_list($understanding['clear_context'] ?? array());
                $clear_context[] = 'recent_providers';
                $clear_context = array_merge($clear_context, $filled_dimensions);
                if (in_array('category', $filled_dimensions, true)) {
                    $new_category = strtolower(trim((string) ($search_entities['primary_category_name'] ?? '')));
                    $prior_category = strtolower(trim((string) ($safe_prior['primary_category_name'] ?? '')));
                    $clear_context = array_merge($clear_context, array('category', 'specialty', 'provider_type'));
                    if ($prior_category !== '' && $new_category !== $prior_category) {
                        $clear_context[] = 'modality';
                    }
                } elseif (in_array('specialty', $filled_dimensions, true)) {
                    $clear_context = array_merge($clear_context, array('specialty', 'provider_type'));
                }
                $understanding['clear_context'] = array_values(array_unique($clear_context));
                $understanding['preserve_context'] = array_values(array_diff(
                    $this->sanitize_grok_context_field_list($understanding['preserve_context'] ?? array()),
                    $understanding['clear_context']
                ));
            }

            $service_change = in_array($this->normalize_grok_context_dimension($understanding['change_dimension'] ?? ''), array('category', 'specialty'), true)
                || !empty($search_entities['primary_category_name'])
                || !empty($search_entities['specialty']);
            if ($service_change) {
                $clear_context = $this->sanitize_grok_context_field_list($understanding['clear_context'] ?? array());
                $preserve_context = $this->sanitize_grok_context_field_list($understanding['preserve_context'] ?? array());
                $explicit_city_reset = (bool) preg_match('/\b(?:any\s+city|anywhere|no\s+(?:city|location)\s+preference|remove|clear|forget)\b[\s\S]{0,20}\b(?:city|location|area)\b/iu', $message);
                $explicit_language_reset = (bool) preg_match('/\b(?:any\s+language|no\s+language\s+preference|remove|clear|forget)\b[\s\S]{0,20}\b(?:language|spanish|espa[ñn]ol)\b/iu', $message);
                $explicit_insurance_reset = (bool) preg_match('/\b(?:any\s+insurance|no\s+insurance\s+preference)\b|\b(?:remove|clear|forget)\b[\s\S]{0,24}\b(?:insurance|coverage|plan)\b/iu', $message);
                $explicit_session_reset = (bool) preg_match('/\b(?:any\s+(?:session|visit)\s+type|no\s+(?:session|visit)\s+preference)\b|\beither\b[\s\S]{0,40}\b(?:works|fine|okay|ok)\b|\b(?:remove|clear|forget)\b[\s\S]{0,24}\b(?:virtual|in[-\s]?person|session|visit)\b/iu', $message);
                if (empty($search_entities['city']) && !empty($safe_prior['city']) && !$explicit_city_reset) {
                    $clear_context = array_values(array_diff($clear_context, array('city')));
                    $preserve_context[] = 'city';
                }
                if (empty($search_entities['language_preference']) && !empty($safe_prior['language_preference']) && !$explicit_language_reset) {
                    $clear_context = array_values(array_diff($clear_context, array('language_preference')));
                    $preserve_context[] = 'language_preference';
                }
                if (empty($search_entities['insurance']) && !empty($safe_prior['insurance']) && !$explicit_insurance_reset) {
                    $clear_context = array_values(array_diff($clear_context, array('insurance')));
                    $preserve_context[] = 'insurance';
                }
                if (empty($search_entities['session_type']) && !empty($safe_prior['session_type']) && !$explicit_session_reset) {
                    $clear_context = array_values(array_diff($clear_context, array('session_type')));
                    $preserve_context[] = 'session_type';
                }
                $understanding['clear_context'] = array_values(array_unique($clear_context));
                $understanding['preserve_context'] = array_values(array_unique(array_diff($preserve_context, $understanding['clear_context'])));
            }
        }

        $allowed_provider_ids = array();
        foreach (array('recent_providers', 'claim_disambiguation_providers', 'claim_name_suggestions') as $context_key) {
            foreach (($safe_prior[$context_key] ?? array()) as $provider) {
                $provider_id = absint($provider['id'] ?? 0);
                if ($provider_id > 0) {
                    $allowed_provider_ids[] = $provider_id;
                }
            }
        }
        $allowed_provider_ids = array_values(array_unique($allowed_provider_ids));
        if (!empty($understanding['target_provider_id']) && !in_array(absint($understanding['target_provider_id']), $allowed_provider_ids, true)) {
            $understanding['target_provider_id'] = 0;
        }

        $reference_pool_size = max(
            count($safe_prior['recent_providers'] ?? array()),
            count($safe_prior['claim_disambiguation_providers'] ?? array()),
            count($safe_prior['claim_name_suggestions'] ?? array())
        );
        if (!empty($understanding['target_provider_rank']) && absint($understanding['target_provider_rank']) > $reference_pool_size) {
            $understanding['target_provider_rank'] = 0;
        }

        $language = strtolower((string) ($understanding['entities']['language'] ?? ''));
        $literal_language = $this->get_literal_response_language($message);
        if ($literal_language !== '') {
            $understanding['entities']['language'] = $literal_language;
        } elseif (!in_array($language, array('en', 'es'), true)) {
            $understanding['entities']['language'] = in_array(($safe_prior['language'] ?? ''), array('en', 'es'), true)
                ? $safe_prior['language']
                : 'en';
        }

        $understanding['raw_text'] = sanitize_text_field($message);
        $understanding['clean_text'] = sanitize_text_field($message);
        $understanding['route_hint'] = sanitize_key((string) $route_hint);
        $understanding['source'] = 'grok_realtime_action';
        $understanding['status'] = 'direct_realtime_contract';

        mia_debug_log('GrokNextAction', 'realtime_contract', array(
            'intent' => $understanding['intent'] ?? '',
            'next_action' => $understanding['next_action'] ?? '',
            'confidence' => $understanding['confidence'] ?? 0,
            'route_hint' => $understanding['route_hint'],
            'target_provider_id' => $understanding['target_provider_id'] ?? 0,
        ));

        return $understanding;
    }

    /**
     * Give the deterministic engine a canonical execution phrase when the
     * Realtime model understood a natural paraphrase that contains no legacy
     * routing keywords. The original user text remains in conversation memory.
     */
    private function build_realtime_action_engine_message($message, $understanding) {
        if (($understanding['source'] ?? '') !== 'grok_realtime_action') {
            return $message;
        }

        $action = $this->normalize_grok_next_action($understanding['next_action'] ?? $understanding['action'] ?? '');
        $entities = is_array($understanding['entities'] ?? null) ? $understanding['entities'] : array();
        $search_entities = is_array($understanding['search_entities'] ?? null) ? $understanding['search_entities'] : array();

        if ($action === 'provider_add') {
            return 'I want to add my practice.';
        }
        if ($action === 'provider_upgrade') {
            return 'I want to upgrade my provider listing.';
        }
        if ($action === 'sponsor_inquiry') {
            return 'I want information about sponsoring the directory.';
        }
        if (in_array($action, array('provider_claim', 'claim_candidate_select'), true)) {
            $parts = array('I want to claim my provider profile.');
            $provider_name = sanitize_text_field($understanding['target_provider_name'] ?? ($entities['provider_name'] ?? ''));
            $city = sanitize_text_field($entities['city'] ?? '');
            $rank = absint($understanding['target_provider_rank'] ?? ($entities['ordinal'] ?? 0));
            if ($provider_name !== '') {
                $parts[] = 'Provider name: ' . $provider_name . '.';
            }
            if ($city !== '') {
                $parts[] = 'City: ' . $city . '.';
            }
            if ($rank > 0) {
                $parts[] = 'Selected option number ' . $rank . '.';
            }
            return implode(' ', $parts);
        }
        if ($action === 'provider_search' && !empty($search_entities)) {
            $parts = array('Find providers');
            $engine_fields = array(
                'primary_category_name' => 'category',
                'specialty' => 'specialty',
                'provider_type' => 'provider type',
                'city' => 'city',
                'modality' => 'modality',
                'language_preference' => 'provider language',
                'insurance' => 'insurance',
                'session_type' => 'session type',
            );
            foreach ($engine_fields as $field => $label) {
                if (!empty($search_entities[$field])) {
                    $parts[] = $label . ': ' . sanitize_text_field($search_entities[$field]);
                }
            }
            return implode(', ', $parts) . '.';
        }

        return $message;
    }

    private function sanitize_grok_understanding($data, $message) {
        if (empty($data) || !is_array($data)) {
            return array();
        }

        $next_action = $this->normalize_grok_next_action($data['next_action'] ?? ($data['action'] ?? ''));
        $intent = $this->normalize_grok_understanding_intent($data['intent'] ?? '');
        $intent_from_action = $this->intent_from_grok_next_action($next_action);
        if ($intent === '' && $intent_from_action !== '') {
            $intent = $intent_from_action;
        } elseif ($intent_from_action === 'provider_followup') {
            $intent = 'provider_followup';
        } elseif ($intent_from_action === MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM) {
            $intent = MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM;
        }
        if ($intent === '') {
            return array();
        }

        $confidence = isset($data['confidence']) ? floatval($data['confidence']) : 0.0;
        $confidence = max(0.0, min(1.0, $confidence));
        if ($confidence < 0.50) {
            return array();
        }

        $clean_text = sanitize_text_field($data['clean_text'] ?? '');
        if ($clean_text === '' || strlen($clean_text) < 3) {
            $clean_text = sanitize_text_field($message);
        }

        $raw_entities = (!empty($data['entities']) && is_array($data['entities'])) ? $data['entities'] : array();
        $entities = array();
        if (!empty($raw_entities)) {
            foreach (array('provider_name', 'city', 'category', 'specialty', 'ordinal', 'language', 'target_provider_name') as $key) {
                if (isset($raw_entities[$key]) && $raw_entities[$key] !== '') {
                    $entities[$key] = is_numeric($raw_entities[$key])
                        ? absint($raw_entities[$key])
                        : sanitize_text_field($raw_entities[$key]);
                }
            }
        }
        $search_entities = $this->sanitize_grok_search_entities($data['search_entities'] ?? ($raw_entities['search_entities'] ?? $raw_entities));
        if ($next_action === 'provider_search') {
            // Realtime may put valid search facts in entities while leaving the
            // parallel search_entities object incomplete.
            foreach (array('category' => 'primary_category_name', 'specialty' => 'specialty', 'city' => 'city') as $entity_key => $search_key) {
                if (empty($search_entities[$search_key]) && !empty($entities[$entity_key])) {
                    $search_entities[$search_key] = sanitize_text_field($entities[$entity_key]);
                }
            }
        }
        $change_dimension = $this->normalize_grok_context_dimension($data['change_dimension'] ?? ($raw_entities['change_dimension'] ?? ''));
        $preserve_context = $this->sanitize_grok_context_field_list($data['preserve_context'] ?? ($raw_entities['preserve_context'] ?? array()));
        $clear_context = $this->sanitize_grok_context_field_list($data['clear_context'] ?? ($raw_entities['clear_context'] ?? array()));

        $target_provider_rank = isset($data['target_provider_rank']) ? absint($data['target_provider_rank']) : 0;
        $target_provider_id = isset($data['target_provider_id']) ? absint($data['target_provider_id']) : 0;
        $target_provider_name = sanitize_text_field($data['target_provider_name'] ?? ($entities['target_provider_name'] ?? ($entities['provider_name'] ?? '')));
        $navigation_state = $this->sanitize_navigation_state($data['navigation_state'] ?? array());

        return array(
            'raw_text' => sanitize_text_field($message),
            'clean_text' => $clean_text,
            'intent' => $intent,
            'next_action' => $next_action,
            'action' => $next_action,
            'confidence' => $confidence,
            'entities' => $entities,
            'search_entities' => $search_entities,
            'change_dimension' => $change_dimension,
            'preserve_context' => $preserve_context,
            'clear_context' => $clear_context,
            'target_provider_rank' => $target_provider_rank,
            'target_provider_id' => $target_provider_id,
            'target_provider_name' => $target_provider_name,
            'needs_clarification' => !empty($data['needs_clarification']),
            'clarification_question' => sanitize_text_field($data['clarification_question'] ?? ''),
            'navigation_state' => $navigation_state,
            'source' => $this->uses_openai() ? 'openai_responses' : 'grok_understanding',
        );
    }

    /** Select an action with OpenAI while retaining the understanding layer's entities. */
    private function apply_openai_decision($message, $understanding, $safe_prior, $route_hint = '') {
        if (!$this->uses_openai() && get_option('myhh_mia_decision_provider', 'openai') !== 'openai') {
            return $understanding;
        }
        $source = (string) ($understanding['source'] ?? '');
        if (strpos($source, 'deterministic_') === 0 || strpos($source, 'fast_path') !== false
            || ($understanding['next_action'] ?? '') === 'crisis'
            || ($understanding['intent'] ?? '') === MyHH_Mia_Intent_Engine::INTENT_CRISIS) {
            return $understanding;
        }
        require_once __DIR__ . '/class-mia-decisions.php';
        if (MyHH_Mia_Decisions::get_api_key() === '' || get_transient('mia_openai_decisions_backoff')) {
            return $understanding;
        }
        $state = $this->build_grok_state_packet($safe_prior, $route_hint, array(), false);
        // Only include conversation direction and public provider context.
        unset($state['taxonomy_vocabulary'], $state['transcript_candidates']);
        $state['pending_question'] = $safe_prior['pending_question'] ?? array();
        $state['care_recipient'] = $safe_prior['care_recipient'] ?? '';
        $state['care_age_group'] = $safe_prior['care_age_group'] ?? '';
        $state['extracted_entities'] = $understanding['entities'] ?? array();
        $state['search_entities'] = $understanding['search_entities'] ?? array();
        $selected_text = $understanding['selected_transcript'] ?? $message;
        $cache_key = 'mia_decisions_' . md5($selected_text . '|' . wp_json_encode($state) . '|' . hash('sha256', MyHH_Mia_Decisions::get_api_key()));
        $decision = get_transient($cache_key);
        if (!is_array($decision)) {
            $decision = MyHH_Mia_Decisions::select_action($selected_text, $state);
        }
        if (is_wp_error($decision)) {
            if ($decision->get_error_code() !== 'uncertain_decision') {
                set_transient('mia_openai_decisions_backoff', 1, 30);
            }
            mia_debug_log('OpenAIDecisions', 'fallback', array('reason' => $decision->get_error_code()));
            return $understanding;
        }
        set_transient($cache_key, $decision, 60);
        // Keep an existing clarification question, otherwise ask a neutral one.
        if ($decision['action'] === 'clarify') {
            $understanding['needs_clarification'] = true;
            if (empty($understanding['clarification_question'])) {
                $understanding['clarification_question'] = ($safe_prior['language'] ?? 'en') === 'es'
                    ? '¿Quieres buscar un proveedor o preguntar sobre un perfil?'
                    : 'Would you like to find a provider or ask about a profile?';
            }
        } else {
            $understanding['needs_clarification'] = false;
            $understanding['clarification_question'] = '';
        }
        $understanding['next_action'] = $decision['action'];
        $understanding['action'] = $decision['action'];
        $understanding['intent'] = $this->intent_from_grok_next_action($decision['action']);
        $understanding['confidence'] = $decision['confidence'];
        $understanding['source'] = 'openai_decisions';
        unset($understanding['status'], $understanding['reason'], $understanding['error'], $understanding['raw_preview']);
        mia_debug_log('OpenAIDecisions', 'selected', array('action' => $decision['action'], 'confidence' => $decision['confidence']));
        return $understanding;
    }

    private function understand_turn_with_grok($message, $route_hint, $safe_prior, $transcript_candidates = array()) {
        $message = trim((string) $message);
        $route_hint = sanitize_key((string) $route_hint);
        $candidate_selection = $this->select_best_transcript_candidate($message, $transcript_candidates);
        $understanding_message = trim((string) ($candidate_selection['text'] ?? $message));
        if ($understanding_message === '') {
            $understanding_message = $message;
        }
        if (!$this->should_use_grok_understanding($understanding_message, $route_hint, $safe_prior)
            && !$this->candidate_has_provider_action_signal($understanding_message)) {
            return array();
        }
        if ($understanding_message !== $message) {
            mia_debug_log('GrokNextAction', 'selected_transcript', array(
                'source' => $candidate_selection['source'] ?? '',
                'reason' => $candidate_selection['reason'] ?? '',
                'message' => substr($message, 0, 90),
                'selected' => substr($understanding_message, 0, 90),
            ));
        }

        $fast_understanding = $this->build_fast_state_understanding($understanding_message, $route_hint, $safe_prior);
        if (!empty($fast_understanding)) {
            $fast_understanding['raw_text'] = sanitize_text_field($message);
            $fast_understanding['selected_transcript'] = sanitize_text_field($understanding_message);
            $fast_understanding['selected_transcript_source'] = sanitize_key((string) ($candidate_selection['source'] ?? ''));
            $fast_understanding['selected_transcript_reason'] = sanitize_key((string) ($candidate_selection['reason'] ?? ''));
            mia_debug_log('GrokNextAction', 'fast_path', array(
                'intent' => $fast_understanding['intent'] ?? '',
                'next_action' => $fast_understanding['next_action'] ?? '',
                'target_rank' => $fast_understanding['target_provider_rank'] ?? 0,
                'message' => substr($message, 0, 80),
                'selected' => substr($understanding_message, 0, 80),
                'selected_source' => $fast_understanding['selected_transcript_source'] ?? '',
            ));
            return $fast_understanding;
        }
        if (empty($this->api_key)) {
            return array(
                'source' => $this->uses_openai() ? 'openai_responses' : 'grok_understanding',
                'status' => 'unavailable',
                'reason' => 'no_api_key',
            );
        }
        if (get_transient(($this->uses_openai() ? 'mia_openai_understand_backoff' : 'mia_grok_understand_backoff'))) {
            return array(
                'source' => $this->uses_openai() ? 'openai_responses' : 'grok_understanding',
                'status' => 'fallback',
                'reason' => 'temporary_backoff',
            );
        }

        $context = $this->build_grok_understanding_context($safe_prior, $route_hint, $candidate_selection['candidates'] ?? array());
        $cache_key = 'mia_understand_' . md5(MyHH_Mia_OpenAI::provider() . '|' . hash('sha256', (string) $this->api_key) . '|' . $message . '|' . $understanding_message . '|' . $route_hint . '|' . wp_json_encode($context));
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            $cached['cached'] = true;
            return $cached;
        }

        mia_debug_log('GrokNextAction', 'start', array(
            'message' => substr($message, 0, 90),
            'selected' => substr($understanding_message, 0, 90),
            'selected_source' => $candidate_selection['source'] ?? '',
            'route_hint' => $route_hint,
            'active_task' => $context['active_task'] ?? '',
            'has_recent_providers' => !empty($context['has_recent_providers']),
            'has_claim_candidates' => !empty($context['has_claim_candidates']),
            'known_categories' => count($context['taxonomy_vocabulary']['known_categories'] ?? array()),
            'known_specialties' => count($context['taxonomy_vocabulary']['known_specialties'] ?? array()),
        ));

        $system_prompt = 'You are Mia\'s language-understanding layer for MyHealthHub, a wellness provider directory. ' .
            'Return ONLY valid compact JSON. Do not answer the user. Do not invent provider data. ' .
            'Normalize messy speech, filler words, bad grammar, accented English, Spanish, and Spanglish. ' .
            'Choose exactly one next_action from the state packet. The backend will execute search, claim, provider detail, comparison, location, and other data actions. ' .
            'Use the state packet to understand whether the user is asking about providers already shown, selecting a claim candidate, or starting a new search. ' .
            'When transcript candidates disagree, prefer the candidate with clearer MyHealthHub/provider/claim/follow-up intent over a phonetically garbled realtime phrase. ' .
            'Allowed intent values: consumer_provider_search, provider_followup, provider_claim_listing, provider_add_listing, provider_upgrade_or_visibility, sponsor_or_company_inquiry, crisis_or_high_risk, general_unknown. ' .
            'Allowed next_action values: provider_search, provider_detail, provider_location, provider_insurance, provider_booking, provider_compare, provider_claim, claim_candidate_select, provider_add, provider_upgrade, sponsor_inquiry, platform_info, crisis, general, clarify. ' .
            'Use provider_followup when the user asks about recent providers by name, ordinal, pronoun, location, insurance, booking, comparison, or "tell me more". ' .
            'For followups, fill target_provider_rank/id/name when the user references first/second/third, this provider, that one, a name, or a claim candidate. ' .
            'Use provider_claim_listing when the user owns/manages/claims/verifies a profile/listing/practice or selects a claim candidate. ' .
            'For claim turns, ALWAYS try to extract a provider name into entities.provider_name. Handle every natural-language form: explicit ("my name is Ashley Hamlin", "I\'m Dr Smith"), corrections ("no I said Ashley Hamlin", "I meant Dr Smith", "actually it\'s Ashley"), fragments after fillers ("umm Ashley Hamlin", "well it\'s Ashley"), inline mentions ("claim this profile Dr Megan", "Felicia I need to claim my profile"), and self-introductions with city ("hi I\'m Ashley Hamlin from Charlotte"). When the user stutters or repeats themselves, return the most complete name spoken. Set provider_name to null only if no name was attempted in the message. Do not invent names. ' .
            'NAME STRUCTURE: Provider names commonly include middle initials, middle names, hyphenated parts, prefixes, and suffixes — keep them ALL as part of provider_name, never split them across fields. Examples: "Dania M Alshafei" → provider_name="Dania M Alshafei" (the M is the middle initial, NOT a separate token); "John A. Smith Jr." → provider_name="John A. Smith Jr."; "Mary-Anne O\'Brien" → provider_name="Mary-Anne O\'Brien"; "Dr. José M. García-López" → provider_name="Dr. José M. García-López". Single-letter middle initials (with or without a period) are ALWAYS part of the surrounding name. Common prefixes (Dr/Dr.,  Mr/Mr., Mrs/Mrs., Ms/Ms.,  Prof, Rev) and suffixes (Jr, Sr, II, III, IV, PhD, MD, LCSW, LMFT, LPC) stay attached. ' .
            'CITY EXTRACTION: ALWAYS extract a city into entities.city whenever the user mentions one — for claim turns, search turns, and follow-ups alike. Handle every natural form: with prefix ("in Charlotte", "at Raleigh", "from Cary", "near Durham", "around Chapel Hill"), without prefix in self-intros ("I\'m Ashley Hamlin Charlotte", "Dr Smith Raleigh"), Spanish prefixes ("en Charlotte", "cerca de Raleigh", "de Durham"), and multi-word cities ("Chapel Hill", "Winston Salem", "Fort Worth", "Holly Springs", "Wake Forest", "Apex"). Recognize NC-area cities specifically: Raleigh, Durham, Chapel Hill, Cary, Apex, Wake Forest, Holly Springs, Fuquay-Varina, Garner, Morrisville, Knightdale, Wendell, Zebulon, Clayton, Smithfield, Charlotte, Greensboro, Winston Salem, Asheville, Wilmington, Fayetteville. Strip trailing state/zip ("Raleigh NC 27601" → city="Raleigh"). When the user gives both a name and a city in the same utterance ("Dr Smith in Raleigh", "my name is Ashley in Charlotte"), put the name in provider_name and the city in entities.city — do NOT mix them. Set city to null only when no city was spoken. ' .
            'IGNORE ASR noise injected from the environment: smart-assistant wake phrases ("hey Siri", "Alexa", "OK Google"), device commands ("play set timer", "set alarm", "open app", "play song", "turn off", "navigate to"), and repeated copies of the same word that look like ASR jitter (e.g. "number one number one Tanya" → treat as "number one Tanya"). When you see these alongside a real claim utterance, extract only the human-intended provider_name and ordinal; do NOT include the noise in clean_text or any entity. ' .
            'Use consumer_provider_search when the user wants new providers. Use taxonomy_vocabulary to map short or messy phrases to known category/specialty names; do not invent category or specialty names outside that vocabulary. ' .
            'A definition, comparison, or educational follow-up about any recognized MyHealthHub wellness category, service, modality, provider type, or license is general domain education, not provider_search, even when recent providers or search criteria exist. Examples include "what does general counseling cover?", "what is CBT?", "explain acupuncture", and "how is a dietitian different from a nutrition coach?" Use intent general_unknown and next_action general unless the user also asks for actual providers, counts, availability, locations, listing facts, or links. ' .
            'For provider_search, return search_entities plus an action contract: change_dimension, preserve_context, and clear_context. ' .
            'Also return navigation_state using only the allowed labels in output_schema. This state describes conversational direction, not health status: never store a diagnosis, symptom detail, or free-text personal summary. Use goal explore_support and stage listening or clarifying when the user is describing a concern but has not asked to search. Use goal learn and stage educating for education. Use readiness not_ready when explicitly stated, exploring when discussing options, and ready only when the user asks to search or take a platform action. ' .
            'After provider results, phrases like "other options", "other cities", "different city", "other specialties", "different service", "similar providers", or "show more" mean provider_search refinement, not provider_followup. Preserve prior useful criteria unless the user asks to change that dimension. For example, "other cities" means change_dimension city, clear_context ["city"], preserve_context ["category","specialty","provider_type","modality","language_preference","insurance","session_type"]. "other specialties" means change_dimension specialty, clear_context ["specialty","provider_type"], preserve_context ["category","city","modality","language_preference","insurance","session_type"]. A category/service change preserves city, provider-language, insurance, and session-type preferences, but clears the previous category, specialty, provider type, and category-specific modality. ' .
            'For short answers after Mia asked what support/city the user needs, treat category/specialty/city-only replies as provider_search continuations. ' .
            'If unclear, set needs_clarification true and ask one short clarification question.';

        $user_payload = array(
            'raw_text' => $message,
            'preferred_text' => $understanding_message,
            'selected_transcript_source' => $candidate_selection['source'] ?? '',
            'selected_transcript_reason' => $candidate_selection['reason'] ?? '',
            'transcript_candidates' => $candidate_selection['candidates'] ?? array(),
            'route_hint' => $route_hint,
            'context' => $context,
            'output_schema' => array(
                'clean_text' => 'string',
                'intent' => 'string',
                'next_action' => 'string',
                'confidence' => 'number 0..1',
                'navigation_state' => array(
                    'goal' => 'learn|explore_support|find_provider|compare_providers|provider_claim|provider_add|provider_upgrade|unsure',
                    'stage' => 'listening|clarifying|educating|search_ready|reviewing_results|action_ready|closing',
                    'readiness' => 'not_ready|exploring|ready|unknown',
                    'support_topics' => array('up to five allowed broad topic labels from the state packet contract'),
                    'open_question' => 'none|location|service_type|modality|provider_language|insurance|session_type|provider_choice|user_readiness',
                    'next_best_action' => 'listen|clarify|educate|search|compare|show_profile|platform_action|close',
                ),
                'target_provider_rank' => 'number|null',
                'target_provider_id' => 'number|null',
                'target_provider_name' => 'string|null',
                'change_dimension' => 'city|specialty|category|provider_type|modality|language_preference|insurance|session_type|none',
                'preserve_context' => array('category', 'specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type'),
                'clear_context' => array('category', 'specialty', 'city', 'provider_type', 'modality', 'language_preference', 'insurance', 'session_type'),
                'search_entities' => array(
                    'category' => 'string|null',
                    'specialty' => 'string|null',
                    'city' => 'string|null',
                    'provider_type' => 'string|null',
                    'modality' => 'string|null',
                    'language_preference' => 'string|null',
                    'insurance' => 'string|null',
                    'session_type' => 'Virtual|In-Person|Hybrid|null',
                ),
                'entities' => array(
                    'provider_name' => 'string|null',
                    'city' => 'string|null',
                    'category' => 'string|null',
                    'specialty' => 'string|null',
                    'ordinal' => 'number|null',
                    'language' => 'en|es|unknown',
                ),
                'needs_clarification' => 'boolean',
                'clarification_question' => 'string',
            ),
        );

        $response = $this->request_chat_completion(array(
            'timeout' => 2.5,  // Fail fast: PHP fast-path / intent engine handles fallback
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'model' => 'grok-3-mini',
                'messages' => array(
                    array('role' => 'system', 'content' => $system_prompt),
                    array('role' => 'user', 'content' => wp_json_encode($user_payload)),
                ),
                'max_tokens' => 180,
                'temperature' => 0.0,
            )),
        ));

        if (is_wp_error($response)) {
            set_transient(($this->uses_openai() ? 'mia_openai_understand_backoff' : 'mia_grok_understand_backoff'), 1, 120); // 2-min cooldown — 12s was too short
            mia_debug_log('GrokUnderstand', 'error', array(
                'error' => $response->get_error_message(),
                'message' => substr($message, 0, 80),
                'selected' => substr($understanding_message, 0, 80),
            ));
            mia_debug_log('GrokNextAction', 'fallback', array(
                'reason' => 'wp_error',
                'message' => substr($message, 0, 80),
                'selected' => substr($understanding_message, 0, 80),
            ));
            return array(
                'source' => $this->uses_openai() ? 'openai_responses' : 'grok_understanding',
                'status' => 'fallback',
                'reason' => 'wp_error',
                'error' => substr($response->get_error_message(), 0, 120),
            );
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            if ($status === 0 || $status === 408 || $status === 429 || $status >= 500) {
                set_transient(($this->uses_openai() ? 'mia_openai_understand_backoff' : 'mia_grok_understand_backoff'), 1, 12);
            }
            mia_debug_log('GrokUnderstand', 'http_error', array('status' => $status, 'message' => substr($message, 0, 80), 'selected' => substr($understanding_message, 0, 80)));
            mia_debug_log('GrokNextAction', 'fallback', array('reason' => 'http_' . $status, 'message' => substr($message, 0, 80), 'selected' => substr($understanding_message, 0, 80)));
            return array(
                'source' => $this->uses_openai() ? 'openai_responses' : 'grok_understanding',
                'status' => 'fallback',
                'reason' => 'http_' . $status,
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $raw = $body['choices'][0]['message']['content'] ?? '';
        $understanding = $this->sanitize_grok_understanding($this->parse_grok_json_object($raw), $understanding_message);
        if (empty($understanding)) {
            mia_debug_log('GrokUnderstand', 'invalid_output', array('raw' => substr((string) $raw, 0, 120)));
            mia_debug_log('GrokNextAction', 'fallback', array('reason' => 'invalid_output', 'raw' => substr((string) $raw, 0, 100)));
            return array(
                'source' => $this->uses_openai() ? 'openai_responses' : 'grok_understanding',
                'status' => 'fallback',
                'reason' => 'invalid_output',
                'raw_preview' => substr((string) $raw, 0, 120),
            );
        }
        $understanding['raw_text'] = sanitize_text_field($message);
        $understanding['selected_transcript'] = sanitize_text_field($understanding_message);
        $understanding['selected_transcript_source'] = sanitize_key((string) ($candidate_selection['source'] ?? ''));
        $understanding['selected_transcript_reason'] = sanitize_key((string) ($candidate_selection['reason'] ?? ''));

        set_transient($cache_key, $understanding, 60);
        mia_debug_log('GrokUnderstand', 'success', array(
            'intent' => $understanding['intent'],
            'action' => $understanding['action'],
            'confidence' => $understanding['confidence'],
            'clean_text' => substr($understanding['clean_text'], 0, 80),
            'selected_source' => $understanding['selected_transcript_source'] ?? '',
        ));
        mia_debug_log('GrokNextAction', 'result', array(
            'intent' => $understanding['intent'],
            'next_action' => $understanding['next_action'],
            'confidence' => $understanding['confidence'],
            'target_rank' => $understanding['target_provider_rank'] ?? 0,
            'target_id' => $understanding['target_provider_id'] ?? 0,
            'target_name' => substr((string) ($understanding['target_provider_name'] ?? ''), 0, 80),
            'selected_source' => $understanding['selected_transcript_source'] ?? '',
        ));

        return $understanding;
    }

    private function get_grok_understood_text($message, $understanding) {
        if (empty($understanding) || !is_array($understanding)) {
            return $message;
        }
        $clean_text = trim((string) ($understanding['clean_text'] ?? ''));
        $confidence = floatval($understanding['confidence'] ?? 0);
        if ($clean_text !== '' && strlen($clean_text) >= 3 && $confidence >= 0.55) {
            return $clean_text;
        }
        return $message;
    }

    private function should_answer_with_grok_clarification($understanding, $result) {
        if (empty($understanding) || empty($understanding['needs_clarification'])) {
            return false;
        }

        $confidence = floatval($understanding['confidence'] ?? 0);
        if ($confidence >= 0.72) {
            return false;
        }

        $intent = $result['intent'] ?? '';
        $has_grounded_result = !empty($result['providers']) || in_array($intent, array(
            MyHH_Mia_Intent_Engine::INTENT_CRISIS,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_ADD,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_OWNER,
            MyHH_Mia_Intent_Engine::INTENT_PROVIDER_UPGRADE,
            MyHH_Mia_Intent_Engine::INTENT_SPONSOR,
        ), true);

        return !$has_grounded_result && $intent === MyHH_Mia_Intent_Engine::INTENT_GENERAL;
    }

    private function build_grok_clarification_reply($understanding) {
        $question = trim((string) ($understanding['clarification_question'] ?? ''));
        if ($question !== '') {
            return $question;
        }

        $language = strtolower((string) ($understanding['entities']['language'] ?? ''));
        if (strpos($language, 'es') === 0) {
            return 'Escuche solo parte de eso. ¿Quieres reclamar tu perfil, buscar un proveedor, o preguntar por un proveedor que ya viste?';
        }

        return 'I heard part of that. Did you mean claim your profile, find a provider, or ask about one of the providers I showed?';
    }

    /**
     * Primary intent classifier for the provider_followup / consumer_provider_search
     * distinction via Grok-3-mini (the most common source of ASR-garbled bugs).
     *
     * Contains a fast-path: when the frontend route_hint already says
     * provider_followup and we have recent providers, the call short-circuits
     * without an API round-trip.
     *
     * The PHP intent engine still handles everything else (claim, crisis, sponsor,
     * add, upgrade). Returns the intent string on success or null on
     * failure/timeout/invalid-output — allowing the caller to fall back.
     *
     * @param string $message    Raw user transcript from the Realtime session.
     * @param string $route_hint Frontend route hint (e.g. 'provider_followup').
     * @param array  $safe_prior Sanitized prior context with recent_providers.
     * @return string|null Intent string or null on failure.
     */
    private function classify_intent_with_grok($message, $route_hint, $safe_prior) {
        $trimmed = trim($message);
        if (strlen($trimmed) < 3) {
            return null;
        }

        // Fast deterministic pre-check: if the frontend route_hint already says
        // provider_followup AND we have recent providers, skip the API call.
        // The frontend hasHeroProviderFollowupSignal is reliable for short
        // ordinal/pronoun references, and this saves ~3s latency per turn.
        if ($route_hint === 'provider_followup' && !empty($safe_prior['recent_providers'])) {
            mia_debug_log('GrokClassify', 'fast_path_route_hint', array(
                'message' => substr($trimmed, 0, 60),
                'route_hint' => $route_hint,
            ));
            return 'provider_followup';
        }

        // Build context: recent provider names the user has seen
        $provider_names = array();
        if (!empty($safe_prior['recent_providers']) && is_array($safe_prior['recent_providers'])) {
            foreach (array_slice($safe_prior['recent_providers'], 0, 6) as $p) {
                $name = $p['name'] ?? '';
                if ($name) {
                    $provider_names[] = $name;
                }
            }
        }
        $provider_context = !empty($provider_names)
            ? 'Recent providers shown: ' . implode(', ', $provider_names)
            : 'No providers have been shown yet in this session.';

        // Route hint context
        $route_context = '';
        if ($route_hint === 'provider_followup') {
            $route_context = 'Note: The frontend detected this as a followup about a provider.';
        }

        // Prior search criteria context
        $prior_criteria = '';
        if (!empty($safe_prior['specialty'])) {
            $prior_criteria .= ' specialty=' . $safe_prior['specialty'];
        }
        if (!empty($safe_prior['primary_category_name'])) {
            $prior_criteria .= ' category=' . $safe_prior['primary_category_name'];
        }
        if (!empty($safe_prior['city'])) {
            $prior_criteria .= ' city=' . $safe_prior['city'];
        }
        $criteria_context = $prior_criteria ? 'Prior search criteria:' . $prior_criteria : '';

        $system_prompt = "You are an intent classifier for a mental health provider directory. " .
            "Analyze the user's message and conversation context, then output ONLY ONE WORD from this list, nothing else:\n\n" .
            "provider_followup — The user is asking about a provider they were just shown or talked about. " .
            "Examples: \"tell me more about the first one\", \"what insurance does she take\", " .
            "\"give me another provider\", \"tell me more\", \"I need insurance info about that one\", " .
            "\"his location\", \"the third one\", \"him\", \"her\", \"that one\", \"first one\". " .
            "This does NOT include looking for a NEW provider.\n\n" .
            "consumer_provider_search — The user wants to find NEW providers they haven't seen yet. " .
            "Examples: \"I need a therapist in Raleigh\", \"find me someone for anxiety\", " .
            "\"show me counselors\", \"I'm looking for help\", \"I need a psychiatrist\".\n\n" .
            "claim — The user is a provider wanting to claim or manage their directory profile. " .
            "Examples: \"I want to claim my listing\", \"claim my profile\", \"I'm a provider\".\n\n" .
            "crisis — The user expresses self-harm, suicide, or urgent danger. " .
            "Examples: \"I want to hurt myself\", \"suicidal\", \"I need help now\".\n\n" .
            "general — Anything else: greetings, chit-chat, thanks, off-topic questions, " .
            "or messages that don't clearly match the above.";

        $user_prompt = $provider_context . "\n" . $route_context . "\n" . $criteria_context . "\n\nUser message: " . $trimmed;

        $messages = array(
            array('role' => 'system', 'content' => $system_prompt),
            array('role' => 'user', 'content' => $user_prompt),
        );

        $response = $this->request_chat_completion(array(
            'timeout' => 5,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'grok-3-mini',
                'messages' => $messages,
                'max_tokens' => 10,
                'temperature' => 0.0,
            )),
        ));

        if (is_wp_error($response)) {
            mia_debug_log('GrokClassify', 'grok_error', array('error' => $response->get_error_message()));
            return null;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            mia_debug_log('GrokClassify', 'http_error', array('status' => $status));
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $raw = trim($body['choices'][0]['message']['content'] ?? '');

        $allowed = array(
            'provider_followup',
            'consumer_provider_search',
            'claim',
            'crisis',
            'general',
        );

        $normalized = strtolower(preg_replace('/[^a-zA-Z_]/', '', $raw));

        if (in_array($normalized, $allowed, true)) {
            mia_debug_log('GrokClassify', 'success', array(
                'message' => substr($trimmed, 0, 60),
                'intent'  => $normalized,
                'route_hint' => $route_hint,
            ));
            return $normalized;
        }

        mia_debug_log('GrokClassify', 'invalid_output', array('raw' => substr($raw, 0, 80)));
        return null;
    }

    /**
     * Throttle repeated chat requests from the same visitor.
     */
    private function enforce_rate_limit($bucket = 'chat') {
        $bucket = sanitize_key((string) $bucket);
        if ($bucket === '') {
            $bucket = 'chat';
        }
        $guest_limits = array(
            'chat'             => 30,
            'realtime_tool'    => 45,
            'predictive_prefetch' => 30,
            'realtime_session' => 30,
            'dynamic_greeting' => 30,
            'voice_metric'     => 90,
            'provider_outcome' => 60,
        );
        $user_limits = array(
            'chat'             => 90,
            'realtime_tool'    => 120,
            'predictive_prefetch' => 90,
            'realtime_session' => 90,
            'dynamic_greeting' => 90,
            'voice_metric'     => 180,
            'provider_outcome' => 180,
        );
        $limits = is_user_logged_in() ? $user_limits : $guest_limits;
        $limit = $limits[$bucket] ?? (is_user_logged_in() ? 90 : 30);
        $window = 5 * MINUTE_IN_SECONDS;
        $key = $this->get_rate_limit_key($bucket);
        $state = get_transient($key);

        if (!is_array($state)) {
            $state = array(
                'count' => 0,
            );
        }

        $state['count']++;
        set_transient($key, $state, $window);

        if ($state['count'] > $limit) {
            return new WP_Error(
                'mia_rate_limited',
                'Mia is getting a lot of requests right now. Please wait a moment and try again.'
            );
        }

        return true;
    }

    /**
     * Build a stable per-visitor rate-limit key.
     */
    private function get_rate_limit_key($bucket = 'chat') {
        $bucket = sanitize_key((string) $bucket);
        if ($bucket === '') {
            $bucket = 'chat';
        }
        if (is_user_logged_in()) {
            return 'mia_rate_limit_' . $bucket . '_user_' . get_current_user_id();
        }

        // Prefer the real visitor IP from Cloudflare / Hostinger proxy headers.
        // REMOTE_ADDR behind a load balancer is the proxy IP and would rate-limit
        // all visitors as a single bucket.  Validate the extracted value is a
        // real IP to prevent header injection from spoofing the key.
        $ip = 'unknown';
        foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR' ) as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                // X-Forwarded-For can be a comma-separated list; take the first entry.
                $candidate = trim( explode( ',', wp_unslash( $_SERVER[ $header ] ) )[0] );
                if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
                    $ip = $candidate;
                    break;
                }
            }
        }
        if ( $ip === 'unknown' && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'unknown';

        return 'mia_rate_limit_' . $bucket . '_' . md5($ip . '|' . $ua);
    }
    
    /**
     * General fallback when API is unavailable and engine returned no response.
     */
    private function get_general_fallback() {
        return "Hi, I'm Mia. Tell me what kind of support you're looking for, and I'll guide you to the right fit.\n\n" .
            "I can:\n" .
            "• **Find providers** who match what you need\n" .
            "• **Help providers add or claim a listing**\n" .
            "• **Explain categories, specialties, and trust signals** so it's easier to know where to start\n\n" .
            "What would you like to look at first?";
    }

    /**
     * Sanitize a provider profile URL, guarding against malformed URLs like
     * https:///listings/... that can appear when GeoDirectory returns paths
     * without a proper base.
     *
     * @param string $url Raw URL from provider data.
     * @return string Sanitized URL or '#' if invalid.
     */
    /**
     * Convert ALL-CAPS provider names from the DB into Title Case for
     * spoken/displayed output. "TANYA ALLEN-BURRIS" → "Tanya Allen-Burris".
     */
    private function prettify_provider_name($name) {
        $name = trim((string) $name);
        if ($name === '') return '';
        // Only re-case if the name is mostly uppercase (≥70%).  Mixed-case names
        // are likely already correct (e.g. "McKenzie", "O'Brien").
        $letters = preg_replace('/[^A-Za-z]/', '', $name);
        if ($letters === '') return $name;
        $upper_count = strlen(preg_replace('/[^A-Z]/', '', $letters));
        if (($upper_count / max(strlen($letters), 1)) < 0.7) {
            return $name;
        }
        $lower = strtolower($name);
        // Title-case but preserve hyphenated parts ("ALLEN-BURRIS" → "Allen-Burris")
        return preg_replace_callback('/\b([a-z])/u', function($m) { return strtoupper($m[1]); }, $lower);
    }

    /**
     * Join a list of strings into a human-readable sentence:
     *  ["a"]            → "a"
     *  ["a","b"]        → "a or b"
     *  ["a","b","c"]    → "a, b, or c"
     */
    private function join_human_list($items, $conjunction = 'or') {
        $items = array_values(array_filter(array_map('trim', (array) $items), function($s) { return $s !== ''; }));
        $n = count($items);
        if ($n === 0) return '';
        if ($n === 1) return $items[0];
        if ($n === 2) return $items[0] . " {$conjunction} " . $items[1];
        $last = array_pop($items);
        return implode(', ', $items) . ", {$conjunction} " . $last;
    }

    /**
     * Build a claim-listing URL for a given provider ID.
     * Hardcoded host: home_url() was producing malformed URLs in some request
     * contexts (https://claim-listing/?listing_id=714).  A hardcoded production
     * domain is the only reliable fix until we identify the URL-mangling layer.
     */
    private function build_claim_url($listing_id) {
        return 'https://myhealthub.com/claim-listing/?listing_id=' . absint($listing_id);
    }

    /**
     * Extract the provider name Grok identified from the user's message.
     * This is the single source of truth for name detection — natural-language
     * extraction is the LLM's job, not PHP regex.  PHP regex remains only as
     * a minimal safety net for the most common phrases when Grok is unavailable.
     */
    private function get_grok_extracted_provider_name($result, $understanding = null) {
        $u = is_array($understanding) ? $understanding : (is_array($result) ? ($result['_grok_understanding'] ?? array()) : array());
        if (!is_array($u)) return '';
        $name = '';
        if (isset($u['entities']['provider_name'])) {
            $name = trim((string) $u['entities']['provider_name']);
        }
        if ($name === '' && isset($u['target_provider_name'])) {
            $name = trim((string) $u['target_provider_name']);
        }
        if ($name === '' || strlen($name) < 2) return '';
        // Reject obvious non-names that Grok occasionally returns
        $junk = array('unknown', 'this', 'my', 'the', 'a', 'an', 'profile', 'listing', 'practice', 'name', 'none', 'null');
        if (in_array(strtolower($name), $junk, true)) return '';
        return $name;
    }

    /**
     * Extract the city Grok identified from the user's message.
     * Like the name helper, this is the trusted source — PHP regex is a fallback.
     * Reads from entities.city first, then search_entities.city as a fallback
     * (search-intent turns populate search_entities; claim turns populate entities).
     */
    private function get_grok_extracted_city($result, $understanding = null) {
        $u = is_array($understanding) ? $understanding : (is_array($result) ? ($result['_grok_understanding'] ?? array()) : array());
        if (!is_array($u)) return '';
        $city = '';
        if (isset($u['entities']['city'])) {
            $city = trim((string) $u['entities']['city']);
        }
        if ($city === '' && isset($u['search_entities']['city'])) {
            $city = trim((string) $u['search_entities']['city']);
        }
        if ($city === '' || strlen($city) < 2) return '';
        // Reject obvious non-cities
        $junk = array('unknown', 'this', 'my', 'the', 'a', 'an', 'here', 'there', 'nearby', 'none', 'null');
        if (in_array(strtolower($city), $junk, true)) return '';
        // Strip trailing state codes ("Raleigh NC", "Raleigh, NC") → "Raleigh"
        $city = preg_replace('/[,\s]+(?:NC|N\.C\.|North\s+Carolina|US|USA)\s*$/i', '', $city);
        $city = preg_replace('/\s+\d{5}(?:-\d{4})?\s*$/', '', $city); // trailing zip
        return trim($city);
    }

    private function sanitize_provider_url($url) {
        $url = trim((string) $url);
        if ($url === '' || $url === '#') {
            return '#';
        }
        // Collapse extra slashes after the authority delimiter.
        $url = preg_replace('#^(https?:)//+#', '$1//', $url);
        // Detect URLs with a missing/mangled host (e.g. "https://listings/foo"
        // where the host accidentally became the path segment) and repair them
        // to the production domain.  Same root cause as build_claim_url().
        if (preg_match('#^https?://(claim-listing|listings)(?:[/?]|$)#i', $url)) {
            $path = preg_replace('#^https?://#', '', $url);
            $url = 'https://myhealthub.com/' . ltrim($path, '/');
        }
        // Root-relative or bare path → prepend hardcoded production host.
        if (!preg_match('#^https?://#', $url)) {
            $url = 'https://myhealthub.com/' . ltrim($url, '/');
        }
        return $url;
    }

    /**
     * Curated bilingual knowledge for the 12 services MyHealthHub represents.
     * Provider availability still comes from the database; this catalog only
     * supplies stable, non-diagnostic educational context.
     */
    private function get_myhealthhub_category_knowledge_catalog() {
        return array(
            'primary-care-medical-services' => array(
                'name' => 'Primary Care & Medical Services',
                'aliases' => array('primary care', 'medical services', 'family medicine', 'internal medicine', 'pediatrics', 'preventive care', 'urgent care'),
                'en' => 'Routine and preventive healthcare, checkups, health screenings, management of common health concerns, family medicine, internal medicine, pediatrics, and referrals to specialists. Primary care is often the first professional stop for a new or ongoing health concern; emergencies require emergency services.',
                'es' => 'Atencion medica rutinaria y preventiva, chequeos, examenes de salud, manejo de problemas comunes, medicina familiar, medicina interna, pediatria y referencias a especialistas. La atencion primaria suele ser el primer punto profesional para una inquietud nueva o continua; las emergencias requieren servicios de emergencia.',
            ),
            'mental-health-therapy' => array(
                'name' => 'Mental Health & Therapy',
                'aliases' => array('mental health', 'therapy', 'counseling', 'psychotherapy', 'general counseling', 'psychiatry', 'talk therapy', 'couples counseling', 'family therapy'),
                'en' => 'Counseling, psychotherapy, psychological assessment, psychiatry, medication management, and relationship or family support. Common concerns include anxiety, depression, trauma, grief, stress, life transitions, substance use, attention concerns, and relationship difficulties. Modalities may include CBT, DBT, EMDR, play therapy, and other evidence-informed approaches.',
                'es' => 'Consejeria, psicoterapia, evaluacion psicologica, psiquiatria, manejo de medicamentos y apoyo para relaciones o familias. Los temas comunes incluyen ansiedad, depresion, trauma, duelo, estres, cambios de vida, uso de sustancias, atencion y relaciones. Las modalidades pueden incluir TCC, DBT, EMDR, terapia de juego y otros enfoques.',
            ),
            'fitness-yoga-movement' => array(
                'name' => 'Fitness, Yoga & Movement',
                'aliases' => array('fitness', 'yoga', 'movement', 'pilates', 'personal training', 'strength training', 'mobility', 'tai chi', 'barre'),
                'en' => 'Personal training, yoga, Pilates, strength and conditioning, mobility, stretching, dance, tai chi, and other movement-based services. Providers may focus on general fitness, instruction, performance, flexibility, or adapting movement to a person\'s goals and abilities.',
                'es' => 'Entrenamiento personal, yoga, Pilates, fuerza y acondicionamiento, movilidad, estiramiento, danza, tai chi y otros servicios basados en movimiento. Los proveedores pueden enfocarse en condicion fisica, instruccion, rendimiento, flexibilidad o adaptacion del movimiento a las metas y capacidades de la persona.',
            ),
            'nutrition-dietary-support' => array(
                'name' => 'Nutrition & Dietary Support',
                'aliases' => array('nutrition', 'dietary support', 'dietitian', 'nutritionist', 'nutrition counseling', 'food allergies', 'weight management'),
                'en' => 'Food and nutrition education, eating-pattern support, goal setting, food-allergy or sensitivity guidance, sports nutrition, and clinical nutrition counseling. Scope depends on credentials: registered dietitians can provide medical nutrition therapy, while nutrition or wellness coaches may focus on education, habits, and accountability.',
                'es' => 'Educacion sobre alimentos y nutricion, apoyo con patrones de alimentacion, metas, alergias o sensibilidades, nutricion deportiva y consejeria nutricional clinica. El alcance depende de las credenciales: los dietistas registrados pueden brindar terapia nutricional medica, mientras que otros coaches suelen enfocarse en educacion y habitos.',
            ),
            'massage-spa-recovery' => array(
                'name' => 'Massage, Spa & Recovery',
                'aliases' => array('massage', 'massage therapy', 'spa', 'recovery', 'bodywork', 'physical therapy', 'chiropractic', 'med spa', 'sports massage'),
                'en' => 'Massage therapy, bodywork, spa and aesthetic services, physical recovery, mobility support, and related hands-on services. Offerings can include relaxation, deep-tissue, sports or prenatal massage, physical therapy, chiropractic care, skincare, and recovery-focused services; credentials and scope vary by service.',
                'es' => 'Terapia de masaje, trabajo corporal, servicios de spa y estetica, recuperacion fisica, apoyo de movilidad y servicios manuales relacionados. Puede incluir masaje de relajacion, tejido profundo, deportivo o prenatal, fisioterapia, quiropractica, cuidado de la piel y servicios de recuperacion; las credenciales varian.',
            ),
            'sleep-rest-wellness' => array(
                'name' => 'Sleep & Rest Wellness',
                'aliases' => array('sleep', 'rest wellness', 'sleep coaching', 'sleep medicine', 'sleep hygiene', 'insomnia', 'sleep apnea'),
                'en' => 'Sleep education, sleep coaching, sleep-hygiene support, and clinical evaluation of sleep concerns. Sleep coaches generally help with routines and behavior, while licensed medical sleep professionals assess issues such as persistent insomnia, sleep apnea, or other sleep disorders.',
                'es' => 'Educacion del sueno, coaching del sueno, apoyo con higiene del sueno y evaluacion clinica de problemas de descanso. Los coaches suelen ayudar con rutinas y habitos, mientras que los profesionales medicos evalúan insomnio persistente, apnea u otros trastornos del sueno.',
            ),
            'social-connections-support-groups' => array(
                'name' => 'Social Connections & Support Groups',
                'aliases' => array('social connections', 'support groups', 'peer support', 'community support', 'group support', 'recovery groups'),
                'en' => 'Peer-support groups, facilitated support groups, community programs, recovery groups, grief groups, and services that reduce isolation or build connection. Some groups are clinician-led, while others are peer-led or community-based, so facilitation and confidentiality practices may differ.',
                'es' => 'Grupos de apoyo entre pares, grupos facilitados, programas comunitarios, grupos de recuperacion o duelo y servicios que reducen el aislamiento. Algunos grupos son dirigidos por profesionales y otros por pares o comunidades, por lo que la facilitacion y confidencialidad pueden variar.',
            ),
            'financial-wellness-planning' => array(
                'name' => 'Financial Wellness & Planning',
                'aliases' => array('financial wellness', 'financial planning', 'financial therapy', 'money coaching', 'budgeting', 'debt counseling', 'retirement planning'),
                'en' => 'Financial education, budgeting, debt support, financial planning, retirement planning, money coaching, and financial therapy. Advisors may focus on financial products and plans, coaches on habits and accountability, and financial therapists on the emotional or relationship patterns connected to money. Mia explains these roles but does not provide personalized financial advice.',
                'es' => 'Educacion financiera, presupuestos, apoyo con deudas, planificacion financiera o de jubilacion, coaching de dinero y terapia financiera. Los asesores, coaches y terapeutas financieros tienen funciones distintas. Mia puede explicar esas diferencias, pero no brinda asesoramiento financiero personalizado.',
            ),
            'career-work-life-coaching' => array(
                'name' => 'Career & Work-Life Coaching',
                'aliases' => array('career coaching', 'work-life coaching', 'executive coaching', 'leadership coaching', 'job transition', 'professional development', 'work stress'),
                'en' => 'Career direction, job transitions, professional development, leadership, executive coaching, workplace communication, and work-life boundaries. Coaches commonly help users clarify goals, decisions, strengths, and action plans; they are not substitutes for licensed mental-health care when clinical support is needed.',
                'es' => 'Direccion profesional, cambios de trabajo, desarrollo profesional, liderazgo, coaching ejecutivo, comunicacion laboral y limites entre trabajo y vida. Los coaches ayudan a aclarar metas, decisiones, fortalezas y planes de accion; no sustituyen la atencion de salud mental cuando se necesita apoyo clinico.',
            ),
            'integrative-holistic-therapies' => array(
                'name' => 'Integrative & Holistic Therapies',
                'aliases' => array('integrative', 'holistic', 'acupuncture', 'naturopathy', 'functional medicine', 'traditional chinese medicine', 'reiki', 'ayurveda', 'herbal medicine'),
                'en' => 'Whole-person and complementary approaches such as acupuncture, traditional Chinese medicine, naturopathy, functional or integrative medicine, Ayurveda, herbal practices, and energy-based services such as Reiki. Training, licensure, evidence, and scope differ substantially, so users should review credentials and coordinate medical concerns with licensed healthcare professionals.',
                'es' => 'Enfoques integrales y complementarios como acupuntura, medicina china tradicional, naturopatia, medicina funcional o integrativa, Ayurveda, practicas herbales y servicios energeticos como Reiki. La formacion, licencia, evidencia y alcance varian, por lo que conviene revisar credenciales y coordinar temas medicos con profesionales licenciados.',
            ),
            'meditation-mindfulness-spirituality' => array(
                'name' => 'Meditation, Mindfulness & Spirituality',
                'aliases' => array('meditation', 'mindfulness', 'spirituality', 'breathwork', 'spiritual direction', 'meditation coaching'),
                'en' => 'Meditation instruction, mindfulness education, breath-awareness practices, contemplative programs, and spiritual direction or companionship. Services may be secular, faith-based, group-based, or individual; users can choose according to their goals, beliefs, and preferred setting.',
                'es' => 'Instruccion de meditacion, educacion de mindfulness, practicas de respiracion consciente, programas contemplativos y direccion o acompanamiento espiritual. Los servicios pueden ser seculares, religiosos, grupales o individuales segun las metas y preferencias de la persona.',
            ),
            'personal-growth-learning' => array(
                'name' => 'Personal Growth & Learning',
                'aliases' => array('personal growth', 'personal development', 'life coaching', 'wellness coaching', 'health coaching', 'mentoring', 'self improvement', 'confidence coaching'),
                'en' => 'Life coaching, health or wellness coaching, mentoring, personal development, confidence building, goal setting, accountability, and educational programs. These services support learning and forward-looking goals; they do not diagnose or treat mental-health conditions unless the provider also holds an appropriate clinical license.',
                'es' => 'Coaching de vida, salud o bienestar, mentoria, desarrollo personal, confianza, metas, responsabilidad y programas educativos. Estos servicios apoyan el aprendizaje y objetivos futuros; no diagnostican ni tratan condiciones de salud mental a menos que el proveedor tenga una licencia clinica adecuada.',
            ),
        );
    }

    /**
     * Build category knowledge section for the system prompt.
     * The curated catalog is authoritative for the core 12 categories; DB
     * descriptions extend the prompt only for future categories.
     */
    private function build_category_knowledge($categories) {
        $catalog = $this->get_myhealthhub_category_knowledge_catalog();

        $lines = array();
        foreach ($categories as $cat) {
            $name = $cat['name'];
            $slug = $cat['slug'];

            $desc = '';
            if (!empty($catalog[$slug]['en'])) {
                $desc = $catalog[$slug]['en'];
            } else {
                $term = get_term_by('slug', $slug, 'myhh_primary_category');
                if ($term && !empty($term->description)) {
                    $desc = trim($term->description);
                }
            }
            if (!empty($desc)) {
                $lines[] = "- **{$name}** — {$desc}";
            } else {
                $lines[] = "- **{$name}** — A recognized directory specialty. Explain its established, non-diagnostic wellness meaning when asked, without assuming that any specific provider offers it.";
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Build specialty knowledge section for the system prompt.
     * Reads term descriptions from the DB; falls back to hardcoded defaults when empty.
     */
    private function build_specialty_knowledge($specialties) {
        $fallbacks = array(
            'anxiety'         => 'Generalized anxiety, social anxiety, panic disorder, phobias, and OCD-related anxiety',
            'depression'      => 'Major depression, persistent depressive disorder, and mood regulation',
            'trauma-ptsd'     => 'Post-traumatic stress, complex trauma, and abuse recovery',
            'ocd'             => 'Obsessive-compulsive disorder, intrusive thoughts, and compulsive behaviors',
            'grief'           => 'Bereavement, complicated grief, and loss adjustment',
            'couples'         => 'Communication issues, infidelity, intimacy, and premarital support',
            'family'          => 'Family conflict, parenting challenges, and blended family dynamics',
            'substance-use'   => 'Drug and alcohol counseling, harm reduction, and recovery support',
            'eating-disorders' => 'Anorexia, bulimia, binge eating, and body image',
            'bipolar'         => 'Bipolar disorder management, mood stabilization, and episode prevention',
            'lgbtq'           => 'Identity, coming out, transition support, and affirming care',
            'anger-management' => 'Emotional regulation, de-escalation, and impulse control',
            'chronic-illness' => 'Coping with diagnosis, pain management, and medical trauma',
            'mindfulness'     => 'MBSR, meditation, grounding techniques, and breathwork',
        );

        $lines = array();
        foreach ($specialties as $spec) {
            $name = $spec['name'];
            $slug = isset($spec['slug']) ? $spec['slug'] : sanitize_title($name);

            $term = get_term_by('slug', $slug, 'myhh_specialty');
            $desc = '';
            if ($term && !empty($term->description)) {
                $desc = trim($term->description);
            }
            if (empty($desc)) {
                // Try name-based fallback
                foreach ($fallbacks as $fslug => $fdesc) {
                    if (strpos($slug, $fslug) !== false || strpos(strtolower($name), str_replace('-', ' ', $fslug)) !== false) {
                        $desc = $fdesc;
                        break;
                    }
                }
            }
            if (!empty($desc)) {
                $lines[] = "- **{$name}** — {$desc}";
            } else {
                $lines[] = "- **{$name}** — (no description yet)";
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Build license type knowledge section for the system prompt.
     * Reads term descriptions from the DB; falls back to hardcoded defaults when empty.
     */
    private function build_license_knowledge($license_types) {
        $fallbacks = array(
            'lcsw'  => 'Licensed Clinical Social Worker — Master\'s-level therapist trained in psychotherapy, case management, and community resources. Can diagnose mental health conditions.',
            'lpc'   => 'Licensed Professional Counselor / Licensed Clinical Mental Health Counselor — Master\'s-level therapist specializing in talk therapy, CBT, and solution-focused approaches. Can diagnose mental health conditions.',
            'lcmhc' => 'Licensed Clinical Mental Health Counselor — Master\'s-level therapist specializing in talk therapy, CBT, and solution-focused approaches. Can diagnose mental health conditions.',
            'lmft'  => 'Licensed Marriage and Family Therapist — Specialized in relationship and family systems therapy. Trained to treat couples and family dynamics.',
            'psyd'  => 'Doctor of Psychology — Doctoral-level psychologist with extensive training in psychological assessment, testing, and therapy.',
            'phd'   => 'Doctor of Philosophy in Psychology — Doctoral-level psychologist focused on research, assessment, and therapy.',
            'pmhnp' => 'Psychiatric Mental Health Nurse Practitioner — Can prescribe medication and provide therapy. Advanced practice nurse with psychiatric specialization.',
            'md'    => 'Medical Doctor / Psychiatrist — Medical doctor specializing in psychiatry. Can prescribe medication, diagnose, and provide therapy.',
            'do'    => 'Doctor of Osteopathic Medicine / Psychiatrist — Medical doctor specializing in psychiatry. Can prescribe medication, diagnose, and provide therapy.',
        );

        $lines = array();
        foreach ($license_types as $lic_name) {
            $slug = sanitize_title($lic_name);
            $key = strtolower(preg_replace('/[^a-z]/i', '', $lic_name));

            $term = get_term_by('slug', $slug, 'myhh_license_type');
            if (!$term) {
                $term = get_term_by('name', $lic_name, 'myhh_license_type');
            }
            $desc = '';
            if ($term && !empty($term->description)) {
                $desc = trim($term->description);
            }
            if (empty($desc)) {
                // Try key-based fallback
                foreach ($fallbacks as $fkey => $fdesc) {
                    if (strpos($key, $fkey) !== false || strpos(strtolower($lic_name), $fkey) !== false) {
                        $desc = $fdesc;
                        break;
                    }
                }
            }
            if (!empty($desc)) {
                $lines[] = "- **{$lic_name}** — {$desc}";
            } else {
                $lines[] = "- **{$lic_name}** — (no description yet)";
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Get dynamic category knowledge for deterministic responses.
     * Reads term descriptions from DB; falls back to hardcoded bilingual defaults.
     * Keys are human-readable names used in regex matching against user messages.
     *
     * @param string $lang 'en' or 'es'
     * @return array [searchable_key => ['en' => '...', 'es' => '...']]
     */
    private function get_dynamic_category_knowledge($lang = 'en') {
        $catalog = $this->get_myhealthhub_category_knowledge_catalog();
        $fallbacks = array();
        foreach ($catalog as $item) {
            $texts = array(
                'en' => '**' . $item['name'] . '** — ' . $item['en'],
                'es' => '**' . $item['name'] . '** — ' . $item['es'],
            );
            foreach ($item['aliases'] as $alias) {
                $fallbacks[strtolower($alias)] = $texts;
            }
            $fallbacks[strtolower($item['name'])] = $texts;
        }

        // Try to enrich from DB term descriptions
        $terms = get_terms(array(
            'taxonomy'   => 'myhh_primary_category',
            'hide_empty' => false,
        ));
        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                if (!empty($term->description)) {
                    $key = strtolower($term->name);
                    if (!isset($fallbacks[$key])) {
                        $fallbacks[$key] = array(
                            'en' => "**{$term->name}** — {$term->description}",
                            'es' => "**{$term->name}** — {$term->description}",
                        );
                    }
                }
            }
        }

        return $fallbacks;
    }

    /**
     * Get dynamic license type knowledge for deterministic responses.
     * Reads term descriptions from DB; falls back to hardcoded bilingual defaults.
     *
     * @param string $lang 'en' or 'es'
     * @return array [searchable_key => ['en' => '...', 'es' => '...']]
     */
    private function get_dynamic_license_knowledge($lang = 'en') {
        $fallbacks = array(
            'lcsw' => array(
                'en' => "**LCSW** (Licensed Clinical Social Worker) — A Master's-level therapist trained in psychotherapy, case management, and community resources. Can diagnose mental health conditions and provide individual, family, and group therapy.",
                'es' => "**LCSW** (Trabajador Social Clinico Licenciado) — Terapeuta con maestria capacitado en psicoterapia, gestion de casos y recursos comunitarios. Puede diagnosticar condiciones de salud mental.",
            ),
            'lpc' => array(
                'en' => "**LPC/LCMHC** (Licensed Professional Counselor / Licensed Clinical Mental Health Counselor) — A Master's-level therapist specializing in talk therapy, CBT, and solution-focused approaches. Can diagnose mental health conditions.",
                'es' => "**LPC/LCMHC** (Consejero Profesional Licenciado) — Terapeuta con maestria especializado en terapia de conversacion, TCC y enfoques centrados en soluciones. Puede diagnosticar condiciones de salud mental.",
            ),
            'lmft' => array(
                'en' => "**LMFT** (Licensed Marriage and Family Therapist) — Specialized in relationship and family systems therapy. Trained to treat couples and family dynamics, communication issues, and intimacy concerns.",
                'es' => "**LMFT** (Terapeuta Matrimonial y Familiar Licenciado) — Especializado en terapia de relaciones y sistemas familiares.",
            ),
            'psyd' => array(
                'en' => "**PsyD** (Doctor of Psychology) — A doctoral-level psychologist with extensive training in psychological assessment, testing, and therapy. Can provide in-depth evaluations and specialized treatment.",
                'es' => "**PsyD** (Doctor en Psicologia) — Psicologo a nivel doctoral con amplia capacitacion en evaluacion e intervencion psicologica.",
            ),
            'phd' => array(
                'en' => "**PhD** (Doctor of Philosophy in Psychology) — A doctoral-level psychologist focused on research, assessment, and evidence-based therapy. Can provide psychological testing and specialized treatment.",
                'es' => "**PhD** (Doctor en Filosofia de la Psicologia) — Psicologo a nivel doctoral enfocado en investigacion, evaluacion y terapia basada en evidencia.",
            ),
            'pmhnp' => array(
                'en' => "**PMHNP** (Psychiatric Mental Health Nurse Practitioner) — An advanced practice nurse with psychiatric specialization. Can prescribe medication and provide therapy.",
                'es' => "**PMHNP** (Enfermera Practicante de Salud Mental Psiquiatrica) — Enfermera de practica avanzada con especializacion psiquiatrica. Puede recetar medicamentos y proporcionar terapia.",
            ),
            'psychiatrist' => array(
                'en' => "**Psychiatrist (MD/DO)** — A medical doctor specializing in psychiatry. Can prescribe medication, diagnose conditions, and provide therapy. Often focuses on medication management alongside therapy.",
                'es' => "**Psiquiatra (MD/DO)** — Doctor en medicina especializado en psiquiatria. Puede recetar medicamentos, diagnosticar condiciones y proporcionar terapia.",
            ),
        );

        // Enrich from DB term descriptions
        $terms = get_terms(array(
            'taxonomy'   => 'myhh_license_type',
            'hide_empty' => false,
        ));
        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                if (!empty($term->description)) {
                    $key = strtolower(preg_replace('/[^a-z]/i', '', $term->name));
                    foreach ($fallbacks as $fkey => $fval) {
                        if (strpos($key, $fkey) !== false || strpos($fkey, $key) !== false) {
                            $fallbacks[$fkey]['en'] = "**{$term->name}** — {$term->description}";
                            break;
                        }
                    }
                }
            }
        }

        return $fallbacks;
    }

    /**
     * Build a dynamic license overview response listing all license types in the directory.
     * Reads descriptions from DB; falls back to hardcoded.
     *
     * @param string $lang 'en' or 'es'
     * @return string
     */
    private function build_dynamic_license_overview($lang = 'en') {
        $knowledge = $this->get_dynamic_license_knowledge($lang);
        $lines = array();

        if ($lang === 'es') {
            $lines[] = "Estos son los tipos de licencia que aparecen en nuestro directorio:";
        } else {
            $lines[] = "Here are the license types you'll see in our directory:";
        }

        foreach ($knowledge as $key => $texts) {
            $lines[] = "- " . ($texts[$lang] ?? $texts['en']);
        }

        if ($lang === 'es') {
            $lines[] = "\nSi me dices que tipo de ayuda buscas, puedo sugerirte el proveedor adecuado.";
        } else {
            $lines[] = "\nIf you tell me what kind of help you're looking for, I can suggest the right type of provider.";
        }

        return implode("\n", $lines);
    }

    /**
     * Build a dynamic specialty overview response listing all specialties in the directory.
     * Reads descriptions from DB; falls back to hardcoded.
     *
     * @param string $lang 'en' or 'es'
     * @return string
     */
    private function build_dynamic_specialty_overview($lang = 'en') {
        // Get specialties from DB
        $terms = get_terms(array(
            'taxonomy'   => 'myhh_specialty',
            'hide_empty' => false,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ));

        // Hardcoded bilingual fallbacks
        $fallbacks = array(
            'anxiety' => array('en' => 'Generalized anxiety, social anxiety, panic disorder, phobias, and OCD-related anxiety', 'es' => 'Ansiedad generalizada, ansiedad social, trastorno de panico, fobias y ansiedad relacionada con TOC'),
            'depression' => array('en' => 'Major depression, persistent depressive disorder, and mood regulation', 'es' => 'Depresion mayor, trastorno depresivo persistente y regulacion del estado de animo'),
            'trauma' => array('en' => 'Post-traumatic stress, complex trauma, and abuse recovery', 'es' => 'Estres post-traumatico, trauma complejo y recuperacion de abuso'),
            'ocd' => array('en' => 'Obsessive-compulsive disorder, intrusive thoughts, and compulsive behaviors', 'es' => 'Trastorno obsesivo-compulsivo, pensamientos intrusivos y comportamientos compulsivos'),
            'grief' => array('en' => 'Bereavement, complicated grief, and loss adjustment', 'es' => 'Duelo, duelo complicado y ajuste a la perdida'),
            'couples' => array('en' => 'Communication issues, infidelity, intimacy, and premarital support', 'es' => 'Problemas de comunicacion, infidelidad, intimidad y apoyo prematrimonial'),
            'family' => array('en' => 'Family conflict, parenting challenges, and blended family dynamics', 'es' => 'Conflicto familiar, desafios de crianza y dinamicas de familias reconstituidas'),
            'substance' => array('en' => 'Drug and alcohol counseling, harm reduction, and recovery support', 'es' => 'Consejeria de drogas y alcohol, reduccion de danos y apoyo a la recuperacion'),
            'eating disorders' => array('en' => 'Anorexia, bulimia, binge eating, and body image', 'es' => 'Anorexia, bulimia, atracones e imagen corporal'),
            'bipolar' => array('en' => 'Bipolar disorder management, mood stabilization, and episode prevention', 'es' => 'Manejo del trastorno bipolar, estabilizacion del estado de animo y prevencion de episodios'),
            'lgbtq' => array('en' => 'Identity, coming out, transition support, and affirming care', 'es' => 'Identidad, salir del closet, apoyo en transicion y cuidado afirmativo'),
            'anger' => array('en' => 'Emotional regulation, de-escalation, and impulse control', 'es' => 'Regulacion emocional, desescalamiento y control de impulsos'),
            'chronic' => array('en' => 'Coping with diagnosis, pain management, and medical trauma', 'es' => 'Afrontamiento de diagnosticos, manejo del dolor y trauma medico'),
            'mindfulness' => array('en' => 'MBSR, meditation, grounding techniques, and breathwork', 'es' => 'MBSR, meditacion, tecnicas de grounding y trabajo con la respiracion'),
        );

        if ($lang === 'es') {
            $lines = array("Estas son las especialidades que cubren nuestros proveedores:");
        } else {
            $lines = array("Here are the specialties our providers cover:");
        }

        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $name = $term->name;
                $desc = '';
                // Try DB description first
                if (!empty($term->description)) {
                    $desc = $term->description;
                }
                // Fallback to hardcoded
                if (empty($desc)) {
                    $slug = strtolower($term->slug);
                    foreach ($fallbacks as $fkey => $fdesc) {
                        if (strpos($slug, $fkey) !== false || strpos(strtolower($name), str_replace('-', ' ', $fkey)) !== false) {
                            $desc = $fdesc[$lang] ?? $fdesc['en'];
                            break;
                        }
                    }
                }
                if (!empty($desc)) {
                    $lines[] = "- **{$name}** — {$desc}";
                } else {
                    $lines[] = "- **{$name}**";
                }
            }
        } else {
            // No terms from DB, use fallbacks
            foreach ($fallbacks as $key => $descs) {
                $name = ucwords(str_replace('-', ' ', $key));
                $lines[] = "- **{$name}** — " . ($descs[$lang] ?? $descs['en']);
            }
        }

        if ($lang === 'es') {
            $lines[] = "\nDime que especialidad te interesa y te ayudare a encontrar un proveedor.";
        } else {
            $lines[] = "\nTell me which specialty you're interested in and I'll help you find a provider.";
        }

        return implode("\n", $lines);
    }

    private function format_category_overview_response($context, $lang = 'en') {
        $categories = !empty($context['categories']) && is_array($context['categories']) ? $context['categories'] : array();
        $counts = array();
        foreach ($categories as $category) {
            $slug = sanitize_title($category['slug'] ?? '');
            if ($slug !== '') {
                $counts[$slug] = intval($category['count'] ?? 0);
            }
        }
        $lines = array();
        foreach ($this->get_myhealthhub_category_knowledge_catalog() as $slug => $item) {
            $count = intval($counts[$slug] ?? 0);
            if ($lang === 'es') {
                $status = $count > 0
                    ? sprintf('%d %s disponible%s', $count, $count === 1 ? 'perfil' : 'perfiles', $count === 1 ? '' : 's')
                    : 'sin perfiles publicados actualmente';
            } else {
                $status = $count > 0
                    ? sprintf('%d %s currently available', $count, $count === 1 ? 'profile' : 'profiles')
                    : 'no profiles currently published';
            }
            $description = $item[$lang] ?? $item['en'];
            $lines[] = "• **{$item['name']}** ({$status})\n  {$description}";
        }

        $list = implode("\n\n", $lines);
        if ($lang === 'es') {
            return "Puedo explicar y buscar servicios dentro de estas **12 categorias principales de bienestar**. La descripcion indica lo que cubre cada area; la disponibilidad de perfiles se muestra por separado:\n\n" .
                "{$list}\n\n" .
                "Tambien ayudo a los proveedores a **agregar o reclamar un perfil** y explico como funcionan los perfiles, credenciales y opciones de visibilidad. Dime un area y puedo explicarla con mas detalle o buscar perfiles disponibles.";
        }

        return "I can explain and search services within these **12 primary wellness categories**. The description shows what each area covers; current profile availability is shown separately:\n\n" .
            "{$list}\n\n" .
            "I also help providers **add or claim a profile** and explain how listings, credentials, and visibility options work. Name any area and I can explain it further or search the profiles currently available.";
    }

    private function get_basic_education_fallback_response($message, $lang = 'en') {
        $message = trim((string) $message);

        // These natural follow-ups may omit the original subject. Keep them
        // deterministic because the answer carries an important safety or
        // evidence boundary even when the realtime model is unavailable.
        if (preg_match('/\bwhat\s+credentials?\s+should\s+i\s+check\b|\bhow\s+(?:do|should)\s+i\s+(?:check|evaluate|choose)\s+(?:their\s+)?credentials?\b/iu', $message)) {
            return ($lang === 'es')
                ? 'Revisa la **licencia profesional**, el organismo que la emitio, si esta vigente, la formacion relevante y si el perfil documenta experiencia con el servicio que buscas. Para terapia financiera, distingue una licencia clinica de una certificacion financiera: una persona puede tener una, ambas o ninguna. Confirma siempre las credenciales directamente con el organismo emisor.'
                : 'Check the provider\'s **professional license**, the issuing board, whether it is current, relevant training, and whether the profile documents experience with the service you need. For financial therapy, distinguish a clinical license from a financial credential: someone may hold one, both, or neither. Always confirm credentials with the issuing organization.';
        }
        if (preg_match('/\b(?:can|could|will|would)\s+you\s+guarantee\b|\bguarantee\b[\s\S]{0,60}\b(?:treatment|therapy|acupuncture|physical\s+therapy|work|result|outcome)\b/iu', $message)) {
            return ($lang === 'es')
                ? 'No. No puedo garantizar que un tratamiento o proveedor funcione para una persona. Puedo explicar diferencias generales y mostrar perfiles relevantes, pero los resultados dependen de la situacion individual, la evaluacion profesional y otros factores.'
                : 'No. I cannot guarantee that a treatment or provider will work for someone. I can explain general differences and show relevant profiles, but outcomes depend on the individual situation, professional assessment, and other factors.';
        }

        if (!MyHH_Mia_Intent_Engine::is_basic_education_question($message)) {
            return null;
        }

        // Do not answer a comparison with a one-topic definition. Let the existing
        // domain-grounded Grok path interpret both concepts in the same request.
        if (preg_match('/\b(?:difference|different|compare|versus|vs|contrast|diferencia|comparar)\b/iu', $message)) {
            return null;
        }

        $definitions = array(
            '/(?=.*\b(?:career\s+coach(?:ing)?|work[-\s]?life\s+coach(?:ing)?|coaching\s+profesional)\b)(?=.*\b(?:counsel(?:ing|ling)|therap(?:y|ist)|consejer[ií]a|terapia)\b)/iu' => array(
                'en' => '**Career coaching and counseling can both help around work concerns, but they serve different roles.** Career or work-life coaching is non-clinical and focuses on goals, decisions, job transitions, workplace communication, and practical action. Licensed counseling addresses emotional distress and patterns affecting wellbeing, including work-related stress, and may assess or treat mental-health concerns within the clinician\'s scope. If the main need is strategy and accountability, coaching may fit; if distress or emotional impact is central, licensed counseling is the more appropriate type to explore.',
                'es' => '**El coaching profesional y la consejeria pueden ayudar con temas laborales, pero cumplen funciones distintas.** El coaching profesional no es clinico y se centra en metas, decisiones, transiciones, comunicacion y planes de accion. La consejeria con licencia aborda el malestar emocional y patrones que afectan el bienestar, incluido el estres laboral, dentro del alcance clinico del profesional. Si la necesidad principal es estrategia y responsabilidad, puede encajar el coaching; si predomina el impacto emocional, corresponde explorar consejeria con licencia.',
            ),
            '/(?=.*\b(?:cbt|cognitive\s+behavio(?:u)?ral\s+therapy|tcc)\b)(?=.*\b(?:emdr|eye\s+movement\s+desensitization)\b)/iu' => array(
                'en' => '**CBT and EMDR are structured therapies, but their emphasis differs.** CBT examines links among thoughts, feelings, and behavior and often uses concrete skills and practice between sessions. EMDR is a structured psychotherapy approach in which a trained clinician uses bilateral stimulation while working with distressing or trauma-related memories. Neither is automatically the right fit for a person; a qualified clinician should assess needs, training, and appropriateness.',
                'es' => '**La TCC y EMDR son terapias estructuradas, pero tienen enfoques distintos.** La TCC examina la relacion entre pensamientos, emociones y conducta y suele usar habilidades concretas y practica entre sesiones. EMDR es un enfoque psicoterapeutico estructurado en el que un profesional capacitado usa estimulacion bilateral al trabajar con recuerdos angustiantes o relacionados con trauma. Un profesional calificado debe evaluar las necesidades y si alguno de los enfoques es apropiado.',
            ),
            '/(?=.*\b(?:general\s+counsel(?:ing|ling)|consejer[ií]a\s+general)\b)(?=.*\b(?:psychotherap(?:y|ist)|psicoterapia)\b)(?:[\s\S]*\b(?:belong(?:s|ed)?\s+to|part\s+of|same\s+as|type\s+of|kind\s+of)\b|[\s\S]*(?:\b(?:did|do|would)\s+you\s+|\byou\s+)(?:suggest(?:ed)?|recommend(?:ed)?|mean|said?)\b)/iu' => array(
                'en' => '**General counseling and psychotherapy overlap, but one is not simply a database subcategory of the other.** General counseling is a broad service label for talk-based support around concerns such as stress, relationships, grief, anxiety, or life transitions. Psychotherapy is a broad clinical process that can include general counseling and more specialized approaches. In practice, the provider\'s license, training, documented specialties, and methods matter more than the label alone.',
                'es' => '**La consejeria general y la psicoterapia se superponen, pero una no es simplemente una subcategoria de la otra.** La consejeria general es una etiqueta amplia para apoyo conversacional sobre estres, relaciones, duelo, ansiedad o transiciones de vida. La psicoterapia es un proceso clinico amplio que puede incluir consejeria general y enfoques mas especializados. En la practica, importan mas la licencia, la formacion, las especialidades documentadas y los metodos del proveedor que la etiqueta por si sola.',
            ),
            '/(?=.*\b(?:sleep\s+coach(?:ing)?|sleep\s+support|coaching\s+del\s+sueno)\b)(?=.*\b(?:counsel(?:ing|ling)|therap(?:y|ist)|consejer[ií]a|terapia|terapeuta)\b)/iu' => array(
                'en' => '**Sleep coaching, counseling, and therapy can overlap, but their focus differs.** Sleep coaching usually concentrates on routines, schedules, habits, and the sleep environment; it does not diagnose or treat a sleep disorder unless the provider separately holds an appropriate clinical license. Counseling is broad, talk-based support that often focuses on a current concern such as work stress or a life transition. Therapy is an overlapping term that may also address longer-standing emotional or behavioral patterns through approaches such as CBT. For persistent insomnia, suspected sleep apnea, or another medical sleep concern, a licensed medical professional is the appropriate evaluator. Exact services depend on each provider\'s credentials and documented approach.',
                'es' => '**El coaching del sueno, la consejeria y la terapia pueden superponerse, pero tienen enfoques distintos.** El coaching del sueno suele centrarse en rutinas, horarios, habitos y el ambiente de descanso; no diagnostica ni trata un trastorno del sueno salvo que el proveedor tambien tenga una licencia clinica apropiada. La consejeria ofrece apoyo conversacional amplio para una preocupacion actual, como estres laboral o una transicion. La terapia puede abordar tambien patrones emocionales o de conducta de mas largo plazo mediante enfoques como la TCC. Para insomnio persistente, posible apnea u otra inquietud medica del sueno, corresponde una evaluacion por un profesional medico licenciado. Los servicios exactos dependen de las credenciales y el enfoque documentado de cada proveedor.',
            ),
            '/(?=.*\b(?:somatic\s+therap(?:y|ies)|somatic|terapia\s+som[aá]tica)\b)(?=.*\b(?:cbt|cognitive\s+behavio(?:u)?ral\s+therapy|tcc|terapia\s+cognitivo[-\s]?conductual)\b)/iu' => array(
                'en' => '**Somatic therapy** is an umbrella term for approaches that pay attention to body sensations and physical responses alongside conversation; the exact method and required training vary. **CBT** is a more structured talk-therapy approach that examines links among thoughts, feelings, and behavior and practices specific skills. Neither label alone proves that an approach is appropriate or that a provider has particular training. I cannot determine personal fit, diagnose a condition, predict outcomes, or confirm a provider\'s modality, insurance, or availability unless those facts are documented in the profile.',
                'es' => '**La terapia somatica** es un termino amplio para enfoques que prestan atencion a las sensaciones corporales y respuestas fisicas junto con la conversacion; el metodo exacto y la formacion requerida varian. **La TCC** es un enfoque conversacional mas estructurado que examina la relacion entre pensamientos, emociones y conducta y practica habilidades especificas. Ninguna etiqueta por si sola demuestra que un enfoque sea adecuado ni que un proveedor tenga una formacion concreta. No puedo determinar el ajuste personal, diagnosticar, predecir resultados ni confirmar modalidad, seguro o disponibilidad sin datos documentados en el perfil.',
            ),
            '/(?=.*\b(?:counsel(?:ing|ling)|consejer[ií]a)\b)(?=.*\b(?:therap(?:y|ist)|terapia|terapeuta)\b)(?=.*\b(?:difference|different|compare|diferencia|compara|distint[oa]s?)\b)/iu' => array(
                'en' => '**Counseling and therapy overlap heavily.** Counseling often focuses on a current concern or life transition, while therapy may also explore longer-term emotional or behavioral patterns. In practice, licensed counselors and therapists can provide many of the same services, so the provider\'s license, specialties, training, and documented approach matter more than the label alone. The exact scope varies by professional and jurisdiction.',
                'es' => '**La consejeria y la terapia se superponen mucho.** La consejeria suele centrarse en una preocupacion actual o una transicion de vida, mientras que la terapia tambien puede explorar patrones emocionales o de conducta de mas largo plazo. En la practica, consejeros y terapeutas licenciados pueden ofrecer muchos servicios similares, por lo que la licencia, las especialidades, la formacion y el enfoque documentado importan mas que la etiqueta. El alcance exacto varia segun el profesional y la jurisdiccion.',
            ),
            '/\b(?:difference\s+between|compare)[\s\S]{0,100}\btherapist\b[\s\S]{0,100}\bcounselor\b[\s\S]{0,100}\bpsychologist\b[\s\S]{0,100}\bpsychiatrist\b/iu' => array(
                'en' => 'These titles overlap, but their training and scope differ. A **therapist** is a broad label for a professional who provides therapy. A **licensed counselor** commonly provides assessment and talk therapy. A **psychologist** has doctoral-level psychology training and may provide testing as well as therapy. A **psychiatrist** is a medical doctor who evaluates mental-health conditions and can prescribe medication. Exact scopes vary by license and jurisdiction, so verify the credential shown on each profile.',
                'es' => 'Estos titulos se superponen, pero su formacion y alcance son distintos. **Terapeuta** es un termino amplio para quien brinda terapia. Un **consejero licenciado** suele ofrecer evaluacion y terapia conversacional. Un **psicologo** tiene formacion doctoral en psicologia y puede ofrecer pruebas ademas de terapia. Un **psiquiatra** es medico y puede recetar medicamentos. El alcance exacto varia segun licencia y jurisdiccion.',
            ),
            '/\bwhich\s+of\s+those\b[\s\S]{0,70}\bprescrib(?:e|ing)\b|\bwhich\s+(?:provider|professional|role|one)\b[\s\S]{0,70}\bprescrib(?:e|ing)\b/iu' => array(
                'en' => '**Psychiatrists can prescribe medication** because they are physicians. Other appropriately licensed medical prescribers, such as some psychiatric nurse practitioners, may also prescribe. Therapists, counselors, and psychologists generally do not prescribe, although a small number of jurisdictions give specially trained psychologists limited prescribing authority. The current license should always be verified.',
                'es' => '**Los psiquiatras pueden recetar medicamentos** porque son medicos. Otros profesionales medicos con licencia, como algunos enfermeros de practica avanzada en psiquiatria, tambien pueden recetar. Terapeutas, consejeros y psicologos generalmente no recetan, aunque algunas jurisdicciones dan autoridad limitada a psicologos con formacion especial.',
            ),
            '/\b(?:compare|difference\s+between)[\s\S]{0,80}\bcbt\b[\s\S]{0,80}\bdbt\b[\s\S]{0,80}\bemdr\b/iu' => array(
                'en' => '**CBT** works with patterns linking thoughts, feelings, and behavior. **DBT** builds on behavioral methods with structured skills for emotion regulation, distress tolerance, mindfulness, and relationships. **EMDR** is a structured psychotherapy approach commonly used by trained clinicians for trauma-related memories and uses bilateral stimulation. They are not interchangeable, and a licensed clinician should assess which approach, if any, fits the person.',
                'es' => '**La TCC** trabaja con patrones entre pensamientos, emociones y conducta. **La DBT** usa habilidades estructuradas de regulacion emocional, tolerancia al malestar, mindfulness y relaciones. **EMDR** es un enfoque psicoterapeutico estructurado usado por profesionales capacitados para recuerdos relacionados con trauma e incluye estimulacion bilateral. No son intercambiables; un profesional debe evaluar cual corresponde.',
            ),
            '/\bwhich\s+(?:provider\s+type|professional|role)\b[\s\S]{0,100}\b(?:anxiety|anxious)\b|\bwho\s+is\b[\s\S]{0,60}\b(?:anxiety|anxious)\b/iu' => array(
                'en' => 'For anxiety, a **licensed therapist, counselor, clinical social worker, or psychologist** is often a reasonable first type of provider to explore for assessment and talk therapy. A **psychiatrist or other qualified medical prescriber** may be relevant when medication evaluation is wanted or symptoms are complex. That is general navigation, not a diagnosis; the right starting point depends on the person\'s needs and urgency.',
                'es' => 'Para la ansiedad, un **terapeuta, consejero, trabajador social clinico o psicologo con licencia** suele ser un primer tipo de proveedor razonable para evaluacion y terapia conversacional. Un **psiquiatra u otro prescriptor medico calificado** puede ser relevante si se desea evaluar medicacion o los sintomas son complejos. Es orientacion general, no un diagnostico.',
            ),
            '/\bwhen\s+(?:(?:should|would)\s+)?(?:someone|a\s+person|people|i|we)\b[\s\S]{0,120}\b(?:emergency|crisis)\s+help\b|\bwhen\b[\s\S]{0,80}\b(?:emergency|crisis)\b[\s\S]{0,50}\bdirectory\s+support\b/iu' => array(
                'en' => 'Directory support is not appropriate for an immediate crisis. Seek emergency help now if someone may harm themselves or another person, is in immediate danger, has a severe medical emergency, cannot stay safe, or is experiencing rapidly worsening symptoms that require urgent care. In the U.S., call **911** for immediate danger or call/text **988** for suicide or crisis support. This is general safety information, not a diagnosis.',
                'es' => 'El directorio no es apropiado para una crisis inmediata. Busca ayuda de emergencia si alguien puede hacerse dano o danar a otra persona, esta en peligro inmediato, tiene una emergencia medica grave, no puede mantenerse a salvo o presenta sintomas que empeoran rapidamente. En Estados Unidos, llama al **911** ante peligro inmediato o llama/envia texto al **988** para apoyo de crisis.',
            ),
            '/\bhow\s+is\s+financial\s+therap(?:y|ist)[\s\S]{0,80}\bdifferent\s+from\s+financial\s+planning\b|\bdifference\s+between\s+financial\s+therap(?:y|ist)[\s\S]{0,80}\bfinancial\s+planning\b/iu' => array(
                'en' => '**Financial therapy** focuses on emotions, beliefs, behavior, and relationship patterns around money. **Financial planning** focuses on financial goals and strategies such as budgeting, saving, investing, insurance, taxes, or retirement. Their scopes can complement each other, but credentials matter: emotional treatment requires an appropriate clinical scope, while personalized financial advice requires the relevant financial qualifications.',
                'es' => '**La terapia financiera** se enfoca en emociones, creencias, conductas y patrones de relacion alrededor del dinero. **La planificacion financiera** se enfoca en metas y estrategias como presupuesto, ahorro, inversion, seguros, impuestos o retiro. Pueden complementarse, pero las credenciales y el alcance profesional importan.',
            ),
            '/\bfinancial\s+therap(?:y|ist)\b[\s\S]{0,100}\b(?:relationship|couple|conflict|money)\b/iu' => array(
                'en' => 'Potentially, yes. A financial therapist may help people explore communication, stress, beliefs, and recurring conflict around money. The profile should clearly document the provider\'s training and scope. Couples therapy, clinical mental-health treatment, and personalized financial advice each require appropriate qualifications, so confirm what the individual provider is licensed or credentialed to do.',
                'es' => 'Posiblemente si. Un terapeuta financiero puede ayudar a explorar comunicacion, estres, creencias y conflictos recurrentes sobre dinero. El perfil debe documentar claramente su formacion y alcance. La terapia de pareja, el tratamiento clinico y el asesoramiento financiero personalizado requieren credenciales apropiadas.',
            ),
            '/\b(?:(?:difference\s+between|compare)[\s\S]{0,100}\bacupuncture\b[\s\S]{0,100}\bphysical\s+therapy\b|how\s+is\s+acupuncture[\s\S]{0,80}\bdifferent\s+from\s+physical\s+therapy\b)/iu' => array(
                'en' => '**Acupuncture** uses very thin needles at selected points and may be explored for symptoms such as pain or tension. **Physical therapy** evaluates movement and function and uses exercise, education, and hands-on techniques to support mobility, strength, and recovery. They use different methods and professional training, and neither replaces appropriate medical evaluation when symptoms are serious or unexplained.',
                'es' => '**La acupuntura** usa agujas muy finas en puntos seleccionados y algunas personas la exploran para dolor o tension. **La fisioterapia** evalua movimiento y funcion y usa ejercicio, educacion y tecnicas manuales para apoyar movilidad, fuerza y recuperacion. Usan metodos y formacion profesional distintos.',
            ),
            '/\b(?:general\s+counsel(?:ing|ling)|consejer[ií]a\s+general)\b/iu' => array(
                'en' => '**General Counseling** is broad, talk-based support for concerns such as stress, life transitions, relationships, grief, anxiety, or feeling overwhelmed. It can be a useful starting point when someone wants support but does not yet need a narrowly defined specialty. The exact services depend on each provider. Would you like me to explain how it differs from another type of care, or find providers?',
                'es' => '**La consejeria general** ofrece apoyo amplio basado en la conversacion para temas como estres, cambios de vida, relaciones, duelo, ansiedad o sentirse abrumado. Puede ser un buen punto de partida cuando alguien busca apoyo sin necesitar todavia una especialidad muy especifica. Los servicios exactos dependen de cada proveedor. Quieres que explique la diferencia con otro tipo de atencion o que busque proveedores?',
            ),
            '/\b(?:difference\s+between[\s\S]{0,50}(?:therapist|therapy)[\s\S]{0,30}(?:counselor|counseling)|diferencia\s+entre[\s\S]{0,50}(?:terapia|terapeuta)[\s\S]{0,30}(?:consejer[ií]a|consejer[oa]))\b/iu' => array(
                'en' => '**Counseling and therapy overlap heavily.** Counseling often focuses on a current concern or life transition, while therapy may explore longer-term emotional or behavioral patterns. In practice, licensed counselors and therapists can provide many of the same services, so the provider\'s license, specialties, and approach matter more than the label alone. Would you like help choosing the right provider type?',
                'es' => '**La consejeria y la terapia se superponen mucho.** La consejeria suele centrarse en una preocupacion actual o una transicion de vida, mientras que la terapia puede explorar patrones emocionales o de comportamiento a mas largo plazo. En la practica, la licencia, las especialidades y el enfoque del proveedor importan mas que la etiqueta. Quieres ayuda para elegir el tipo de proveedor?',
            ),
            '/\b(?:cbt|cognitive\s+behavio(?:u)?ral\s+therapy|tcc|terapia\s+cognitivo[-\s]?conductual)\b/iu' => array(
                'en' => '**CBT, or cognitive behavioral therapy,** is a structured form of talk therapy that looks at links between thoughts, feelings, and behavior. Providers may use it for concerns such as anxiety, depression, stress, or unhelpful habits. Mia can explain the category, but a licensed clinician should determine whether it fits a person\'s needs. Would you like to see CBT providers?',
                'es' => '**La TCC, o terapia cognitivo-conductual,** es una terapia estructurada que observa la relacion entre pensamientos, emociones y conductas. Los proveedores pueden usarla para ansiedad, depresion, estres o habitos poco utiles. Un profesional licenciado debe determinar si es adecuada para una persona. Quieres ver proveedores de TCC?',
            ),
            '/\b(?:dbt|dialectical\s+behavio(?:u)?ral\s+therapy|terapia\s+dial[eé]ctica)\b/iu' => array(
                'en' => '**DBT, or dialectical behavior therapy,** is a structured approach that teaches skills for emotional regulation, distress tolerance, mindfulness, and relationships. A licensed clinician should determine whether it is appropriate for an individual. Would you like to see providers who list DBT?',
                'es' => '**La DBT, o terapia dialectico-conductual,** es un enfoque estructurado que ensena habilidades de regulacion emocional, tolerancia al malestar, mindfulness y relaciones. Un profesional licenciado debe determinar si es apropiada para cada persona. Quieres ver proveedores que ofrecen DBT?',
            ),
            '/\b(?:emdr|eye\s+movement\s+desensitization|desensibilizaci[oó]n\s+y\s+reprocesamiento)\b/iu' => array(
                'en' => '**EMDR** is a structured psychotherapy approach often used by specially trained clinicians when working with trauma-related memories. It involves guided attention and bilateral stimulation. A qualified clinician should assess whether it is appropriate. Would you like to see providers who list EMDR?',
                'es' => '**EMDR** es un enfoque estructurado de psicoterapia que profesionales capacitados suelen usar con recuerdos relacionados con trauma. Incluye atencion guiada y estimulacion bilateral. Un profesional calificado debe evaluar si es apropiado. Quieres ver proveedores que ofrecen EMDR?',
            ),
            '/\b(?:acupuncture|acupuncturist|acupuntura|acupunturista)\b/iu' => array(
                'en' => '**Acupuncture** is a practice in which a trained professional places very thin needles at selected points on the body. People may explore it for concerns such as pain, muscle tension, headaches, nausea, stress-related symptoms, or general wellness support. Training and licensure matter, and it should complement rather than replace appropriate medical evaluation. Would you like me to explain how to evaluate an acupuncturist or find profiles?',
                'es' => '**La acupuntura** es una practica en la que un profesional capacitado coloca agujas muy finas en puntos seleccionados del cuerpo. Algunas personas la exploran para dolor, tension muscular, dolores de cabeza, nauseas, sintomas relacionados con estres o bienestar general. La formacion y licencia importan, y no debe sustituir una evaluacion medica apropiada. Quieres que explique como evaluar un acupunturista o que busque perfiles?',
            ),
            '/\b(?:physical\s+therapy|physiotherapy|fisioterapia)\b/iu' => array(
                'en' => '**Physical therapy** uses assessment, guided movement, exercise, education, and hands-on techniques to support mobility, strength, function, and recovery after injury, surgery, pain, or illness. A licensed physical therapist evaluates the person and develops an appropriate plan. Would you like to understand another recovery service or find physical-therapy providers?',
                'es' => '**La fisioterapia** usa evaluacion, movimiento guiado, ejercicio, educacion y tecnicas manuales para apoyar movilidad, fuerza, funcion y recuperacion despues de una lesion, cirugia, dolor o enfermedad. Un fisioterapeuta licenciado evalua a la persona y desarrolla un plan apropiado. Quieres entender otro servicio de recuperacion o buscar proveedores?',
            ),
            '/\b(?:massage\s+therapy|massage|masaje)\b/iu' => array(
                'en' => '**Massage therapy** uses hands-on techniques to work with muscles and soft tissue. People commonly seek it for relaxation, muscle tension, mobility, or recovery support. Services and credentials vary by provider. Would you like me to explain a massage specialty or find providers?',
                'es' => '**La terapia de masaje** usa tecnicas manuales para trabajar musculos y tejidos blandos. Muchas personas la buscan para relajacion, tension muscular, movilidad o apoyo en la recuperacion. Los servicios y credenciales varian por proveedor. Quieres que explique una especialidad o que busque proveedores?',
            ),
            '/\b(?:mindfulness|meditation|meditaci[oó]n)\b/iu' => array(
                'en' => '**Mindfulness and meditation services** focus on present-moment awareness, attention, and guided practice. Offerings may include classes, coaching, or clinician-led approaches, depending on the provider. Would you like to explore providers in this area?',
                'es' => '**Los servicios de mindfulness y meditacion** se centran en la conciencia del momento presente, la atencion y la practica guiada. Pueden incluir clases, coaching o enfoques dirigidos por profesionales clinicos, segun el proveedor. Quieres explorar proveedores en esta area?',
            ),
            '/\b(?:nutrition|diet(?:ary|itian)?|nutrici[oó]n|dietista)\b/iu' => array(
                'en' => '**Nutrition support** can include general food education, goal-setting, and individualized nutrition counseling. Scope varies by credential; registered dietitians can provide clinical nutrition care, while wellness coaches may focus on habits and accountability. Would you like help choosing the right type?',
                'es' => '**El apoyo nutricional** puede incluir educacion alimentaria, metas y consejeria nutricional individualizada. El alcance depende de la credencial; los dietistas registrados pueden ofrecer atencion nutricional clinica, mientras que los coaches suelen enfocarse en habitos y responsabilidad. Quieres ayuda para elegir el tipo adecuado?',
            ),
        );

        foreach ($definitions as $pattern => $texts) {
            if (preg_match($pattern, $message)) {
                return $texts[$lang] ?? $texts['en'];
            }
        }

        $category_aliases = array();
        foreach ($this->get_myhealthhub_category_knowledge_catalog() as $item) {
            $aliases = array_merge(array($item['name']), $item['aliases']);
            foreach ($aliases as $alias) {
                $category_aliases[] = array('alias' => $alias, 'item' => $item);
            }
        }
        usort($category_aliases, static function ($left, $right) {
            return strlen($right['alias']) <=> strlen($left['alias']);
        });
        foreach ($category_aliases as $candidate) {
            if (preg_match('/\b' . preg_quote($candidate['alias'], '/') . '\b/iu', $message)) {
                $item = $candidate['item'];
                return '**' . $item['name'] . '** — ' . ($item[$lang] ?? $item['en']);
            }
        }

        // OpenAI can answer a specific question that has no curated definition.
        if ($this->uses_openai() && $this->api_key !== '') { return null; }

        return ($lang === 'es')
            ? 'Puedo explicar categorias, servicios, modalidades y tipos de licencia dentro del directorio. Dime el nombre del tema que quieres entender y te dare una explicacion clara y no diagnostica.'
            : 'I can explain wellness categories, service types, modalities, and license types used in the directory. Tell me the topic you want to understand and I will give you a clear, non-diagnostic explanation.';
    }

    private function enforce_education_inventory_boundary($response, $language = 'en') {
        $paragraphs = preg_split('/\n\s*\n/u', trim((string) $response));
        $clean = array();
        foreach ($paragraphs as $paragraph) {
            $has_inventory_claim = preg_match('/\b(?:many|most|some|all|several|muchos|algunos|todos)\b[^.!?]{0,50}\b(?:providers|therapists|counselors|proveedores|terapeutas)\b[^.!?]{0,80}\b(?:listed|directory|listados|directorio)\b/iu', $paragraph);
            $has_unsolicited_search_pitch = preg_match('/^\s*(?:if\s+you\s+(?:are|want|would)|if\s+you[\x{27}\x{2019}]re|si\s+(?:quieres|buscas))\b[^\n]{0,250}\b(?:providers|directory|options|proveedores|directorio|opciones)\b/iu', $paragraph);
            if (!$has_inventory_claim && !$has_unsolicited_search_pitch) $clean[] = $paragraph;
        }
        $reply = $this->remove_education_search_nudge(implode("\n\n", $clean));
        return $reply !== '' ? $reply : ($language === 'es'
            ? 'Puedo explicar el enfoque general, pero no confirmar que proveedores lo ofrecen sin revisar sus perfiles.'
            : 'I can explain the general approach, but I cannot confirm which listed providers offer it without checking their profiles.');
    }

    private function remove_education_search_nudge($response) {
        $response = trim((string) $response);
        $response = preg_replace('/\s*(?:Would\s+you\s+like|Do\s+you\s+want|Want\s+me\s+to)[^?]*\?\s*$/iu', '', $response);
        $response = preg_replace('/\s*[¿]?\s*(?:Quieres|Deseas|Te\s+gustaria)[^?]*\?\s*$/iu', '', $response);
        return trim((string) $response);
    }

    /**
     * Deterministic answers for common support and directory questions.
     */
    private function get_deterministic_general_response($message, $context, $prior_context = array(), $original_prior_context = array()) {
        $msg = strtolower(trim($message));
        $lang = $this->resolve_response_language($msg, $prior_context);
        $directory_url = function_exists('myhh_get_directory_url') ? myhh_get_directory_url() : '/listings/';
        $locations = !empty($context['locations']) ? implode(', ', array_slice($context['locations'], 0, 8)) : 'Raleigh, Durham, Chapel Hill, Cary, Apex, and nearby Triangle-area cities';
        $total_providers = intval($context['total_providers'] ?? 0);

        if (preg_match('/\b(?:actually|changed?\s+my\s+mind|main\s+concern|focus\s+(?:only|just)|less\s+important)\b/iu', $msg)
            && preg_match('/\b(?:work|career|job)\b/iu', $msg)
            && preg_match('/\b(?:stress|stuck|overwhelm(?:ed|ing)?)\b/iu', $msg)) {
            return $lang === 'es'
                ? 'Entendido. Nos enfocaremos en **el estres laboral y la sensacion de estar estancado en tu carrera**; el sueno no es la prioridad de esta conversacion. La consejeria con licencia puede abordar el impacto emocional y los patrones relacionados con el trabajo, mientras que el coaching profesional no clinico se centra en metas, decisiones y pasos practicos. No buscare proveedores hasta que estes listo.'
                : 'Understood. We will focus on **work stress and feeling stuck in your career**; sleep is not the priority for this conversation. Licensed counseling can address the emotional strain and patterns around work, while non-clinical career coaching focuses on goals, decisions, and practical next steps. I will not search for providers until you are ready.';
        }

        if ($this->has_explicit_search_defer_signal($msg)
            && preg_match('/\b(?:work|career|job)\b/iu', $msg)
            && preg_match('/\b(?:sleep|sleeping|insomnia|rest)\b/iu', $msg)) {
            return $lang === 'es'
                ? 'Parece que quieres entender primero **el estres laboral y el sueno deficiente**. La consejeria puede ayudar con el impacto emocional del estres; el coaching profesional puede centrarse en limites, decisiones y metas; y el apoyo del sueno puede abordar rutinas o preocupaciones persistentes. No necesitas elegir ni buscar todavia; podemos comparar estas opciones primero.'
                : 'It sounds like you want to understand **work stress and poor sleep** before choosing anything. Counseling may help with the emotional impact of stress, career coaching can focus on boundaries, decisions, and goals, and sleep support can address routines or persistent concerns. You do not need to choose or search yet; we can compare these options first.';
        }

        if (preg_match('/\b(?:difference|different|compare|explain)\b[\s\S]{0,90}\bclaim(?:ing)?\b[\s\S]{0,60}\badd(?:ing)?\b|\bclaim(?:ing)?\b[\s\S]{0,60}\b(?:versus|vs\.?|or|and)\b[\s\S]{0,30}\badd(?:ing)?\b/iu', $msg)) {
            return $lang === 'es'
                ? 'Si la practica **ya aparece** en el directorio, reclamar el perfil confirma que te pertenece y te permite gestionarlo. Si **no aparece**, agregar una practica crea un perfil nuevo. La ruta responsable es buscar primero el nombre exacto y usar Agregar tu Practica solo si no existe un perfil.'
                : 'If the practice **already appears** in the directory, claiming verifies that it belongs to you and lets you manage it. If it **does not appear**, adding a practice creates a new profile. The reliable path is to search the exact practice name first, then use Add Your Practice only when no listing exists.';
        }

        if (preg_match('/\b(?:don[\'’]?t|do\s+not)\s+open\b[\s\S]{0,60}\bform\b/iu', $msg)
            && preg_match('/\bmy\s+(?:practice|business|clinic|office)\s+is\s+(?:located\s+)?in\s+([\p{L}][\p{L} .\'’-]{1,60})/iu', $message, $city_match)) {
            $city = trim((string) preg_replace('/\b(?:but|and|for|with)\b[\s\S]*$/iu', '', $city_match[1]), " \t\n\r\0\x0B,.;:!?");
            return $lang === 'es'
                ? "Entendido: tu practica esta en **{$city}** y no abrire ningun formulario todavia. Primero busca el nombre exacto en el directorio; si aparece, reclamalo, y si no aparece, entonces agrega una practica nueva."
                : "Got it: your practice is in **{$city}**, and I won't open a form yet. First search the exact practice name in the directory; if it appears, claim it, and if it does not, add a new practice.";
        }

        if (preg_match('/\bwhy\b[\s\S]{0,80}\b(?:can(?:not|\'t)|unable)\b[\s\S]{0,80}\b(?:nearby|near\s+me|closest|location)\b|\bwhy\b[\s\S]{0,80}\b(?:need|use|ask\s+for)\b[\s\S]{0,50}\blocation\b/iu', $msg)) {
            if ($lang === 'es') {
                return 'Para buscar **cerca de ti**, necesito una ubicacion de referencia. Puedes permitir la ubicacion del navegador o escribir una ciudad; si no compartes ninguna, puedo mostrar opciones mas amplias, pero no afirmar cuales estan cerca.';
            }
            return 'To search **near you**, I need a location reference. You can allow browser location or type a city. Without either one, I can show broader options, but I cannot truthfully say which providers are nearby.';
        }

        // ── Out-of-scope redirect (medical advice, coping strategies, jokes, entertainment, etc.) ──
        // These patterns catch requests that fall outside Mia's three core jobs.
        // The redirect is deterministic so xAI never answers these directly.
        $out_of_scope_patterns = array(
            '/\b(?:what\s+medication|which\s+medication|should\s+i\s+take|what\s+(?:should|can)\s+i\s+take\s+for|do\s+i\s+have|diagnos|prescrib|what\s+dosage|recommend\s+(?:a\s+)?medic|medical\s+advice|therapy\s+technique|how\s+(?:to\s+)?treat\s+my|cure\s+my)\b/i',
            '/\b(?:coping\s+strateg|coping\s+skill|how\s+do\s+i\s+cope|how\s+to\s+deal\s+with|how\s+to\s+handle\s+my|self\s*-?\s*help?\s+tip|tip\s+for\s+(?:my|dealing)|tip\s+on\s+(?:managing|dealing|handling))/i',
            '/\b(?:what\s+should\s+i\s+do\s+about|what\s+can\s+i\s+do\s+about|give\s+me\s+advice\s+(?:on|about)|advise\s+me\s+on)\b/i',
            '/\b(?:tell\s+me\s+(?:a\s+)?joke|say\s+something\s+funny|make\s+me\s+laugh|riddle|trivia|fun\s+fact|entertain\s+me)\b/i',
            '/\b(?:what\s+(?:dose|dosage)|supplement\s+(?:should|recommend)|what\s+herb|natural\s+remedy)\b/i',
            '/\b(?:meal\s+plan|exercise\s+routine|workout\s+plan|diet\s+plan|nutrition\s+plan|fitness\s+routine)\b/i',
        );
        foreach ($out_of_scope_patterns as $pattern) {
            if (preg_match($pattern, $msg)) {
                if ($lang === 'es') {
                    return "No estoy capacitada para eso — soy una guia de directorio, no una profesional clinica. Puedo ayudarte a encontrar un especialista calificado. Quieres que busque uno cerca de ti?";
                }
                return "I'm not able to help with that — I'm a directory guide, not a clinician. I can help you find a qualified specialist, though. Want me to search for one near you?";
            }
        }

        if (preg_match('/what can you do|how can you help|what can you help with|what do you help with|what do you do|who are you|what is mia|qu[eé] puedes hacer|c[oó]mo puedes ayudar|en qu[eé] ayudas|qui[eé]n eres|qu[eé] es mia/iu', $msg)) {
            if ($lang === 'es') {
                return "Puedo ayudarte de tres maneras:\n\n" .
                    "• **Encontrar proveedores** que encajen mejor contigo\n" .
                    "• **Guiar a proveedores** que quieran agregar o reclamar un perfil\n" .
                    "• **Dar educacion basica** sobre categorias, especialidades y senales de confianza para que sepas por donde empezar\n\n" .
                    "Si quieres, dime que tipo de apoyo buscas o [ver el directorio]({$directory_url}).";
            }
            return "I can help in three main ways:\n\n" .
                "• **Find providers** who fit what you need\n" .
                "• **Guide providers** who want to add or claim a listing\n" .
                "• **Give basic education** about categories, specialties, and trust signals so you know where to start\n\n" .
                "Tell me what kind of support you're looking for, or [browse the directory]({$directory_url}).";
        }

        $asks_for_service_overview = $this->looks_like_platform_service_overview_request($message, array(), $prior_context);
        if ($asks_for_service_overview) {
            $this->_conversational_speech = $this->build_category_overview_conversational_speech($context, $lang, $msg);
            return $this->format_category_overview_response($context, $lang);
        }

        $education_reply = $this->get_basic_education_fallback_response($msg, $lang);
        if ($education_reply) {
            return $this->remove_education_search_nudge($education_reply);
        }
        if (MyHH_Mia_Intent_Engine::is_basic_education_question($msg)
            && preg_match('/\b(?:difference|different|compare|versus|vs|contrast|diferencia|comparar)\b/iu', $msg)) {
            return null;
        }

        if ($this->patient_request_kind($message) === 'personal_concern') {
            return $this->build_patient_concern_response($message, $lang, $prior_context);
        }

        if (preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening)\b/i', $msg)) {
            if (!empty($prior_context['specialty']) || !empty($prior_context['city'])) {
                $topic = !empty($prior_context['specialty']) ? $prior_context['specialty'] : 'providers';
                $place = !empty($prior_context['city']) ? ' in ' . $prior_context['city'] : '';
                if ($lang === 'es') {
                    return "Que gusto tenerte de vuelta. Puedo seguir contigo en **{$topic}**{$place}, o empezar una busqueda nueva. Que prefieres?";
                }
                return "Welcome back. I can keep going with **{$topic}**{$place}, or we can start fresh. Which feels right?";
            }

            if ($lang === 'es') {
                return "Hola, soy Mia. Cuentame que tipo de apoyo buscas y te guio hacia la mejor opcion.";
            }
            return "Hi, I'm Mia. Tell me what kind of support you're looking for, and I'll guide you to the right fit.";
        }

        if (preg_match('/(12\s+)?wellness categories|what (?:are\s+(?:(?:the|your|those)\s+)?(?:(?:primary|wellness)\s+)?categories|categories)|which categories|all (?:the )?categories|category list|list.*categories|basic education|explain.*(?:categories|services)|what (?:do you|does mia) cover|what services (?:do you cover|are available)|everything you cover|categor[ií]as|categor[ií]as de bienestar|12 categor[ií]as|todas las categor[ií]as|explica.*(?:categor[ií]as|servicios)|qu[eé] (?:cubres|cubre mia)|qu[eé] servicios (?:cubres|hay disponibles)|todo lo que cubres/iu', $msg)) {
            $this->_conversational_speech = $this->build_category_overview_conversational_speech($context, $lang, $msg);
            return $this->format_category_overview_response($context, $lang);
        }

        if (preg_match('/how does (this|it|myhealthhub) work|what is myhealthhub|what is this site|como funciona|que es myhealthhub|que es este sitio/i', $msg)) {
            if ($lang === 'es') {
                return "Este es un directorio de bienestar pensado para ayudarte a encontrar proveedores y servicios en el area del Triangle.\n\n" .
                    "Puedes **explorar perfiles directamente**, usar a Mia para reducir opciones, o enviar una solicitud en [Get Matched](/get-matched/) si quieres una recomendacion mas guiada.";
            }
            return "This is a wellness directory focused on helping people find providers and services in the Triangle area.\n\n" .
                "You can **browse listings directly**, use Mia to narrow down your options, or submit a request through [Get Matched](/get-matched/) if you want a more guided match.";
        }

        if (preg_match('/is (it|this) free|does it cost|how much does it cost|free to use|cost money|es gratis|cuanto cuesta|tiene costo/i', $msg)) {
            if ($lang === 'es') {
                return "Si. Explorar proveedores y usar a Mia para recorrer el directorio es gratis para los visitantes.\n\n" .
                    "Si quieres una ayuda mas guiada, tambien puedes usar [Get Matched](/get-matched/) y el equipo te compartira recomendaciones.";
            }
            return "Yes. Browsing providers and using Mia to explore the directory is free for visitors.\n\n" .
                "If you want a more guided experience, you can also use [Get Matched](/get-matched/) and the team will follow up with recommendations.";
        }

        if (preg_match('/where do you serve|what areas do you cover|which cities|service area|locations|que areas cubren|que ciudades|donde atienden|ubicaciones/i', $msg)) {
            if ($lang === 'es') {
                return "Actualmente nos enfocamos en el area de Raleigh-Durham Triangle. Algunas de las ubicaciones principales del directorio son **{$locations}**.\n\n" .
                    "Si quieres, te reduzco la busqueda a una ciudad especifica.";
            }
            return "We currently focus on the Raleigh-Durham Triangle area. Some of the main locations in the directory are **{$locations}**.\n\n" .
                "If you want, I can narrow things to a specific city.";
        }

        if (preg_match('/how many providers|how big is the directory|how many listings|cuantos proveedores|cuantos perfiles|que tan grande es el directorio/i', $msg)) {
            if ($lang === 'es') {
                return "Ahora mismo hay **{$total_providers} perfiles publicados** en el directorio.\n\n" .
                    "Si quieres, te los reduzco por ciudad, especialidad o tipo de proveedor.";
            }
            return "Right now there are **{$total_providers} published provider listings** in the directory.\n\n" .
                "If you want, I can narrow that down by city, specialty, or provider type.";
        }

        if (preg_match('/\b(?:every|all|todos|todas|cada)\b/iu', $msg) && preg_match('/\b(?:licenses?|licencias?)\b/iu', $msg)
            && preg_match('/\b(?:checked|check|verified|verify|verification|verificad[oa]s?|revisad[oa]s?)\b/iu', $msg)) {
            $reply = $lang === 'es'
                ? 'No necesariamente. Un perfil publicado o una insignia general de verificacion no garantiza que se haya revisado cada licencia; consulta la verificacion especifica del perfil.'
                : "Not necessarily. A published listing or general verification badge doesn't guarantee every license was checked; check the profile's specific verification status.";
            $this->_conversational_speech = $reply;
            return $reply;
        }
        if (preg_match('/how are providers verified|how do you verify|are providers vetted|verified|como verifican a los proveedores|como verifican|estan verificados|verificados/i', $msg)) {
            if ($lang === 'es') {
                return "Las senales de confianza ayudan a entender que tan completo o confirmado esta un perfil:\n\n" .
                    "• **Board Verified** es la senal mas fuerte cuando esta disponible\n" .
                    "• **Verified** indica que el perfil paso por revision\n" .
                    "• **Verified Source** significa que fue confirmado desde una fuente confiable\n" .
                    "• **Claimed** significa que el proveedor tomo control de su perfil\n\n" .
                    "Puedes leer mas aqui: [How We Verify Listings](/how-we-verify/).";
            }
            return "Trust signals help explain how complete or confirmed a profile is:\n\n" .
                "• **Board Verified** is the strongest verification signal when available\n" .
                "• **Verified** means the profile has gone through review\n" .
                "• **Verified Source** means it was confirmed from a reliable source\n" .
                "• **Claimed** means the provider has taken control of the profile\n\n" .
                "You can learn more here: [How We Verify Listings](/how-we-verify/).";
        }

        if (preg_match('/difference between browse and get matched|get matched|diferencia entre explorar y get matched|como funciona get matched/i', $msg)) {
            if ($lang === 'es') {
                return "Explorar te permite revisar proveedores por tu cuenta y a tu ritmo. [Get Matched](/get-matched/) es mejor si quieres que el equipo reduzca opciones segun tus necesidades.\n\n" .
                    "Si ya sabes lo que buscas, tambien puedo ayudarte a buscar desde aqui.";
            }
            return "Browsing lets you explore providers yourself at your own pace. [Get Matched](/get-matched/) is better if you want the team to help narrow down options based on your needs.\n\n" .
                "If you already know what you're looking for, I can also help you search right here.";
        }

        if (preg_match('/contact (someone|support|team)|how do i contact|customer support|como contacto|contactar al equipo|soporte/i', $msg)) {
            if ($lang === 'es') {
                return "Puedes comunicarte con el equipo desde la [pagina de Contacto](/contact/).\n\n" .
                    "Si tu objetivo es encontrar un proveedor, normalmente puedo ayudarte mas rapido reduciendo primero el directorio aqui.";
            }
            return "You can reach the team through the [Contact page](/contact/).\n\n" .
                "If your goal is finding a provider, I can usually help you faster by narrowing down the directory here first.";
        }

        // ── Category explanation ──
        // Dynamic: reads term descriptions from DB, falls back to hardcoded
        $category_knowledge = $this->get_dynamic_category_knowledge($lang);
        uksort($category_knowledge, static function ($left, $right) {
            return strlen($right) <=> strlen($left);
        });
        foreach ($category_knowledge as $key => $texts) {
            if (preg_match('/\b' . preg_quote($key, '/') . '\b/i', $msg)) {
                return $texts[$lang] ?? $texts['en'];
            }
        }

        // ── License type explanation ──
        // Dynamic: reads term descriptions from DB, falls back to hardcoded
        $license_knowledge = $this->get_dynamic_license_knowledge($lang);
        foreach ($license_knowledge as $key => $texts) {
            if (preg_match('/\b' . preg_quote($key, '/') . '\b/i', $msg)) {
                return $texts[$lang] ?? $texts['en'];
            }
        }
        // General "what license type" question — build dynamically from DB
        if (preg_match('/what (?:is|are|do).*license|license type|what do (?:the )?(?:letters|initials)|what(?:\'s| is) (?:a|an)\s+(?:l|p|m)/i', $msg)) {
            return $this->build_dynamic_license_overview($lang);
        }

        // ── Specialty explanation ──
        if (preg_match('/what (?:is|does|covers?|means?)\s+(?:the\s+)?(?:specialty|specialization|specialities|specialties)\b|explain.*specialty|what specialties|que(?:\s+(?:son|significa|cubren))?\s+(?:las?\s+)?especialidades/i', $msg)) {
            return $this->build_dynamic_specialty_overview($lang);
        }

        return null;
    }

    /**
     * Deterministic follow-up answers for common search-adjacent questions.
     */
    private function get_deterministic_followup_response($message, $result, $prior_context = array(), $original_prior_context = array()) {
        $msg = strtolower(trim($message));
        // ── Strip trailing ASR noise fragments from followup messages ──
        // xAI's realtime transcription frequently appends phantom syllables
        // after the user finishes speaking. "tell me more" → "tell me more Can",
        // "tell me about the first" → "tell me about the first one Ken".
        // These trailing words are common English words that don't add meaning
        // and break intent detection.  Strip them before processing.
        // (Debug log 1777505821549: "tell me more Can" routed as provider_followup
        // but "Can" at the end was ASR noise.)
        $msg = preg_replace('/\s+(?:can|ken|kin|and|in|on|the|a|an|is|it|of|or|um|uh|ah|oh|mm|en|un|am)\s*$/i', '', $msg);
        $lang = $this->resolve_response_language($msg, $prior_context, $result);
        $providers = $result['providers'] ?? array();
        $has_route_hint_override = !empty($result['_route_hint_override']) && $result['_route_hint_override'] === 'provider_followup';

        // ── When the user references a specific provider by pronoun (him/her/them),
        //    ordinal ("the third one"), deictic ("that one", "the first"), or name,
        //    we MUST use the previously shown providers — NOT a fresh search result.
        //    Without this, "tell me more about him" after seeing ASHLEY ALMON
        //    resolves "him" against a new rotated search result list, picking the
        //    wrong provider or falling through to a fresh search entirely.
        //    IMPORTANT: $original_prior_context contains the PRE-intent-engine context
        //    (i.e. the providers the user actually saw). $prior_context may already
        //    have been overwritten by build_updated_context with a new rotated set.
        //    We prefer $original_prior_context for deictic resolution.
        $is_deictic_reference = preg_match(
            '/\b(?:him|her|them|that\s+one|this\s+one|the\s+one|the\s+(?:first|second|third|1st|2nd|3rd|fourth|fifth|sixth|seventh|eighth|ninth|tenth|7th|8th|9th|10th|last|top|bottom|next|other|sartre|searched|surge|surd)\s+(?:one|result|option|provider|choice|listing|match|account)?|first\s+one|second\s+one|third\s+one|fourth\s+one|fifth\s+one|sixth\s+one|the\s+one\s+you\s+(?:mentioned|said|just)|force\s+one|for\s+the\s+one|for\s+one|the\s+last\s+one|last\s+one|the\s+other\s+one|other\s+one|the\s+next\s+one|next\s+one|this\s+account|that\s+account)\b/i',
            $msg
        );
        // Prefer original (pre-intent-engine) providers for deictic resolution
        // because $prior_context may have been overwritten by build_updated_context
        // with a new rotated search set that the user never saw.
        $deictic_source = (!empty($original_prior_context['recent_providers']) && is_array($original_prior_context['recent_providers']))
            ? $original_prior_context
            : $prior_context;
        if ($has_route_hint_override && !empty($original_prior_context['recent_providers']) && is_array($original_prior_context['recent_providers'])) {
            $providers = $original_prior_context['recent_providers'];
            $deictic_source = $original_prior_context;
        }
        if ($is_deictic_reference && !empty($deictic_source['recent_providers']) && is_array($deictic_source['recent_providers'])) {
            $providers = $deictic_source['recent_providers'];
        }

        if (empty($providers) && !empty($deictic_source['recent_providers']) && is_array($deictic_source['recent_providers'])) {
            $providers = $deictic_source['recent_providers'];
        }

        // Set-level questions such as "Which of those offers acupuncture?"
        // refer to a service shared by zero or more prior results, not to a
        // provider whose business name happens to contain that service word.
        $service_filter_providers = (!empty($original_prior_context['recent_providers']) && is_array($original_prior_context['recent_providers']))
            ? $original_prior_context['recent_providers']
            : $providers;
        $resolve_context = !empty($original_prior_context['recent_providers']) ? $original_prior_context : $prior_context;
        $target_name = sanitize_text_field($result['_grok_understanding']['target_provider_name'] ?? '');
        if ($target_name !== '') $resolve_context['_target_provider_name'] = $target_name;
        $fact_provider = $this->resolve_referenced_provider($msg, $providers, $resolve_context);
        if ($target_name !== '' && !$fact_provider) {
            $reply = $lang === 'es'
                ? "No puedo identificar con seguridad el perfil de {$target_name}. Que perfil quieres que revise?"
                : "I can't safely identify {$target_name}'s profile from these results. Which profile should I check?";
            $this->_conversational_speech = $reply;
            return $reply;
        }
        $is_set_question = preg_match('/\b(?:which|who|those|these)\b|\b(?:esos|estos)\b/iu', $msg);
        if (!$is_set_question && $this->is_existing_provider_fact_question($msg)) {
            $fact_reply = $this->build_single_provider_fact_response($msg, $fact_provider, $lang);
            if ($fact_reply !== null) return $fact_reply;
        }
        $service_filter_response = $this->build_prior_provider_service_filter_response($msg, $service_filter_providers, $lang);
        if ($service_filter_response !== null) {
            return $service_filter_response;
        }

        // Also use original context for resolve_referenced_provider so that
        // last_provider_focus and other session state is from what the user saw.
        $resolve_context = (!empty($original_prior_context['recent_providers']) && is_array($original_prior_context['recent_providers']))
            ? $original_prior_context
            : $prior_context;
        $specialty = $resolve_context['specialty'] ?? $resolve_context['primary_category_name'] ?? $prior_context['specialty'] ?? $prior_context['primary_category_name'] ?? '';
        $city = $resolve_context['city'] ?? $prior_context['city'] ?? '';
        $referenced_provider = $fact_provider;

        // ── Authoritative deictic anchor ──
        // When the user says "this provider", "that one", "their address",
        // "show me details" without an explicit ordinal or name, and
        // resolve_referenced_provider returned null (because these are bare
        // deictic markers rather than concrete references), pin to
        // last_provider_focus so the followup targets the correct provider.
        // Without this, "where is this provider located" after "tell me more
        // about the first one" can drift to a different Raleigh provider.
        // (Audit 2026-04-30: location queries answered about wrong provider.)
        if (!$referenced_provider && !empty($resolve_context['last_provider_focus'])) {
            $bare_deictic = preg_match(
                '/\b(?:this|that|their|his|her)\s+(?:provider|one|profile|listing|address|location|insurance|contact|details?|info|information)\b|' .
                '\b(?:show\s+(?:me\s+)?(?:details?|more|info|information)|where\s+is|what\'?s?\s+(?:their|the|this|that))\b/i',
                $msg
            );
            if ($bare_deictic) {
                $focus_id = absint($resolve_context['last_provider_focus']);
                foreach ($providers as $p) {
                    if (absint($p['id'] ?? 0) === $focus_id) {
                        $referenced_provider = $p;
                        mia_debug_log('Chat', 'resolve_provider_deictic_anchor', array(
                            'message' => substr($msg, 0, 80),
                            'focus_id' => $focus_id,
                            'provider' => $p['name'] ?? 'unknown',
                        ));
                        break;
                    }
                }
                // If focus provider was rotated out of the current list,
                // use the first provider rather than doing a fresh search.
                if (!$referenced_provider && !empty($providers)) {
                    $referenced_provider = $providers[0];
                    mia_debug_log('Chat', 'resolve_provider_deictic_fallback', array(
                        'message' => substr($msg, 0, 80),
                        'focus_id' => $focus_id,
                        'fallback' => $providers[0]['name'] ?? 'unknown',
                    ));
                }
            }
        }

        if (preg_match('/\b(?:profile|listing)\s+links\b|\blinks\s+(?:to|for)\s+(?:the\s+)?(?:profiles|listings)\b|\benlaces?\s+de\s+(?:los\s+)?perfiles?\b|\benlaces?\s+de\s+perfil\b/iu', $msg) && !empty($providers)) {
            $items = array();
            $spoken_names = array();
            foreach (array_slice($providers, 0, 4) as $provider) {
                $name = sanitize_text_field($provider['name'] ?? 'Provider');
                $url = $this->sanitize_provider_url($provider['url'] ?? '');
                if ($url === '') {
                    continue;
                }
                $items[] = '• [**' . $name . '**](' . $url . ')';
                $spoken_names[] = $name;
            }
            if (!empty($items)) {
                $this->_conversational_speech = ($lang === 'es')
                    ? 'Los enlaces de ' . implode(', ', $spoken_names) . ' estan en la pantalla.'
                    : 'The profile links for ' . implode(', ', $spoken_names) . ' are on screen.';
                return ($lang === 'es' ? "Aqui tienes los enlaces de perfil:\n\n" : "Here are the profile links:\n\n") . implode("\n", $items);
            }
        }

        if (preg_match('/\b(?:clickable\s+)?(?:profile|listing)\s+(?:link|url)\b|\b(?:link|url)\s+(?:to|for)\s+(?:the\s+)?(?:profile|listing)\b|\bopen\s+(?:their|the|this|that)\s+(?:profile|listing)\b/iu', $msg)) {
            $link_provider = $referenced_provider;
            if (!$link_provider && !empty($resolve_context['last_provider_focus'])) {
                $focus_id = absint($resolve_context['last_provider_focus']);
                foreach ($providers as $provider) {
                    if (absint($provider['id'] ?? 0) === $focus_id) {
                        $link_provider = $provider;
                        break;
                    }
                }
            }
            $link_provider = $link_provider ?: (!empty($providers[0]) ? $providers[0] : null);
            if ($link_provider) {
                $name = sanitize_text_field($link_provider['name'] ?? 'Provider');
                $url = $this->sanitize_provider_url($link_provider['url'] ?? '');
                $this->_conversational_speech = ($lang === 'es')
                    ? "El enlace de {$name} esta en la pantalla."
                    : "The link for {$name} is on screen.";
                return ($lang === 'es')
                    ? "Abre el perfil de [**{$name}**]({$url})."
                    : "Open [**{$name}'s profile**]({$url}).";
            }
        }

        if (empty($providers) && empty($specialty) && empty($city)) {
            return null;
        }

        if ($this->looks_like_negative_search_refinement($msg, $prior_context) && !$this->has_explicit_search_replacement($msg, $result)) {
            return $this->build_negative_search_refinement_response($msg, $prior_context, $lang);
        }

        if ($referenced_provider
            && preg_match('/(tell me (?:more )?(?:about|on)?|more (?:about|on|info(?:rmation)? (?:on|about|for))|details? (?:on|about|for)|info(?:rmation)? (?:on|about|for)|which one was that|who (?:was|is) that|remind me about|the provider you mentioned|what about (that one|this one|the first|the second|the third|the last|him|her|them|that|this)|tell me about the|about the (?:first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|1st|2nd|3rd|4th|5th|6th|7th|8th|9th|10th|top|bottom|next|other)|can you tell me (?:more )?(?:about|the|on)|could you tell me (?:more )?(?:about|the|on)|I (?:want to|wanna|d like to|would like to) (?:know|hear|learn) (?:more )?(?:about|on)?|let me (?:know|hear) about|give me (?:more )?(?:info|details|information) (?:on|about|for)|describe (?:the )?(?:first|second|third|1st|2nd|3rd|fourth|fifth|sixth|seventh|eighth|ninth|tenth|last|top|bottom|other|next)|explain (?:about|the (?:first|second|third|1st|2nd|3rd|fourth|fifth|sixth|seventh|eighth|ninth|tenth|last|other|next))|I\'?m interested in (?:the )?(?:first|second|third|1st|2nd|3rd|fourth|fifth|sixth|seventh|eighth|ninth|tenth|last|other|next)|how about (?:the )?(?:first|second|third|1st|2nd|3rd|fourth|fifth|sixth|seventh|eighth|ninth|tenth|last|other|next)|go with (?:the )?(?:first|second|third|1st|2nd|3rd|fourth|fifth|sixth|seventh|eighth|ninth|tenth|last|other|next)|I\'?ll take (?:the )?(?:first|second|third|1st|2nd|3rd|fourth|fifth|sixth|seventh|eighth|ninth|tenth|last|other|next)|what do you know about|the (?:first|second|third|1st|2nd|3rd|fourth|fifth|sixth|seventh|eighth|ninth|tenth|last|top|bottom|next|other) (?:one|result|option|provider|choice|listing|match)|the one you (?:mentioned|said|just (?:said|showed|listed))|the (?:top|bottom|other|next) one|I like (?:the )?(?:first|second|third|1st|2nd|3rd|fourth|fifth|sixth|seventh|eighth|ninth|tenth|last|other|next)|quiero saber (?:m[aá]s )?(?:sobre|de)|puedes decirme (?:m[aá]s )?(?:sobre|de)|cu[eé]ntame (?:m[aá]s )?(?:sobre|de)|informaci[oó]n (?:sobre|de)|describe (?:el|la) (?:primero|primera|segundo|segunda|tercero|tercera|cuarto|cuarta|quinto|quinta|sexto|sexta|[uú]ltimo)|expl[ií]came (?:el|la|sobre)|vamos con (?:el|la) (?:primero|primera|segundo|segunda|tercero|tercera|cuarto|cuarta|quinto|quinta|sexto|sexta)|me quedo con (?:el|la) (?:primero|primera|segundo|segunda|tercero|tercera|cuarto|cuarta|quinto|quinta|sexto|sexta))/i', $msg)
            && !preg_match('/insurance|accept insurance|take insurance|book|booking|available|appointment|schedule|openings|compare|difference|best|recommend|choose|pick|phone|telephone|call|contact|address|location|located|where|website|site|directions|map|office|ubicaci[oó]n|direcci[oó]n|tel[eé]fono|contacto|donde/i', $msg)) {
            mia_debug_log('Chat', 'followup_provider_focus', ['message' => substr($msg, 0, 80), 'provider' => ($referenced_provider['name'] ?? 'unknown'), 'provider_id' => ($referenced_provider['id'] ?? 0)]);
            $this->_conversational_speech = $this->build_provider_detail_conversational_speech($referenced_provider, $lang);
            return $this->build_provider_focus_response($referenced_provider, $lang);
        }

        // ── "tell me more about" WITHOUT a specific reference ──
        // User said "can you tell me more about" but didn't specify which provider.
        // Instead of falling through to a new search, ask which one they mean.
        // Debug log 1777539583535 turn 3: "I need me provider. Tell me more" —
        // the garbled Realtime transcript lacks "about", so the original pattern
        // missed it and fell through to a fresh search.  Now also matches
        // "tell me more" without "about" when there are recent providers.
        $followup_info_pattern = '/(tell me (?:more )?(?:about|on)?|more (?:about|on|info(?:rmation)? (?:on|about|for))|details? (?:on|about|for)|info(?:rmation)? (?:on|about|for)|can you tell me (?:more )?(?:about|on)?|could you tell me (?:more )?(?:about|on)?|I (?:want to|wanna|d like to|would like to) (?:know|hear|learn) (?:more )?(?:about|on)?|let me (?:know|hear) about|give me (?:more )?(?:info|details|information) (?:on|about|for)|describe|explain about|what do you know about|quiero saber (?:m[aá]s )?(?:sobre|de)|puedes decirme (?:m[aá]s )?(?:sobre|de)|cu[eé]ntame (?:m[aá]s )?(?:sobre|de)|informaci[oó]n (?:sobre|de)|expl[ií]case)/i';
        // When _route_hint_override is set, the frontend has already
        // determined this is a followup — relax the insurance/location
        // guard so "tell me more about insurance/location" triggers
        // the followup handler instead of falling through to a fresh
        // search listing (debug log 1777548040629 turn 4 barge-in).
        if (!$referenced_provider
            && (preg_match($followup_info_pattern, $msg) || ($has_route_hint_override && preg_match('/\btell me\b/i', $msg)))
            && ($has_route_hint_override || !preg_match('/insurance|accept insurance|take insurance|book|booking|available|appointment|schedule|openings|compare|difference|best|recommend|choose|pick/i', $msg))
            && !empty($providers)) {
            // Build a quick "which one?" prompt listing the providers
            $items = array();
            foreach (array_slice($providers, 0, 4) as $i => $p) {
                $name = $p['name'] ?? 'Unknown';
                $city = trim((string) ($p['city'] ?? ''));
                $label = ($i + 1) . '. **' . $name . '**';
                if ($city) { $label .= ' — ' . $city; }
                $items[] = $label;
            }
            $list_text = implode("\n", $items);
            if ($lang === 'es') {
                return "De cual quieres saber mas? Puedes decir el numero o el nombre:\n\n{$list_text}\n\nPor ejemplo: \"el primero\" o dime el nombre.";
            }
            return "Which one would you like to know more about? You can say the number or the name:\n\n{$list_text}\n\nFor example: \"the first one\" or just tell me the name.";
        }

        $asks_address_guarantee = preg_match('/\b(?:enough|sufficient)\b[\s\S]{0,70}\b(?:address|location|distance)\b[\s\S]{0,70}\b(?:guarantee|know|determine|tell)\b|\b(?:guarantee|know|determine|tell)\b[\s\S]{0,70}\bwhich\b[\s\S]{0,40}\b(?:closest|nearest)\b/iu', $msg);
        if ($asks_address_guarantee) {
            $has_distance_evidence = !empty($providers) && count(array_filter($providers, function($provider) {
                return isset($provider['distance_miles']) && is_numeric($provider['distance_miles']);
            })) === count($providers);
            if (!$has_distance_evidence) {
                $reply = ($lang === 'es')
                    ? 'No. Tengo la ciudad y algunos datos de direccion, pero no una distancia calculada y comparable para cada perfil. Puedo mostrar cual aparece primero para la ciudad solicitada, pero no garantizar cual esta fisicamente mas cerca.'
                    : 'No. I have city and some address data, but not a calculated, comparable distance for every profile. I can show which result ranks first for the requested city, but I cannot guarantee which one is physically closest.';
                $this->_conversational_speech = $reply;
                return $reply;
            }
        }

        $asks_to_rank_distance = count($providers) >= 2
            && preg_match('/\b(?:which|who)\s+(?:of\s+)?(?:those|these|the)?\s*(?:providers?|options?|matches?|results?)?\s*(?:(?:is|are|appears?|seems?)\s+)?(?:the\s+)?(?:nearest|closest)|\bclosest\s+to\b|\bnearest\s+to\b/iu', $msg);
        if ($asks_to_rank_distance) {
            $providers_with_distance = array_values(array_filter($providers, function($provider) {
                return isset($provider['distance_miles']) && is_numeric($provider['distance_miles']);
            }));
            if (count($providers_with_distance) !== count($providers)) {
                $names = array_values(array_filter(array_map(function($provider) {
                    return sanitize_text_field($provider['name'] ?? '');
                }, array_slice($providers, 0, 2))));
                $name_text = implode(' and ', $names);
                if ($lang === 'es') {
                    $reply = 'No tengo una distancia calculada y comparable para cada opcion, asi que no puedo ordenar estos perfiles por cercania. ';
                    $reply .= $name_text !== '' ? $name_text . ' aparecen en la zona solicitada. ' : '';
                    $reply .= 'Abre sus perfiles para comparar la direccion disponible.';
                } else {
                    $reply = "I don't have a calculated, comparable distance for every option, so I cannot rank these profiles by proximity. ";
                    $reply .= $name_text !== '' ? $name_text . ' are listed in the requested area. ' : '';
                    $reply .= 'Open their profiles to compare the available address details.';
                }
                $this->_conversational_speech = $reply;
                return $reply;
            }

            usort($providers_with_distance, function($left, $right) {
                return (float) $left['distance_miles'] <=> (float) $right['distance_miles'];
            });
            $closest = $providers_with_distance[0];
            $name = sanitize_text_field($closest['name'] ?? 'The first provider');
            $url = $this->sanitize_provider_url($closest['url'] ?? '');
            $miles = round((float) $closest['distance_miles'], 1);
            $reply = ($lang === 'es')
                ? "Segun la distancia calculada disponible, [**{$name}**]({$url}) aparece mas cerca, aproximadamente a {$miles} millas."
                : "Based on the available calculated distance, [**{$name}**]({$url}) appears closest at about {$miles} miles.";
            $this->_conversational_speech = strip_tags(str_replace('**', '', $reply));
            return $reply;
        }

        $comparison_pattern = '/compar(?:e|ed|ing|isons?)?|difference between|vs\.?|versus|how do they compare|comparar|compar[aá]los|diferencia|head to head|side by side|pros? and cons?|which (?:one )?(?:is )?(?:better|best|worse|for)|who (?:is )?(?:better|best|worse)|pick between|choose between|one or the other/i';
        $is_comparison_request = (bool) preg_match($comparison_pattern, $msg);

        if (!$is_comparison_request && preg_match('/\b(?:phone|telephone|call|contact|address|location|located|where|website|site|sitio|telefono|tel[eé]fono|direcci[oó]n|contacto|ubicaci[oó]n|directions|how to get|how far|nearby|nearest|closest|office|map|drive|distance|donde est[aá]n|donde|c[oó]mo llegar)\b/iu', $msg)) {
            // Prefer referenced_provider (from ordinal/deictic), then last focused
            // provider, then first in list — prevents context drift after
            // "compare" or "insurance" followups where last_provider_focus
            // was set but resolve_referenced_provider returns null for bare
            // topic words like "location" or "insurance".
            $contact_provider = $referenced_provider;
            if (!$contact_provider && !empty($resolve_context['last_provider_focus'])) {
                $focus_id = absint($resolve_context['last_provider_focus']);
                foreach ($providers as $p) {
                    if (absint($p['id'] ?? 0) === $focus_id) {
                        $contact_provider = $p;
                        break;
                    }
                }
            }
            $contact_provider = $contact_provider ?: (!empty($providers[0]) ? $providers[0] : null);
            if ($contact_provider) {
                $this->_conversational_speech = $this->build_provider_contact_conversational_speech($contact_provider, $lang);
                return $this->build_provider_contact_response($contact_provider, $lang);
            }
        }

        // Comparison check comes FIRST — "which is better" with 2+ providers triggers side-by-side,
        // not a single recommendation. The recommendation fallback below handles "which is best"
        // when only one provider is available or no comparison keyword is present.
        if ($is_comparison_request) {
            if (count($providers) >= 2) {
                $a = $referenced_provider ?: $providers[0];
                $b = $providers[1];
                if (!empty($referenced_provider) && count($providers) > 1 && intval($providers[0]['id'] ?? 0) === intval($referenced_provider['id'] ?? 0)) {
                    $b = $providers[1];
                } elseif (!empty($referenced_provider) && count($providers) > 1) {
                    $b = $providers[0];
                }

                if ($lang === 'es') {
                    return "Aqui tienes una comparacion de dos opciones actuales usando solo lo confirmado en sus perfiles:\n\n" .
                        "• [**{$a['name']}**]({$a['url']}) — " . $this->build_provider_comparison_summary($a, $lang) . "\n" .
                        "• [**{$b['name']}**]({$b['url']}) — " . $this->build_provider_comparison_summary($b, $lang) . "\n\n" .
                        "El seguro, la disponibilidad y otros detalles no mostrados aqui no estan confirmados; puedo comparar uno de esos aspectos si esta documentado.";
                }
                return "Here's how [**{$a['name']}**]({$a['url']}) and [**{$b['name']}**]({$b['url']}) compare using only facts confirmed on their profiles:\n\n" .
                    "• [**{$a['name']}**] — " . $this->build_provider_comparison_summary($a, $lang) . "\n" .
                    "• [**{$b['name']}**] — " . $this->build_provider_comparison_summary($b, $lang) . "\n\n" .
                    "Insurance, availability, and any other details not shown here are not confirmed. I can compare one of those dimensions next if it is documented.";
            }

            if ($lang === 'es') {
                return "Puedo comparar proveedores cuando tengamos por lo menos dos buenas opciones. Dime la especialidad o la ciudad que quieres y primero lo reduzco.";
            }
            return "I can compare providers once we have at least two solid matches. Tell me the specialty or city you want, and I'll narrow it down first.";
        }

        if (preg_match('/which.*(best|better|recommend|good|right|choose|pick|start with)|who.*(best|recommend|should i see)|help me (choose|pick|decide)|which one|why\s+(?:did\s+you\s+)?recommend(?:ed)?|por\s+qu[eé]\s+recomend(?:aste|[oó])/iu', $msg)) {
            if (!empty($providers)) {
                $top = $referenced_provider ?: $providers[0];
                $label = $specialty ? " for **{$specialty}**" : '';
                $place = $city ? " in **{$city}**" : '';
                if ($lang === 'es') {
                    $label = $specialty ? " para **{$specialty}**" : '';
                    $place = $city ? " en **{$city}**" : '';
                    $response = "Segun las opciones actuales{$label}{$place}, te sugeriria empezar con [**{$top['name']}**]({$top['url']}).";
                } else {
                    $response = "Based on the current matches{$label}{$place}, I'd suggest starting with [**{$top['name']}**]({$top['url']}).";
                }

                $reasons = array();
                if (!empty($top['verification_status'])) {
                    $reasons[] = $this->format_verification_label($top['verification_status'], $lang);
                }
                if (!empty($top['claimed'])) {
                    $reasons[] = ($lang === 'es') ? 'perfil reclamado' : 'claimed profile';
                }
                if (!empty($top['rating_average']) && !empty($top['rating_count'])) {
                    $reasons[] = ($lang === 'es')
                        ? "{$top['rating_average']}/5 de calificacion con {$top['rating_count']} testimonios aprobados"
                        : "{$top['rating_average']}/5 rating from {$top['rating_count']} approved testimonials";
                }
                if (!empty($top['has_booking_link'])) {
                    $reasons[] = ($lang === 'es') ? 'reserva en linea disponible' : 'online booking available';
                }
                if (!empty($top['specialties'])) {
                    $reasons[] = ($lang === 'es')
                        ? 'las especialidades incluyen ' . implode(', ', array_slice($top['specialties'], 0, 2))
                        : 'specialties include ' . implode(', ', array_slice($top['specialties'], 0, 2));
                }

                if (!empty($reasons)) {
                    $response .= ($lang === 'es')
                        ? "\n\nPor que es un buen punto de partida:\n"
                        : "\n\nWhy this is a strong starting point:\n";
                    foreach (array_slice($reasons, 0, 4) as $reason) {
                        $response .= "• {$reason}\n";
                    }
                }

                if (count($providers) > 1) {
                    $second = $providers[1];
                    $response .= ($lang === 'es')
                        ? "\nTambien puedes compararlo con [**{$second['name']}**]({$second['url']}) si quieres otra opcion."
                        : "\nYou could also compare with [**{$second['name']}**]({$second['url']}) if you want another option.";
                }

                return trim($response);
            }

            if ($lang === 'es') {
                return "Elegir al proveedor correcto depende de lo que mas te importe: especialidad, ciudad, seguro, tipo de sesion y si quieres un perfil reclamado o verificado.\n\nDime cual de esos factores pesa mas para ti y lo reduzco.";
            }
            return "Choosing the right provider depends on what matters most to you: specialty fit, city, insurance, session type, and whether you want a claimed or verified profile.\n\nTell me which factor matters most, and I can narrow things down.";
        }

        $asks_insurance = (bool) preg_match('/insurance|accept insurance|take insurance|bcbs|blue cross|aetna|cigna|united|medicaid|medicare|coverage|covered|seguro|cobertura|acepta\s+seguro/i', $msg);
        $asks_availability = (bool) preg_match('/available|appointment|book|booking|schedule|openings|taking new patients|accepting new patients|availability|how soon|earliest|when can|set up|reservar|cita|disponibilidad|agendar/i', $msg);
        if ($asks_insurance && $asks_availability) {
            if ($lang === 'es') {
                return 'Los perfiles actuales no confirman de forma confiable **el seguro y la disponibilidad en tiempo real**. Esos datos pueden cambiar, asi que abre el perfil de la opcion que prefieras y confirma ambos directamente con la practica. No debo inferir ninguno de los dos.';
            }
            return '**Insurance and real-time availability are not confirmed** by the current profile data. Both can change, so open the profile you prefer and confirm each one directly with the practice. I should not infer either detail.';
        }

        if ($asks_insurance) {
            // Resolve insurance provider: prefer ordinal/deictic reference,
            // then last focused provider, then null (generic response).
            $insurance_provider = $referenced_provider;
            if (!$insurance_provider && !empty($resolve_context['last_provider_focus'])) {
                $focus_id = absint($resolve_context['last_provider_focus']);
                foreach ($providers as $p) {
                    if (absint($p['id'] ?? 0) === $focus_id) {
                        $insurance_provider = $p;
                        break;
                    }
                }
            }
            if ($insurance_provider) {
                if ($lang === 'es') {
                    return "Para [**{$insurance_provider['name']}**]({$insurance_provider['url']}), lo mas confiable es revisar directamente el perfil y confirmar alli los detalles del seguro.\n\nSi quieres, tambien puedo compararlo con otra opcion del mismo grupo.";
                }
                return "For [**{$insurance_provider['name']}**]({$insurance_provider['url']}), the most reliable next step is to open the profile and confirm the insurance details listed there directly.\n\nIf you want, I can also compare it with another option from the same set.";
            }
            if ($lang === 'es') {
                return "Los detalles del seguro pueden variar por proveedor, asi que el paso mas confiable es abrir cada perfil y confirmar directamente lo que aparece alli.\n\nSi quieres, primero puedo ayudarte a reducir a las opciones mas fuertes y luego revisas el seguro en esos perfiles.";
            }
            return "Insurance details can vary by provider, so the most reliable next step is to open each profile and confirm what's listed there directly.\n\n" .
                "If you want, I can still help you narrow to the strongest matches first, then you can check insurance on those profiles.";
        }

        if ($asks_availability) {
            // Resolve booking provider: prefer ordinal/deictic reference,
            // then last focused provider, then first with booking link.
            $booking_provider = $referenced_provider;
            if (!$booking_provider && !empty($resolve_context['last_provider_focus'])) {
                $focus_id = absint($resolve_context['last_provider_focus']);
                foreach ($providers as $p) {
                    if (absint($p['id'] ?? 0) === $focus_id) {
                        $booking_provider = $p;
                        break;
                    }
                }
            }
            if ($booking_provider && !empty($booking_provider['has_booking_link'])) {
                if ($lang === 'es') {
                    return "[**{$booking_provider['name']}**]({$booking_provider['url']}) parece una buena opcion para avanzar rapido porque el perfil incluye reserva en linea.\n\nTambien puedes revisar el perfil para ver contacto y disponibilidad.";
                }
                return "[**{$booking_provider['name']}**]({$booking_provider['url']}) looks like a strong option if you want to move quickly, because the profile includes online booking.\n\nYou can also check the profile for contact details and availability.";
            }
            if (!empty($providers)) {
                $with_booking = array_values(array_filter($providers, function ($provider) {
                    return !empty($provider['has_booking_link']);
                }));

                if (!empty($with_booking)) {
                    $provider = $with_booking[0];
                    if ($lang === 'es') {
                        return "[**{$provider['name']}**]({$provider['url']}) parece el mejor punto para empezar si quieres avanzar mas rapido, porque el perfil incluye reserva en linea.\n\n" .
                            "Para los demas, la pagina del perfil sigue siendo el mejor lugar para revisar contacto y disponibilidad.";
                    }
                    return "[**{$provider['name']}**]({$provider['url']}) looks like the best place to start if you want a faster next step, because the profile includes online booking.\n\n" .
                        "For the others, the profile page is still the best place to check contact details and availability.";
                }
            }

            if ($lang === 'es') {
                return "La disponibilidad cambia rapido, asi que el perfil del proveedor es el mejor lugar para empezar. Si hay un enlace para reservar, normalmente es la opcion mas rapida.\n\nTambien puedo ayudarte a priorizar los perfiles que parecen mas faciles de contactar.";
            }
            return "Availability changes quickly, so the provider profile is the best place to start. If there's a booking link, that's usually the fastest option.\n\n" .
                "I can also help you prioritize providers that look easiest to contact.";
        }

        if (preg_match('/virtual|telehealth|online therapy|remote|in person|in-person|hybrid/i', $msg)) {
            $session_type = null;
            if (strpos($msg, 'virtual') !== false || strpos($msg, 'telehealth') !== false || strpos($msg, 'online') !== false || strpos($msg, 'remote') !== false) {
                $session_type = 'Virtual';
            } elseif (strpos($msg, 'hybrid') !== false) {
                $session_type = 'Hybrid';
            } elseif (strpos($msg, 'in person') !== false || strpos($msg, 'in-person') !== false) {
                $session_type = 'In-Person';
            }

            if ($session_type) {
                if ($lang === 'es') {
                    return "Puedes filtrar el directorio por sesiones **{$session_type}** aqui: [Ver proveedores {$session_type}](/listings/?session_type=" . rawurlencode($session_type) . ").\n\n" .
                        "Si quieres, tambien puedo seguir ayudandote a reducir por especialidad o ciudad.";
                }
                return "You can filter the directory by **{$session_type}** sessions here: [Browse {$session_type} Providers](/listings/?session_type=" . rawurlencode($session_type) . ").\n\n" .
                    "If you want, I can also keep helping you narrow by specialty or city.";
            }
        }

        if (preg_match('/verified|claimed|what does .*verified|what does .*claimed|trust|legit/i', $msg)) {
            if ($lang === 'es') {
                return "Las senales de confianza te ayudan a evaluar perfiles rapidamente:\n\n" .
                    "• **Board Verified** significa que el perfil tiene la senal de verificacion mas fuerte disponible\n" .
                    "• **Verified** significa que el perfil fue revisado por el proceso de verificacion\n" .
                    "• **Verified Source** significa que el perfil fue confirmado por una fuente confiable, pero no al nivel de verificacion de board\n" .
                    "• **Claimed by Provider** significa que el proveedor tomo control de su propio perfil\n\n" .
                    "Cuando es posible, Mia ahora prioriza estas senales al recomendar proveedores.";
            }
            return "Trust signals help you quickly evaluate profiles:\n\n" .
                "• **Board Verified** means the listing has the strongest verification signal available\n" .
                "• **Verified** means the profile has been checked through the verification process\n" .
                "• **Verified Source** means the listing was confirmed from a reliable source, but not at the same level as board verification\n" .
                "• **Claimed by Provider** means the provider has taken control of their own profile\n\n" .
                "When possible, Mia now prioritizes these signals when recommending providers.";
        }

        // ── "Show me more" / "More providers" / "Any others?" ──
        // User wants to see additional providers beyond the ones already shown.
        // Instead of just linking to the directory, we let the search flow handle
        // this naturally: the intent engine classifies it as consumer_provider_search,
        // and the rotation system delivers a fresh set of providers from the same
        // search criteria.  Returning null here lets the search_grounded path run.
        if (preg_match('/' .
            '\b(?:show\s+me\s+more|more\s+(?:providers?|options?|results?|matches?|choices?|listings?|names?|therapists?|counselors?)|any\s+(?:other|more)\s+(?:providers?|options?|ones?|results?|choices?)|other\s+(?:providers?|options?|choices?|results?|matches?)|another\s+(?:set|batch|group|round|page)\s+of\s+(?:providers?|results?|options?)|give\s+me\s+another\s+(?:provider|option|one|choice|result|match|listing|therapist|counselor|set|batch|group|round)|see\s+more|next\s+(?:page|set|batch|group|round|four|4)|anything\s+else|who\s+else|anyone\s+else|what\s+else\s+is\s+there|are\s+there\s+(?:any\s+)?(?:more|other|additional)|i\s+want\s+to\s+see\s+more|give\s+me\s+more|keep\s+going|scroll|more\s+like\s+these|similar\s+providers?|different\s+(?:ones?|providers?|options?))\b' .
            // Spanish equivalents
            '|\b(?:m[aá]s\s+(?:proveedores?|opciones?|resultados?|terapeutas?|consejeros?|nombres?|listados?)|mu[eé]strame\s+m[aá]s|ver\s+m[aá]s|otros?\s+(?:proveedores?|opciones?|resultados?)|otra\s+(?:p[aá]gina|ronda|vez)|hay\s+m[aá]s|qui[eé]n\s+m[aá]s|alguien\s+m[aá]s|algo\s+m[aá]s|siguientes?|m[aá]s\s+como\s+estos|proveedores?\s+similares?|dame\s+m[aá]s|sigue|otro\s+grupo)\b' .
            '/i', $msg)) {
            // Return null so the code falls through to the normal search flow.
            // The intent engine will classify this as consumer_provider_search,
            // and the rotation system will deliver fresh providers from the same
            // criteria stored in prior_context.
            mia_debug_log('Chat', 'more_providers_request', ['message' => substr($msg, 0, 80), 'has_prior_context' => !empty($prior_context['specialty']) || !empty($prior_context['city'])]);
            return null;
        }

        return null;
    }

    private function is_existing_provider_fact_question($message) {
        if ($this->has_explicit_search_activation_signal($message)
            || preg_match('/\b(?:claim|add|register|reclamar|agregar)\b.{0,50}\b(?:practice|listing|profile|business|perfil|consulta)\b/iu', $message)) return false;
        if (preg_match('/\b(?:which|any|do\s+any)(?:\s+of)?\s+(?:these|those|one|ones|providers)\b[\s\S]{0,70}\b(?:accept|accepts|insurance|offer|offers|virtual|language|speak|speaks)\b/iu', $message)) return true;
        $reference = preg_match('/\b(?:they|their|her|his|him|them)\b|\b(?:those|these)\s+(?:(?:two|three|four)\s+)?(?:providers|profiles|options)|\bproviders\s+(?:you|that\s+you)\s+(?:recommend|recommended|showed|mentioned)|\b(?:that|this|their|his|her)\s+(?:provider|profile|listing)|\b(?:first|second|third)\s+(?:person|one|provider)|\b(?:esos|estos)\s+(?:proveedores|perfiles)\b/iu', $message);
        $fact = preg_match('/\b(?:whether|which|do|does|any|focus|work|offer|accept|confirm|document|known|unknown|why|cu[aá]les|ofrecen|trabajan|sabemos|conocemos|desconocemos|confirmado|documentado)\b/iu', $message);
        $listed = preg_match('/\b(?:actually|explicitly|specifically)\s+(?:listed|documented|confirmed)|\b(?:profile|listing)\s+(?:actually\s+|explicitly\s+|specifically\s+)?(?:lists?|says?|shows?|documents?)\b/iu', $message);
        $recommendation_reason = preg_match('/\bwhy\b.{0,40}\byou\b.{0,20}\b(?:chose|choose|recommend(?:ed)?|suggest(?:ed)?)\b/iu', $message)
            && preg_match('/\b(?:her|him|them|provider|person|first|second|third)\b/iu', $message);
        return (bool) (($reference && $fact) || $listed || $recommendation_reason);
    }

    private function extract_provider_fact_topic($message) {
        $patterns = array(
            '/\b(?:profile|listing)\s+(?:(?:actually|explicitly|specifically)\s+)?(?:lists?|shows?|documents?)\s+([^?.!]+)[?.!]*$/iu',
            '/\bwhether\s+(.+?)\s+(?:is|was)\s+(?:(?:actually|explicitly|specifically)\s+)?(?:listed|documented|confirmed)\b/iu',
            '/\bwhy\b.{0,80}\b(?:chose|choose|recommend(?:ed)?|suggest(?:ed)?)\b.{0,80}\bfor\s+([^?.!]+)[?.!]*$/iu',
        );
        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $message, $match)) continue;
            $topic = trim(sanitize_text_field($match[1]));
            $topic = trim(preg_replace('/\s+(?:treatment|therapy|support|care)$/iu', '', $topic));
            if ($topic !== '' && strlen($topic) <= 80 && str_word_count($topic) <= 8) return $topic;
        }
        return '';
    }

    private function build_single_provider_fact_response($message, $provider, $lang) {
        $topic = $this->extract_provider_fact_topic($message);
        $fact_field = '';
        $insurance = $this->extract_insurance_entity_from_turn($message);
        if ($insurance !== '') { $topic = $insurance; $fact_field = 'insurance_accepted'; }
        elseif (preg_match('/\b(?:virtual|online|in[-\s]?person|hybrid)\b/iu', $message)) { $topic = $this->extract_session_type_entity_from_turn($message); $fact_field = 'session_type'; }
        elseif (preg_match('/\b(?:speak|speaks|language|idioma|habla)\b/iu', $message) && preg_match('/\b(English|Spanish|espa[ñn]ol|ingl[eé]s)\b/iu', $message, $language)) { $topic = in_array(strtolower(remove_accents($language[1])), array('spanish','espanol'), true) ? 'Spanish' : 'English'; $fact_field = 'languages'; }
        if ($topic === '') return null;
        if (!$provider) {
            $reply = $lang === 'es' ? 'Que perfil quieres que revise?' : 'Which profile should I check?';
        } else {
            $name = sanitize_text_field($provider['name'] ?? 'This provider');
            $facts = array_merge((array) ($provider['specialties'] ?? array()), (array) ($provider['modalities'] ?? array()),
                (array) ($provider['insurance_accepted'] ?? array()), (array) ($provider['languages'] ?? array()),
                (array) ($provider['age_groups'] ?? array()), array($provider['license_type'] ?? '', $provider['session_type'] ?? ''));
            if ($fact_field !== '') $facts = (array) ($provider[$fact_field] ?? array());
            $confirmed = in_array($this->normalize_provider_reference_text($topic), array_map(array($this, 'normalize_provider_reference_text'), $facts), true);
            if ($confirmed) {
                $reply = $lang === 'es' ? "Si. El perfil de {$name} indica {$topic}." : "Yes. {$name}'s profile lists {$topic}.";
                if (preg_match('/\bwhy\b/iu', $message) && $lang !== 'es') {
                    $reply = "{$name}'s profile lists {$topic}; that's the documented connection to your request.";
                }
            } else {
                $reply = $lang === 'es'
                    ? "No puedo confirmar {$topic} en los datos del perfil de {$name}. Eso no significa que no lo ofrezca; hay que confirmarlo con el proveedor."
                    : "I can't confirm {$topic} in {$name}'s listed details. That doesn't mean they don't offer it; it needs checking with the provider.";
            }
        }
        $this->_conversational_speech = $reply;
        return $reply;
    }

    private function build_prior_provider_service_filter_response($message, $providers, $lang = 'en') {
        if (empty($providers) || !is_array($providers)) {
            return null;
        }

        if ($this->is_existing_provider_fact_question($message)) {
            $requested = $this->extract_insurance_entity_from_turn($message);
            $field = $requested !== '' ? 'insurance_accepted' : '';
            if ($requested === '' && preg_match('/\b(?:virtual|online|in[-\s]?person|hybrid)\b/iu', $message)) { $requested = $this->extract_session_type_entity_from_turn($message); $field = 'session_type'; }
            if ($requested === '' && preg_match('/\b(?:speak|speaks|language|idioma|habla)\b/iu', $message) && preg_match('/\b(English|Spanish|espa[ñn]ol|ingl[eé]s)\b/iu', $message, $m)) { $requested = in_array(strtolower(remove_accents($m[1])), array('spanish','espanol'), true) ? 'Spanish' : 'English'; $field = 'languages'; }
            if ($requested !== '') {
                $matches = array();
                foreach ($providers as $provider) foreach ((array) ($provider[$field] ?? array()) as $fact) {
                    if (strcasecmp((string) $fact, $requested) === 0) { $matches[] = '[**' . sanitize_text_field($provider['name']) . '**](' . $this->sanitize_provider_url($provider['url'] ?? '') . ')'; break; }
                }
                if (!$matches) return $lang === 'es'
                    ? "Ninguno de estos perfiles confirma **{$requested}**. Confirma ese detalle directamente con el proveedor; no significa que no lo ofrezca."
                    : "None of these profiles confirms **{$requested}**. Please verify that detail directly with the provider; missing information does not mean it is unavailable.";
                return ($lang === 'es' ? 'Estos perfiles indican ' : 'These profiles list ') . "**{$requested}**: " . implode(', ', $matches) . '. ' . ($lang === 'es' ? 'Confirma los detalles actuales con el proveedor.' : 'Confirm the current details directly with the provider.');
            }
        }

        if ($this->is_existing_provider_fact_question($message)) {
            if (preg_match('/\b(?:unknown|not\s+know|not\s+confirmed|no\s+sabemos|desconocemos|no\s+confirmad[oa])\b/iu', $message)) {
                $labels = $lang === 'es'
                    ? array('insurance_accepted' => 'seguros aceptados', 'session_type' => 'modalidad de sesiones', 'languages' => 'idiomas', 'age_groups' => 'edades atendidas')
                    : array('insurance_accepted' => 'accepted insurance', 'session_type' => 'session format', 'languages' => 'languages', 'age_groups' => 'age groups served');
                $lines = array();
                foreach (array_slice($providers, 0, 4) as $provider) {
                    $missing = array();
                    foreach ($labels as $key => $label) {
                        if (empty($provider[$key]) || $provider[$key] === 'unknown') $missing[] = $label;
                    }
                    $name = sanitize_text_field($provider['name'] ?? 'Provider');
                    $url = $this->sanitize_provider_url($provider['url'] ?? '');
                    $lines[] = '[**' . $name . '**](' . $url . '): ' . ($lang === 'es' ? 'no confirmado: ' : 'not confirmed: ') . implode(', ', array_merge($missing, array($lang === 'es' ? 'disponibilidad actual' : 'current availability'))) . '.';
                }
                return implode("\n\n", $lines) . "\n\n" . ($lang === 'es'
                    ? 'Son los mismos perfiles. Un dato ausente no significa que no ofrezcan ese servicio; confirma los detalles directamente con cada proveedor.'
                    : 'These are the same profiles. Missing information does not mean a service is unavailable; confirm details directly with each provider.');
            }
            $attributes = array();
            if (preg_match_all('/\b(?:works?\s+with|focus(?:es)?\s+on|specializ(?:e|es)\s+in|offers?|accepts?|trabajan?\s+con)\s+([^,;?.!]+?)(?=\s+(?:and|but|or|y|pero)\b|[,;?.!]|$)/iu', $message, $parts)) {
                foreach ($parts[1] as $part) {
                    $part = trim(sanitize_text_field($part));
                    if ($part !== '' && str_word_count($part) <= 8) $attributes[] = $part;
                }
            }
            if (!empty($attributes)) {
                $lines = array();
                foreach (array_slice($providers, 0, 4) as $provider) {
                    $facts = implode(' ', array_merge(
                        (array) ($provider['specialties'] ?? array()), (array) ($provider['modalities'] ?? array()),
                        (array) ($provider['languages'] ?? array()), (array) ($provider['insurance_accepted'] ?? array()),
                        (array) ($provider['age_groups'] ?? array())
                    ));
                    $checks = array();
                    foreach (array_unique($attributes) as $attribute) {
                        $confirmed = preg_match('/\b' . preg_quote($attribute, '/') . '\b/iu', $facts);
                        $checks[] = $attribute . ': ' . ($confirmed ? ($lang === 'es' ? 'documentado' : 'documented') : ($lang === 'es' ? 'no confirmado en el perfil' : 'not confirmed in the profile'));
                    }
                    $name = sanitize_text_field($provider['name'] ?? 'Provider');
                    $url = $this->sanitize_provider_url($provider['url'] ?? '');
                    $lines[] = '[**' . $name . '**](' . $url . '): ' . implode('; ', $checks) . '.';
                }
                return ($lang === 'es' ? 'Revisando los mismos perfiles, no una nueva busqueda:' : 'Checking the same profiles, not a new search:') . "\n\n" . implode("\n\n", $lines) . "\n\n" .
                    ($lang === 'es' ? 'Un dato no confirmado no significa que no ofrezcan ese servicio. Confirma los detalles directamente con el proveedor.' : 'An unconfirmed detail does not mean they do not offer it. Confirm the missing details directly with the provider.');
            }
        }

        $requested = $this->extract_prior_provider_service_filter_request($message);
        if ($requested === '') {
            return null;
        }

        $requested = sanitize_text_field($requested);
        $requested = preg_replace('/\b(?:language\s+support|language|service|services|care|support|servicio|servicios|atenci[oó]n|apoyo|idioma)\b/iu', ' ', $requested);
        $requested = trim((string) preg_replace('/\s+/u', ' ', $requested));
        if ($requested === '' || strlen($requested) > 100) {
            return null;
        }

        $normalize = static function ($value) {
            $value = strtolower(html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8'));
            $value = preg_replace('/[^a-z0-9áéíóúñü\s]+/iu', ' ', $value);
            return trim((string) preg_replace('/\s+/u', ' ', $value));
        };
        $needle = $normalize($requested);
        $matches = array();

        foreach (array_values($providers) as $provider) {
            $fields = array_merge(
                is_array($provider['specialties'] ?? null) ? $provider['specialties'] : array(),
                is_array($provider['modalities'] ?? null) ? $provider['modalities'] : array(),
                is_array($provider['languages'] ?? null) ? $provider['languages'] : array(),
                array($provider['provider_type'] ?? '', $provider['primary_category'] ?? '', $provider['license_type'] ?? '')
            );
            $matched_labels = array();
            foreach (array_filter(array_map('trim', $fields)) as $field) {
                $haystack = $normalize($field);
                if ($haystack !== '' && ($haystack === $needle || strpos($haystack, $needle) !== false || strpos($needle, $haystack) !== false)) {
                    $matched_labels[] = sanitize_text_field($field);
                }
            }
            if (!empty($matched_labels)) {
                $provider['_service_matches'] = array_values(array_unique($matched_labels));
                $matches[] = $provider;
            }
        }

        if (empty($matches)) {
            if ($lang === 'es') {
                $reply = "Ninguno de esos perfiles indica explicitamente **{$requested}** en los datos actuales. Puedo buscar otros perfiles que si lo indiquen.";
            } else {
                $reply = "None of those profiles explicitly lists **{$requested}** in the current directory data. I can search for other profiles that do.";
            }
            $this->_conversational_speech = strip_tags(str_replace('**', '', $reply));
            return $reply;
        }

        $items = array();
        foreach ($matches as $provider) {
            $name = sanitize_text_field($provider['name'] ?? 'Provider');
            $url = $this->sanitize_provider_url($provider['url'] ?? '#');
            $city = sanitize_text_field($provider['city'] ?? '');
            $labels = implode(', ', array_slice($provider['_service_matches'], 0, 2));
            $detail = $labels;
            if ($city !== '') {
                $detail .= ($detail !== '' ? ' • ' : '') . $city;
            }
            $items[] = "• [**{$name}**]({$url})" . ($detail !== '' ? " — {$detail}" : '');
        }
        $list = implode("\n", $items);
        $names = array_values(array_filter(array_map(static function ($provider) {
            return sanitize_text_field($provider['name'] ?? '');
        }, $matches)));

        if ($lang === 'es') {
            $reply = "De los perfiles que acabas de ver, estos indican explicitamente **{$requested}**:\n\n{$list}\n\nPuedes abrir cualquiera de los perfiles para confirmar los servicios y detalles actuales.";
            $this->_conversational_speech = 'De las opciones anteriores, ' . implode(' y ', $names) . " indican {$requested}. Los enlaces estan en la pantalla.";
            return $reply;
        }

        $reply = "From the profiles you just saw, these explicitly list **{$requested}**:\n\n{$list}\n\nOpen either profile to confirm its current services and details.";
        $this->_conversational_speech = 'From the options I just showed, ' . implode(' and ', $names) . " list {$requested}. Their profile links are on screen.";
        return $reply;
    }

    private function extract_prior_provider_service_filter_request($message) {
        // A relative clause inside a longer question is not a standalone filter.
        if (preg_match('/[,;]|\band\s+(?:i|we|my|whether|do|does)\b/iu', $message)) return '';
        $requested = '';
        if (preg_match('/\b(?:which|who)(?:\s+of\s+(?:those|these|the))?\s*(?:providers?|options?|matches?|results?)?\s*(?:specifically\s+|actually\s+|explicitly\s+)?(?:offers?|provides?|lists?|documents?|confirms?|shows?|states?|specializes?\s+in|works?\s+(?:in|with))\s+(.+?)\s*[?.!]*$/iu', $message, $match)) {
            $requested = $match[1] ?? '';
        } elseif (preg_match('/\b(?:cu[aá]l(?:es)?|qui[eé]n(?:es)?)(?:\s+de\s+(?:esos|esas|estos|estas))?\s*(?:proveedores?|opciones?|resultados?)?\s*(?:ofrece(?:n)?|brinda(?:n)?|incluye(?:n)?|documenta(?:n)?|confirma(?:n)?|muestra(?:n)?|se\s+especializa(?:n)?\s+en|trabaja(?:n)?\s+con)\s+(.+?)\s*[?.!]*$/iu', $message, $match)) {
            $requested = $match[1] ?? '';
        } else {
            return '';
        }
        return trim((string) $requested);
    }

    private function build_provider_comparison_summary($provider, $lang = 'en') {
        $parts = array();

        if (!empty($provider['city'])) {
            $parts[] = $provider['city'];
        }
        if (!empty($provider['license_type'])) {
            $parts[] = $provider['license_type'];
        }
        if (!empty($provider['verification_status'])) {
            $parts[] = $this->format_verification_label($provider['verification_status'], $lang);
        }
        if (!empty($provider['claimed'])) {
            $parts[] = ($lang === 'es') ? 'reclamado' : 'claimed';
        }
        if (!empty($provider['rating_average']) && !empty($provider['rating_count'])) {
            $parts[] = ($lang === 'es')
                ? "{$provider['rating_average']}/5 ({$provider['rating_count']} resenas)"
                : "{$provider['rating_average']}/5 ({$provider['rating_count']} reviews)";
        }
        if (!empty($provider['has_booking_link'])) {
            $parts[] = ($lang === 'es') ? 'reserva en linea' : 'online booking';
        }
        if (!empty($provider['specialties'])) {
            $parts[] = implode(', ', array_slice($provider['specialties'], 0, 2));
        }
        if (!empty($provider['modalities'])) {
            $parts[] = (($lang === 'es') ? 'modalidades: ' : 'modalities: ') . implode(', ', array_slice($provider['modalities'], 0, 2));
        }
        if (!empty($provider['languages'])) {
            $parts[] = (($lang === 'es') ? 'idiomas: ' : 'languages: ') . implode(', ', array_slice($provider['languages'], 0, 2));
        }
        if (!empty($provider['session_type'])) {
            $session = sanitize_text_field($provider['session_type']);
            $parts[] = strtolower($session) === 'unknown' ? ($lang === 'es' ? 'formato de sesion no publicado' : 'session format not listed') : (($lang === 'es') ? 'sesiones: ' : 'sessions: ') . $session;
        }
        if (!empty($provider['insurance_accepted'])) {
            $parts[] = (($lang === 'es') ? 'seguro documentado: ' : 'documented insurance: ') . implode(', ', array_slice($provider['insurance_accepted'], 0, 3));
        }

        if (empty($parts)) {
            return ($lang === 'es')
                ? 'el perfil no publica suficientes detalles para una comparacion responsable'
                : 'the profile does not publish enough detail for a responsible comparison';
        }

        return implode(' | ', $parts);
    }

    private function build_provider_focus_response($provider, $lang = 'en') {
        $summary = $this->build_provider_comparison_summary($provider, $lang);
    
        if ($lang === 'es') {
            $response = "Claro. [**{$provider['name']}**]({$provider['url']}) es una de las opciones que acabo de mencionar.";
            if (!empty($summary)) {
                $response .= "\n\nLo principal que veo en el perfil:\n• {$summary}";
            }
            $detail_lines = $this->build_provider_detail_lines($provider, $lang);
            if (!empty($detail_lines)) {
                $response .= "\n\n" . $detail_lines;
            }
            $response .= "\n\nSi quieres, puedo compararlo con otra opcion del mismo grupo o ayudarte a revisar seguro, reserva o ciudad.";
            return $response;
        }
    
        // Lead with what makes this provider distinctive, not a generic opener
        $lead_specs = !empty($provider['specialties']) ? array_slice($provider['specialties'], 0, 2) : array();
        if (!empty($lead_specs)) {
            $response = "[**{$provider['name']}**]({$provider['url']}) specializes in " . implode(' and ', $lead_specs) . ".";
        } elseif (!empty($provider['primary_category'])) {
            $response = "[**{$provider['name']}**]({$provider['url']}) works in {$provider['primary_category']}.";
        } else {
            $response = "Here's a closer look at [**{$provider['name']}**]({$provider['url']}).";
        }
        if (!empty($summary)) {
            $response .= "\n\n" . $summary;
        }
        $detail_lines = $this->build_provider_detail_lines($provider, $lang);
        if (!empty($detail_lines)) {
            $response .= "\n\n" . $detail_lines;
        }
        $response .= "\n\nWant me to compare with another option, check location details, or find someone with a different specialty?";
        return $response;
    }

    /**
     * Build a natural, conversational speech version of a provider detail
     * response for TTS.  Instead of reading bullet points verbatim, Mia
     * explains the provider as if she personally knows the information —
     * connecting facts into flowing sentences, adding contextual framing,
     * and sounding like a knowledgeable guide rather than a script reader.
     */
    private function build_provider_detail_conversational_speech($provider, $lang = 'en') {
        if (empty($provider) || !is_array($provider)) return '';
        $name = trim((string) ($provider['name'] ?? 'this provider'));
        $city = trim((string) ($provider['city'] ?? ''));
        $specs = array_filter(array_map('trim', ($provider['specialties'] ?? array())));
        $license = trim((string) ($provider['license_type'] ?? ''));
        $phone = trim((string) ($provider['phone'] ?? ''));
        $address = trim((string) ($provider['address'] ?? ''));
        $street = trim((string) ($provider['street'] ?? ''));
        $verification = trim((string) ($provider['verification_status'] ?? ''));
        $claimed = !empty($provider['claimed']);
        $pcat = trim((string) ($provider['primary_category'] ?? ''));
        $session_type = trim((string) ($provider['session_type'] ?? ''));
        if (in_array(strtolower($session_type), array('unknown', 'unspecified', 'not specified', 'not_specified', 'n/a', 'none', 'null'), true)) $session_type = '';
        $insurance = array_filter(array_map('trim', (array) ($provider['insurance_accepted'] ?? array())));

        if ($lang === 'es') {
            $sentences = array();
            $sentences[] = "Claro, te cuento sobre {$name}.";
            if (!empty($specs)) {
                $spec_list = implode(', ', array_slice($specs, 0, 3));
                $sentences[] = "Se especializa en {$spec_list}.";
            } elseif (!empty($pcat)) {
                $sentences[] = "Trabaja en el area de {$pcat}.";
            }
            if (!empty($city)) {
                $sentences[] = "Estan ubicados en {$city}.";
            }
            if (!empty($license)) {
                $sentences[] = "Tienen credencial de {$license}.";
            }
            if ($session_type !== '') {
                $sentences[] = "El perfil documenta sesiones {$session_type}.";
            }
            if (!empty($insurance)) {
                $sentences[] = 'El perfil documenta estos seguros: ' . implode(', ', array_slice($insurance, 0, 3)) . '.';
            }
            if (!empty($verification)) {
                $vlabel = $this->format_verification_label($verification, 'es');
                if ($verification === 'board_confirmed') {
                    $sentences[] = "Algo importante: su perfil esta verificado a nivel de junta, que es la senal de confianza mas fuerte que tenemos.";
                } elseif ($verification === 'core_verified') {
                    $sentences[] = "Su perfil esta verificado en nuestro sistema.";
                } elseif ($verification === 'source_only') {
                    $sentences[] = "Lo tenemos confirmado a traves de fuentes confiables.";
                }
            }
            if ($claimed) {
                $sentences[] = "El proveedor mismo gestiona su perfil, asi que la informacion esta actualizada.";
            }
            if (!empty($phone)) {
                $sentences[] = "Si quieres contactarlos, su telefono es {$phone}.";
            }
            $sentences[] = "Quieres que te compare con otra opcion o revisamos seguro o ubicacion?";
            return implode(' ', $sentences);
        }

        // ── English conversational speech ──
        $sentences = array();

        // Opening — warm, personal framing
        $sentences[] = "Sure, here's what I can tell you about {$name}.";

        // Specialties — connect to what they do
        if (!empty($specs)) {
            $spec_list = implode(', ', array_slice($specs, 0, 3));
            $sentences[] = "They specialize in {$spec_list}.";
        } elseif (!empty($pcat)) {
            $sentences[] = "They work in {$pcat}.";
        }

        // Location — contextual
        if (!empty($city)) {
            $sentences[] = "They're based in {$city}.";
        }

        // License — framed as a credential strength
        if (!empty($license)) {
            $sentences[] = "They hold a {$license} credential.";
        }
        if ($session_type !== '') {
            $sentences[] = "The profile documents {$session_type} sessions.";
        }
        if (!empty($insurance)) {
            $sentences[] = 'The profile documents these insurance plans: ' . implode(', ', array_slice($insurance, 0, 3)) . '.';
        }

        // Verification — explain what it means, don't just label it
        if (!empty($verification)) {
            if ($verification === 'board_confirmed') {
                $sentences[] = "Something worth noting: their profile is board verified, which is the strongest trust signal we have.";
            } elseif ($verification === 'core_verified') {
                $sentences[] = "Their profile has been verified through our review process.";
            } elseif ($verification === 'source_only') {
                $sentences[] = "We've confirmed them through reliable sources.";
            }
        }

        // Claimed — explain the benefit
        if ($claimed) {
            $sentences[] = "The provider themselves manages their profile, so the information tends to be more up to date.";
        }

        // Phone — helpful framing
        if (!empty($phone)) {
            $sentences[] = "If you'd like to reach out, their number is {$phone}.";
        }

        // Closing — conversational next-step offer
        $sentences[] = "Would you like me to compare them with another option, or should we look into insurance or location details?";

        return implode(' ', $sentences);
    }

    private function build_grounded_no_results_reply($criteria, $lang = 'en') {
        $criteria = is_array($criteria) ? $criteria : array();
        $lang = $lang === 'es' ? 'es' : 'en';
        $requested_support = sanitize_text_field(
            ($criteria['requested_specialty'] ?? '') ?: (($criteria['specialty'] ?? '') ?: ($criteria['primary_category_name'] ?? ''))
        );
        $requested_city = sanitize_text_field($criteria['city'] ?? '');
        $has_locked_category = !empty($criteria['primary_category']) || !empty($criteria['primary_category_name']);

        if ($lang === 'es') {
            if ($requested_support !== '' && $requested_city !== '') {
                $reply = "Todavia no encontre un proveedor de {$requested_support} en {$requested_city}.";
                if ($has_locked_category) {
                    $reply .= ' No lo sustitui por otro tipo de proveedor.';
                }
                return $reply . ' Quieres que mantenga el mismo servicio y busque en una ciudad cercana?';
            }
            if ($requested_support !== '') {
                return "Todavia no encontre una coincidencia exacta para {$requested_support}. Dime una ciudad y mantendre la busqueda dentro de ese servicio.";
            }
            return 'No encontre una coincidencia exacta con esos criterios. Dime otra ciudad o el tipo de apoyo que buscas y lo intento de nuevo.';
        }

        if ($requested_support !== '' && $requested_city !== '') {
            $reply = "I couldn't find a {$requested_support} provider in {$requested_city} yet.";
            if ($has_locked_category) {
                $reply .= " I didn't substitute a different kind of provider.";
            }
            return $reply . ' Would you like me to keep the same service and try a nearby city?';
        }
        if ($requested_support !== '') {
            return "I couldn't find an exact {$requested_support} match yet. Tell me a city and I'll keep the search within that service.";
        }
        return "I couldn't find an exact match for those criteria. Tell me another city or the kind of support you want and I'll try again.";
    }

    /**
     * Conversational speech for provider search results (format_providers_fallback).
     * Instead of reading "Why this is a strong first fit: • reason1 • reason2"
     * Mia explains naturally: "I'd start with X because they specialize in Y
     * and they're in Z. I also found A in B, and C who works with D."
     */
    private function build_search_results_conversational_speech($providers, $criteria, $lang = 'en') {
        if (empty($providers) || !is_array($providers)) return '';
        if (!$this->all_providers_match_requested_city($providers, $criteria['city'] ?? '')) {
            return $this->format_providers_fallback(array('providers' => $providers, 'criteria' => $criteria, 'detected_language' => $lang), '');
        }
        $count = count($providers);
        $specialty = $criteria['specialty'] ?? $criteria['primary_category_name'] ?? '';
        $city = $criteria['city'] ?? '';
        $requested = sanitize_text_field($criteria['requested_specialty'] ?? '');
        $qualification = $requested === '' ? '' : ($lang === 'es'
            ? "Son opciones mas amplias; {$requested} no esta confirmado en todos los perfiles. "
            : "These are broader options; {$requested} isn't confirmed in every profile. ");

        if ($lang === 'es') {
            $sentences = array();
            $scope = $specialty && $city ? "{$specialty} en {$city}" : ($specialty ?: ($city ?: 'proveedores'));
            $sentences[] = "Encontre {$count} opciones de {$scope}.";
            if (!empty($providers[0])) {
                $top = $providers[0];
                $name = $top['name'] ?? 'esta opcion';
                $top_specs = array_filter(array_map('trim', ($top['specialties'] ?? array())));
                $top_city = trim((string) ($top['city'] ?? ''));
                $reason_parts = array();
                if (!empty($top_specs)) $reason_parts[] = 'se especializa en ' . implode(', ', array_slice($top_specs, 0, 2));
                if (!empty($top_city)) $reason_parts[] = "esta en {$top_city}";
                if (!empty($top['verification_status']) && $top['verification_status'] === 'board_confirmed') $reason_parts[] = 'tiene verificacion de junta';
                if ($reason_parts) {
                    $sentences[] = "Empezaria con {$name} porque " . implode(' y ', array_slice($reason_parts, 0, 2)) . '.';
                } else {
                    $sentences[] = "Empezaria con {$name}.";
                }
            }
            $alts = array_slice($providers, 1, 1);
            if (!empty($alts)) {
                foreach ($alts as $i => $p) {
                    $aname = $p['name'] ?? 'otro';
                    $acity = trim((string) ($p['city'] ?? ''));
                    $aspecs = array_filter(array_map('trim', ($p['specialties'] ?? array())));
                    $bits = array();
                    if (!empty($aspecs)) $bits[] = implode(', ', array_slice($aspecs, 0, 2));
                    if (!empty($acity)) $bits[] = $acity;
                    if (!empty($bits)) {
                        $sentences[] = "Tambien esta {$aname}, que trabaja en " . implode(' en ', array_slice($bits, 0, 2)) . '.';
                    } else {
                        $sentences[] = "Tambien esta {$aname}.";
                    }
                }
            }
            if ($count > 2) $sentences[] = 'Las otras opciones estan en la pantalla.';
            return $qualification . implode(' ', $sentences);
        }

        // ── English ──
        $sentences = array();

        // Opening with count and scope
        $scope = $specialty && $city ? "{$specialty} in {$city}" : ($specialty ?: ($city ?: 'providers'));
        $sentences[] = "I found {$count} {$scope}.";

        // Top recommendation with natural reasons
        if (!empty($providers[0])) {
            $top = $providers[0];
            $name = $top['name'] ?? 'this option';
            $top_specs = array_filter(array_map('trim', ($top['specialties'] ?? array())));
            $top_city = trim((string) ($top['city'] ?? ''));
            $top_license = trim((string) ($top['license_type'] ?? ''));
            $reason_parts = array();
            if (!empty($top_specs)) $reason_parts[] = 'they specialize in ' . implode(', ', array_slice($top_specs, 0, 2));
            if (!empty($top_city)) $reason_parts[] = "they're in {$top_city}";
            if (!empty($top['verification_status']) && $top['verification_status'] === 'board_confirmed') {
                $reason_parts[] = "they're board verified";
            } elseif (!empty($top['claimed'])) {
                $reason_parts[] = "their profile is self-managed";
            }
            if (!empty($reason_parts)) {
                $sentences[] = "I'd start with {$name} because " . implode(' and ', array_slice($reason_parts, 0, 2)) . '.';
            } else {
                $sentences[] = "I'd start with {$name}.";
            }
        }

        // Alternative providers — each as a natural sentence
        $alts = array_slice($providers, 1, 1);
        if (!empty($alts)) {
            foreach ($alts as $i => $p) {
                $aname = $p['name'] ?? 'another option';
                $acity = trim((string) ($p['city'] ?? ''));
                $aspecs = array_filter(array_map('trim', ($p['specialties'] ?? array())));
                $bits = array();
                if (!empty($aspecs)) $bits[] = 'works in ' . implode(', ', array_slice($aspecs, 0, 2));
                if (!empty($acity)) $bits[] = "based in {$acity}";
                if (!empty($bits)) {
                    $sentences[] = "{$aname} " . implode(' and ', array_slice($bits, 0, 2)) . '.';
                } else {
                    $sentences[] = "There's also {$aname}.";
                }
            }
        }

        // More count
        $more = max(0, $count - 2);
        if ($more > 0) {
            $sentences[] = "The other {$more} options are on screen.";
        }

        // Closing
        return $qualification . implode(' ', $sentences);
    }

    /**
     * Conversational speech for provider contact info (build_provider_contact_response).
     * Instead of "Phone: 555-1234. Address: 123 Main St."
     * Mia says: "You can reach them at 555-1234. Their office is at 123 Main St."
     */
    private function build_provider_contact_conversational_speech($provider, $lang = 'en') {
        if (empty($provider) || !is_array($provider)) return '';
        $name = trim((string) ($provider['name'] ?? 'this provider'));
        $phone = trim((string) ($provider['phone'] ?? ''));
        $address = trim((string) ($provider['address'] ?? ''));
        $street = trim((string) ($provider['street'] ?? ''));
        $city = trim((string) ($provider['city'] ?? ''));
        $website = trim((string) ($provider['website'] ?? ''));

        if ($lang === 'es') {
            $sentences = array();
            $sentences[] = "Te comparto los datos de contacto de {$name}.";
            if (!empty($phone)) {
                $sentences[] = "Su telefono es {$phone}.";
            } else {
                $sentences[] = "No tienen telefono publicado en este momento.";
            }
            $full_address = $address ?: $street;
            if ($full_address && $city && strcasecmp(trim($full_address), trim($city)) !== 0) $full_address .= ", {$city}";
            elseif (!$full_address && $city) $full_address = $city;
            if (!empty($full_address)) {
                $sentences[] = "Estan ubicados en {$full_address}.";
            } else {
                $sentences[] = "No tienen direccion publicada por el momento.";
            }
            $sentences[] = "Te recomiendo confirmar los datos en el perfil antes de llamar o visitar.";
            return implode(' ', $sentences);
        }

        $sentences = array();
        $sentences[] = "Here's how you can reach {$name}.";
        if (!empty($phone)) {
            $sentences[] = "Their number is {$phone}.";
        } else {
            $sentences[] = "They don't have a phone number listed right now.";
        }
        $full_address = $address ?: $street;
        if ($full_address && $city && strcasecmp(trim($full_address), trim($city)) !== 0) $full_address .= ", {$city}";
        elseif (!$full_address && $city) $full_address = $city;
        if (!empty($full_address)) {
            $sentences[] = "Their office is at {$full_address}.";
        } else {
            $sentences[] = "They don't have an address listed at the moment.";
        }
        if (!empty($website)) {
            $sentences[] = "You can also check their website at {$website}.";
        }
        $sentences[] = "I'd still double-check the details on their profile before calling or visiting.";
        return implode(' ', $sentences);
    }

    private function build_category_overview_conversational_speech($context, $lang = 'en', $message = '') {
        $full_list = preg_match('/\b(?:all|every|everything|twelve|12|list|enumerate|todas?|todo|doce|enumera)\b/i', remove_accents($message));
        if (!$full_list) {
            return $lang === 'es'
                ? 'Puedo ayudarte a explorar atencion primaria, terapia, ejercicio, nutricion y otros servicios de bienestar. Que tipo de apoyo buscas?'
                : 'I can help you explore primary care, therapy, fitness, nutrition, and other wellness services. What are you looking for?';
        }
        $categories = !empty($context['categories']) && is_array($context['categories']) ? $context['categories'] : array();
        $category_names = array_values(array_filter(array_map(static function ($category) {
            return html_entity_decode($category['name'] ?? '', ENT_QUOTES, 'UTF-8');
        }, $categories)));
        $last_category = array_pop($category_names);
        $spoken_categories = implode(', ', $category_names);
        if ($last_category) {
            $spoken_categories .= ($spoken_categories ? ', and ' : '') . $last_category;
        }

        if ($lang === 'es') {
            if (empty($categories)) {
                return 'Usamos 12 categorias de bienestar. Puedo buscar por terapia, cuidado medico, fitness, nutricion, masaje, sueno, grupos de apoyo, bienestar financiero, carrera, terapias holisticas, mindfulness o crecimiento personal. Dime que buscas.';
            }
            $sentences = array();
            $sentences[] = 'Cubro 12 categorias principales de bienestar: ' . $spoken_categories . '.';
            $sentences[] = 'La pantalla explica cada area y separa el alcance del servicio de la disponibilidad actual de perfiles.';
            $sentences[] = 'Tambien ayudo a proveedores a agregar o reclamar un perfil. Dime que area quieres explorar.';
            return implode(' ', $sentences);
        }

        if (empty($categories)) {
            return 'We use 12 main wellness categories. I can search by therapy, medical care, fitness, nutrition, massage, sleep, support groups, financial wellness, career coaching, holistic therapies, mindfulness, or personal growth. Tell me what you\'re looking for.';
        }

        $sentences = array();
        $sentences[] = 'I cover 12 main wellness categories: ' . $spoken_categories . '.';
        $sentences[] = 'The screen explains every area and separates service scope from current profile availability.';
        $sentences[] = 'I also help providers add or claim a profile. Tell me which area you want to explore.';
        return implode(' ', $sentences);
    }

    /**
     * Build detailed info lines for a provider deep-dive.
     * Returns formatted bullet lines with specialties, category, contact, verification, etc.
     */
    private function build_provider_detail_lines($provider, $lang = 'en') {
        if (empty($provider) || !is_array($provider)) return '';
    
        $lines = array();
    
        // Specialties
        $specs = array_filter(array_map('trim', ($provider['specialties'] ?? array())));
        if (!empty($specs)) {
            $spec_text = implode(', ', array_slice($specs, 0, 5));
            $lines[] = ($lang === 'es') ? "**Especialidades:** {$spec_text}" : "**Specialties:** {$spec_text}";
        }
    
        // Primary category
        $pcat = trim((string) ($provider['primary_category'] ?? ''));
        if (!empty($pcat)) {
            $lines[] = ($lang === 'es') ? "**Categoría:** {$pcat}" : "**Category:** {$pcat}";
        }
    
        // License type
        $license = trim((string) ($provider['license_type'] ?? ''));
        if (!empty($license)) {
            $lines[] = ($lang === 'es') ? "**Licencia:** {$license}" : "**License:** {$license}";
        }
    
        // Phone
        $phone = trim((string) ($provider['phone'] ?? ''));
        if (!empty($phone)) {
            $lines[] = ($lang === 'es') ? "**Teléfono:** {$phone}" : "**Phone:** {$phone}";
        }
    
        // Address
        $address = trim((string) ($provider['address'] ?? ''));
        $street = trim((string) ($provider['street'] ?? ''));
        $city = trim((string) ($provider['city'] ?? ''));
        $full_address = $address ?: $street;
        if ($full_address && $city && strcasecmp(trim($full_address), trim($city)) !== 0) $full_address .= ", {$city}";
        elseif (!$full_address && $city) $full_address = $city;
        if (!empty($full_address)) {
            $lines[] = ($lang === 'es') ? "**Ubicación:** {$full_address}" : "**Location:** {$full_address}";
        }
    
        // Verification status
        $verification = trim((string) ($provider['verification_status'] ?? ''));
        if (!empty($verification)) {
            $label = $this->format_verification_label($verification, $lang);
            $lines[] = ($lang === 'es') ? "**Verificación:** {$label}" : "**Verification:** {$label}";
        }
    
        // Claimed status
        if (!empty($provider['claimed'])) {
            $lines[] = ($lang === 'es')
                ? "**Perfil reclamado:** Sí — el proveedor gestiona esta información"
                : "**Profile claimed:** Yes — the provider manages this information";
        }
    
        // Website
        $website = trim((string) ($provider['website'] ?? ''));
        if (!empty($website)) {
            $lines[] = ($lang === 'es') ? "**Sitio web:** [{$website}]({$website})" : "**Website:** [{$website}]({$website})";
        }
    
        if (empty($lines)) return '';
    
        $prefix = ($lang === 'es') ? '**Más detalles:**' : '**More details:**';
        return $prefix . "\n" . implode("\n", array_map(function($l) { return "- {$l}"; }, $lines));
    }

    private function build_provider_contact_response($provider, $lang = 'en') {
        if (empty($provider) || !is_array($provider)) {
            return null;
        }

        $name = $provider['name'] ?? 'this provider';
        $url = $provider['url'] ?? '#';
        $phone = trim((string) ($provider['phone'] ?? ''));
        $address = trim((string) ($provider['address'] ?? ''));
        $street = trim((string) ($provider['street'] ?? ''));
        $website = trim((string) ($provider['website'] ?? ''));
        $contact_lines = array();

        if ($phone) {
            $contact_lines[] = ($lang === 'es') ? "**Telefono:** {$phone}" : "**Phone:** {$phone}";
        } else {
            $contact_lines[] = ($lang === 'es') ? "**Telefono:** no aparece publicado" : "**Phone:** not listed";
        }
        if ($address) {
            if ($street) {
                $contact_lines[] = ($lang === 'es') ? "**Direccion:** {$address}" : "**Address:** {$address}";
            } else {
                $contact_lines[] = ($lang === 'es') ? "**Ubicacion:** {$address}" : "**Location:** {$address}";
            }
        } else {
            $contact_lines[] = ($lang === 'es') ? "**Direccion:** no aparece publicada" : "**Address:** not listed";
        }
        if ($website) {
            $contact_lines[] = ($lang === 'es') ? "**Sitio web:** {$website}" : "**Website:** {$website}";
        }

        if ($lang === 'es') {
            return "Esto es lo que veo para [**{$name}**]({$url}):\n\n• " . implode("\n• ", $contact_lines) . "\n\nAntes de llamar o visitar, te recomiendo confirmar los detalles en el perfil.";
        }

        return "Here's what I see for [**{$name}**]({$url}):\n\n• " . implode("\n• ", $contact_lines) . "\n\nBefore calling or visiting, I'd still confirm the details on the profile.";
    }

    private function build_provider_claim_guidance_response($message, $prior_context = array(), $result = array(), $original_prior_context = array()) {
        $msg = strtolower(trim((string) $message));
        $lang = $this->resolve_response_language($msg, $prior_context, $result);
        // Use original (pre-intent-engine) context for provider_owner_mode checks.
        // $prior_context is $updated_context which already has provider_owner_mode set
        // by build_updated_context(), so checking it would always find the mode set
        // on the current turn — blocking the "Claim after browsing" path.
        // (Debug log 1777505821549: "Felicia I need to claim my profile" after seeing
        // ALSTON providers showed generic claim guidance instead of "Which of these
        // is your profile?" because provider_owner_mode was already set.)
        $mode_context = !empty($original_prior_context) ? $original_prior_context : $prior_context;
        $providers = array();

        // ── Provider list priority in claim flow ──
        // When the user is mid-disambiguation (provider_owner_mode set OR
        // claim_disambiguation_providers populated), the disambiguation list is
        // the authoritative source.  recent_providers may hold stale results
        // from an earlier consumer search and must NOT be the source for ordinal
        // selection like "number one".
        // (Debug CSV 1778550052245 T4: ordinal "number one" + "Tanya" picked
        //  ALEXIA ALI because recent_providers took priority over the actual
        //  Tanya disambiguation list.)
        $in_claim_flow = !empty($mode_context['provider_owner_mode']) ||
            (!empty($prior_context['claim_disambiguation_providers']) && is_array($prior_context['claim_disambiguation_providers'])) ||
            (!empty($original_prior_context['claim_disambiguation_providers']) && is_array($original_prior_context['claim_disambiguation_providers']));

        if ($in_claim_flow) {
            if (!empty($prior_context['claim_disambiguation_providers']) && is_array($prior_context['claim_disambiguation_providers'])) {
                $providers = $prior_context['claim_disambiguation_providers'];
            } elseif (!empty($original_prior_context['claim_disambiguation_providers']) && is_array($original_prior_context['claim_disambiguation_providers'])) {
                $providers = $original_prior_context['claim_disambiguation_providers'];
            }
        }

        // Fallback sources (outside claim flow, or claim_disambiguation_providers empty)
        if (empty($providers) && !empty($prior_context['recent_providers']) && is_array($prior_context['recent_providers'])) {
            $providers = $prior_context['recent_providers'];
        }
        if (empty($providers) && !empty($result['providers']) && is_array($result['providers'])) {
            $providers = $result['providers'];
        }
        // Final fallback to claim_disambiguation_providers when not in a flagged
        // claim flow (e.g. selecting from a "did you mean" list right after).
        if (empty($providers) && !empty($prior_context['claim_disambiguation_providers']) && is_array($prior_context['claim_disambiguation_providers'])) {
            $providers = $prior_context['claim_disambiguation_providers'];
        }
        if (empty($providers) && !empty($original_prior_context['claim_disambiguation_providers']) && is_array($original_prior_context['claim_disambiguation_providers'])) {
            $providers = $original_prior_context['claim_disambiguation_providers'];
        }

        $has_claim_candidates = (!empty($prior_context['claim_disambiguation_providers']) && is_array($prior_context['claim_disambiguation_providers'])) ||
            (!empty($original_prior_context['claim_disambiguation_providers']) && is_array($original_prior_context['claim_disambiguation_providers']));

        // Questions about the active claim workflow are not provider names.
        // Answer them directly and leave the selected/candidate provider intact.
        if (preg_match('/\b(?:what|and\s+what)\s+happens?\s+(?:next|after)\b[\s\S]{0,45}\bclaim|\bafter\s+(?:i|we)\s+claim\b/iu', $msg)) {
            if ($lang === 'es') {
                return 'Despues de enviar el reclamo, recibiras una confirmacion por email. El reclamo sigue el proceso de verificacion del sitio y, cuando quede aprobado, podras mantener actualizados los datos, especialidades y contacto del perfil.';
            }
            return "After you submit the claim, you'll receive an email confirmation. The claim then follows the site's verification process, and once approved you can keep the profile details, specialties, and contact information up to date.";
        }
        if (preg_match('/\b(?:is|does)\s+claim(?:ing)?\b[\s\S]{0,30}\b(?:free|cost)|\bclaim(?:ing)?\b[\s\S]{0,20}\bfree\b/iu', $msg)) {
            return $lang === 'es'
                ? 'Si. Reclamar un perfil es **gratis**. Usa el enlace de reclamo del perfil correcto y envia los datos de verificacion solicitados.'
                : 'Yes. Claiming a profile is **free**. Use the claim link for the correct profile and submit the requested verification details.';
        }
        if (preg_match('/\b(?:direct\s+)?claim\s+link\b|\blink\b[\s\S]{0,25}\bclaim\b/iu', $msg)) {
            if (count($providers) === 1 && !empty($providers[0]['id'])) {
                $p = $providers[0];
                $name = $p['name'] ?? 'this profile';
                $claim_url = $this->build_claim_url($p['id']);
                $this->_claim_disambiguation_providers = array($p);
                $this->_conversational_speech = $lang === 'es'
                    ? "Aqui tienes otra vez el enlace de reclamo para {$name}."
                    : "Here is the claim link for {$name} again.";
                return $lang === 'es'
                    ? "Aqui tienes otra vez el enlace directo para **{$name}**: [**Claim Your Free Profile**]({$claim_url})."
                    : "Here is the direct claim link for **{$name}** again: [**Claim Your Free Profile**]({$claim_url}).";
            }
            if (count($providers) > 1) {
                return $lang === 'es'
                    ? 'Tengo varios perfiles posibles. Dime el numero o el nombre y la ciudad para darte el enlace de reclamo correcto.'
                    : 'I still have several possible profiles. Tell me the number or the provider name and city so I can give you the correct claim link.';
            }
        }

        // ── Claim after browsing: user was looking at providers and now wants to claim ──
        // If the user just saw a list of providers and says "claim my listing/profile",
        // ask "which one is yours?" rather than asking for name/city from scratch.
        $recently_listed = (!empty($providers) && count($providers) >= 1);
        $is_initial_claim_request = preg_match('/\bclaim\b/i', $msg) || preg_match('/\bmy\s+(?:profile|listing|list|practice)\b/i', $msg);

        // ── Deictic + focus resolution (FIRST claim turn) ──
        // When the user just looked at a specific provider ("tell me more about
        // Mary McKenzie") and then says "I need to claim this profile", the word
        // "this" refers to the focused provider — NOT all recent providers.
        // last_provider_focus holds that ID.  Resolve it here so Mia gives a
        // direct claim link instead of re-showing the unrelated list.
        // (Debug CSV 1778547929060 T6: focus=MARY MACKENZIE ALMOND but Mia
        //  showed all 4 ASHLEY/CASSANDRA/MARY/JAMIE as candidates.)
        // The original context is what the visitor saw; result building can reset its focus.
        $focus_context = !empty($original_prior_context) ? $original_prior_context : $prior_context;
        $focus_id = absint($focus_context['last_provider_focus'] ?? 0);
        // Only an unqualified reference uses focus. An explicit name/ordinal must resolve itself.
        $has_deictic_reference = preg_match('/\b(?:this|that|the)\s+(?:profile|listing|one|account|provider)\b(?:\s+(?:please|now))?[\s.!?]*$/i', $msg) ||
            preg_match('/\bthis\s+is\s+me\b|\bthat\'?s\s+me\b/i', $msg) ||
            preg_match('/\b(?:este|ese)\s+(?:perfil|listado)\b(?:\s+por\s+favor)?[\s.!?]*$/iu', $msg);
        $is_initial_claim_request = $is_initial_claim_request || preg_match('/\breclamar\b/iu', $msg);
        if ($is_initial_claim_request && $has_deictic_reference) {
            $focus_providers = !empty($focus_context['recent_providers']) ? $focus_context['recent_providers'] : $providers;
            $focused_provider = null;
            if ($focus_id === 0 && count($focus_providers) === 1) $focus_id = absint($focus_providers[0]['id'] ?? 0);
            foreach ($focus_providers as $p) {
                if (absint($p['id'] ?? 0) === $focus_id) {
                    $focused_provider = $p;
                    break;
                }
            }
            if ($focused_provider) {
                $this->_claim_disambiguation_providers = array($focused_provider);
                $fp_name       = $focused_provider['name'] ?? 'this profile';
                $fp_url        = $this->sanitize_provider_url($focused_provider['url'] ?? '#');
                $fp_claim      = $this->build_claim_url($focused_provider['id'] ?? 0);
                $fp_city       = trim((string)($focused_provider['city'] ?? ''));
                $fp_where      = $fp_city ? " in {$fp_city}" : '';
                $fp_specs      = array_filter(array_map('trim', ($focused_provider['specialties'] ?? array())));
                $fp_spec_lbl   = !empty($fp_specs) ? ' (' . implode(', ', array_slice($fp_specs, 0, 3)) . ')' : '';
                if ($lang === 'es') {
                    $this->_conversational_speech = "Perfecto. Si {$fp_name} es tu perfil, usa el enlace de reclamo en el chat.";
                    return "Perfecto. Si [**{$fp_name}**]({$fp_url}){$fp_spec_lbl} es tu perfil, usa este enlace directo: [**Claim Your Free Profile**]({$fp_claim}).\n\nEl reclamo es **gratis** y toma 2 minutos. Si ese no es tu perfil, dime el nombre correcto.";
                }
                $this->_conversational_speech = "If {$fp_name} is your profile, use the claim link I put in the chat.";
                return "If [**{$fp_name}**]({$fp_url}){$fp_where}{$fp_spec_lbl} is your profile, use this direct link: [**Claim Your Free Profile**]({$fp_claim}).\n\nClaiming is **free** and takes about 2 minutes. If that's not your profile, tell me the correct name and city.";
            }
            // Never substitute the first result when a deictic claim has no reliable focus.
            $this->_claim_disambiguation_providers = $focus_providers;
            $this->_conversational_speech = $lang === 'es'
                ? 'Cual perfil quieres reclamar? Dime el nombre o el numero.'
                : 'Which profile do you want to claim? Tell me the name or number.';
            return $lang === 'es'
                ? 'Cual perfil quieres reclamar? Dime el nombre o el numero para darte el enlace correcto.'
                : 'Which profile do you want to claim? Tell me the name or number so I can give you the correct link.';
        }

        // ── Grok-driven inline-name bypass ──
        // When the user says "I need to claim this profile Dr Megan" but
        // recent_providers contains unrelated consumer-search results, we must
        // NOT show those stale providers as claim candidates.  Grok extracts
        // the named provider into entities.provider_name; if that name doesn't
        // match anyone in $providers, search the DB directly.  This replaces
        // the previous regex maze (extract from "profile X", "X claim",
        // "Dr. X in Y", etc.) with one trusted source.
        $grok_provider_name = $this->get_grok_extracted_provider_name($result);

        // ── Fallback regex extraction when Grok returns empty ──
        // Grok occasionally times out or returns empty understanding.  One
        // narrow pattern recovers the most common case: "I need to claim
        // <Name>" or "claim <Name>".  If the extracted name matches a recent
        // provider, the inline-name bypass below can give a direct claim link
        // instead of forcing the user through a disambiguation list.
        // (Debug CSV 1778591772248 T8: "I need to claim Mary McKenzie" with
        //  Mary in recent_providers but Grok understanding empty — Mia showed
        //  the full 4-provider list instead of resolving directly to Mary.)
        if ($grok_provider_name === '' && preg_match('/\bclaim\s+(?:this\s+|that\s+|the\s+|my\s+|a\s+)?(?:profile\s+(?:of\s+|for\s+)?|listing\s+(?:of\s+|for\s+)?|practice\s+(?:of\s+|for\s+)?)?(.+?)\s*$/i', $msg, $cm)) {
            $candidate = trim($cm[1]);
            // Filter generic words that aren't names
            if (strlen($candidate) >= 3 &&
                !preg_match('/^(?:profile|listing|practice|my|this|that|the|it|one|page)$/i', $candidate) &&
                !preg_match('/^(?:my\s+)?(?:profile|listing|practice)$/i', $candidate)) {
                $grok_provider_name = $candidate;
            }
        }

        if ($recently_listed && $is_initial_claim_request && empty($mode_context['provider_owner_mode']) && $grok_provider_name !== '') {
            $icn_lower    = strtolower($grok_provider_name);
            $icn_parts    = preg_split('/\s+/', $icn_lower);
            $icn_first    = $icn_parts[0] ?? '';
            $found_in_recent = false;
            $matched_recent_provider = null;
            foreach ($providers as $rp) {
                $rpn        = strtolower(trim($rp['name'] ?? ''));
                $rpn_parts  = preg_split('/\s+/', $rpn);
                $rpn_first  = $rpn_parts[0] ?? '';
                if ($rpn === $icn_lower ||
                    strpos($rpn, $icn_lower) !== false ||
                    strpos($icn_lower, $rpn) !== false ||
                    ($icn_first && strlen($icn_first) >= 3 && $rpn_first === $icn_first)) {
                    $found_in_recent = true;
                    $matched_recent_provider = $rp;
                    break;
                }
            }
            // ── Named provider IS in recent list → direct claim link ──
            // "I need to claim Mary McKenzie" + recent_providers has Mary →
            // give direct claim link for Mary, not the disambiguation list.
            // (Debug CSV 1778591772248 T8.)
            if ($found_in_recent && $matched_recent_provider) {
                $mp_name      = $matched_recent_provider['name'] ?? 'this profile';
                $mp_url       = $this->sanitize_provider_url($matched_recent_provider['url'] ?? '#');
                $mp_claim     = $this->build_claim_url($matched_recent_provider['id'] ?? 0);
                $mp_city      = trim((string)($matched_recent_provider['city'] ?? ''));
                $mp_where     = $mp_city ? " in {$mp_city}" : '';
                $mp_specs     = array_filter(array_map('trim', ($matched_recent_provider['specialties'] ?? array())));
                $mp_spec_lbl  = !empty($mp_specs) ? ' (' . implode(', ', array_slice($mp_specs, 0, 3)) . ')' : '';
                if ($lang === 'es') {
                    $this->_conversational_speech = "Si {$mp_name} es tu perfil, usa el enlace de reclamo en el chat.";
                    return "Si [**{$mp_name}**]({$mp_url}){$mp_spec_lbl} es tu perfil, usa este enlace directo: [**Claim Your Free Profile**]({$mp_claim}).\n\nEl reclamo es **gratis** y toma 2 minutos. Si ese no es tu perfil, dime el nombre correcto.";
                }
                $this->_conversational_speech = "If {$mp_name} is your profile, use the claim link I put in the chat.";
                return "If [**{$mp_name}**]({$mp_url}){$mp_where}{$mp_spec_lbl} is your profile, use this direct link: [**Claim Your Free Profile**]({$mp_claim}).\n\nClaiming is **free** and takes about 2 minutes. If that's not your profile, tell me the correct name and city.";
            }
            if (!$found_in_recent) {
                // Named provider not in stale list — search DB with Grok's name + city.
                $icn_match = $this->lookup_claim_provider_from_message($msg, $prior_context, $grok_provider_name, $this->get_grok_extracted_city($result));
                if (!empty($icn_match)) {
                    if (count($icn_match) === 1) {
                        $ip          = $icn_match[0];
                        $ipname      = $ip['name'] ?? 'this profile';
                        $ip_url      = $this->sanitize_provider_url($ip['url'] ?? '#');
                        $ip_claim    = $this->build_claim_url($ip['id'] ?? 0);
                        $ip_city     = trim((string)($ip['city'] ?? ''));
                        $ip_where    = $ip_city ? " in {$ip_city}" : '';
                        $ip_specs    = array_filter(array_map('trim', ($ip['specialties'] ?? array())));
                        $ip_spec_lbl = !empty($ip_specs) ? ' (' . implode(', ', array_slice($ip_specs, 0, 3)) . ')' : '';
                        if ($lang === 'es') {
                            $this->_conversational_speech = "Encontre el perfil. Si es el tuyo, usa el enlace de reclamo que puse en el chat.";
                            return "Encontre [**{$ipname}**]({$ip_url}){$ip_spec_lbl}. Si es tu perfil, usa este enlace directo: [**Claim Your Free Profile**]({$ip_claim}).\n\nEl reclamo es **gratis**. Te pedira nombre, email y un enlace de prueba. Si no es tu perfil, dime el nombre correcto.";
                        }
                        $this->_conversational_speech = "I found the profile. If that's yours, use the claim link I put in the chat.";
                        return "I found [**{$ipname}**]({$ip_url}){$ip_where}{$ip_spec_lbl}. If that's your profile, use this direct link: [**Claim Your Free Profile**]({$ip_claim}).\n\nClaiming is **free**. The form asks for your name, email, and a proof link like your website or license page. If that's not your profile, tell me the correct name and city.";
                    }
                    // Multiple matches — build disambiguation list
                    $ip_items = array();
                    foreach (array_slice($icn_match, 0, 4) as $ii => $ip) {
                        $ipname   = $ip['name'] ?? 'Unknown';
                        $ip_url   = $this->sanitize_provider_url($ip['url'] ?? '#');
                        $ip_claim = $this->build_claim_url($ip['id'] ?? 0);
                        $ip_city  = trim((string)($ip['city'] ?? ''));
                        $ip_line  = ($ii + 1) . ". [**{$ipname}**]({$ip_url})";
                        if ($ip_city) { $ip_line .= " — {$ip_city}"; }
                        $ip_line .= " · [Claim]({$ip_claim})";
                        $ip_items[] = $ip_line;
                    }
                    $ip_list = implode("\n", $ip_items);
                    $this->_claim_disambiguation_providers = array_slice($icn_match, 0, 4);
                    $this->_conversational_speech = "I found a few matches for that name. Say the number or name and city. I put the full list in the chat.";
                    if ($lang === 'es') {
                        return "Encontre varios perfiles con ese nombre. Cual es el tuyo?\n\n{$ip_list}\n\nSi tu perfil no esta en la lista, dime tu nombre y ciudad y lo busco.";
                    }
                    return "I found a few profiles matching that name. Which one is yours?\n\n{$ip_list}\n\nIf yours isn't listed, tell me your full name and city and I'll search for it.";
                }
                // No DB match for the named provider
                $this->_conversational_speech = "I couldn't find a profile with that name. Try your full name and city. For example: Dr. Jane Smith in Raleigh.";
                if ($lang === 'es') {
                    return "No encontre un perfil con ese nombre. Intenta con el **nombre completo** y la **ciudad** (por ejemplo: \"Dr. Jane Smith en Raleigh\").\n\nTambien puedes buscar en [Providers](/listings/) o crear un perfil en [Add Your Practice](/add-your-practice/).";
                }
                return "I couldn't find a profile with that name in the directory. Try the **full name** and **city** (for example: \"Dr. Jane Smith in Raleigh\").\n\nYou can also search on the [Providers](/listings/) page or create a new profile at [Add Your Practice](/add-your-practice/).";
            }
        }

        if ($recently_listed && $is_initial_claim_request && empty($mode_context['provider_owner_mode'])) {
            $items = array();
            foreach (array_slice($providers, 0, 4) as $i => $p) {
                $name        = $p['name'] ?? 'Unknown';
                $city        = trim((string) ($p['city'] ?? ''));
                $profile_url = $this->sanitize_provider_url($p['url'] ?? '#');
                $claim_url   = $this->build_claim_url($p['id'] ?? 0);
                // Name → profile page so user can verify before claiming.
                // Separate "Claim" link → claim form.
                $line = ($i + 1) . ". [**{$name}**]({$profile_url})";
                if ($city) { $line .= " — {$city}"; }
                $line .= " · [Claim]({$claim_url})";
                $items[] = $line;
            }
            $list_text = implode("\n", $items);
            $this->_claim_disambiguation_providers = array_slice($providers, 0, 4);
            // Keep the spoken prompt short; the full linked list is shown in chat.
            // Reading all names aloud caused long TTS and made the next selection
            // turn more likely to pick up stale audio/text.
            if ($lang === 'es') {
                $this->_conversational_speech = "Encontre algunas posibles coincidencias. Di el numero, por ejemplo numero uno, o di el nombre del proveedor y la ciudad. Puse la lista completa en el chat.";
                return "Cual de estos es tu perfil? Dime el numero o el nombre y te doy el enlace directo de reclamo:\n\n{$list_text}\n\nSi tu perfil no esta en la lista, dime el nombre y la ciudad y lo busco.";
            }
            $this->_conversational_speech = "I found a few matches. Say the number or the provider name and city. The full list is in the chat.";
            return "Which of these is your profile? Tell me the number or name and I'll give you a direct claim link:\n\n{$list_text}\n\nIf your profile isn't listed here, tell me your name and city and I'll search for it.";
        }

        // ── Claim-flow continuation: user gave name/city after "tell me your name and city" ──
        // In provider_owner_mode, the user is responding to Mia's question. Extract the
        // name/city and search for their profile so we can give a direct claim link.
        $is_claim_context = !empty($mode_context['provider_owner_mode']) &&
            in_array($mode_context['provider_owner_mode'], array(
                MyHH_Mia_Intent_Engine::INTENT_PROVIDER_CLAIM,
                MyHH_Mia_Intent_Engine::INTENT_PROVIDER_OWNER,
            ), true);
        if (!$is_claim_context && ($has_claim_candidates || ($is_initial_claim_request && $grok_provider_name !== ''))) {
            $is_claim_context = true;
        }

        // ── Name-match against recent_providers BEFORE disambiguation ──
        // When the user says "My name is Ashley Almon" and ASHLEY ALMON is in
        // recent_providers, go directly to the single-provider claim link
        // instead of asking "which of these is your profile?".
        // (Debug log 1777280880936: user named a previously-shown provider
        // but the code fell through to disambiguation or empty-result paths.)
        $name_match_provider = null;
        if ($is_claim_context && !empty($providers)) {
            // Extract the name portion from the message using the same patterns
            // as lookup_claim_provider_from_message.
            $extracted_name = '';
            if (preg_match('/\b(?:my\s+name\s+is|i\'?m)\s+(.+?)(?:\s+(?:and\s+)?(?:i(?:\'?m|\s+am)\s+)?(?:in|at|from)\s+|$)/i', $msg, $nm)) {
                $extracted_name = trim(preg_replace('/[.,;:!?]+\s*$/', '', $nm[1]));
            }
            if (empty($extracted_name) && preg_match('/\b(?:practice|business|provider|clinic)\s+name\s+is\s+(.+?)(?:\s+(?:in|at|from)\s+|$)/i', $msg, $nm)) {
                $extracted_name = trim(preg_replace('/[.,;:!?]+\s*$/', '', $nm[1]));
            }
            // ── Leading name before claim phrase ──
            // "Felicia I need to claim my profile" → extract "Felicia"
            // "Felicia Alston is my profile" → extract "Felicia Alston"
            // (Debug log 1777505821549: "Felicia I need to claim my profile"
            // didn't match because no "my name is" pattern existed for it.)
            if (empty($extracted_name) && preg_match('/^([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s+I\s+(?:need\s+to\s+)?claim\b/i', $msg, $nm)) {
                $extracted_name = trim($nm[1]);
            }
            if (empty($extracted_name) && preg_match('/^([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s+(?:is\s+)?(?:my|the)\s+(?:profile|listing|account|practice)\b/i', $msg, $nm)) {
                $extracted_name = trim($nm[1]);
            }
            if (!empty($extracted_name)) {
                $name_lower = strtolower($extracted_name);
                foreach ($providers as $p) {
                    $provider_name_lower = strtolower(trim($p['name'] ?? ''));
                    // Exact or contains match (e.g. "ashley almon" in "ashley almon")
                    if ($provider_name_lower === $name_lower || strpos($provider_name_lower, $name_lower) !== false || strpos($name_lower, $provider_name_lower) !== false) {
                        $name_match_provider = $p;
                        break;
                    }
                }
                // ── First-name fuzzy match for ASR misrecognition ──
                // When the extracted name doesn't match any provider exactly or
                // as a substring, try matching just the first name against the
                // first name of each provider.  This handles ASR errors like
                // "Felicia Alison" → "Felicia Alston" where the last name was
                // misrecognized but the first name is correct.
                // (Debug log 1777505821549: "Felicia Alison is my profile" didn't
                // match "Felicia Alston" because the full name wasn't a substring.)
                if (!$name_match_provider) {
                    // Strip leading/trailing punctuation that ASR often inserts —
                    // "Tanya." should still match "Tanya" as a first name.
                    $first_name = strtolower(trim(preg_split('/\s+/', $extracted_name)[0]));
                    $first_name = preg_replace('/^[^a-z\-\']+|[^a-z\-\']+$/i', '', $first_name);
                    if ($first_name && strlen($first_name) >= 3) {
                        foreach ($providers as $p) {
                            $provider_first = strtolower(trim(preg_split('/\s+/', $p['name'] ?? '')[0]));
                            $provider_first = preg_replace('/^[^a-z\-\']+|[^a-z\-\']+$/i', '', $provider_first);
                            if ($provider_first === $first_name) {
                                $name_match_provider = $p;
                                break;
                            }
                        }
                    }
                }
            }
        }

        // ── Ordinal / deictic selection in claim context ──
        // "the first one", "number 1", "the third", "that one", "1" — the user
        // is picking from the numbered list we just showed them.  Resolve the
        // reference to a specific provider before any name-search or
        // disambiguation logic runs.
        // (Requested: user should be able to say "the first one" or "third one"
        // instead of spelling out the provider name.)
        $ordinal_match_provider = null;
        if ($is_claim_context && !empty($providers)) {
            $ordinal_idx = 0;
            // "number 1", "number one", "#2", "the third one", "1st one", "the first", "second"
            if (preg_match('/\b(?:number\s*|#)\s*(\d+)\b/i', $msg, $om) ||
                preg_match('/\b(\d)(?:st|nd|rd|th)\s+(?:one|profile|listing)?\b/i', $msg, $om)) {
                $ordinal_idx = (int) $om[1];
            } elseif (preg_match('/\b(?:number|option|profile|listing)\s+(one|two|three|four|five|six|seven|eight|nine|ten)\b/i', $msg, $om)) {
                $num_map = array('one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10);
                $ordinal_idx = isset($num_map[strtolower($om[1])]) ? $num_map[strtolower($om[1])] : 0;
            } elseif (preg_match('/\b(?:the\s+)?(first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|1st|2nd|3rd|4th|5th|6th|7th|8th|9th|10th|sartre)\b/i', $msg, $om)) {
                $num_map = array('first' => 1, 'second' => 2, 'third' => 3, 'fourth' => 4, 'fifth' => 5, 'sixth' => 6, 'seventh' => 7, 'eighth' => 8, 'ninth' => 9, 'tenth' => 10, '1st' => 1, '2nd' => 2, '3rd' => 3, '4th' => 4, '5th' => 5, '6th' => 6, '7th' => 7, '8th' => 8, '9th' => 9, '10th' => 10, 'sartre' => 3);
                $ordinal_idx = isset($num_map[strtolower($om[1])]) ? $num_map[strtolower($om[1])] : 0;
            } elseif (preg_match('/\b(?:that\s+one|this\s+one|the\s+one|that\s+profile|this\s+profile|that\s+listing|this\s+listing|this\s+account|that\s+account)\b/i', $msg)) {
                // Deictic reference without ordinal — use last_provider_focus if
                // available, otherwise default to first provider.
                // (Debug log 1777420050565: "I need to claim this profile" after
                // asking about the 3rd provider resolved to the 1st instead.)
                $focus_id = absint($prior_context['last_provider_focus'] ?? 0);
                if ($focus_id > 0) {
                    foreach ($providers as $pi => $p) {
                        if (absint($p['id'] ?? 0) === $focus_id) {
                            $ordinal_idx = $pi + 1;
                            break;
                        }
                    }
                }
                if ($ordinal_idx < 1) {
                    $ordinal_idx = 1;
                }
            }
            if ($ordinal_idx >= 1 && $ordinal_idx <= count($providers)) {
                $ordinal_match_provider = $providers[$ordinal_idx - 1];
            }
        }

        // ── Claim-flow name search: always search when user gives a name ──
        // In claim context, if the user provides a name ("My name is Ashley Almon"),
        // search for it via WP_Query regardless of whether recent_providers is
        // populated.  The recent list might not contain the named provider (e.g.
        // a Charlotte search returned 4 random providers that didn't include
        // Ashley Almon).  Without this, the disambiguation path just re-shows
        // the same stale provider list.
        // (Debug log 1777280880936, browser test: "My name is Ashley Almon" after
        // Charlotte search → just re-listed the 4 Charlotte providers.)
        $claim_name_searched = false;
        if ($is_claim_context && empty($name_match_provider) && empty($ordinal_match_provider)) {
            // ── Grok-first gating ──
            // If Grok extracted a provider_name from the message, run the search.
            // Otherwise fall back to a minimal regex heuristic (only "my name is X"
            // / "practice name is X").  The narrow short-phrase heuristic that
            // used to live here was a regex sprawl liability — Grok handles
            // ASR garble, corrections, fragments, and mixed Spanish/English.
            $grok_provider_name_local = $grok_provider_name;
            $grok_city_local = $this->get_grok_extracted_city($result);
            $has_name_pattern = ($grok_provider_name_local !== '') ||
                preg_match('/\b(?:my\s+name\s+is|i\'?m)\b/i', $msg) ||
                preg_match('/\b(?:practice|business|provider|clinic)\s+name\s+is\b/i', $msg);
            if ($has_name_pattern) {
                $claim_match = $this->lookup_claim_provider_from_message($msg, $prior_context, $grok_provider_name_local, $grok_city_local);
                $claim_name_searched = true;
                if (!empty($claim_match)) {
                    // If WP_Query found exactly 1 provider, use it directly
                    if (count($claim_match) === 1) {
                        $name_match_provider = $claim_match[0];
                    } elseif (count($claim_match) >= 2) {
                        // Multiple matches from WP_Query — replace the provider list
                        // with the name-matched results for disambiguation below
                        $providers = $claim_match;
                    }
                } else {
                    // WP_Query found 0 results for the given name.
                    // Clear the stale recent_providers so the "couldn't find"
                    // message is shown instead of re-listing old providers.
                    $providers = array();
                }
            }
        }

        // ── Specialty-based narrowing in claim context ──
        // User is answering "which one?" by naming a specialty (e.g. "anxiety" or "the one that does couples therapy")
        if ($is_claim_context && !empty($prior_context['claim_disambiguation_providers'])) {
            $disamb_providers = $prior_context['claim_disambiguation_providers'];
            $narrowed = $this->narrow_claim_by_specialty($msg, $disamb_providers);
            if (!empty($narrowed) && count($narrowed) < count($disamb_providers)) {
                if (count($narrowed) === 1) {
                    // Found the one match
                    $p = $narrowed[0];
                    $name = $p['name'] ?? 'this profile';
                    $profile_url = $this->sanitize_provider_url($p['url'] ?? '#');
                    $claim_url = $this->build_claim_url($p['id'] ?? 0);
                    $city = trim((string) ($p['city'] ?? ''));
                    $where = $city ? " in {$city}" : '';
                    if ($lang === 'es') {
                        return "Ese es [**{$name}**]({$profile_url}). Si es tu perfil, usa este enlace: [**Claim Your Free Profile**]({$claim_url}). El reclamo es gratis y rapido.";
                    }
                    return "That would be [**{$name}**]({$profile_url}){$where}. If that's your profile, use this direct link: [**Claim Your Free Profile**]({$claim_url}). Claiming is free and takes about 2 minutes.";
                }
                // Still multiple — show narrowed list
                $providers = $narrowed;
                // Fall through to disambiguation below
            }
        }

        // ── Name matched a recent provider or WP_Query: direct claim link ──
        // The user said "My name is Ashley Almon" and we found ASHLEY ALMON —
        // give the direct claim link without disambiguation.
        $direct_match_provider = $name_match_provider ?: $ordinal_match_provider;
        if ($direct_match_provider && !empty($direct_match_provider['id'])) {
            $p = $direct_match_provider;
            $this->_claim_disambiguation_providers = array($p);
            $name = $p['name'] ?? 'this profile';
            $profile_url = $this->sanitize_provider_url($p['url'] ?? '#');
            $claim_url = $this->build_claim_url($p['id'] ?? 0);
            $city = trim((string) ($p['city'] ?? ''));
            $where = $city ? " in {$city}" : '';
            $spec_label = '';
            $specs = array_filter(array_map('trim', ($p['specialties'] ?? array())));
            if (!empty($specs)) {
                $spec_label = ' (' . implode(', ', array_slice($specs, 0, 3)) . ')';
            }
            if ($lang === 'es') {
                return "Encontre [**{$name}**]({$profile_url}){$spec_label}. Si es tu perfil, usa este enlace directo: [**Claim Your Free Profile**]({$claim_url}).\n\n" .
                    "El reclamo es **gratis**. Te pedira nombre, email y un enlace de prueba. Si no es tu perfil, dime el nombre correcto.";
            }
            return "I found [**{$name}**]({$profile_url}){$where}{$spec_label}. If that's your profile, use this direct link: [**Claim Your Free Profile**]({$claim_url}).\n\n" .
                "Claiming is **free**. The form asks for your name, email, and a proof link like your website or license page. If that's not your profile, tell me the correct name and city.";
        }

        if ($is_claim_context && empty($providers) && $this->looks_like_claim_list_selection($msg)) {
            if ($lang === 'es') {
                return "Escuche que elegiste un numero, pero ya no tengo esa lista disponible. Dime el nombre del proveedor o practica y la ciudad, y te busco el enlace directo de reclamo.";
            }
            return "I heard you pick a number, but I no longer have that list available. Tell me the provider or practice name and city, and I'll find the direct claim link.";
        }

        // ── "Yes" confirmation after "did you mean" suggestions ──
        // When Mia showed "Did you mean: A, B, C?" and the user says "yes",
        // confirm the first (best-match) suggestion instead of showing the
        // disambiguation list again. The user's "yes" means "yes, the first one."
        if ($is_claim_context && count($providers) >= 2 &&
            preg_match('/^\s*(?:yes|yeah|yep|correct|right|that\'?s?\s*right|sure|ok|okay)\s*[!.?]*\s*$/i', trim($msg)) &&
            !empty($prior_context['claim_disambiguation_providers'])) {
            $p = $providers[0];
            $name = $p['name'] ?? 'this profile';
            $profile_url = $this->sanitize_provider_url($p['url'] ?? '#');
            $claim_url = $this->build_claim_url($p['id'] ?? 0);
            $city = trim((string) ($p['city'] ?? ''));
            $where = $city ? " in {$city}" : '';
            $spec_label = '';
            $specs = array_filter(array_map('trim', ($p['specialties'] ?? array())));
            if (!empty($specs)) {
                $spec_label = ' (' . implode(', ', array_slice($specs, 0, 3)) . ')';
            }
            if ($lang === 'es') {
                return "Perfecto. Si [**{$name}**]({$profile_url}) es tu perfil, usa este enlace: [**Claim Your Free Profile**]({$claim_url}).\n\n" .
                    "El reclamo es gratis y rapido. Te pedira tu nombre, email y un enlace de prueba. Si ese no es tu perfil, dime el nombre correcto y la ciudad.";
            }
            return "Great. If [**{$name}**]({$profile_url}){$where}{$spec_label} is your profile, use this direct link: [**Claim Your Free Profile**]({$claim_url}).\n\n" .
                "Claiming is **free** and takes about 2 minutes. The form asks for your name, email, and a proof link like your website or license page. If that's not your profile, tell me the correct name and city.";
        }

        // ── Stale-provider guard ──
        // When the user's message is clearly about a different provider than
        // the ones in $providers (e.g. they said a name that doesn't match
        // any listed provider, and it's not an ordinal/yes selection), the
        // $providers array is stale from a previous search and should not be
        // used for disambiguation.  Clear it so the "couldn't find" path
        // fires instead of showing an irrelevant provider list.
        // (Debug log 1777325005623: user said "Ashley almond" but $providers
        // still held the Adams/Charlotte results from the previous turn,
        // producing "Which of these is your profile?" with unrelated names.)
        if ($is_claim_context && !empty($providers) && empty($name_match_provider) && empty($ordinal_match_provider)) {
            $msg_references_provider = false;
            foreach ($providers as $p) {
                $pname = strtolower(trim($p['name'] ?? ''));
                if ($pname && (strpos($msg, $pname) !== false || strpos($pname, $msg) !== false)) {
                    $msg_references_provider = true;
                    break;
                }
                // First-name match: "felicia" in message matches "felicia alston"
                // (Debug log 1777505821549: "Felicia I need to claim my profile"
                // didn't match "Felicia Alston" because the full name wasn't a
                // substring, but the first name clearly references the provider.)
                $provider_first = strtolower(trim(preg_split('/\s+/', $pname)[0]));
                if ($provider_first && strlen($provider_first) >= 3 && strpos($msg, $provider_first) !== false) {
                    $msg_references_provider = true;
                    break;
                }
            }
            // Also keep providers if the message is a short selection phrase
            // (ordinal, yes, "that one", etc.) — those are clearly referencing
            // the list we just showed.
            $is_list_selection = $this->looks_like_claim_list_selection($msg) ||
                preg_match('/^\s*(?:yes|yeah|yep|correct|right|that\'?s?\s*right|sure|ok|okay)\s*[!.?]*\s*$/i', trim($msg));

            // Also keep providers if the message is a claim-continuation fragment
            // ("claim my", "umm I'm", "well so") — these are mid-thought utterances
            // from the user, NOT a topic switch.  Clearing the list here forces
            // Mia back to the generic intro and loses all disambiguation context.
            // (Debug CSV 1778547929060 final turn: "claim my" cleared the 4-provider
            //  list mid-flow and Mia restarted the claim conversation.)
            $is_claim_fragment = (str_word_count(trim($msg)) <= 3 && preg_match('/\b(?:claim|my|profile|listing|i\'?m|um+|uh+|hmm|well|so|like)\b/i', $msg)) ||
                preg_match('/^\s*(?:umm?|uhh?|hmm?|er+|ah+|well|so|like|i\s+mean|kind\s+of|sort\s+of)\b/i', $msg);

            if (!$msg_references_provider && !$is_list_selection && !$is_claim_fragment) {
                // The user is talking about a different provider — try a name
                // search with the raw message.  If that also finds nothing,
                // $providers will be empty and the "couldn't find" path fires.
                $claim_match = $this->lookup_claim_provider_from_message($message, $prior_context, $this->get_grok_extracted_provider_name($result), $this->get_grok_extracted_city($result));
                if (!empty($claim_match)) {
                    $providers = $claim_match;
                } else {
                    $providers = array();
                }
            }
        }

        // ── Multiple providers found ──
        // Two distinct cases:
        //   1. Same-name collision (2+ providers with the same name) → disambiguate by specialty/practice
        //   2. Different names (user's search returned multiple unrelated providers) → ask which one is theirs
        if ($is_claim_context && count($providers) >= 2) {
            // Detect what's shared so we can phrase the question naturally.
            // shared_first_name = "Tanya" if all providers start with "Tanya"
            $first_names = array();
            foreach ($providers as $p) {
                $parts = preg_split('/\s+/', strtolower(trim($p['name'] ?? '')));
                $first_names[] = $parts[0] ?? '';
            }
            $first_names_uniq = array_values(array_unique(array_filter($first_names)));
            $shared_first_name = (count($first_names_uniq) === 1) ? $first_names_uniq[0] : '';
            $same_full_name = true;
            $first_full = strtolower(trim($providers[0]['name'] ?? ''));
            foreach ($providers as $p) {
                if (strtolower(trim($p['name'] ?? '')) !== $first_full) {
                    $same_full_name = false;
                    break;
                }
            }

            // Build the numbered list (still shown in chat for visual reference).
            $items = array();
            foreach (array_slice($providers, 0, 5) as $i => $p) {
                $name        = $p['name'] ?? 'Unknown';
                $profile_url = $this->sanitize_provider_url($p['url'] ?? '#');
                $claim_url   = $this->build_claim_url($p['id'] ?? 0);
                $parts = array();
                $city = trim((string) ($p['city'] ?? ''));
                if ($city) { $parts[] = $city; }
                $practice = trim((string) ($p['practice_name'] ?? ''));
                if ($practice) { $parts[] = $practice; }
                $pcat = trim((string) ($p['primary_category'] ?? ''));
                if ($pcat) { $parts[] = $pcat; }
                $specs = array_filter(array_map('trim', ($p['specialties'] ?? array())));
                if (!empty($specs)) {
                    $specs_display = array_slice($specs, 0, 3);
                    $parts[] = implode(', ', $specs_display);
                }
                $line = ($i + 1) . ". [**{$name}**]({$profile_url})";
                if (!empty($parts)) { $line .= ' — ' . implode(' | ', $parts); }
                $line .= " · [Claim]({$claim_url})";
                $items[] = $line;
            }
            $list_text = implode("\n", $items);

            // Store disambiguation list in context for specialty-based narrowing on next turn
            $this->_claim_disambiguation_providers = array_slice($providers, 0, 5);

            // ── Build a short, distinguishing spoken prompt ──
            // For 2 providers: "Tanya Allen-Burris in Charlotte or Tanya Smith in Raleigh — which one?"
            // For 3+: lead with a count and one distinguishing detail per option.
            $top_n = array_slice($providers, 0, 3);
            $distinguishers = array();
            foreach ($top_n as $p) {
                $pname = $p['name'] ?? '';
                $pcity = trim((string) ($p['city'] ?? ''));
                $ppractice = trim((string) ($p['practice_name'] ?? ''));
                // Prefer city; fall back to practice; else specialty
                $detail = $pcity;
                if (empty($detail) && $ppractice) { $detail = $ppractice; }
                if (empty($detail)) {
                    $ps = array_filter(array_map('trim', ($p['specialties'] ?? array())));
                    if (!empty($ps)) { $detail = $ps[0]; }
                }
                // For same-full-name collisions, the name itself is shared — lead with the detail.
                // For same-first-name (or different names), include the full provider name.
                if ($same_full_name) {
                    $distinguishers[] = $detail ?: $pname;
                } elseif ($shared_first_name && $detail) {
                    // Shorten "TANYA ALLEN-BURRIS in Charlotte" to "Tanya Allen-Burris in Charlotte"
                    $distinguishers[] = $this->prettify_provider_name($pname) . " in {$detail}";
                } else {
                    $distinguishers[] = $this->prettify_provider_name($pname) . ($detail ? " in {$detail}" : '');
                }
            }

            if ($same_full_name) {
                // True same-name collision — distinguish by detail only
                $spoken_options = $this->join_human_list($distinguishers);
                if ($lang === 'es') {
                    $this->_conversational_speech = "Encontre varios con ese nombre. Es el de " . $spoken_options . "? Di el numero o el lugar.";
                    return "Encontre varios perfiles con ese nombre. Dime cual es el tuyo (numero, especialidad o nombre de practica):\n\n{$list_text}\n\nPor ejemplo: \"el numero 1\" o \"el de ansiedad\".";
                }
                $this->_conversational_speech = "I found a few with that name. Is it the one in " . $spoken_options . "? Say the number or the city.";
                return "I found multiple profiles with that name. Which one is yours? Say the number, specialty, or practice name:\n\n{$list_text}\n\nFor example: \"number 1\" or \"the one for anxiety\".";
            }

            // Same-first-name OR different names — read short distinguishing options
            $spoken_options = $this->join_human_list($distinguishers);
            if ($lang === 'es') {
                $this->_conversational_speech = "Encontre " . count($providers) . ". " . $spoken_options . ". Cual es el tuyo?";
                return "Cual de estos es tu perfil? Dime el numero o el nombre:\n\n{$list_text}\n\nSi tu perfil no esta en la lista, dime tu nombre y ciudad y lo busco.";
            }
            $this->_conversational_speech = "I found " . count($providers) . " matches: " . $spoken_options . ". Which one is yours?";
            return "Which of these is your profile? Tell me the number or name:\n\n{$list_text}\n\nIf your profile isn't listed here, tell me your name and city and I'll search for it.";
        }

        // ── Single provider found: direct claim link ──
        // (if we got exactly 1 result from lookup, skip the reference-matching
        //  logic below and go straight to the direct claim link)
        if ($is_claim_context && count($providers) === 1) {
            $p = $providers[0];
            $this->_claim_disambiguation_providers = array($p);
            $name = $p['name'] ?? 'this profile';
            $profile_url = $this->sanitize_provider_url($p['url'] ?? '#');
            $claim_url = $this->build_claim_url($p['id'] ?? 0);
            $city = trim((string) ($p['city'] ?? ''));
            $where = $city ? " in {$city}" : '';
            $spec_label = '';
            $specs = array_filter(array_map('trim', ($p['specialties'] ?? array())));
            if (!empty($specs)) {
                $spec_label = ' (' . implode(', ', array_slice($specs, 0, 3)) . ')';
            }

            if ($lang === 'es') {
                return "Encontre [**{$name}**]({$profile_url}){$spec_label}. Si es tu perfil, usa este enlace directo: [**Claim Your Free Profile**]({$claim_url}).\n\n" .
                    "El reclamo es **gratis**. Te pedira nombre, email y un enlace de prueba. Si no es tu perfil, dime el nombre correcto.";
            }
            return "I found [**{$name}**]({$profile_url}){$where}{$spec_label}. If that's your profile, use this direct link: [**Claim Your Free Profile**]({$claim_url}).\n\n" .
                "Claiming is **free**. The form asks for your name, email, and a proof link like your website or license page. If that's not your profile, tell me the correct name and city.";
        }

        // ── Name given but no results found in claim context ──
        // The user answered with a name/city but the directory didn't match.
        // Don't loop back to generic instructions — acknowledge and offer alternatives.
        // If fuzzy name suggestions are available, show "did you mean" options.
        if ($is_claim_context && empty($providers) && !preg_match('/^\s*(?:yes|yeah|yep|correct|right|that\'?s?\s*right|sure|ok|okay)\s*[!.?]*\s*$/i', trim($msg))) {
            // ── Did-you-mean trigger: Grok-first ──
            // Fire fuzzy "Did you mean" only when the user actually attempted a name.
            // Trust Grok's entities.provider_name; fall back to a single narrow regex
            // ("my name is X" / "Dr./Mr./Mrs. X") when Grok is unavailable.
            // The old short-phrase heuristic with a 20-word exclusion list was the
            // regex-sprawl bug we're explicitly removing — Grok knows what's a name.
            $grok_name_for_didyoumean = $this->get_grok_extracted_provider_name($result);
            $looks_like_name = ($grok_name_for_didyoumean !== '') ||
                preg_match('/\b(?:my\s+name\s+is|i\'?m\s+(?:dr|mr|mrs|ms)\.?|dr\.|mr\.|mrs\.|ms\.)\b/i', $msg);

            if ($looks_like_name) {
                // Build "did you mean" suggestions from fuzzy matching results
                $suggestions = $this->_claim_name_suggestions;
                $suggestion_text = '';
                if (!empty($suggestions)) {
                    $suggestion_items = array();
                    foreach ($suggestions as $s) {
                        $s_name        = $s['name'] ?? 'Unknown';
                        $s_profile_url = $this->sanitize_provider_url($s['url'] ?? '#');
                        $s_claim_url   = $this->build_claim_url($s['id'] ?? 0);
                        // Name → profile page; separate Claim link → claim form
                        $suggestion_items[] = "[**{$s_name}**]({$s_profile_url}) [Claim]({$s_claim_url})";
                    }
                    $suggestion_text = implode(', ', $suggestion_items);
                    // Store suggestions in disambiguation context for next-turn selection
                    $this->_claim_disambiguation_providers = array_slice($suggestions, 0, 3);
                }

                if ($lang === 'es') {
                    if (!empty($suggestion_text)) {
                        return "No encontre un perfil con ese nombre exacto. Quizas quisiste decir: {$suggestion_text}?

" .
                            "Si ninguno es correcto, puedes:\n" .
                            "- Intentar con el **nombre completo** y la **ciudad** (por ejemplo: \"Maria Garcia en Raleigh\")\n" .
                            "- Buscar manualmente en [Providers](/listings/)\n" .
                            "- Crear un perfil nuevo en [Add Your Practice](/add-your-practice/)";
                    }
                    return "No encontre un perfil con ese nombre en el directorio. Puede que este escrito diferente o que aun no este listado.\n\n" .
                        "Puedes:\n" .
                        "- Intentar con el **nombre completo** y la **ciudad** (por ejemplo: \"Maria Garcia en Raleigh\")\n" .
                        "- Buscar manualmente en [Providers](/listings/)\n" .
                        "- Crear un perfil nuevo en [Add Your Practice](/add-your-practice/)";
                }

                if (!empty($suggestion_text)) {
                    // Short spoken prompt — don't read out full names & links over TTS.
                    // The full "Did you mean" list is visible in the chat widget.
                    $first_suggestion_name = $suggestions[0]['name'] ?? 'a similar name';
                    $this->_conversational_speech = "I couldn't find that exact name. Did you mean {$first_suggestion_name}? Say yes or give me the full name and city.";
                    return "I couldn't find a profile with that exact name. Did you mean: {$suggestion_text}?\n\n" .
                        "If none of those are correct, you can:\n" .
                        "- Try the **full name** and **city** (for example: \"Dr. John Smith in Raleigh\")\n" .
                        "- Search manually on the [Providers](/listings/) page\n" .
                        "- Create a new profile at [Add Your Practice](/add-your-practice/)";
                }
                $this->_conversational_speech = "I couldn't find that name in the directory. Try the full name and city — for example, Dr. John Smith in Raleigh.";
                return "I couldn't find a profile with that name in the directory. It might be spelled differently or not yet listed.\n\n" .
                    "You can:\n" .
                    "- Try the **full name** and **city** (for example: \"Dr. John Smith in Raleigh\")\n" .
                    "- Search manually on the [Providers](/listings/) page\n" .
                    "- Create a new profile at [Add Your Practice](/add-your-practice/)";
            }
        }

        // Handle "yes" / "yeah" confirmation in claim context
        if ($is_claim_context && preg_match('/^\s*(?:yes|yeah|yep|correct|right|that\'?s?\s*right|sure|ok|okay)\s*[!.?]*\s*$/i', trim($msg))) {
            if (!empty($providers)) {
                $referenced_provider = $providers[0];
                $name = $referenced_provider['name'] ?? 'this profile';
                $profile_url = $this->sanitize_provider_url($referenced_provider['url'] ?? '#');
                $claim_url = $this->build_claim_url($referenced_provider['id'] ?? 0);
                $city = trim((string) ($referenced_provider['city'] ?? ''));
                $where = $city ? " in {$city}" : '';

                if ($lang === 'es') {
                    return "Perfecto. Si [**{$name}**]({$profile_url}) es tu perfil, usa este enlace: [**Claim Your Free Profile**]({$claim_url}).\n\n" .
                        "El reclamo es gratis y rapido. Te pedira tu nombre, email y un enlace de prueba. Si ese no es tu perfil, dime el nombre correcto y la ciudad.";
                }
                return "Great. If [**{$name}**]({$profile_url}){$where} is your profile, use this direct link: [**Claim Your Free Profile**]({$claim_url}).\n\n" .
                    "Claiming is free and takes about 2 minutes. The form asks for your name, email, and a proof link like your website or license page. If that's not your profile, tell me the correct name and city.";
            }
            // "yes" but no providers yet — ask for name/city
            if ($lang === 'es') {
                return "Perfecto. Para encontrar tu perfil, necesito el nombre del proveedor o practica y la ciudad. Por ejemplo: \"Mi nombre es Maria Garcia en Raleigh\".";
            }
            return "Great. To find your profile, I need your provider or practice name and city. For example: \"My name is Dr. Smith in Raleigh\".";
        }

        $explicit_provider_reference = false;
        foreach ($providers as $provider) {
            if (!empty($provider['name']) && strpos($msg, strtolower($provider['name'])) !== false) {
                $explicit_provider_reference = true;
                break;
            }
        }
        if (!$explicit_provider_reference && preg_match('/\b(first|1st|second|2nd|third|3rd|last one|the last one)\b/i', $msg)) {
            $explicit_provider_reference = true;
        }
        $strong_deictic_reference = preg_match('/\b(that one|this one|the one|that provider|this provider|the provider|that profile|this profile|the profile|that listing|this listing|the listing|this account|that account)\b/i', $msg);
        $referenced_provider = ($explicit_provider_reference || $strong_deictic_reference)
            ? $this->resolve_referenced_provider($msg, $providers, $prior_context)
            : null;
        $used_pronoun_fallback = false;
        if ($referenced_provider && !$explicit_provider_reference && $strong_deictic_reference) {
            $used_pronoun_fallback = true;
        }
        if (!$referenced_provider && !empty($providers) && $strong_deictic_reference) {
            $referenced_provider = $providers[0];
            $used_pronoun_fallback = true;
        }

        if ($referenced_provider && !empty($referenced_provider['id'])) {
            $name = $referenced_provider['name'] ?? 'this profile';
            $profile_url = $this->sanitize_provider_url($referenced_provider['url'] ?? '#');
            $claim_url = $this->build_claim_url($referenced_provider['id']);
            $city = trim((string) ($referenced_provider['city'] ?? ''));
            $where = $city ? " in {$city}" : '';
            $where_es = $city ? " en {$city}" : '';

            if ($used_pronoun_fallback) {
                if ($lang === 'es') {
                    return "¿Te refieres a [**{$name}**]({$profile_url}){$where_es}? Para evitar reclamar el perfil equivocado, confirma el nombre del proveedor o la practica y la ciudad.\n\n" .
                        "Puedes decir **\"si, reclamar {$name}\"**, o buscar el perfil exacto en [Providers](/listings/) y usar **\"Claim Your Free Profile\"** cuando estes seguro.";
                }

                return "Do you mean [**{$name}**]({$profile_url}){$where}? To avoid sending you to the wrong claim form, confirm the provider or practice name and city first.\n\n" .
                    "You can say **\"yes, claim {$name}\"**, or search the exact profile on [Providers](/listings/) and use **\"Claim Your Free Profile\"** when you are sure it is yours.";
            }

            if ($lang === 'es') {
                return "Si [**{$name}**]({$profile_url}) es tu perfil, usa este enlace directo: [**Claim Your Free Profile**]({$claim_url}).\n\n" .
                    "El reclamo es **gratis**. Te pedira tu nombre, email y un enlace de prueba, como tu sitio web, perfil profesional o pagina de licencia. Despues recibiras confirmacion por email y podras mantener actualizados tus datos, especialidades y contacto.\n\n" .
                    "Si ese no es tu perfil, busca tu nombre en [Providers](/listings/) o crea uno nuevo en [Add Your Practice](/add-your-practice/).";
            }

            return "If [**{$name}**]({$profile_url}) is your profile, use this direct link: [**Claim Your Free Profile**]({$claim_url}).\n\n" .
                "Claiming is **free**. The form asks for your name, email, and a proof link, such as your website, professional profile, or license page. After submission, you'll receive an email confirmation and can keep your details, specialties, and contact info updated.\n\n" .
                "If that is not your profile, search your name on [Providers](/listings/) or create a new profile at [Add Your Practice](/add-your-practice/).";
        }

        if ($lang === 'es') {
            return "Puedo ayudarte a reclamar tu perfil — es gratis y toma solo 2 minutos.\n\n" .
                "Dime el **nombre del proveedor o practica** y la **ciudad**, y busco tu perfil para darte el enlace directo. Por ejemplo: \"Maria Garcia en Raleigh\".\n\n" .
                "Si prefieres hacerlo tu mismo, busca tu nombre en [Providers](/listings/) y haz clic en **\"Claim Your Free Profile\"**. Si no encuentras tu perfil, crealo aqui: [Add Your Practice](/add-your-practice/).";
        }

        return "I can help you claim your profile — it's free and takes about 2 minutes.\n\n" .
            "Tell me your **provider or practice name** and **city**, and I'll search for your profile to give you a direct claim link. For example: \"Dr. Smith in Raleigh\".\n\n" .
            "If you prefer to do it yourself, search your name on [Providers](/listings/) and click **\"Claim Your Free Profile\"**. If you can't find your profile, create one here: [Add Your Practice](/add-your-practice/).";
    }
    
    /**
     * Look up providers by name/city extracted from a claim-flow follow-up message.
     * When the user says "my name is Dr. Smith in Raleigh" after Mia asks for their
     * practice name and city, this searches the directory for a matching provider
     * so we can give them a direct claim link instead of generic instructions.
     */
    /**
     * Fuzzy-match a provider name against all published providers using
     * Levenshtein distance and metaphone (phonetic) similarity.
     *
     * Called when an exact WP_Query search for the user's name returns 0 results.
     * Returns an array of provider data arrays (same shape as format_provider_result)
     * sorted by similarity score (best first), up to $limit results.
     *
     * @param string $search_name  The name the user provided (e.g. "Ashely Almon").
     * @param string $search_city  Optional city to narrow the candidate pool.
     * @param int    $limit        Max number of suggestions to return (default 3).
     * @return array  Array of provider data arrays with an added 'similarity_score' key.
     */
    private function fuzzy_match_provider_names($search_name, $search_city = '', $limit = 3) {
        if (empty($search_name) || strlen(trim($search_name)) < 2) {
            return array();
        }

        $search_name_lower = strtolower(trim($search_name));
        $search_name_meta  = metaphone($search_name_lower);
        $search_words      = array_filter(preg_split('/\s+/', $search_name_lower));

        // Build candidate pool from all published providers (with optional city filter)
        $args = array(
            'post_type'      => 'gd_place',
            'post_status'    => 'publish',
            'posts_per_page' => 200,  // broad pool for fuzzy matching
            'no_found_rows'  => true,
            'fields'         => 'ids', // only IDs for performance
        );

        if (!empty($search_city)) {
            $city_term = get_term_by('name', $search_city, 'city');
            if ($city_term && !is_wp_error($city_term)) {
                $args['tax_query'] = array(array(
                    'taxonomy' => 'city',
                    'field'    => 'term_id',
                    'terms'    => $city_term->term_id,
                ));
            } else {
                // Try partial city match
                $city_first_word = preg_split('/\s+/', $search_city);
                $city_first_word = $city_first_word[0];
                $possible_terms = get_terms(array(
                    'taxonomy'   => 'city',
                    'name__like' => $city_first_word,
                    'hide_empty' => false,
                    'number'     => 5,
                ));
                if (!empty($possible_terms) && !is_wp_error($possible_terms)) {
                    $args['tax_query'] = array(array(
                        'taxonomy' => 'city',
                        'field'    => 'term_id',
                        'terms'    => $possible_terms[0]->term_id,
                    ));
                }
            }
        }

        $query = new \WP_Query($args);
        if (empty($query->posts)) {
            return array();
        }

        $scored = array();
        foreach ($query->posts as $pid) {
            $provider_name = get_the_title($pid);
            if (empty($provider_name)) continue;

            $provider_lower = strtolower(trim($provider_name));
            $provider_meta  = metaphone($provider_lower);
            $provider_words = array_filter(preg_split('/\s+/', $provider_lower));

            $score = 0;

            // 1. Exact match (shouldn't happen but just in case)
            if ($provider_lower === $search_name_lower) {
                $score = 1000;
            }
            // 2. Metaphone (phonetic) similarity
            elseif ($provider_meta === $search_name_meta) {
                $score = 800;
            }
            // 3. Both first and last name metaphones match (swapped or not)
            elseif (count($search_words) >= 2 && count($provider_words) >= 2) {
                $search_metas  = array_map('metaphone', $search_words);
                $provider_metas = array_map('metaphone', $provider_words);
                $meta_match_count = count(array_intersect($search_metas, $provider_metas));
                if ($meta_match_count >= 2) {
                    $score = 700;
                } elseif ($meta_match_count === 1) {
                    $score = 400;
                }
            }

            // 4. Levenshtein distance (character-level similarity)
            //    Only compute if we don't already have a high score
            if ($score < 700) {
                $lev_full = levenshtein($search_name_lower, $provider_lower);
                // Normalize: lower distance = better. Invert to a positive score.
                // Max realistic name length ~30 chars, so 30-lev gives 0..30 range → scale to 0..500
                $max_len = max(strlen($search_name_lower), strlen($provider_lower), 1);
                if ($lev_full <= $max_len && $lev_full <= 5) {
                    // Very close: 1-5 char edits → high score
                    $score = max($score, 600 - ($lev_full * 40));
                } elseif ($lev_full <= $max_len && $lev_full <= 10) {
                    // Moderate similarity
                    $score = max($score, 300 - ($lev_full * 20));
                }

                // 5. Per-word Levenshtein (catches first-name OR last-name misspellings)
                if (count($search_words) >= 2 && count($provider_words) >= 2 && $score < 500) {
                    $best_word_score = 0;
                    foreach ($search_words as $sw) {
                        foreach ($provider_words as $pw) {
                            $wl = levenshtein($sw, $pw);
                            $wmax = max(strlen($sw), strlen($pw), 1);
                            if ($wl <= 2 && $wl <= $wmax) {
                                $best_word_score = max($best_word_score, 250 - ($wl * 50));
                            } elseif ($wl <= 3 && $wl <= $wmax) {
                                $best_word_score = max($best_word_score, 150 - ($wl * 30));
                            }
                        }
                    }
                    $score = max($score, $best_word_score);
                }

                // 6. Contains match (one name contains the other)
                if ($score < 300) {
                    if (strpos($provider_lower, $search_name_lower) !== false ||
                        strpos($search_name_lower, $provider_lower) !== false) {
                        $score = max($score, 350);
                    }
                }
            }

            // Only keep candidates with meaningful similarity (score >= 150)
            if ($score >= 150) {
                $post = get_post($pid);
                $provider_data = $this->format_provider_result($post);
                if (!empty($provider_data)) {
                    $provider_data['similarity_score'] = $score;
                    $scored[] = $provider_data;
                }
            }
        }
        wp_reset_postdata();

        // Sort by similarity score descending
        usort($scored, function($a, $b) {
            return ($b['similarity_score'] ?? 0) - ($a['similarity_score'] ?? 0);
        });

        $results = array_slice($scored, 0, $limit);

        mia_debug_log('Chat', 'fuzzy_match_result', array(
            'search_name' => substr($search_name, 0, 60),
            'city'        => $search_city,
            'candidates'  => count($query->posts),
            'scored'      => count($scored),
            'returned'    => count($results),
        ));

        return $results;
    }

    private function lookup_claim_provider_from_message($msg, $prior_context = array(), $grok_name = '', $grok_city = '') {
        // Extract a possible provider name from the message
        $search_name = '';
        $search_city = '';

        $msg = trim(preg_replace('/^[,.;:\s]+/', '', (string) $msg));

        // ── Grok-first city extraction ──
        // Mirror the name-extraction pattern: trust Grok's entities.city when
        // available, fall back to the regex below only when Grok is unavailable.
        $grok_city = trim((string) $grok_city);
        if ($grok_city !== '' && strlen($grok_city) >= 2) {
            $search_city = $grok_city;
            mia_debug_log('Chat', 'claim_lookup_grok_city', array('city' => substr($grok_city, 0, 60)));
        }

        // ── Strip obvious ASR noise injections ──
        // Smart-assistant wake phrases and device commands often leak into the
        // mic stream and corrupt the user's actual utterance.  Remove them so
        // regex name extraction doesn't capture "you play set timer number one
        // number one Tanya" as the provider name.
        // (Debug CSV 1778550052245 T4: ASR returned "Tanya. You play set timer
        //  number one number one Tanya" — Grok's prompt handles it, but the
        //  regex fallback needs this defense too.)
        $msg = preg_replace('/\b(?:hey\s+siri|ok\s+google|hey\s+google|alexa|cortana)\b[\s,.]*/i', ' ', $msg);
        $msg = preg_replace('/\b(?:play|set|stop|pause|resume|turn\s+(?:on|off)|open|close|navigate\s+to|call|text|send)\s+(?:set\s+)?(?:timer|alarm|song|music|video|playlist|app|application)\b[\s,.]*/i', ' ', $msg);
        // Collapse immediate word repetitions (ASR jitter): "number one number one" → "number one"
        $msg = preg_replace('/\b(\w+(?:\s+\w+){0,2})\s+\1\b/i', '$1', $msg);
        $msg = preg_replace('/\s{2,}/', ' ', trim($msg));

        // ── Grok-first name extraction ──
        // The LLM is the right tool for parsing natural language: corrections
        // ("no I said Ashley Hamlin"), fragments ("umm Ashley"), self-intros
        // ("hi I'm Ashley from Charlotte"), etc.  Trust Grok's entities.provider_name
        // when available — this replaces the regex sprawl we were maintaining.
        $grok_name = trim((string) $grok_name);
        if (preg_match('/^(.+?)\s+(?:in|at|from|en)\s+([^,;!?]+)[.!?]*$/iu', $grok_name, $named_location)) {
            $grok_name = trim($named_location[1]);
            if ($search_city === '') $search_city = trim($named_location[2], " .!?");
        }
        if ($grok_name !== '' && strlen($grok_name) >= 2) {
            $search_name = $grok_name;
            mia_debug_log('Chat', 'claim_lookup_grok_name', array('name' => substr($grok_name, 0, 60)));
        }

        // ── Minimal regex safety net (only when Grok didn't extract a name) ──
        // Two patterns kept: "my name is X" (most common) and "practice name is X"
        // (different from personal name, can override).  Everything else — corrections,
        // fragments, ASR garble — is the LLM's responsibility, not regex's.
        // The stop class excludes "." when preceded by a single capital letter so
        // middle initials ("Dania M. Alshafei", "John A. Smith Jr.") stay intact.
        // Standalone "." (sentence-end) and "," ";" "!" "?" still terminate capture.
        // Note: (?-i:[A-Z]) disables case-insensitivity for the character class so
        // a middle-initial period (preceded by an uppercase letter like "M") is
        // NOT treated as a sentence end.  "Dania M. Alshafei" captures fully.
        if (empty($search_name) && preg_match('/\b(?:my\s+name\s+is|name\s+is|i\'?m)\s+(.+?)(?:\s+(?:and\s+)?(?:i(?:\'?m|\s+am)\s+)?(?:in|at|from)\s+|(?<!(?-i:[A-Z]))\.|[,;!?]|$)/i', $msg, $m)) {
            $search_name = trim($m[1]);
        }
        // "practice/business name is X" — explicit business-name signal can override
        // (only when Grok didn't extract anything specific)
        if (empty($search_name) && preg_match('/\b(?:practice|business|provider|profile|listing|clinic)\s+name\s+is\s+(.+?)(?:\s+(?:in|at|from)\s+|(?<!(?-i:[A-Z]))\.|[,;!?]|$)/i', $msg, $m)) {
            $search_name = trim($m[1]);
        }
        // No explicit "my name is" — try bare "Name in/at/from City" pattern
        // (user might just say "Dr. John Smith in Chapel Hill")
        // Capture multi-word city: "Chapel Hill", "Winston Salem", "Pinehurst"
        if (empty($search_name) && preg_match('/^(.+?)\s+(?:in|at|from)\s+(.+?)$/i', $msg, $m)) {
            $candidate = trim($m[1]);
            $city_candidate = trim($m[2]);
            // Skip if it starts with search verbs
            if (!preg_match('/^\s*(?:find|search|show|look|need|want|help|i\s+need|i\s+want|can\s+you|could\s+you)/i', $candidate)) {
                $search_name = $candidate;
                // Pre-fill city ONLY when Grok didn't already provide one — never overwrite.
                if (empty($search_city)) {
                    $search_city = $city_candidate;
                }
            }
        }
        // Name-only without city delimiter (e.g. "Dr. Smith" or "my name is Dr. Smith")
        // "my name is X" without "in/at/from" — capture to end of string
        if (empty($search_name) && preg_match('/\b(?:my\s+name\s+is|name\s+is|it\'?s|it\s+is|this\s+is|that\s+is|i\'?m)\s+(.+?)$/i', $msg, $m)) {
            $candidate = trim($m[1]);
            if (strlen($candidate) >= 2 && !preg_match('/^\s*(?:find|search|show|look|need|want|help)/i', $candidate)) {
                $search_name = $candidate;
            }
        }
        // Last resort: treat the entire message as a potential name (e.g. "Dr. Smith" or "Smith")
        // Only if it's 2+ characters and doesn't look like a search verb or domain term
        if (empty($search_name)) {
            $clean_msg = preg_replace('/^(?:um+|uh+|uhm+|er+|ah+|so+)\s+/i', '', trim($msg));
            $clean_msg = preg_replace('/\s+$/i', '', $clean_msg);
            // Must be at least 2 characters, not a search verb, not just "yes"/"no"
            if (strlen($clean_msg) >= 2 &&
                !preg_match('/^\s*(?:find|search|show|look|need|want|help|i\s+need|i\s+want|can\s+you|could\s+you|yes|no|yeah|nope|ok|okay|sure)/i', $clean_msg) &&
                !preg_match('/\b(?:therapist|counselor|therapy|provider|doctor|psychiatrist|psychologist|nearby|near\s+me)\b/i', $clean_msg)) {
                $search_name = $clean_msg;
            }
        }
    
        // Extract city from "in/at/from City Name" — supports multi-word cities
        // like "Chapel Hill", "Winston Salem", "Cary NC", "Fort Worth"
        if (empty($search_city) && preg_match('/\b(?:in|at|from)\s+(.+?)$/i', $msg, $m)) {
            $city_raw = trim($m[1]);
            // Strip trailing punctuation that ASR often appends
            $city_raw = preg_replace('/[.,;!?]+$/', '', $city_raw);
            // If the city looks too long (user may have continued speaking),
            // take only the first 3 words max — cities rarely exceed that
            $city_words = preg_split('/\s+/', $city_raw);
            if (count($city_words) > 3) {
                $city_raw = implode(' ', array_slice($city_words, 0, 3));
            }
            $search_city = $city_raw;
        }
        // Also check prior context for city
        if (empty($search_city) && !empty($prior_context['city'])) {
            $search_city = $prior_context['city'];
        }
    
        if (empty($search_name)) {
            mia_debug_log('Chat', 'claim_lookup_empty_name', ['message' => substr($msg, 0, 80), 'city' => $search_city]);
            return array();
        }

        // Strip trailing punctuation that ASR often appends (periods, commas,
        // exclamation marks, question marks, semicolons).  Without this,
        // WP_Query's `s` parameter tokenises "ashley almon." into "ashley"
        // AND "almon." — the LIKE '%almon.%' clause then fails to match the
        // post title "ASHLEY ALMON" because the title has no period.
        // (Debug log 1777280880936: "My name is Ashley Almon." → 0 providers.)
        $search_name = preg_replace('/[.,;:!?]+\s*$/', '', $search_name);

        mia_debug_log('Chat', 'claim_lookup_search', ['name' => substr($search_name, 0, 60), 'city' => $search_city, 'source' => $prior_context['provider_owner_mode'] ?? '']);
    
        // Search for the provider by name using WP_Query on gd_place post type
        $args = array(
            'post_type'      => 'gd_place',
            'post_status'    => 'publish',
            'posts_per_page' => 3,
            's'              => $search_name,
        );
        if (!empty($search_city)) {
            $args['tax_query'] = array(array(
                'taxonomy' => 'city',
                'field'    => 'name',
                'terms'    => $search_city,
            ));
            // Also try a LIKE match for multi-word cities that may not
            // exactly match the taxonomy term (e.g. "Chapel Hill" vs "Chapel Hill NC")
            $city_term = get_term_by('name', $search_city, 'city');
            if (!$city_term) {
                // Try partial match — find a city term whose name starts with the first word
                $city_first_word = preg_split('/\s+/', $search_city);
                $city_first_word = $city_first_word[0];
                $possible_terms = get_terms(array(
                    'taxonomy'   => 'city',
                    'name__like' => $city_first_word,
                    'hide_empty' => false,
                    'number'     => 5,
                ));
                if (!empty($possible_terms) && !is_wp_error($possible_terms)) {
                    // Use the first matching term
                    $args['tax_query'] = array(array(
                        'taxonomy' => 'city',
                        'field'    => 'term_id',
                        'terms'    => $possible_terms[0]->term_id,
                    ));
                    mia_debug_log('Chat', 'claim_lookup_city_fuzzy', ['searched' => $search_city, 'matched' => $possible_terms[0]->name, 'term_id' => $possible_terms[0]->term_id]);
                } else {
                    // No matching city term — remove tax_query to avoid 0 results
                    unset($args['tax_query']);
                    mia_debug_log('Chat', 'claim_lookup_city_not_found', ['searched' => $search_city]);
                }
            }
        }
    
        $query = new \WP_Query($args);
        $results = array();
        foreach ($query->posts as $post) {
            $provider_data = $this->format_provider_result($post);
            if (!empty($provider_data)) {
                $results[] = $provider_data;
            }
        }

        // If city filter gave no results, retry without city (broader search)
        if (empty($results) && !empty($search_city)) {
            unset($args['tax_query']);
            $args['posts_per_page'] = 5;
            $query2 = new \WP_Query($args);
            foreach ($query2->posts as $post) {
                $provider_data = $this->format_provider_result($post);
                if (!empty($provider_data)) {
                    $results[] = $provider_data;
                }
            }
            if (!empty($results)) {
                mia_debug_log('Chat', 'claim_lookup_city_retry_success', ['name' => substr($search_name, 0, 60), 'city_dropped' => $search_city, 'found' => count($results)]);
            }
        }

        // ── Fuzzy name suggestions when exact search yields no results ──
        // If WP_Query returned 0 results, try phonetic/Levenshtein fuzzy matching
        // so we can offer "did you mean" suggestions in the claim guidance response.
        if (empty($results) && !empty($search_name)) {
            $fuzzy = $this->fuzzy_match_provider_names($search_name, $search_city, 3);
            if (!empty($fuzzy)) {
                $this->_claim_name_suggestions = $fuzzy;
                mia_debug_log('Chat', 'claim_lookup_fuzzy_suggestions', array(
                    'name'   => substr($search_name, 0, 60),
                    'city'   => $search_city,
                    'suggestions' => array_map(function($p) { return $p['name'] . ' (score:' . ($p['similarity_score'] ?? 0) . ')'; }, $fuzzy),
                ));
            }
        }

        mia_debug_log('Chat', 'claim_lookup_result', ['name' => substr($search_name, 0, 60), 'city' => $search_city, 'count' => count($results)]);
    
        return $results;
    }

    /**
     * Narrow a list of disambiguation providers by specialty/category/practice name
     * mentioned in the user's message. Called when the user answers "which one?"
     * by naming a specialty, practice, or selecting by number.
     *
     * @param string $msg       Lowercase user message.
     * @param array  $providers Array of provider data arrays (from format_provider_result).
     * @return array Narrowed list of providers (may be empty or same count if no match).
     */
    private function narrow_claim_by_specialty($msg, $providers) {
        if (empty($providers) || !is_array($providers)) {
            return array();
        }

        // 1. Number selection: "number 1", "the first one", "1st", "#2"
        if (preg_match('/\b(?:number\s*|#)\s*(\d+)\b/i', $msg, $m) ||
            preg_match('/\b(\d)(?:st|nd|rd|th)\s+(?:one|profile|listing)\b/i', $msg, $m) ||
            preg_match('/\b(?:the\s+)?(first|second|third|1st|2nd|3rd)\s+(?:one|profile|listing)\b/i', $msg, $m)) {

            $num_map = array('first' => 1, 'second' => 2, 'third' => 3, '1st' => 1, '2nd' => 2, '3rd' => 3);
            $idx = isset($m[1]) ? (int) $m[1] : 0;
            if ($idx === 0 && isset($num_map[strtolower($m[1] ?? '')])) {
                $idx = $num_map[strtolower($m[1])];
            }
            if ($idx >= 1 && $idx <= count($providers)) {
                return array($providers[$idx - 1]);
            }
        }

        // 2. Practice name match
        foreach ($providers as $p) {
            $practice = strtolower(trim((string) ($p['practice_name'] ?? '')));
            if (!empty($practice) && strpos($msg, $practice) !== false) {
                return array($p);
            }
        }

        // 3. Specialty / category match
        $narrowed = array();
        foreach ($providers as $p) {
            $specs = array_map('strtolower', array_filter(array_map('trim', ($p['specialties'] ?? array()))));
            $pcat = strtolower(trim((string) ($p['primary_category'] ?? '')));
            $cat = strtolower(trim((string) ($p['category'] ?? '')));
            $all_terms = array_merge($specs, array_filter(array($pcat, $cat)));

            foreach ($all_terms as $term) {
                if (empty($term)) continue;
                // Check if the user's message contains this specialty/category
                if (strpos($msg, $term) !== false) {
                    $narrowed[] = $p;
                    break;
                }
                // Also check partial match (e.g. "anxiety" matches "Anxiety Disorders")
                $term_words = array_filter(explode(' ', $term));
                foreach ($term_words as $tw) {
                    if (strlen($tw) >= 4 && strpos($msg, $tw) !== false) {
                        $narrowed[] = $p;
                        break 2;
                    }
                }
            }
        }

        if (!empty($narrowed)) {
            return $narrowed;
        }

        // 4. Keyword-based specialty matching using the intent engine's synonym map
        // (e.g. "ptsd" → "Trauma & PTSD", "ocd" → "OCD")
        $specialty_keywords = array(
            'anxiety' => array('anxiety', 'anxious', 'worry', 'panic', 'phobia', 'ocd', 'obsessive'),
            'depression' => array('depression', 'depressed', 'sad', 'hopeless'),
            'trauma' => array('trauma', 'ptsd', 'post-traumatic', 'abuse', 'assault'),
            'couples' => array('couples', 'marriage', 'relationship', 'marital', 'partner'),
            'family' => array('family', 'parenting', 'child', 'children', 'adolescent'),
            'addiction' => array('addiction', 'substance', 'alcohol', 'drug', 'gambling'),
            'grief' => array('grief', 'loss', 'bereavement', 'mourning'),
            'lgbtq' => array('lgbtq', 'lgbt', 'queer', 'transgender', 'gender', 'identity'),
            'eating' => array('eating', 'anorexia', 'bulimia', 'binge'),
            'bipolar' => array('bipolar', 'mania', 'manic'),
            'mindfulness' => array('mindfulness', 'meditation', 'mindful'),
            'chronic' => array('chronic', 'pain', 'illness', 'fibromyalgia'),
        );

        foreach ($providers as $p) {
            $specs = array_map('strtolower', array_filter(array_map('trim', ($p['specialties'] ?? array()))));
            $pcat = strtolower(trim((string) ($p['primary_category'] ?? '')));
            $all_terms = array_merge($specs, array_filter(array($pcat)));

            foreach ($specialty_keywords as $spec_key => $keywords) {
                $keyword_hit = false;
                foreach ($keywords as $kw) {
                    if (strpos($msg, $kw) !== false) {
                        $keyword_hit = true;
                        break;
                    }
                }
                if (!$keyword_hit) continue;

                // Check if this provider has the matching specialty
                foreach ($all_terms as $term) {
                    if (empty($term)) continue;
                    if (strpos($term, $spec_key) !== false || strpos(strtolower($spec_key), $term) !== false) {
                        $narrowed[] = $p;
                        break 2;
                    }
                }
            }
        }

        return $narrowed;
    }

    private function format_provider_result($post) {
        if (!$post) return null;
        $post_id = $post->ID;
        $name = get_the_title($post_id);
        if (empty($name)) return null;
    
        $url = get_permalink($post_id);
        $city_terms = get_the_terms($post_id, 'city');
        $city = '';
        if (!empty($city_terms) && !is_wp_error($city_terms)) {
            $city = $city_terms[0]->name;
        }

        // Collect category/specialty for disambiguation
        $category = '';
        $cat_terms = get_the_terms($post_id, 'gd_placecategory');
        if (!empty($cat_terms) && !is_wp_error($cat_terms)) {
            $category = $cat_terms[0]->name;
        }
        $primary_category = '';
        $pc_terms = get_the_terms($post_id, 'myhh_primary_category');
        if (!empty($pc_terms) && !is_wp_error($pc_terms)) {
            $primary_category = $pc_terms[0]->name;
        }
        $specialties = array();
        $sp_terms = get_the_terms($post_id, 'myhh_specialty');
        if (!empty($sp_terms) && !is_wp_error($sp_terms)) {
            foreach ($sp_terms as $st) {
                $specialties[] = $st->name;
            }
        }
        // Practice/business name from custom field
        $practice_name = '';
        $practice_meta = get_post_meta($post_id, 'geodir_business_name', true);
        if (!empty($practice_meta)) {
            $practice_name = $practice_meta;
        }
    
        return array(
            'id'               => $post_id,
            'name'             => $name,
            'url'              => $url ? $url : '#',
            'city'             => $city,
            'category'         => $category,
            'primary_category' => $primary_category,
            'specialties'      => $specialties,
            'practice_name'    => $practice_name,
        );
    }

    private function looks_like_provider_owner_fragment($message) {
        $msg = strtolower(trim((string) $message));
        if ($msg === '') {
            return false;
        }

        return (bool) (
            preg_match('/\bclaim(?:ing)?\b/i', $msg) ||
            preg_match('/\bmy\s+(?:profile|proof|provider profile|provider page|listing|practice profile|business profile)\b/i', $msg) ||
            preg_match('/^\s*i\s+need\s+(?:the\s+)?my\s*$/i', $msg) ||
            preg_match('/\b(?:manage|update|edit|fix|verify)\s+my\s+(?:profile|listing|practice|business)\b/i', $msg) ||
            preg_match('/\b(?:reclamar|reclamo|gestionar|actualizar|editar|arreglar|verificar)\s+mi\s+(?:perfil|listado|practica|práctica|consulta|negocio)\b/iu', $msg) ||
            preg_match('/\bmi\s+(?:perfil|perfile|listado|perfil de proveedor|pagina de proveedor|página de proveedor|practica|práctica|consulta|negocio)\b/iu', $msg) ||
            preg_match('/^\s*(?:necesito|quiero)\s+mi\s*$/iu', $msg)
        );
    }

    private function looks_like_vague_search_fragment($message) {
        $msg = strtolower(trim((string) $message));
        if ($msg === '') {
            return false;
        }

        if (preg_match('/\b(?:therapist|therapists|therapy|counselor|counselors|provider|providers|doctor|psychologist|psychiatrist|charlotte|ansiedad|depresi[oó]n|terapeuta|psic[oó]logo|psiquiatra|consejero|proveedor|pareja|matrimonio|familia|categor[ií]a|ciudad|opci[oó]n)\b/iu', $msg)) {
            return false;
        }

        return (bool) (
            preg_match('/^\s*(?:i\s+need|i\s+want|need|want|help|help me|find me|show me|can you find|could you find)\s*$/i', $msg) ||
            preg_match('/^\s*(?:necesito|quiero|ayuda|ayudame|ayúdame|busco|buscar|muestrame|muéstrame)\s*$/iu', $msg) ||
            preg_match('/^\s*(?:i\s+need|i\s+want|need|want)\s+(?:help|support|someone|somebody|person|people)\s*$/i', $msg) ||
            preg_match('/^\s*(?:necesito|quiero|busco)\s+(?:ayuda|apoyo|alguien|persona|gente)\s*$/iu', $msg)
        );
    }

    private function resolve_referenced_provider($message, $providers, $prior_context = array()) {
        if (empty($providers) || !is_array($providers)) {
            return null;
        }

        $providers = array_values($providers);
        $message = strtolower((string) $message);

        $pool = $this->get_provider_reference_pool($providers, $prior_context);
        if (!empty($prior_context['_target_provider_name'])) {
            // Never substitute the current first result for an unresolved named person.
            $target = $this->normalize_provider_reference_text($prior_context['_target_provider_name']);
            foreach ($pool as $provider) {
                if ($this->normalize_provider_reference_text($provider['name'] ?? '') === $target) return $provider;
            }
            return null;
        }
        $named = $this->resolve_named_provider_from_recent_list($message, $pool);
        if ($named) return $named;
        if (!empty($prior_context['last_provider_focus'])
            && preg_match('/\b(?:her|his|their|him|them|they|(?:that|this)\s+(?:one|profile|provider|listing))\b/iu', $message)
            && !preg_match('/\b(?:first|second|third|fourth|fifth|last|next|other|number|[1-9](?:st|nd|rd|th))\b/iu', $message)) {
            foreach ($pool as $provider) {
                if (absint($provider['id'] ?? 0) === absint($prior_context['last_provider_focus'])) return $provider;
            }
        }

        // Ordinal matching — longest/most-specific phrase first to prevent
        // false positives like "one" inside "the third one" or "second" inside
        // "the second one from the bottom".  Bare "one" is removed from index 0
        // because it matches any occurrence of "one" (e.g. "the third one", "someone").
        $ordinals = array(
            0 => array('the one you just said', 'the one at the top', 'the first result', 'the first option', 'the first provider', 'the first choice', 'the first listing', 'the first match', 'the top one', 'the top result', 'the top option', 'the first one', 'that one', 'this one', 'the one', 'first one', 'first', '1st'),
            1 => array('the second result', 'the second option', 'the second provider', 'the second choice', 'the second listing', 'the second match', 'the second one', 'second one', 'second', '2nd', 'two'),
            2 => array('the third result', 'the third option', 'the third provider', 'the third choice', 'the third listing', 'the third match', 'the third one', 'third one', 'third', '3rd', 'three', 'the sartre', 'about the sartre', 'the searched', 'the surge', 'the surd'),
            // ASR mishears for "fourth": "Force One", "for the one", "for one", "41"
            3 => array('the fourth result', 'the fourth option', 'the fourth provider', 'the fourth choice', 'the fourth listing', 'the fourth one', 'fourth one', 'fourth', '4th', 'four', 'force one', 'for the one', 'for one'),
            4 => array('the fifth result', 'the fifth option', 'the fifth provider', 'the fifth choice', 'the fifth one', 'fifth one', 'fifth', '5th', 'five'),
            5 => array('the sixth result', 'the sixth option', 'the sixth one', 'sixth one', 'sixth', '6th', 'six'),
            6 => array('the seventh result', 'the seventh option', 'the seventh one', 'seventh one', 'seventh', '7th', 'seven'),
            7 => array('the eighth result', 'the eighth option', 'the eighth one', 'eighth one', 'eighth', '8th', 'eight'),
            8 => array('the ninth result', 'the ninth option', 'the ninth one', 'ninth one', 'ninth', '9th', 'nine'),
            9 => array('the tenth result', 'the tenth option', 'the tenth one', 'tenth one', 'tenth', '10th', 'ten'),
        );

        // Sort each ordinal's needles by length (longest first) so specific phrases
        // like "the third one" are checked before generic words like "third".
        foreach ($ordinals as $index => $needles) {
            usort($ordinals[$index], function($a, $b) { return strlen($b) - strlen($a); });
        }

        // Also check higher ordinals (2,3,4,5) BEFORE lower ones (0,1) so that
        // "the third one" matches index 2, not the bare "one" in any other index.
        $check_order = array(9, 8, 7, 6, 5, 4, 3, 2, 1, 0);
        foreach ($check_order as $index) {
            if (!isset($providers[$index]) || !isset($ordinals[$index])) {
                continue;
            }
            foreach ($ordinals[$index] as $needle) {
                if (strpos($message, $needle) !== false) {
                    return $providers[$index];
                }
            }
        }

        // Numeric selection: "number 3", "#4"
        if (preg_match('/\b(?:number\s*|#)\s*(\d+)\b/i', $message, $m)) {
            $idx = (int) $m[1];
            if ($idx >= 1 && $idx <= count($providers)) {
                return $providers[$idx - 1];
            }
        }

        // "the last one" / "the last result" / "the last provider" / "the bottom one"
        if (preg_match('/\b(?:the\s+)?last\s+(?:one|result|option|provider|choice|listing|match)\b|the\s+bottom\s+one\b/i', $message)) {
            return $providers[count($providers) - 1];
        }

        // "the other one" — resolve to the provider after the last focused one,
        // or the second one in the list if no focus context
        if (preg_match('/\b(?:the\s+)?other\s+(?:one|result|option|provider|choice|listing|match)\b/i', $message)) {
            mia_debug_log('Chat', 'resolve_provider_other', ['message' => substr($message, 0, 80), 'focus_id' => $prior_context['last_provider_focus'] ?? 0]);
            if (!empty($prior_context['last_provider_focus'])) {
                $focus_id = absint($prior_context['last_provider_focus']);
                for ($i = 0; $i < count($providers); $i++) {
                    if (absint($providers[$i]['id'] ?? 0) === $focus_id && isset($providers[$i + 1])) {
                        return $providers[$i + 1];
                    }
                }
            }
            return isset($providers[1]) ? $providers[1] : $providers[0];
        }

        // "next one" — resolve to the provider after the last focused one
        if (preg_match('/\b(?:the\s+)?next\s+(?:one|result|option|provider|choice|listing|match)\b/i', $message)) {
            mia_debug_log('Chat', 'resolve_provider_next', ['message' => substr($message, 0, 80), 'focus_id' => $prior_context['last_provider_focus'] ?? 0]);
            if (!empty($prior_context['last_provider_focus'])) {
                $focus_id = absint($prior_context['last_provider_focus']);
                for ($i = 0; $i < count($providers); $i++) {
                    if (absint($providers[$i]['id'] ?? 0) === $focus_id && isset($providers[$i + 1])) {
                        return $providers[$i + 1];
                    }
                }
            }
            return isset($providers[1]) ? $providers[1] : $providers[0];
        }

        // "the one you mentioned/said/just showed"
        if (preg_match('/\bthe\s+one\s+you\s+(?:mentioned|said|just\s+(?:said|showed|listed))\b/i', $message)) {
            mia_debug_log('Chat', 'resolve_provider_mentioned', ['message' => substr($message, 0, 80), 'focus_id' => $prior_context['last_provider_focus'] ?? 0]);
            if (!empty($prior_context['last_provider_focus'])) {
                $focus_id = absint($prior_context['last_provider_focus']);
                foreach ($providers as $provider) {
                    if (absint($provider['id'] ?? 0) === $focus_id) {
                        return $provider;
                    }
                }
            }
            return $providers[0];
        }

        // Pronoun references: "what about him/her/them"
        if (preg_match('/\b(?:what\s+about|tell\s+me\s+(?:more\s+)?about|more\s+about|details?\s+(?:on|about|for))\s+(?:him|her|them)\b/i', $message)) {
            mia_debug_log('Chat', 'resolve_provider_pronoun', ['message' => substr($message, 0, 80), 'focus_id' => $prior_context['last_provider_focus'] ?? 0]);
            if (!empty($prior_context['last_provider_focus'])) {
                $focus_id = absint($prior_context['last_provider_focus']);
                foreach ($providers as $provider) {
                    if (absint($provider['id'] ?? 0) === $focus_id) {
                        return $provider;
                    }
                }
            }
            return $providers[0];
        }

        // Name match — exact or last name only
        foreach ($providers as $provider) {
            if (!empty($provider['name']) && strpos($message, strtolower($provider['name'])) !== false) {
                return $provider;
            }
        }

        // Fuzzy name match — check if any provider's first or last name appears in the message
        foreach ($providers as $provider) {
            if (empty($provider['name'])) continue;
            $name_parts = preg_split('/\s+/', strtolower($provider['name']));
            foreach ($name_parts as $part) {
                // Skip very short parts and common titles
                if (strlen($part) <= 2 || in_array($part, array('dr', 'mr', 'mrs', 'ms', 'jr', 'sr', 'phd', 'lcsw', 'lpc', 'lmft', 'psyd'))) continue;
                // Provider-name parts must be whole words: 'One' is not a match for 'phone'.
                if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($part, '/') . '(?![\p{L}\p{N}])/iu', $message)) {
                    return $provider;
                }
            }
        }

        // ── Phonetic name match (metaphone) ──
        // When the user says a provider name that ASR mishears (e.g. "DANIA"
        // → "Daniel"), exact and substring matching fail.  Metaphone compares
        // the phonetic root so "dania" (metaphone: TN) matches "daniel"
        // (metaphone: TNL — close enough with a shared TN prefix).
        // (Debug log 1777418457105: "could you tell me about Daniel" should
        // have matched DANIA M ALSHAFEI but fell through to first provider.)
        $msg_words = array_filter(preg_split('/\s+/', $message), function($w) {
            return strlen($w) > 2 && !in_array($w, array('the', 'and', 'for', 'about', 'could', 'would', 'tell', 'show', 'give', 'want', 'more', 'can', 'who', 'what', 'how', 'this', 'that', 'they', 'them', 'their'));
        });
        foreach ($msg_words as $msg_word) {
            $msg_meta = metaphone($msg_word);
            if (strlen($msg_meta) < 2) continue;
            foreach ($providers as $provider) {
                if (empty($provider['name'])) continue;
                $name_parts = preg_split('/\s+/', strtolower($provider['name']));
                foreach ($name_parts as $part) {
                    if (strlen($part) <= 2) continue;
                    $part_meta = metaphone($part);
                    // Exact metaphone match (e.g. "dania"→TN == "dania"→TN)
                    if ($part_meta === $msg_meta) {
                        mia_debug_log('Chat', 'resolve_provider_phonetic_exact', ['message_word' => $msg_word, 'provider_part' => $part, 'metaphone' => $part_meta]);
                        return $provider;
                    }
                    // Prefix match — the metaphone of "daniel" is TNL and
                    // "dania" is TN; TN is a prefix of TNL, so they match.
                    if (strlen($msg_meta) >= 2 && strlen($part_meta) >= 2) {
                        $shorter = strlen($msg_meta) <= strlen($part_meta) ? $msg_meta : $part_meta;
                        $longer  = strlen($msg_meta) <= strlen($part_meta) ? $part_meta : $msg_meta;
                        if (strpos($longer, $shorter) === 0 && strlen($shorter) >= 2) {
                            mia_debug_log('Chat', 'resolve_provider_phonetic_prefix', ['message_word' => $msg_word, 'msg_meta' => $msg_meta, 'provider_part' => $part, 'part_meta' => $part_meta]);
                            return $provider;
                        }
                    }
                }
            }
        }

        if (!empty($prior_context['last_provider_focus'])) {
            $focus_id = absint($prior_context['last_provider_focus']);
            foreach ($providers as $provider) {
                if (absint($provider['id'] ?? 0) === $focus_id) {
                    return $provider;
                }
            }
        }

        if (preg_match('/that one|this one|what about|the provider/i', $message)) {
            return $providers[0];
        }

        return null;
    }

    private function build_provider_memory($providers) {
        $memory = array();
        if (empty($providers) || !is_array($providers)) {
            return $memory;
        }

        foreach (array_slice(array_values($providers), 0, 4) as $provider) {
            $provider_name = $this->get_safe_provider_display_name($provider);
            $memory[] = array(
                'id' => absint($provider['id'] ?? 0),
                'name' => sanitize_text_field($provider_name),
                'url' => $this->sanitize_provider_url($provider['url'] ?? ''),
                'city' => sanitize_text_field($provider['city'] ?? ''),
                'phone' => sanitize_text_field($provider['phone'] ?? ''),
                'website' => esc_url_raw($provider['website'] ?? ''),
                'street' => sanitize_text_field($provider['street'] ?? ''),
                'zip' => sanitize_text_field($provider['zip'] ?? ''),
                'address' => sanitize_text_field($provider['address'] ?? ''),
                'license_type' => sanitize_text_field($provider['license_type'] ?? ''),
                'specialties' => array_map('sanitize_text_field', array_slice((array) ($provider['specialties'] ?? array()), 0, 3)),
                'modalities' => array_map('sanitize_text_field', array_slice((array) ($provider['modalities'] ?? array()), 0, 5)),
                'languages' => array_map('sanitize_text_field', array_slice((array) ($provider['languages'] ?? array()), 0, 5)),
                'age_groups' => array_map('sanitize_text_field', array_slice((array) ($provider['age_groups'] ?? array()), 0, 8)),
                'insurance_accepted' => array_map('sanitize_text_field', array_slice((array) ($provider['insurance_accepted'] ?? array()), 0, 12)),
                'session_type' => sanitize_text_field($provider['session_type'] ?? ''),
                'verification_status' => sanitize_text_field($provider['verification_status'] ?? ''),
                'claimed' => !empty($provider['claimed']),
                'has_booking_link' => !empty($provider['has_booking_link']),
                'rating_average' => floatval($provider['rating_average'] ?? 0),
                'rating_count' => intval($provider['rating_count'] ?? 0),
            );
        }

        return $memory;
    }

    private function sanitize_provider_memory($providers, $limit = 4) {
        $clean = array();
        foreach (array_slice(array_values((array) $providers), 0, min(8, max(1, $limit))) as $provider) {
            if (!is_array($provider)) {
                continue;
            }
            $provider_name = $this->get_safe_provider_display_name($provider);
            $clean[] = array(
                'id' => absint($provider['id'] ?? 0),
                'name' => sanitize_text_field($provider_name),
                'url' => $this->sanitize_provider_url($provider['url'] ?? ''),
                'city' => sanitize_text_field($provider['city'] ?? ''),
                'phone' => sanitize_text_field($provider['phone'] ?? ''),
                'website' => esc_url_raw($provider['website'] ?? ''),
                'street' => sanitize_text_field($provider['street'] ?? ''),
                'zip' => sanitize_text_field($provider['zip'] ?? ''),
                'address' => sanitize_text_field($provider['address'] ?? ''),
                'license_type' => sanitize_text_field($provider['license_type'] ?? ''),
                'specialties' => array_map('sanitize_text_field', array_slice((array) ($provider['specialties'] ?? array()), 0, 3)),
                'modalities' => array_map('sanitize_text_field', array_slice((array) ($provider['modalities'] ?? array()), 0, 5)),
                'languages' => array_map('sanitize_text_field', array_slice((array) ($provider['languages'] ?? array()), 0, 5)),
                'age_groups' => array_map('sanitize_text_field', array_slice((array) ($provider['age_groups'] ?? array()), 0, 8)),
                'insurance_accepted' => array_map('sanitize_text_field', array_slice((array) ($provider['insurance_accepted'] ?? array()), 0, 12)),
                'session_type' => sanitize_text_field($provider['session_type'] ?? ''),
                'verification_status' => sanitize_text_field($provider['verification_status'] ?? ''),
                'claimed' => !empty($provider['claimed']),
                'has_booking_link' => !empty($provider['has_booking_link']),
                'rating_average' => floatval($provider['rating_average'] ?? 0),
                'rating_count' => intval($provider['rating_count'] ?? 0),
            );
        }
        return $clean;
    }

    private function is_platform_provider_name($name) {
        $value = strtolower(trim(wp_strip_all_tags((string) $name)));
        if ($value === '') {
            return true;
        }
        $value = preg_replace('/^https?:\/\//', '', $value);
        $value = preg_replace('/^www\./', '', $value);
        return (bool) preg_match('/^(?:myhealthub|myhealthhub)\.com\/?$/i', $value)
            || (bool) preg_match('/\b(?:my\s*health\s*ub|my\s*health\s*hub|myhealthub|myhealthhub)(?:\.com)?\b/i', $value)
            || (bool) preg_match('/^[a-z0-9.-]+\.(?:com|org|net|health|care)(?:\/.*)?$/i', $value);
    }

    private function get_safe_provider_display_name($provider, $index = 0, $lang = 'en') {
        $raw = '';
        if (is_array($provider)) {
            $raw = trim((string) ($provider['name'] ?? $provider['title'] ?? ''));
        }
        $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
        if ($raw !== '' && !$this->is_platform_provider_name($raw)) {
            return sanitize_text_field($raw);
        }

        $city = is_array($provider) ? trim((string) ($provider['city'] ?? '')) : '';
        if ($city !== '') {
            return ($lang === 'es') ? "Proveedor en {$city}" : "Provider in {$city}";
        }

        $label = $index > 0 ? (string) $index : 'option';
        return ($lang === 'es') ? "Proveedor {$label}" : "Provider {$label}";
    }

    private function sanitize_provider_result_names($providers, $lang = 'en') {
        $clean = array();
        foreach (array_values((array) $providers) as $index => $provider) {
            if (!is_array($provider)) {
                continue;
            }
            $provider['name'] = $this->get_safe_provider_display_name($provider, $index + 1, $lang);
            if (!empty($provider['title']) && $this->is_platform_provider_name($provider['title'])) {
                $provider['title'] = $provider['name'];
            }
            $clean[] = $provider;
        }
        return $clean;
    }

    private function get_provider_memory_from_context($prior_context) {
        if (empty($prior_context['recent_providers']) || !is_array($prior_context['recent_providers'])) {
            return array();
        }
        return $this->sanitize_provider_memory($prior_context['recent_providers']);
    }

    private function format_verification_label($status, $lang = 'en') {
        switch ($status) {
            case 'board_confirmed':
                return ($lang === 'es') ? 'Verificado por Junta' : 'Board Verified';
            case 'core_verified':
                return ($lang === 'es') ? 'Verificado' : 'Verified';
            case 'source_only':
                return ($lang === 'es') ? 'Fuente Verificada' : 'Verified Source';
            default:
                return ($lang === 'es') ? 'estado de verificacion por confirmar' : 'verification status needs confirmation';
        }
    }

    private function get_literal_response_language($message) {
        if (preg_match('/\b(?:respond|reply|answer|explain|switch|back)\b[^.!?]{0,30}\benglish\b|\b(?:responde|explica|vuelve)\b[^.!?]{0,30}\bingl[eé]s\b/iu', $message)) return 'en';
        if (preg_match('/\b(?:respond|reply|answer|explain|switch)\b[^.!?]{0,30}\bspanish\b|\ben\s+espa[ñn]ol\b|\b(?:mu[eé]strame|dame|busco|quiero|necesito|recomendaste|explica|expl[ií]came|cu[eé]ntame)\b/iu', $message)) return 'es';
        if (preg_match('/^(?:please\s+)?(?:find|show|give|tell|explain|compare|what|how|which|can|could|i\s+(?:need|want|prefer|would|am))\b/iu', trim($message))) return 'en';
        return '';
    }

    private function resolve_response_language($message_lower, $prior_context = array(), $result = array()) {
        $literal_language = $this->get_literal_response_language($message_lower);
        if ($literal_language !== '') return $literal_language;
        if (!empty($result['detected_language'])) {
            return $result['detected_language'];
        }

        if (!empty($prior_context['language'])) {
            return $prior_context['language'];
        }

        if (preg_match('/[áéíóúñü¿¡]/iu', $message_lower)) {
            return 'es';
        }

        if (preg_match('/\b(hola|buenas|quiero|necesito|busco|ayuda|terapeuta|seguro|ciudad|comparar|mejor|opciones|proveedor|proveedores|espanol|español|como|gratis|verifican|puedes|hacer|usar)\b/i', $message_lower)) {
            return 'es';
        }

        return 'en';
    }

    /**
     * Smart follow-up fallback when Grok API is unavailable.
     * Uses actual provider context to give decisive, concierge-quality responses.
     */
    private function smart_followup_fallback($message, $history, $prior_context, $result) {
        $msg_lower   = strtolower(trim($message));
        $has_history = !empty($history) && count($history) > 0;
        $has_prior   = !empty($prior_context) && (
            !empty($prior_context['specialty']) || !empty($prior_context['primary_category_name']) || !empty($prior_context['city'])
        );

        if (!$has_history && !$has_prior) {
            return null;
        }

        $specialty = $prior_context['specialty'] ?? $prior_context['primary_category_name'] ?? '';
        $city      = $prior_context['city'] ?? '';
        $recent    = (!empty($prior_context['recent_providers']) && is_array($prior_context['recent_providers']))
            ? $prior_context['recent_providers'] : array();
        $top       = !empty($recent[0]) ? $recent[0] : null;

        // Resolve the currently focused provider (last_provider_focus > first result)
        $focus_id = !empty($prior_context['last_provider_focus']) ? absint($prior_context['last_provider_focus']) : 0;
        $focused  = null;
        if ($focus_id) {
            foreach ($recent as $rp) {
                if (absint($rp['id'] ?? 0) === $focus_id) {
                    $focused = $rp;
                    break;
                }
            }
        }
        $current = $focused ?? $top;

        // ── Recommendation / comparison ──────────────────────────────────────
        if (preg_match('/which.*(best|recommend|good|right|choose|pick|suggest)/i', $msg_lower)
            || preg_match('/who.*(best|recommend|should|see)/i', $msg_lower)
            || preg_match('/help me (choose|pick|decide)/i', $msg_lower)
            || preg_match('/what do you (think|recommend|suggest)/i', $msg_lower)) {

            if ($current) {
                $name   = $current['name'] ?? 'the first option';
                $url    = $current['url'] ?? '#';
                $specs  = !empty($current['specialties']) ? implode(' and ', array_slice($current['specialties'], 0, 2)) : '';
                $lic    = $current['license_type'] ?? '';
                $pcity  = $current['city'] ?? $city;
                $resp   = "I'd start with [**{$name}**]({$url}).";
                $detail = array();
                if ($specs)  $detail[] = "they specialize in {$specs}";
                if ($pcity)  $detail[] = "based in {$pcity}";
                if ($lic)    $detail[] = "{$lic} credential";
                if ($detail) $resp .= " " . ucfirst(implode(', ', $detail)) . ".";
                if (count($recent) > 1 && !empty($recent[1]['name'])) {
                    $s2      = $recent[1];
                    $s2name  = $s2['name'];
                    $s2url   = $s2['url'] ?? '#';
                    $s2specs = !empty($s2['specialties']) ? implode(', ', array_slice($s2['specialties'], 0, 2)) : '';
                    $resp   .= "\n\n[**{$s2name}**]({$s2url}) is also worth a look" . ($s2specs ? " — {$s2specs}" : '') . ".";
                }
                $resp .= "\n\nClick either profile to see contact info and book directly.";
                return $resp;
            }
            $resp = "Hard to say without knowing what you're dealing with.";
            if ($specialty || $city) {
                $ctx  = $specialty && $city ? "{$specialty} in {$city}" : ($specialty ?: $city);
                $resp .= " Tell me your main concern — anxiety, trauma, relationships, medication — and I'll find you the best fit in {$ctx}.";
            } else {
                $resp .= " Tell me the main thing you want support for and I'll find you the right match.";
            }
            return $resp;
        }

        // ── More info about a provider ────────────────────────────────────────
        if (preg_match('/tell me (more|about)|more (info|details|about)|what about/i', $msg_lower)) {
            if ($current) {
                $name  = $current['name'] ?? 'this provider';
                $url   = $current['url'] ?? '#';
                $specs = !empty($current['specialties']) ? implode(', ', array_slice($current['specialties'], 0, 2)) : '';
                $pcity = $current['city'] ?? '';
                $lic   = $current['license_type'] ?? '';
                $resp  = "[**{$name}**]({$url})";
                if ($specs) $resp .= " specializes in {$specs}";
                if ($pcity) $resp .= ($specs ? ", based in {$pcity}" : " is in {$pcity}");
                if ($lic)   $resp .= " ({$lic})";
                $resp .= ". Their profile has full contact info and how to book.";
                return $resp;
            }
            return "Click any provider's name to open their full profile — that's where you'll find their specialties, credentials, and how to reach them.";
        }

        // ── Cost / insurance ──────────────────────────────────────────────────
        if (preg_match('/cost|price|insurance|afford|pay|fee|how much|accept/i', $msg_lower)) {
            if ($current) {
                $name = $current['name'] ?? 'this provider';
                $url  = $current['url'] ?? '#';
                return "Rates and insurance vary by practice. Head to [**{$name}**]({$url})'s profile and contact them directly about your specific plan.";
            }
            return "Each practice handles pricing and insurance independently. Click any provider's name and reach out to them directly about your plan.";
        }

        // ── Availability / booking ────────────────────────────────────────────
        if (preg_match('/available|appointment|book|schedule|open|when can|soon/i', $msg_lower)) {
            if ($current) {
                $name = $current['name'] ?? 'this provider';
                $url  = $current['url'] ?? '#';
                return "To book with [**{$name}**]({$url}), visit their profile for contact info — most accept new patients by phone or email. Want me to pull up a different option?";
            }
            return "Availability is set by each practice. Click a provider's name to get their contact info and request a first appointment.";
        }

        // ── Thanks / goodbye ─────────────────────────────────────────────────
        if (preg_match('/thank|thanks|thx|bye|goodbye|appreciate/i', $msg_lower)) {
            return "Glad I could help! Come back anytime you need to find a provider.";
        }

        // ── Social acknowledgment (ok / got it / sounds good) ────────────────
        if (preg_match('/^(?:ok|okay|got it|sounds good|alright|sure|cool|great|perfect|nice|yep|yup)\s*[.!]?$/iu', $msg_lower)) {
            if ($current && !empty($current['name'])) {
                $name = $current['name'];
                $url  = $current['url'] ?? '#';
                return "Whenever you're ready — I can compare options, pull up more on [**{$name}**]({$url}), or search for something different.";
            }
            if ($specialty || $city) {
                $ctx = $specialty && $city ? "{$specialty} in {$city}" : ($specialty ?: $city);
                return "Still here! Want me to narrow the {$ctx} results, or are you looking for something different?";
            }
            return "What would you like to do next? I can search for a provider, get details on someone, or explore a different category.";
        }

        // ── Returning user greeting ───────────────────────────────────────────
        if (preg_match('/^(hi|hello|hey|howdy)\b/i', $msg_lower) && $has_prior) {
            if ($specialty) {
                $resp = "Hey! You were looking at {$specialty} providers";
                if ($city) $resp .= " in {$city}";
                $resp .= ". Want to pick up where you left off, or search for something new?";
                return $resp;
            }
            return "Hey! What are you looking for today? Tell me a specialty or city and I'll find you options.";
        }

        // ── Refine / explore more ─────────────────────────────────────────────
        if (preg_match('/different|another|other|else|more options|near/i', $msg_lower) && $has_prior) {
            if ($specialty) {
                $resp = "Sure — what do you want to change?";
                if ($city) $resp .= " Currently showing {$specialty} in {$city}.";
                $resp .= " Say a different city, a different specialty like trauma or ADHD, or a credential type like LCSW or psychiatrist.";
                return $resp;
            }
            return "What type of provider are you looking for, and in which city?";
        }

        // ── Generic catch-all ─────────────────────────────────────────────────
        if ($has_prior && $has_history) {
            if ($specialty || $city) {
                $ctx = $specialty && $city ? "{$specialty} in {$city}" : ($specialty ?: $city);
                return "Still on {$ctx}? Tell me what you need — I can narrow by insurance, city, therapy style, or pull up a specific provider.";
            }
            return "What would you like to do next? I can search for providers, show details on someone specific, or try a different category.";
        }

        return null;
    }

    private function looks_like_negative_search_refinement($message, $prior_context = array()) {
        $msg = strtolower(trim((string) $message));
        if ($msg === '') {
            return false;
        }

        $has_search_context = !empty($prior_context['recent_providers'])
            || !empty($prior_context['specialty'])
            || !empty($prior_context['primary_category'])
            || !empty($prior_context['primary_category_name']);

        if (!$has_search_context) {
            return false;
        }

        return (bool) preg_match(
            '/\b(?:i\s+)?(?:do\s+not|don\'t|dont|did\s+not|didn\'t|didnt|no)\s+(?:need|want|like|mean|asked?\s+for|look(?:ing)?\s+for)\b|' .
            '\b(?:not|no)\s+(?:that|this|those|general\s+counsel(?:ing|ling|or)?|counsel(?:ing|ling|or)?|therapy|therapist|provider|option|one)\b|' .
            '\b(?:that|this|those)\s+(?:is|are|was|were)\s+not\s+(?:what\s+)?(?:i\s+)?(?:need|want|meant|asked?\s+for|look(?:ing)?\s+for)\b|' .
            '\b(?:eso|esa|ese|estos|estas)\s+no\s+(?:es|son)\s+lo\s+que\s+(?:necesito|quiero|busco)\b|' .
            '\bno\s+(?:necesito|quiero|busco)\b/u',
            $msg
        );
    }

    private function has_explicit_search_replacement($message, $result = array()) {
        $msg = strtolower(trim((string) $message));
        if ($msg === '') {
            return false;
        }

        $has_contrast = (bool) preg_match(
            '/\b(?:but|instead|rather|except|actually|now|pero|en\s+cambio|sino|mejor|ahora)\b|' .
            '[.?!]\s*(?:i\s+)?(?:need|want|looking\s+for|find|show|busco|necesito|quiero)\b/iu',
            $msg
        );
        if (!$has_contrast) {
            return false;
        }

        $criteria = $result['criteria'] ?? array();
        if (!empty($criteria['explicit_specialty']) ||
            !empty($criteria['explicit_primary_category']) ||
            !empty($criteria['explicit_city']) ||
            !empty($criteria['explicit_provider_type'])) {
            return true;
        }

        return (bool) preg_match(
            '/\b(?:need|want|looking\s+for|find|show|busco|necesito|quiero|mu[eé]strame|mostrar|encuentra)\b.*' .
            '\b(?:couples?|relationship|marriage|family|general\s+counsel(?:ing|ling|or)?|anxiety|depression|trauma|grief|stress|therap(?:y|ist)|counsel(?:ing|or)|psychologist|psychiatrist|charlotte|raleigh|durham|pareja|matrimonio|familia|terapia\s+de\s+pareja|terapeuta|psic[oó]logo|psiquiatra|categor[ií]a|ciudad)\b/iu',
            $msg
        );
    }

    private function should_backfill_search_providers($message, $result, $updated_context, $negative_search_refinement = false) {
        if (($result['intent'] ?? '') !== MyHH_Mia_Intent_Engine::INTENT_CONSUMER_SEARCH) {
            return false;
        }

        if ($negative_search_refinement && !$this->has_explicit_search_replacement($message, $result)) {
            return false;
        }

        if (!empty($result['criteria']['context_shifted'])) {
            return false;
        }

        return !empty($updated_context['recent_providers']);
    }

    private function build_negative_search_refinement_response($message, $prior_context, $lang = 'en') {
        $msg = strtolower(trim((string) $message));
        $specialty = $prior_context['specialty'] ?? $prior_context['primary_category_name'] ?? '';
        $city = $prior_context['city'] ?? '';

        if ($lang === 'es') {
            $scope = $specialty ? " {$specialty}" : '';
            $place = $city ? " en {$city}" : '';
            return "Entendido - no buscare mas{$scope}{$place} con ese filtro. Dime lo que prefieres en su lugar, por ejemplo ansiedad, trauma, parejas, medicacion, un terapeuta especifico, o una ciudad diferente.";
        }

        $excluded = '';
        if (preg_match('/\b(?:general\s+counsel(?:ing|ling|or)?|counsel(?:ing|ling|or)?|therapy|therapist|provider|option)\b/i', $msg, $match)) {
            $excluded = trim($match[0]);
        } elseif ($specialty) {
            $excluded = $specialty;
        }

        $scope = $excluded ? " {$excluded}" : " that";
        $place = $city ? " in {$city}" : '';
        return "Got it - I won't keep searching for{$scope}{$place}. Tell me what you want instead, like anxiety, trauma, couples therapy, medication support, a different provider type, or a different city.";
    }

    /**
     * Format providers as a smart fallback when Grok API is unavailable.
     * Provides actual search results in a conversational, AI-like format.
     */
    private function all_providers_match_requested_city($providers, $city) {
        if (trim((string) $city) === '') return true;
        foreach ($providers as $provider) {
            if (strcasecmp(trim((string) ($provider['city'] ?? '')), trim((string) $city)) !== 0) return false;
        }
        return true;
    }

    private function format_providers_fallback($result, $original_message) {
        $providers = $result['providers'] ?? array();
        $criteria = $result['criteria'] ?? array();
        $specialty = $criteria['specialty'] ?? $criteria['primary_category_name'] ?? '';
        $city = $criteria['city'] ?? '';
        $count = count($providers);
        $detected_lang = $result['detected_language'] ?? 'en';
        $location_note = '';
        if (!$this->all_providers_match_requested_city($providers, $city)) {
            $location_note = $detected_lang === 'es'
                ? "Algunas opciones estan fuera de **{$city}**; no todas son coincidencias en esa ciudad. La ciudad real aparece en cada perfil."
                : "Some options are outside **{$city}**; these are not all matches in your requested city. Each profile shows its actual city.";
            $city = '';
        }

        $preference_gap_notes = array();
        foreach (array('modality' => 'modalities', 'language_preference' => 'languages', 'insurance' => 'insurance_accepted', 'session_type' => 'session_type') as $criterion => $provider_field) {
            $requested_value = trim((string) ($criteria[$criterion] ?? ''));
            if ($requested_value === '' || empty($providers)) {
                continue;
            }

            $has_profile_evidence = false;
            foreach ($providers as $provider) {
                $documented_values = $provider[$provider_field] ?? array();
                $documented_values = is_array($documented_values) ? $documented_values : array($documented_values);
                foreach ($documented_values as $documented_value) {
                    $documented_value = trim((string) $documented_value);
                    $session_supported = $criterion === 'session_type' && (
                        strcasecmp($documented_value, $requested_value) === 0 ||
                        (strcasecmp($documented_value, 'Hybrid') === 0 && in_array(strtolower($requested_value), array('virtual', 'in-person'), true))
                    );
                    if ($session_supported || stripos($documented_value, $requested_value) !== false) {
                        $has_profile_evidence = true;
                        break 2;
                    }
                }
            }

            if (!$has_profile_evidence) {
                $preference_gap_notes[$criterion] = $requested_value;
            }
        }

        if ($count === 0) {
            if ($specialty && $city) {
                return "I searched for {$specialty} providers in {$city}, but I didn't find any matches in our directory yet.\n\nWould you like me to search in a nearby city, or would you like to [browse all providers](" . (function_exists('myhh_get_directory_url') ? myhh_get_directory_url() : '/listings/') . ")?";
            }
            return "I couldn't find any providers matching your search. Could you tell me more about what you're looking for? For example, what type of service or which city?";
        }

        $listings_url = function_exists('myhh_get_directory_url') ? myhh_get_directory_url() : '/listings/';
        $top = $providers[0];
        $top_name = $this->get_safe_provider_display_name($top, 1, $detected_lang);
        $top_url = $top['url'] ?? '#';
        $top_city = $top['city'] ?? '';
        $top_license = $top['license_type'] ?? '';
        $top_specialties = !empty($top['specialties']) && is_array($top['specialties'])
            ? array_values(array_filter(array_slice($top['specialties'], 0, 2)))
            : array();

        $top_reasons = array();
        if ($specialty && !empty($top_specialties)) {
            $top_reasons[] = $detected_lang === 'es'
                ? 'sus especialidades incluyen ' . implode(', ', $top_specialties)
                : 'their specialties include ' . implode(', ', $top_specialties);
        } elseif (!empty($top_specialties)) {
            $top_reasons[] = $detected_lang === 'es'
                ? 'trabaja en ' . implode(', ', $top_specialties)
                : 'they work in ' . implode(', ', $top_specialties);
        }
        if ($top_city) {
            $top_reasons[] = $detected_lang === 'es'
                ? "esta en {$top_city}"
                : "they're in {$top_city}";
        }
        if ($top_license) {
            $top_reasons[] = $detected_lang === 'es'
                ? "credencial {$top_license}"
                : "{$top_license} credential";
        }
        if (!empty($top['verification_status'])) {
            $top_reasons[] = strtolower($this->format_verification_label($top['verification_status'], $detected_lang));
        } elseif (!empty($top['claimed'])) {
            $top_reasons[] = $detected_lang === 'es' ? 'perfil reclamado' : 'claimed profile';
        }

        $alternatives = array_slice($providers, 1, 3);
        $more_count = max(0, $count - 4);

        if ($detected_lang === 'es') {
            $scope = $specialty && $city
                ? "**{$count} opciones de {$specialty} en {$city}**"
                : ($specialty ? "**{$count} opciones de {$specialty}**" : ($city ? "**{$count} opciones en {$city}**" : "**{$count} opciones**"));
            $msg = "Ya tengo {$scope}. Empezaria con [**{$top_name}**]({$top_url})";
            if (!empty($top_reasons)) {
                $msg .= " — " . implode(', ', array_slice($top_reasons, 0, 3));
            }
            $msg .= ".\n";
            if (!empty($alternatives)) {
                $msg .= "\nTambien miraria:\n";
                foreach ($alternatives as $provider_index => $provider) {
                    $provider_name = $this->get_safe_provider_display_name($provider, $provider_index + 2, 'es');
                    $provider_url = $provider['url'] ?? '#';
                    $provider_bits = array();
                    if (!empty($provider['specialties']) && is_array($provider['specialties'])) {
                        $provider_bits[] = implode(', ', array_slice($provider['specialties'], 0, 2));
                    }
                    if (!empty($provider['city'])) {
                        $provider_bits[] = $provider['city'];
                    }
                    if (!empty($provider['license_type'])) {
                        $provider_bits[] = $provider['license_type'];
                    }
                    $msg .= "• [**{$provider_name}**]({$provider_url})";
                    if (!empty($provider_bits)) {
                        $msg .= " — " . implode(' • ', array_slice($provider_bits, 0, 2));
                    }
                    $msg .= "\n";
                }
            }
            if ($more_count > 0) {
                $msg .= "\nTambien puedes [ver las {$count} opciones]({$listings_url}) si quieres comparar mas.";
            }
            if (!empty($preference_gap_notes['modality'])) {
                $msg .= "\n\n_Ningun perfil de este grupo indica explicitamente **{$preference_gap_notes['modality']}**; estas son opciones mas amplias, no coincidencias confirmadas para esa modalidad._";
            }
            if (!empty($preference_gap_notes['language_preference'])) {
                $msg .= "\n\n_Ningun perfil de este grupo confirma explicitamente atencion en **{$preference_gap_notes['language_preference']}**; verifica el idioma directamente con el proveedor._";
            }
            if (!empty($preference_gap_notes['insurance'])) {
                $msg .= "\n\n_Ningun perfil de este grupo confirma explicitamente **{$preference_gap_notes['insurance']}**; verifica la cobertura con el proveedor y tu plan._";
            }
            if (!empty($preference_gap_notes['session_type'])) {
                $msg .= "\n\n_Ningun perfil de este grupo confirma sesiones **{$preference_gap_notes['session_type']}**; verifica la disponibilidad con el proveedor._";
            }
            $msg .= "\n\nSi quieres, lo reduzco por seguro, ciudad, estilo de terapia o disponibilidad.";
            return trim(($location_note !== '' ? $location_note . "\n\n" : '') . $msg);
        }

        $scope = $specialty && $city
            ? "**{$count} {$specialty} matches in {$city}**"
            : ($specialty ? "**{$count} {$specialty} matches**" : ($city ? "**{$count} matches in {$city}**" : "**{$count} strong matches**"));

        // If the user named a specific provider but we only found fuzzy matches,
        // acknowledge the misspelling with a "did you mean?" prefix
        $provider_name = $criteria['provider_name'] ?? '';
        $is_fuzzy_match = !empty($provider_name) && !empty($result['fallback_used']);
        if ($is_fuzzy_match) {
            $msg = "I couldn't find an exact match for **{$provider_name}**. Did you mean:\n\n";
            foreach ($providers as $idx => $p) {
                $p_name = $this->get_safe_provider_display_name($p, $idx + 1, 'en');
                $p_url = $this->sanitize_provider_url($p['url'] ?? '#');
                $p_bits = array();
                if (!empty($p['city'])) $p_bits[] = $p['city'];
                if (!empty($p['license_type'])) $p_bits[] = $p['license_type'];
                if (!empty($p['specialties']) && is_array($p['specialties'])) $p_bits[] = implode(', ', array_slice($p['specialties'], 0, 2));
                $msg .= "• [**{$p_name}**]({$p_url})";
                if (!empty($p_bits)) $msg .= ' — ' . implode(' • ', array_slice($p_bits, 0, 3));
                $msg .= "\n";
            }
            $msg .= "\nIf none of these are right, try the **full name** and **city** (for example: \"Dr. Angela Green in Raleigh\"). Or [browse all providers]({$listings_url}).";
            return trim($msg);
        }

        $msg = "I found {$scope}. I'd start with [**{$top_name}**]({$top_url})";
        if (!empty($top_reasons)) {
            $msg .= " — " . implode(', ', array_slice($top_reasons, 0, 3));
        }
        $msg .= ".\n";
        if (!empty($alternatives)) {
            $msg .= "\nAlso worth a look:\n";
                foreach ($alternatives as $provider_index => $provider) {
                    $provider_name = $this->get_safe_provider_display_name($provider, $provider_index + 2, 'en');
                    $provider_url = $provider['url'] ?? '#';
                $provider_bits = array();
                if (!empty($provider['specialties']) && is_array($provider['specialties'])) {
                    $provider_bits[] = implode(', ', array_slice($provider['specialties'], 0, 2));
                }
                if (!empty($provider['city'])) {
                    $provider_bits[] = $provider['city'];
                }
                if (!empty($provider['license_type'])) {
                    $provider_bits[] = $provider['license_type'];
                }
                $msg .= "• [**{$provider_name}**]({$provider_url})";
                if (!empty($provider_bits)) {
                    $msg .= " — " . implode(' • ', array_slice($provider_bits, 0, 2));
                }
                $msg .= "\n";
            }
        }
        if ($more_count > 0) {
            $msg .= "\nYou can also [view all {$count} matches]({$listings_url}) if you want a wider comparison.";
        }
        if (!empty($preference_gap_notes['modality'])) {
            $msg .= "\n\n_No current profile in this result set explicitly lists **{$preference_gap_notes['modality']}**; these are broader options, not confirmed modality matches._";
        }
        if (!empty($preference_gap_notes['language_preference'])) {
            $msg .= "\n\n_No current profile in this result set explicitly confirms **{$preference_gap_notes['language_preference']}**; please verify language availability directly with the provider._";
        }
        if (!empty($preference_gap_notes['insurance'])) {
            $msg .= "\n\n_No current profile in this result set explicitly confirms **{$preference_gap_notes['insurance']}**; please verify coverage with the provider and your plan._";
        }
        if (!empty($preference_gap_notes['session_type'])) {
            $msg .= "\n\n_No current profile in this result set explicitly confirms **{$preference_gap_notes['session_type']}** sessions; please verify availability with the provider._";
        }

        // Broad browse nudge — when user didn't specify specialty/city/category
        if (!empty($criteria['is_broad_browse'])) {
            $msg .= "\n\n_You can narrow results by telling me a **specialty** (like anxiety, depression), a **city** (like Raleigh, Charlotte), or a **category** (like Couples Therapy, Psychiatry)._";
        } else {
            $msg .= "\n\nWant me to narrow this? Tell me your insurance, a specific city, or what kind of support you're looking for.";
        }

        return trim(($location_note !== '' ? $location_note . "\n\n" : '') . $msg);
    }

    private function build_provider_search_key($criteria) {
        return md5(implode('|', array(
            strtolower((string) ($criteria['primary_category'] ?? '')),
            strtolower((string) ($criteria['specialty'] ?? '')),
            strtolower((string) ($criteria['city'] ?? '')),
            strtolower((string) ($criteria['provider_type'] ?? '')),
            strtolower((string) ($criteria['modality'] ?? '')),
            strtolower((string) ($criteria['language_preference'] ?? '')),
            strtolower((string) ($criteria['insurance'] ?? '')),
            strtolower((string) ($criteria['session_type'] ?? '')),
        )));
    }

    private function build_provider_exposure_payload($raw_exposure, $providers = array(), $criteria = array(), $intent = '', $response_type = '') {
        $payload = is_array($raw_exposure) ? $raw_exposure : array();
        $providers = is_array($providers) ? array_values($providers) : array();
        $criteria = is_array($criteria) ? $criteria : array();

        if (empty($payload['search_key'])) {
            $payload['search_key'] = $this->build_provider_search_key($criteria);
        }

        if (empty($payload['provider_ids_shown']) && !empty($providers)) {
            $payload['provider_ids_shown'] = array_values(array_filter(array_map(function($provider) {
                return intval($provider['id'] ?? 0);
            }, array_slice($providers, 0, 4))));
        }

        if (empty($payload['rank_scores']) && !empty($providers)) {
            $payload['rank_scores'] = array_values(array_filter(array_map(function($provider) {
                $provider_id = intval($provider['id'] ?? 0);
                if ($provider_id <= 0) {
                    return null;
                }
                return array(
                    'id' => $provider_id,
                    'score' => round(floatval($provider['rank_score'] ?? 0), 3),
                );
            }, array_slice($providers, 0, 4))));
        }

        $payload['criteria'] = array(
            'primary_category' => $criteria['primary_category'] ?? ($payload['criteria']['primary_category'] ?? ''),
            'specialty' => $criteria['specialty'] ?? ($payload['criteria']['specialty'] ?? ''),
            'city' => $criteria['city'] ?? ($payload['criteria']['city'] ?? ''),
            'provider_type' => $criteria['provider_type'] ?? ($payload['criteria']['provider_type'] ?? ''),
            'modality' => $criteria['modality'] ?? ($payload['criteria']['modality'] ?? ''),
            'language_preference' => $criteria['language_preference'] ?? ($payload['criteria']['language_preference'] ?? ''),
            'insurance' => $criteria['insurance'] ?? ($payload['criteria']['insurance'] ?? ''),
            'session_type' => $criteria['session_type'] ?? ($payload['criteria']['session_type'] ?? ''),
        );
        $payload['intent'] = $intent ?: ($payload['intent'] ?? '');
        $payload['response_type'] = $response_type ?: ($payload['response_type'] ?? '');
        $payload['timestamp'] = $payload['timestamp'] ?? gmdate('Y-m-d\TH:i:s\Z');
        $payload['candidate_count'] = intval($payload['candidate_count'] ?? count($providers));
        $payload['recently_seen'] = array_values(array_filter(array_map('intval', $payload['recently_seen'] ?? array())));

        if (empty($payload['provider_ids_shown']) && empty($payload['rank_scores']) && empty($payload['criteria']['primary_category']) && empty($payload['criteria']['specialty']) && empty($payload['criteria']['city']) && empty($payload['criteria']['provider_type'])) {
            return array();
        }

        return $payload;
    }

    private function append_provider_exposure_log($payload) {
        if (empty($payload) || !is_array($payload)) {
            return;
        }

        $log_file = WP_CONTENT_DIR . '/mia-provider-exposure.jsonl';
        if (file_exists($log_file) && filesize($log_file) > 5 * MB_IN_BYTES) {
            @unlink($log_file . '.1');
            @rename($log_file, $log_file . '.1');
        }
        @file_put_contents($log_file, json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Persistent QA logging.
     *
     * Appends a JSON-line entry to wp-content/mia-qa-log.jsonl.
     * One JSON object per line for easy parsing.
     */
    private function log_interaction($data) {
        $log_dir = WP_CONTENT_DIR;
        $log_file = $log_dir . '/mia-qa-log.jsonl';
        $provider_exposure = $this->build_provider_exposure_payload(
            $data['provider_exposure'] ?? array(),
            $data['providers'] ?? array(),
            $data['criteria'] ?? array(),
            $data['intent'] ?? '',
            $data['response_type'] ?? ''
        );

        $entry = array(
            'ts'              => gmdate('Y-m-d\TH:i:s\Z'),
            'intent'          => $data['intent'] ?? '',
            'criteria'        => $data['criteria'] ?? null,
            'provider_count'  => $data['provider_count'] ?? 0,
            'provider_exposure' => $provider_exposure,
            'crisis_override' => $data['crisis_override'] ?? false,
            'response_type'   => $data['response_type'] ?? '',
            'fallback_used'   => $data['fallback_used'] ?? false,
            'near_me'         => $data['near_me'] ?? false,
            'message_length'  => strlen($data['input'] ?? ''),
        );

        // Append as JSON line (one object per line)
        @file_put_contents($log_file, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
        if (!empty($provider_exposure)) {
            $this->append_provider_exposure_log(array(
                'ts' => $entry['ts'],
                'intent' => $entry['intent'],
                'response_type' => $entry['response_type'],
                'provider_count' => $entry['provider_count'],
                'provider_exposure' => $provider_exposure,
            ));
        }
    }
    
    /**
     * Render the floating widget
     */
    public function render_widget() {
        // All CSS, JS, and miaConfig are rendered inline below — no wp_enqueue_scripts needed.

        // Check if Mia is enabled
        if (!$this->is_enabled()) {
            return;
        }
        ?>
        <div id="mia-widget" class="mia-widget" style="display: none;">
            <!-- Widget Toggle Button -->
            <button id="mia-toggle" class="mia-toggle" aria-label="Open Mia chat">
                <div class="mia-toggle__avatar">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
                <span class="mia-toggle__label">Ask Mia</span>
            </button>
            
            <!-- Chat Window -->
            <div id="mia-chat-window" class="mia-chat-window" style="display: none;">
                <!-- Header -->
                <div class="mia-chat__header">
                    <div class="mia-chat__header-info">
                        <div class="mia-chat__avatar">
                            <svg width="20" height="20" fill="none" stroke="white" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="mia-chat__name">Mia</div>
                            <div class="mia-chat__status">
                                <span class="mia-chat__status-dot"></span>
                                Online
                            </div>
                        </div>
                    </div>
                    <button id="mia-new-chat" class="mia-chat__reset" type="button" aria-label="Start a new chat">New chat</button>
                    <button id="mia-close" class="mia-chat__close" aria-label="Close chat">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <!-- Messages -->
                <div id="mia-messages" class="mia-chat__messages">
                    <div class="mia-message mia-message--bot">
                        <div class="mia-message__content">
                            Hi, I'm Mia. Tell me what kind of support you're looking for, and I'll guide you to the right fit.
                        </div>
                    </div>
                </div>
                
                <!-- Quick-start chips -->
                <div class="mia-chat__chips" id="mia-chat-chips">
                    <button class="mia-chip mia-chip--geo" onclick="widgetNearMe()" aria-label="Find providers near me">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Providers near me
                    </button>
                </div>
                
                <!-- Input -->
                <div class="mia-chat__input-container">
                    <input type="text" id="mia-input" class="mia-chat__input" placeholder="Type your message..." autocomplete="off">
                    <button id="mia-send" class="mia-chat__send" aria-label="Send message">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <style>
            .mia-widget {
                position: fixed;
                bottom: 24px;
                right: 24px;
                z-index: 9999;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            
            .mia-toggle {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 14px 20px;
                background: linear-gradient(135deg, #0A74DA 0%, #4F46E5 100%);
                color: white;
                border: none;
                border-radius: 50px;
                cursor: pointer;
                box-shadow: 0 8px 24px rgba(10, 116, 218, 0.3);
                transition: all 0.3s;
            }
            
            .mia-toggle:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 32px rgba(10, 116, 218, 0.4);
            }
            
            .mia-toggle__avatar {
                width: 36px;
                height: 36px;
                background: rgba(255,255,255,0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .mia-toggle__label {
                font-weight: 600;
                font-size: 0.95rem;
            }
            
            .mia-chat-window {
                position: absolute;
                bottom: 70px;
                right: 0;
                width: 380px;
                max-width: calc(100vw - 48px);
                background: white;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.15);
                overflow: hidden;
                display: flex;
                flex-direction: column;
                max-height: 600px;
            }
            
            .mia-chat__header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 16px 20px;
                background: linear-gradient(135deg, #0A74DA 0%, #4F46E5 100%);
                color: white;
            }
            
            .mia-chat__header-info {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .mia-chat__avatar {
                width: 40px;
                height: 40px;
                background: rgba(255,255,255,0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .mia-chat__name {
                font-weight: 700;
                font-size: 1rem;
            }
            
            .mia-chat__status {
                font-size: 0.75rem;
                display: flex;
                align-items: center;
                gap: 4px;
            }
            
            .mia-chat__status-dot {
                width: 6px;
                height: 6px;
                background: #10B981;
                border-radius: 50%;
            }

            .mia-chat__reset {
                margin-left: auto;
                margin-right: 8px;
                background: rgba(255,255,255,0.14);
                border: 1px solid rgba(255,255,255,0.22);
                color: white;
                border-radius: 999px;
                padding: 7px 12px;
                cursor: pointer;
                font-size: 0.76rem;
                font-weight: 600;
                transition: all 0.2s;
            }

            .mia-chat__reset:hover {
                background: rgba(255,255,255,0.2);
            }
            
            .mia-chat__close {
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                padding: 4px;
                opacity: 0.8;
                transition: opacity 0.2s;
            }
            
            .mia-chat__close:hover {
                opacity: 1;
            }
            
            .mia-chat__messages {
                flex: 1;
                overflow-y: auto;
                padding: 20px;
                display: flex;
                flex-direction: column;
                gap: 12px;
                min-height: 300px;
                max-height: 400px;
            }
            
            .mia-message {
                max-width: 85%;
            }
            
            .mia-message--bot {
                align-self: flex-start;
            }
            
            .mia-message--user {
                align-self: flex-end;
            }
            
            .mia-message__content {
                padding: 12px 16px;
                border-radius: 16px;
                font-size: 0.9rem;
                line-height: 1.5;
            }
            
            .mia-message--bot .mia-message__content {
                background: #F3F4F6;
                color: #1F2937;
                border-bottom-left-radius: 4px;
            }
            
            .mia-message--user .mia-message__content {
                background: linear-gradient(135deg, #0A74DA 0%, #4F46E5 100%);
                color: white;
                border-bottom-right-radius: 4px;
            }
            
            .mia-chat__input-container {
                display: flex;
                padding: 16px;
                gap: 10px;
                border-top: 1px solid #E5E7EB;
            }
            
            .mia-chat__input {
                flex: 1;
                padding: 12px 16px;
                border: 2px solid #E5E7EB;
                border-radius: 12px;
                font-size: 0.9rem;
                outline: none;
                transition: border-color 0.2s;
            }
            
            .mia-chat__input:focus {
                border-color: #0A74DA;
            }
            
            .mia-chat__send {
                width: 44px;
                height: 44px;
                background: linear-gradient(135deg, #0A74DA 0%, #4F46E5 100%);
                border: none;
                border-radius: 12px;
                color: white;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: transform 0.2s;
            }
            
            .mia-chat__send:hover {
                transform: scale(1.05);
            }
            
            .mia-message__typing {
                display: flex;
                gap: 4px;
                padding: 12px 16px;
            }
            
            .mia-message__typing span {
                width: 8px;
                height: 8px;
                background: #9CA3AF;
                border-radius: 50%;
                animation: typing 1.4s infinite both;
            }
            
            .mia-message__typing span:nth-child(2) { animation-delay: 0.2s; }
            .mia-message__typing span:nth-child(3) { animation-delay: 0.4s; }
            
            @keyframes typing {
                0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
                40% { transform: scale(1); opacity: 1; }
            }
            
            @media (max-width: 480px) {
                .mia-widget {
                    bottom: 16px;
                    right: 16px;
                }
                
                .mia-toggle__label {
                    display: none;
                }
                
                .mia-chat-window {
                    width: calc(100vw - 32px);
                    right: -8px;
                    bottom: 60px;
                    max-height: 70vh;
                }
            }
            
            /* Quick-start chips */
            .mia-chat__chips {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                padding: 8px 16px 4px;
                border-top: 1px solid #F3F4F6;
            }
            
            .mia-chip {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 6px 12px;
                background: #F0F7FF;
                color: #0A74DA;
                border: 1px solid #BFDBFE;
                border-radius: 20px;
                font-size: 0.78rem;
                font-weight: 500;
                cursor: pointer;
                transition: background 0.18s, transform 0.15s;
                white-space: nowrap;
            }
            
            .mia-chip:hover {
                background: #DBEAFE;
                transform: translateY(-1px);
            }
            
            .mia-chip--geo svg {
                flex-shrink: 0;
            }
            
            /* Secondary widget state - for homepage where hero is primary */
            .mia-widget--secondary {
                opacity: 0.7;
                transition: opacity 0.3s ease;
            }
            .mia-widget--secondary:hover {
                opacity: 1;
            }
            .mia-widget--secondary .mia-toggle {
                transform: scale(0.9);
            }
            .mia-widget--secondary .mia-toggle__label {
                font-size: 0.75rem;
            }
        </style>
        
        <script>
            // Mia configuration — output inline so it's always available
            var miaConfig = Object.assign({}, window.miaConfig || {}, <?php echo json_encode(array(
                'ajaxUrl'     => admin_url('admin-ajax.php'),
                'nonce'       => wp_create_nonce('mia_chat_nonce'),
                'homeUrl'     => home_url(),
                'listingsUrl' => function_exists('myhh_get_directory_url') ? myhh_get_directory_url() : '/listings/',
                'isAdmin'     => current_user_can('manage_options'),
            )); ?>);
        </script>
        <script>
            // Mia Chat Widget JavaScript
            (function() {
                const widget = document.getElementById('mia-widget');
                const toggle = document.getElementById('mia-toggle');
                const chatWindow = document.getElementById('mia-chat-window');
                const closeBtn = document.getElementById('mia-close');
                const newChatBtn = document.getElementById('mia-new-chat');
                const messagesContainer = document.getElementById('mia-messages');
                const input = document.getElementById('mia-input');
                const sendBtn = document.getElementById('mia-send');
                
                // =============================================
                // CONVERSATION STATE
                // =============================================
                let conversationHistory = [];
                let priorContext = {};
                let isOpen = false;

                function recordProviderProfileClick(anchor) {
                    if (!anchor || !miaConfig.ajaxUrl || !miaConfig.nonce) return;
                    let profileUrl;
                    try {
                        profileUrl = new URL(anchor.href, miaConfig.homeUrl || window.location.origin);
                    } catch (error) {
                        return;
                    }
                    const homeUrl = new URL(miaConfig.homeUrl || window.location.origin);
                    if (profileUrl.origin !== homeUrl.origin || !/^\/listings\/[^/]+\/?$/i.test(profileUrl.pathname)) return;

                    const exposure = (window.MiaHero && window.MiaHero.latestProviderExposure) || window.MiaLatestProviderExposure || {};
                    const container = anchor.closest('#mia-live-overlay, #mia-hero-messages, #mia-messages');
                    if (!container) return;
                    const links = Array.from(container.querySelectorAll('a[href*="/listings/"]')).filter(function(link, index, allLinks) {
                        return /^\/listings\/[^/]+\/?$/i.test(new URL(link.href, homeUrl).pathname) && allLinks.findIndex(function(item) {
                            return new URL(item.href, homeUrl).pathname === new URL(link.href, homeUrl).pathname;
                        }) === index;
                    });
                    const derivedRank = Math.max(1, links.indexOf(anchor) + 1);
                    const rank = Math.max(1, Math.min(4, parseInt(anchor.dataset.miaProviderRank, 10) || derivedRank));
                    const exposedIds = Array.isArray(exposure.provider_ids_shown) ? exposure.provider_ids_shown : [];
                    const providerId = Math.max(0, parseInt(anchor.dataset.miaProviderId, 10) || parseInt(exposedIds[rank - 1], 10) || 0);
                    const surface = container.id === 'mia-messages' ? 'widget' : (container.id === 'mia-live-overlay' ? 'hero_voice' : 'hero_text');
                    const fields = {
                        action: 'mia_provider_outcome',
                        nonce: miaConfig.nonce,
                        event: 'profile_click',
                        provider_id: String(providerId),
                        profile_url: profileUrl.href,
                        rank: String(rank),
                        search_key: String(exposure.search_key || ''),
                        surface: surface
                    };

                    if (navigator.sendBeacon) {
                        const form = new FormData();
                        Object.keys(fields).forEach(function(key) { form.append(key, fields[key]); });
                        if (navigator.sendBeacon(miaConfig.ajaxUrl, form)) return;
                    }
                    fetch(miaConfig.ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams(fields),
                        keepalive: true,
                        credentials: 'same-origin'
                    }).catch(function() {});
                }

                if (!window.MiaProviderOutcomeListenerInstalled) {
                    window.MiaProviderOutcomeListenerInstalled = true;
                    document.addEventListener('click', function(event) {
                        const anchor = event.target && event.target.closest ? event.target.closest('a[href]') : null;
                        if (anchor) recordProviderProfileClick(anchor);
                    }, true);
                }

                // =============================================
                // LOCALSTORAGE PERSISTENCE (lightweight session continuity)
                // =============================================
                const MIA_STORAGE_KEY = 'mia_session_context';
                const MIA_STORAGE_HISTORY_KEY = 'mia_conversation_history';

                /**
                 * Load session context from localStorage
                 * This enables context carry-forward across page refreshes
                 */
                function loadSessionContext() {
                    try {
                        const stored = localStorage.getItem(MIA_STORAGE_KEY);
                        if (stored) {
                            const parsed = JSON.parse(stored);
                            // Only restore if less than 30 minutes old
                            if (parsed.timestamp && (Date.now() - parsed.timestamp) < 30 * 60 * 1000) {
                                return parsed.context || {};
                            }
                        }
                    } catch (e) {
                        // Silently fail if localStorage is unavailable
                    }
                    return {};
                }

                /**
                 * Save session context to localStorage
                 */
                function saveSessionContext() {
                    try {
                        const data = {
                            context: priorContext,
                            timestamp: Date.now()
                        };
                        localStorage.setItem(MIA_STORAGE_KEY, JSON.stringify(data));
                    } catch (e) {
                        // Silently fail if localStorage is unavailable
                    }
                }

                /**
                 * Load conversation history from localStorage
                 */
                function loadConversationHistory() {
                    try {
                        const stored = localStorage.getItem(MIA_STORAGE_HISTORY_KEY);
                        if (stored) {
                            const parsed = JSON.parse(stored);
                            // Only restore if less than 30 minutes old
                            if (parsed.timestamp && (Date.now() - parsed.timestamp) < 30 * 60 * 1000) {
                                return parsed.history || [];
                            }
                        }
                    } catch (e) {
                        // Silently fail
                    }
                    return [];
                }

                /**
                 * Save conversation history to localStorage
                 */
                function saveConversationHistory() {
                    try {
                        // Keep only last 20 messages to stay within storage limits
                        const trimmedHistory = conversationHistory.slice(-20);
                        const data = {
                            history: trimmedHistory,
                            timestamp: Date.now()
                        };
                        localStorage.setItem(MIA_STORAGE_HISTORY_KEY, JSON.stringify(data));
                    } catch (e) {
                        // Silently fail if localStorage is unavailable
                    }
                }

                // Initialize from localStorage on page load
                const savedContext = loadSessionContext();
                const savedHistory = loadConversationHistory();
                if (savedContext && Object.keys(savedContext).length > 0) {
                    priorContext = savedContext;
                }
                if (savedHistory && savedHistory.length > 0) {
                    conversationHistory = savedHistory;
                }
                
                // Bilingual UI text
                const uiText = {
                    en: {
                        askMia: 'Ask Mia',
                        online: 'Online',
                        placeholder: 'Type your message...',
                        greeting: "Hi, I'm Mia. Tell me what kind of support you're looking for, and I'll guide you to the right fit.",
                        error: 'Sorry, I had trouble processing that. Please try again.',
                        connectionError: 'Connection error. Please try again.'
                    },
                    es: {
                        askMia: 'Pregunta a Mia',
                        online: 'En línea',
                        placeholder: 'Escribe tu mensaje...',
                        greeting: 'Hola, soy Mia. Cuéntame qué tipo de apoyo buscas y te guío hacia la mejor opción.',
                        error: 'Lo siento, tuve un problema procesando eso. Por favor intenta de nuevo.',
                        connectionError: 'Error de conexión. Por favor intenta de nuevo.'
                    }
                };
                
                // Detect browser language
                function getBrowserLanguage() {
                    const lang = (navigator.language || navigator.userLanguage || 'en').toLowerCase();
                    return lang.startsWith('es') ? 'es' : 'en';
                }
                
                const browserLang = getBrowserLanguage();
                const t = uiText[browserLang] || uiText.en;

                function clearWidgetStorage() {
                    try {
                        localStorage.removeItem(MIA_STORAGE_KEY);
                        localStorage.removeItem(MIA_STORAGE_HISTORY_KEY);
                    } catch (e) {}
                }

                function resetWidgetConversation() {
                    conversationHistory = [];
                    priorContext = {};
                    clearWidgetStorage();
                    messagesContainer.innerHTML =
                        '<div class="mia-message mia-message--bot">' +
                            '<div class="mia-message__content">' + t.greeting + '</div>' +
                        '</div>';
                    const chipsBar = document.getElementById('mia-chat-chips');
                    if (chipsBar) chipsBar.style.display = 'flex';
                    input.value = '';
                    input.focus();
                }
                
                // =============================================
                // GEOLOCATION — Triangle service area (250-mile threshold)
                // =============================================
                const triangleCities = [
                    { name: 'Raleigh',        lat: 35.7796, lng: -78.6382 },
                    { name: 'Durham',         lat: 35.9940, lng: -78.8986 },
                    { name: 'Chapel Hill',    lat: 35.9132, lng: -79.0558 },
                    { name: 'Cary',           lat: 35.7915, lng: -78.7811 },
                    { name: 'Apex',           lat: 35.7327, lng: -78.8503 },
                    { name: 'Morrisville',    lat: 35.8235, lng: -78.8256 },
                    { name: 'Holly Springs',  lat: 35.6513, lng: -78.8336 },
                    { name: 'Fuquay-Varina',  lat: 35.5843, lng: -78.8000 },
                    { name: 'Wake Forest',    lat: 35.9799, lng: -78.5097 },
                    { name: 'Garner',         lat: 35.7113, lng: -78.6142 },
                    { name: 'Knightdale',     lat: 35.7968, lng: -78.4897 },
                    { name: 'Carrboro',       lat: 35.9101, lng: -79.0753 },
                ];
                
                function haversineDistance(lat1, lng1, lat2, lng2) {
                    const R = 3959;
                    const dLat = (lat2 - lat1) * Math.PI / 180;
                    const dLng = (lng2 - lng1) * Math.PI / 180;
                    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                              Math.sin(dLng/2) * Math.sin(dLng/2);
                    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                }
                
                function nearestCity(lat, lng) {
                    const MAX_DISTANCE = 250;
                    let best = null, bestDist = Infinity;
                    triangleCities.forEach(c => {
                        const d = haversineDistance(lat, lng, c.lat, c.lng);
                        if (d < bestDist) { bestDist = d; best = c.name; }
                    });
                    if (bestDist > MAX_DISTANCE) return null;
                    return { city: best, miles: Math.round(bestDist) };
                }
                
                // Show location permission prompt inside the chat (two-step flow)
                window.widgetNearMe = function() {
                    // Make sure the chat window is open first
                    if (!isOpen) {
                        isOpen = true;
                        chatWindow.style.display = 'flex';
                    }

                    // Hide chips bar once flow starts
                    const chipsBar = document.getElementById('mia-chat-chips');
                    if (chipsBar) chipsBar.style.display = 'none';

                    // Show the user's intent as a user message
                    addMessage('📍 Providers near me', 'user');

                    // Check if Geolocation API is available at all
                    if (!navigator.geolocation) {
                        addMessage("I can't use your location on this device, but I can continue this search if you type your city or area.", 'bot');
                        input.focus();
                        return;
                    }

                    // Show Mia's location-permission prompt as a bot message with Allow/Deny buttons
                    const promptDiv = document.createElement('div');
                    promptDiv.className = 'mia-message mia-message--bot';
                    promptDiv.innerHTML =
                        '<div class="mia-message__content" id="mia-geo-prompt">' +
                            '<p style="margin:0 0 10px;">I can continue this search with providers closest to you. Would you like to share your location?</p>' +
                            '<div style="display:flex;gap:8px;flex-wrap:wrap;">' +
                                '<button id="widget-geo-allow-btn" onclick="widgetGeoAllow()" ' +
                                    'style="padding:7px 16px;background:linear-gradient(135deg,#0A74DA,#4F46E5);color:white;border:none;border-radius:20px;font-size:0.8rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;transition:transform .15s,box-shadow .15s;" ' +
                                    'onmouseover="this.style.transform=\'scale(1.04)\';this.style.boxShadow=\'0 4px 14px rgba(10,116,218,0.45)\';" ' +
                                    'onmouseout="this.style.transform=\'scale(1)\';this.style.boxShadow=\'none\';">' +
                                    '<span>📍</span> Allow Location' +
                                '</button>' +
                                '<button id="widget-geo-deny-btn" onclick="widgetGeoDeny()" ' +
                                    'style="padding:7px 14px;background:#F3F4F6;color:#374151;border:1px solid #D1D5DB;border-radius:20px;font-size:0.8rem;font-weight:600;cursor:pointer;transition:background .15s;" ' +
                                    'onmouseover="this.style.background=\'#E5E7EB\';" ' +
                                    'onmouseout="this.style.background=\'#F3F4F6\';">' +
                                    'No Thanks' +
                                '</button>' +
                            '</div>' +
                        '</div>';
                    messagesContainer.appendChild(promptDiv);
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                };

                window.widgetGeoAllow = function() {
                    // Disable buttons immediately to prevent double-click
                    const allowBtn = document.getElementById('widget-geo-allow-btn');
                    const denyBtn  = document.getElementById('widget-geo-deny-btn');
                    if (allowBtn) { allowBtn.disabled = true; allowBtn.style.opacity = '0.6'; allowBtn.innerHTML = '<span>⏳</span> Detecting…'; }
                    if (denyBtn)  { denyBtn.style.display = 'none'; }

                    // Call the browser's native geolocation API — this triggers the permission dialog
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            const geoPrompt = document.getElementById('mia-geo-prompt');
                            const result = nearestCity(position.coords.latitude, position.coords.longitude);

                            if (!result) {
                                // Outside the Triangle service area
                                if (geoPrompt) geoPrompt.innerHTML = '<p style="margin:0;">It looks like you\'re outside our current service area. I can still continue this search if you type a city in the <strong>Raleigh-Durham Triangle</strong> area below.</p>';
                                input.focus();
                                return;
                            }

                            // Update prompt to success state in-place
                            if (geoPrompt) geoPrompt.innerHTML = '<p style="margin:0;">📍 Got it — you\'re near <strong>' + result.city + '</strong>! Let me find providers for you…</p>';

                            // Send enriched message to backend
                            showTyping();
                            const geoMessage = 'Show me providers near ' + result.city;

                            // Prepare session_context for backend (lightweight persistence)
                            const sessionContext = loadSessionContext();

                            fetch(miaConfig.ajaxUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({
                                    action: 'mia_chat',
                                    nonce: miaConfig.nonce,
                                    message: geoMessage,
                                    history: JSON.stringify(conversationHistory),
                                    prior_context: JSON.stringify(priorContext),
                                    session_context: JSON.stringify(sessionContext)
                                })
                            })
                            .then(r => r.json())
                            .then(data => {
                                hideTyping();
                                if (data.success && data.data.message) {
                                    addMessage(data.data.message, 'bot');
                                    conversationHistory.push(
                                        { role: 'user', content: geoMessage },
                                        { role: 'assistant', content: data.data.message }
                                    );
                                    if (data.data.prior_context) priorContext = data.data.prior_context;
                                    // Persist to localStorage for continuity across page refreshes
                                    saveSessionContext();
                                    saveConversationHistory();
                                    if (miaConfig.isAdmin && data.data.debug) showDebugTrace(data.data.debug);
                                } else {
                                    addMessage(t.error, 'bot');
                                }
                            })
                            .catch(() => {
                                hideTyping();
                                addMessage(t.connectionError, 'bot');
                            });
                        },
                        function(err) {
                            // Permission denied or error — update the prompt in-place
                            const geoPrompt = document.getElementById('mia-geo-prompt');
                            if (geoPrompt) geoPrompt.innerHTML = '<p style="margin:0;">I can\'t use your location right now, but I can continue this search if you type your city or area. We cover the <strong>Raleigh-Durham Triangle</strong> area.</p>';
                            input.focus();
                        },
                        { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 }
                    );
                };

                window.widgetGeoDeny = function() {
                    const geoPrompt = document.getElementById('mia-geo-prompt');
                    if (geoPrompt) geoPrompt.innerHTML = '<p style="margin:0;">No problem. Which city or area are you in? I\'ll continue this search from there. We cover <strong>Raleigh, Durham, Chapel Hill, Cary, Apex, Morrisville</strong>, and surrounding Triangle-area cities.</p>';
                    input.focus();
                };
                
                
                function applyUIText() {
                    const label = toggle.querySelector('.mia-toggle__label');
                    if (label) label.textContent = t.askMia;
                    
                    const status = chatWindow.querySelector('.mia-chat__status');
                    if (status) {
                        const dot = status.querySelector('.mia-chat__status-dot');
                        status.textContent = ' ' + t.online;
                        if (dot) status.insertBefore(dot, status.firstChild);
                    }
                    
                    input.placeholder = t.placeholder;
                    
                    // Update greeting if it's the default
                    const firstBotMsg = messagesContainer.querySelector('.mia-message--bot .mia-message__content');
                    if (firstBotMsg && firstBotMsg.textContent.includes('Hi! I\'m Mia')) {
                        firstBotMsg.textContent = t.greeting;
                    }
                }
                
                // =============================================
                // HOMEPAGE DETECTION - Widget hidden on homepage
                // Hero chat is the primary interface on homepage
                // =============================================
                const isHomepage = window.location.pathname === '/' || 
                                   window.location.pathname === '' ||
                                   document.body.classList.contains('home') ||
                                   document.body.classList.contains('front-page');
                
                // Show widget after page load (except on homepage)
                setTimeout(() => {
                    if (isHomepage) {
                        // Hide widget completely on homepage - hero chat is primary
                        widget.style.display = 'none';
                    } else {
                        // Show widget normally on other pages
                        widget.style.display = 'block';
                        applyUIText();
                    }
                }, 1000);
                
                // Toggle chat window
                toggle.addEventListener('click', () => {
                    isOpen = !isOpen;
                    chatWindow.style.display = isOpen ? 'flex' : 'none';
                    if (isOpen) {
                        input.focus();
                    }
                });
                
                closeBtn.addEventListener('click', () => {
                    isOpen = false;
                    chatWindow.style.display = 'none';
                });
                
                // Send message
                function sendMessage() {
                    const message = input.value.trim();
                    if (!message) return;

                    // Intercept "near me" / "nearby" intent → trigger geolocation flow
                    const lowerMsg = message.toLowerCase();
                    if (/\bnear\s*me\b|\bnearby\b|\bproviders near me\b/.test(lowerMsg)) {
                        addMessage(message, 'user');
                        input.value = '';
                        widgetNearMe();
                        return;
                    }

                    // Add user message to UI
                    addMessage(message, 'user');
                    input.value = '';

                    // Show typing indicator
                    showTyping();

                    // Prepare session_context for backend (lightweight persistence)
                    const sessionContext = loadSessionContext();

                    // Send to API
                    fetch(miaConfig.ajaxUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'mia_chat',
                            nonce: miaConfig.nonce,
                            message: message,
                            history: JSON.stringify(conversationHistory),
                            prior_context: JSON.stringify(priorContext),
                            session_context: JSON.stringify(sessionContext)
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideTyping();
                        if (data.success && data.data.message) {
                            if (data.data.provider_exposure) {
                                window.MiaLatestProviderExposure = data.data.provider_exposure;
                            }
                            addMessage(data.data.message, 'bot');
                            conversationHistory.push(
                                { role: 'user', content: message },
                                { role: 'assistant', content: data.data.message }
                            );
                            // Update prior context for follow-up carry-forward
                            if (data.data.prior_context) {
                                priorContext = data.data.prior_context;
                            }
                            // Persist to localStorage for continuity across page refreshes
                            saveSessionContext();
                            saveConversationHistory();
                            // Admin debug trace
                            if (miaConfig.isAdmin && data.data.debug) {
                                showDebugTrace(data.data.debug);
                            }
                        } else {
                            addMessage(t.error, 'bot');
                        }
                    })
                    .catch(() => {
                        hideTyping();
                        addMessage(t.connectionError, 'bot');
                    });
                }
                
                function addMessage(text, type) {
                    const div = document.createElement('div');
                    div.className = `mia-message mia-message--${type}`;
                    div.innerHTML = `<div class="mia-message__content">${type === 'user' ? String(text || '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char])) : formatMessage(text)}</div>`;
                    messagesContainer.appendChild(div);
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
                
                function formatMessage(text) {
                    if (window.MiaMessageLinks) {
                        return window.MiaMessageLinks.inline(text).replace(/\n/g, '<br>');
                    }
                    const escaped = document.createElement('div');
                    escaped.textContent = String(text || '');
                    return escaped.innerHTML.replace(/\n/g, '<br>');
                }

                function showDebugTrace(trace) {
                    const div = document.createElement('div');
                    div.className = 'mia-debug-trace';
                    let rows = trace.map(t => {
                        let val = typeof t.value === 'object' ? JSON.stringify(t.value) : t.value;
                        return '<tr><td style="font-weight:600;padding:2px 8px 2px 0;white-space:nowrap;color:#6366F1;">' + t.step + '</td><td style="padding:2px 0;word-break:break-word;">' + val + '</td></tr>';
                    }).join('');
                    div.innerHTML = '<details style="margin:4px 0;"><summary style="cursor:pointer;font-size:0.7rem;color:#9CA3AF;user-select:none;">\uD83D\uDD0D Debug Trace</summary>' +
                        '<table style="font-size:0.7rem;color:#6B7280;margin-top:4px;border-collapse:collapse;width:100%;">' + rows + '</table></details>';
                    messagesContainer.appendChild(div);
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
                
                function showTyping() {
                    const div = document.createElement('div');
                    div.className = 'mia-message mia-message--bot mia-typing-indicator';
                    div.innerHTML = '<div class="mia-message__content mia-message__typing"><span></span><span></span><span></span></div>';
                    messagesContainer.appendChild(div);
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
                
                function hideTyping() {
                    const indicator = document.querySelector('.mia-typing-indicator');
                    if (indicator) indicator.remove();
                }
                
                sendBtn.addEventListener('click', sendMessage);
                input.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') sendMessage();
                });
                if (newChatBtn) {
                    newChatBtn.addEventListener('click', resetWidgetConversation);
                }
                
                // Global function for homepage buttons and external calls
                // On homepage, redirects to inline hero chat (primary interface)
                // On other pages, opens widget normally
                window.openMiaChat = function(initialMessage) {
                    // Check if we're on homepage and hero chat exists
                    const heroInput = document.getElementById('mia-hero-input');
                    const heroMessages = document.getElementById('mia-hero-messages');
                    
                    if (isHomepage && heroInput && heroMessages) {
                        // Scroll to hero chat
                        const heroInteraction = document.getElementById('mia-hero-interaction');
                        if (heroInteraction) {
                            heroInteraction.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        
                        // Focus and optionally fill the hero input
                        setTimeout(() => {
                            heroInput.focus();
                            if (initialMessage) {
                                heroInput.value = initialMessage;
                                // Trigger hero chat if there's a send function
                                if (typeof sendHeroChatMessage === 'function') {
                                    sendHeroChatMessage();
                                }
                            }
                        }, 300);
                        return;
                    }
                    
                    // Non-homepage or no hero: use widget
                    isOpen = true;
                    chatWindow.style.display = 'flex';
                    input.focus();
                    
                    if (initialMessage) {
                        input.value = initialMessage;
                        sendMessage();
                    }
                };
            })();
        </script>
        <?php
    }
}

// Initialize
MyHH_Mia_Chatbot::get_instance();
