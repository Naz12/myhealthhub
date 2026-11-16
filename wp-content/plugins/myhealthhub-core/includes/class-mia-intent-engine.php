<?php
/**
 * Mia Intent Engine
 *
 * Handles intent classification, structured extraction, provider retrieval,
 * ranking, and response template generation for the Mia wellness concierge.
 *
 * Pipeline: Message → Crisis Check → Intent Classification → Structured Extraction
 *           → Directory Retrieval → Ranking → Response Template
 *
 * @package MyHealthHub
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class MyHH_Mia_Intent_Engine {

    // Intent constants
    const INTENT_CONSUMER_SEARCH      = 'consumer_provider_search';
    const INTENT_PROVIDER_ADD         = 'provider_add_listing';
    const INTENT_PROVIDER_CLAIM       = 'provider_claim_listing';
    const INTENT_PROVIDER_OWNER       = 'provider_owner_ambiguous';
    const INTENT_PROVIDER_UPGRADE     = 'provider_upgrade_or_visibility';
    const INTENT_SPONSOR              = 'sponsor_or_company_inquiry';
    const INTENT_CRISIS               = 'crisis_or_high_risk';
    const INTENT_GENERAL              = 'general_unknown';

    /** @var array Debug trace for admin visibility */
    private $debug_trace = array();

    /** @var array Prior conversation context carried forward */
    private $prior_context = array();

    /** @var bool Whether 'near me' / 'nearby' was detected in current message */
    private $near_me_detected = false;

    /** @var string Detected language: 'en', 'es', or 'unknown' */
    private $detected_language = 'unknown';

    /** @var array Rotation metadata from the latest ranked provider search */
    private $last_rotation_meta = array();

    /** @var array Provider exposure snapshot from the latest ranked provider search */
    private $last_provider_exposure = array();

    /** @var bool Run ranking without advancing shared diversity state. */
    private $predictive_preview_mode = false;

    /**
     * @var array Common typo and misspelling normalization map
     * Maps misspelled variants to their correct forms for matching
     */
    private $typo_normalization_map = array(
        // Therapist variations
        'therapust' => 'therapist',
        'therepist' => 'therapist',
        'therapest' => 'therapist',
        'thereapist' => 'therapist',
        'therapst' => 'therapist',
        'theripist' => 'therapist',
        'theraist' => 'therapist',
        'tharapist' => 'therapist',
        'therapist' => 'therapist',
        'therapits' => 'therapist',
        // Counseling variations
        'counceling' => 'counseling',
        'counsling' => 'counseling',
        'counselling' => 'counseling',
        'counsleing' => 'counseling',
        'councilling' => 'counseling',
        'counsiling' => 'counseling',
        'counslor' => 'counselor',
        'counslour' => 'counselor',
        // Anxiety variations
        'anxity' => 'anxiety',
        'anxeity' => 'anxiety',
        'anixety' => 'anxiety',
        'anxiaty' => 'anxiety',
        'anxety' => 'anxiety',
        'ansiedad' => 'anxiety',  // Spanish carried into English sentence
        // Depression variations
        'depresion' => 'depression',
        'depresssion' => 'depression',
        'deppression' => 'depression',
        'depresson' => 'depression',
        'depresed' => 'depressed',
        'deprased' => 'depressed',
        // Psychiatry/Psychologist variations
        'psychatrist' => 'psychiatrist',
        'psychiatrst' => 'psychiatrist',
        'psyciatrist' => 'psychiatrist',
        'psycholgist' => 'psychologist',
        'psychologistt' => 'psychologist',
        'psycologist' => 'psychologist',
        // Marriage variations
        'marrige' => 'marriage',
        'marraige' => 'marriage',
        'marrage' => 'marriage',
        // Relationship variations
        'realtionship' => 'relationship',
        'relashionship' => 'relationship',
        'relationshp' => 'relationship',
        'relationsip' => 'relationship',
        // Addiction variations
        'addction' => 'addiction',
        'addiciton' => 'addiction',
        'adiction' => 'addiction',
        'addicton' => 'addiction',
        // Substance variations
        'substace' => 'substance',
        'subtance' => 'substance',
        // Stress variations
        'stres' => 'stress',
        'stess' => 'stress',
        // Trauma variations
        'trama' => 'trauma',
        'traumma' => 'trauma',
        'traum' => 'trauma',
        // Panic variations
        'panik' => 'panic',
        'panoc' => 'panic',
        // Doctor variations
        'docter' => 'doctor',
        'docor' => 'doctor',
        'docktor' => 'doctor',
        // Professional variations
        'profesional' => 'professional',
        'proffesional' => 'professional',
        // Provider variations (broken English)
        'proveder' => 'provider',
        'providor' => 'provider',
        'proveeder' => 'provider',
        'provaider' => 'provider',
        'provder' => 'provider',
        // Find / looking for
        'lokking' => 'looking',
        'loking' => 'looking',
        'finde' => 'find',
        // Help variations
        'halp' => 'help',
        'helf' => 'help',
        // Need variations
        'nead' => 'need',
        'ned' => 'need',
        // Want variations
        'wan ' => 'want ',
        'wanna' => 'want to',
        'wana' => 'want to',
        // Insurance variations
        'insurence' => 'insurance',
        'insuranse' => 'insurance',
        'insuranc' => 'insurance',
        // Appointment / availability
        'appoinment' => 'appointment',
        'appointmen' => 'appointment',
        'availabilty' => 'availability',
        // Mental health shorthand
        'mentl health' => 'mental health',
        'menta health' => 'mental health',
        'mental helth' => 'mental health',
        // Voice-specific ASR mishearings (non-native speakers)
        'the rapist' => 'therapist',
        'there pist' => 'therapist',
        'thera pest' => 'therapist',
        'terrapist' => 'therapist',
        'terapist' => 'therapist',
        'cancel or' => 'counselor',
        'counsel or' => 'counselor',
        'counsoler' => 'counselor',
        'counciler' => 'counselor',
        'councelor' => 'counselor',
        'cycle gist' => 'psychologist',
        'syco logist' => 'psychologist',
        'sycologist' => 'psychologist',
        'psicologist' => 'psychologist',
        'sikiatrist' => 'psychiatrist',
        'sykiatrist' => 'psychiatrist',
        'mental house' => 'mental health',
        'mental held' => 'mental health',
        'mental helps' => 'mental health',
        'in surance' => 'insurance',
        'de pression' => 'depression',
        'de pressed' => 'depressed',
        'depresed' => 'depressed',
        'sub stance' => 'substance',
        'pro vider' => 'provider',
        'bro vider' => 'provider',
        'a point ment' => 'appointment',
        'near buy' => 'nearby',
        'look king' => 'looking',
        'look in for' => 'looking for',
        'serapis' => 'therapist',
        'cup ples therapy' => 'couples therapy',
        'cup pulls therapy' => 'couples therapy',
        // Voice ASR mishearings for claim/ownership phrases
        'climb my profile'       => 'claim my profile',
        'climb my listing'       => 'claim my listing',
        'clean my listing'       => 'claim my listing',
        'clean my profile'       => 'claim my profile',
        'clame my profile'       => 'claim my profile',
        'clame my listing'       => 'claim my listing',
        'claim my provid'        => 'claim my provider',
        'claiming provider'      => 'claim my provider',
        'claiming my profile'    => 'claim my profile',
        'claiming my listing'    => 'claim my listing',
        'claiming my list'       => 'claim my listing',
        'claim my list'          => 'claim my listing',
        'claiming list'          => 'claim my listing',
        'my proof'               => 'my profile',
        'my provider proof'      => 'my provider profile',
        'my provider page'       => 'claim my provider page',
        'play music profile'     => 'claim my profile',
        'play mindless profile'  => 'claim my profile',
        'play minus profile'     => 'claim my profile',
        'play me this profile'   => 'claim my profile',
        'you signed provider'    => 'claim my provider',
        'you sign provider'      => 'claim my provider',
        'you side provider'      => 'claim my provider',
        'you send provider'      => 'claim my provider',
        'you sent provider'      => 'claim my provider',
        'for claiming or'        => 'claim my profile',
        'manage my provid'       => 'manage my provider',
        'verify my pract'        => 'verify my practice',
        'verify my list'         => 'verify my listing',
        'my business listed'     => 'my business is listed',
        'im listed'              => "i'm already listed",
        'i am listed'            => "i'm already listed",
        // ── Additional typo / misspelling / broken-English normalization ──
        // Massage / spa / bodywork
        'masssage' => 'massage', 'massge' => 'massage', 'masage' => 'massage',
        'massaje' => 'massage', 'masaj' => 'massage',
        'made spa' => 'med spa', 'made a spa' => 'med spa', 'made the spa' => 'med spa',
        'mate spa' => 'med spa', 'meta spa' => 'med spa', 'mid spa' => 'med spa',
        'medspa' => 'med spa', 'medical spa' => 'med spa',
        'chiropracter' => 'chiropractor', 'chiropracter' => 'chiropractor',
        'kiropractor' => 'chiropractor', 'quiropractico' => 'chiropractor',
        'phisical therapy' => 'physical therapy', 'fisical therapy' => 'physical therapy',
        'fisioterapia' => 'physical therapy', 'physio therapy' => 'physical therapy',
        // Nutrition / diet
        'nutrtion' => 'nutrition', 'nutriton' => 'nutrition', 'nutrishun' => 'nutrition',
        'nutricion' => 'nutrition', 'nutrición' => 'nutrition',
        'dietitian' => 'dietitian', 'dietition' => 'dietitian', 'dietetian' => 'dietitian',
        'dietetian' => 'dietitian', 'diatitian' => 'dietitian',
        'dit' => 'diet', 'diete' => 'diet',
        // Fitness / yoga
        'yogaa' => 'yoga', 'ioga' => 'yoga',
        'pilates' => 'pilates', 'pilatts' => 'pilates', 'pilatos' => 'pilates',
        'fitnes' => 'fitness', 'fitniss' => 'fitness', 'fitnes' => 'fitness',
        'exersize' => 'exercise', 'excersize' => 'exercise', 'exercize' => 'exercise',
        'exersise' => 'exercise', 'exerscise' => 'exercise', 'exersise' => 'exercise',
        'gymm' => 'gym',
        'personal trainor' => 'personal trainer', 'persona trainer' => 'personal trainer',
        // Integrative / holistic / acupuncture
        'holistik' => 'holistic', 'holistc' => 'holistic', 'wholestic' => 'holistic',
        'acupunctur' => 'acupuncture', 'acupunture' => 'acupuncture', 'acupuntura' => 'acupuncture',
        'akupuncture' => 'acupuncture', 'acupuntur' => 'acupuncture',
        'acupresure' => 'acupressure', 'acupressur' => 'acupressure',
        'naturopathic' => 'naturopath', 'naturophath' => 'naturopath',
        'homeopatic' => 'homeopathic', 'homeopathi' => 'homeopathic',
        'reiki' => 'reiki', 'rayki' => 'reiki', 'rekei' => 'reiki',
        'aurveda' => 'ayurveda', 'aruyveda' => 'ayurveda', 'ayerveda' => 'ayurveda',
        'herbalist' => 'herbalist', 'herbolario' => 'herbalist',
        // Medical / doctor
        'medica' => 'medical', 'medcal' => 'medical', 'medicl' => 'medical',
        'docto' => 'doctor', 'doctr' => 'doctor',
        'phisician' => 'physician', 'physcian' => 'physician', 'fisician' => 'physician',
        'pediastrician' => 'pediatrician', 'pediatra' => 'pediatrician',
        'pediatritian' => 'pediatrician', 'pediaction' => 'pediatrician',
        // Insurance
        'aseguranza' => 'insurance', 'seguro' => 'insurance',
        'insurace' => 'insurance', 'insurane' => 'insurance',
        // Coaching
        'coatch' => 'coach', 'coch' => 'coach', 'couch' => 'coach',
        'coaching' => 'coaching', 'coching' => 'coaching',
        // Location / city
        'raliegh' => 'raleigh', 'raleig' => 'raleigh', 'raley' => 'raleigh',
        'duram' => 'durham', 'durnam' => 'durham',
        'chaple hill' => 'chapel hill', 'chapell hill' => 'chapel hill',
        'morrisvile' => 'morrisville', 'morisville' => 'morrisville',
        'caryy' => 'cary',
        'apx' => 'apex', 'apeex' => 'apex',
        // General broken English
        'serch' => 'search', 'sarch' => 'search', 'serach' => 'search',
        'lookin' => 'looking', 'lookn' => 'looking', 'lookng' => 'looking',
        'recomend' => 'recommend', 'recomnd' => 'recommend', 'recomemd' => 'recommend',
        'suport' => 'support', 'suppot' => 'support', 'supprt' => 'support',
        'hlep' => 'help', 'hepl' => 'help',
        'proflie' => 'profile', 'profil' => 'profile', 'porfile' => 'profile',
        'listign' => 'listing', 'listng' => 'listing', 'listin' => 'listing',
        'practce' => 'practice', 'pratice' => 'practice', 'practis' => 'practice',
        'busness' => 'business', 'busines' => 'business', 'bisness' => 'business',
        'upgade' => 'upgrade', 'upgrde' => 'upgrade', 'upgarde' => 'upgrade',
        'negozio' => 'business',
        // Common filler / speech disfluency outputs from ASR
        'um um' => '', 'uh uh' => '', 'ah ah' => '',
        'like like' => '', 'you know you know' => '',
    );

    /**
     * @var array Paraphrase and natural language intent signals
     * Maps conversational phrases to intent classifications
     */
    private $paraphrase_signals = array(
        // Consumer search - help seeking phrases
        'i need help' => 'search',
        "i'm struggling" => 'search',
        "i'm having a hard time" => 'search',
        "i don't know what to do" => 'search',
        'i need someone to talk to' => 'search',
        'need to talk' => 'search',
        'talk to someone' => 'search',
        'someone to talk to' => 'search',
        'who can help' => 'search',
        'where can i get help' => 'search',
        'looking for help' => 'search',
        'seeking help' => 'search',
        'help me find' => 'search',
        // Broken English help-seeking patterns
        'i need find' => 'search',
        'i wan find' => 'search',
        'i want find' => 'search',
        'need find' => 'search',
        'find me a' => 'search',
        'find me someone' => 'search',
        'looking for someone' => 'search',
        'looking someone' => 'search',
        'show me provider' => 'search',
        'show provider' => 'search',
        'find me provider' => 'search',
        'find provider' => 'search',
        'find me a provider' => 'search',
        'could you find me provider' => 'search',
        'can you find me provider' => 'search',
        'find me a therapist' => 'search',
        'find me a counselor' => 'search',
        'i need a provider' => 'search',
        'i need provider' => 'search',
        'need a provider' => 'search',
        'want a provider' => 'search',
        'any provider' => 'search',
        'some provider' => 'search',
        'i looking' => 'search',
        'i search' => 'search',
        'me need' => 'search',
        'me want' => 'search',
        'can help me' => 'search',
        'somebody help' => 'search',
        'need help with' => 'search',
        'want help with' => 'search',
        'halp me' => 'search',
        // Mental health emotional signals
        'feeling down' => 'mental_health',
        'feeling really down' => 'mental_health',
        'feeling down lately' => 'mental_health',
        'feel down' => 'mental_health',
        'feeling low' => 'mental_health',
        'not doing well' => 'mental_health',
        'going through a hard time' => 'mental_health',
        'having a rough time' => 'mental_health',
        'feeling overwhelmed' => 'mental_health',
        'stressed out' => 'mental_health',
        'burned out' => 'mental_health',
        'burnt out' => 'mental_health',
        'burnout' => 'mental_health',
        'overwhelmed' => 'mental_health',
        'can not cope' => 'mental_health',
        "can't cope" => 'mental_health',
        // Broken English emotional signals
        'i feel bad' => 'mental_health',
        'i feel very bad' => 'mental_health',
        'i no feel good' => 'mental_health',
        'i not feel good' => 'mental_health',
        'feel sad all time' => 'mental_health',
        'always sad' => 'mental_health',
        'cannot stop crying' => 'mental_health',
        'can not stop crying' => 'mental_health',
        'very stress' => 'mental_health',
        'too much stress' => 'mental_health',
        'have anxiety' => 'mental_health',
        'got anxiety' => 'mental_health',
        'my anxiety' => 'mental_health',
        'i have depression' => 'mental_health',
        'i got depression' => 'mental_health',
        'my depression' => 'mental_health',
        // Relationship signals
        'marriage problems' => 'relationships',
        'relationship issues' => 'relationships',
        'relationship problems' => 'relationships',
        'relationship help' => 'relationships',
        'need relationship' => 'relationships',
        'having problems with my partner' => 'relationships',
        'having problems with my spouse' => 'relationships',
        'problem with my husband' => 'relationships',
        'problem with my wife' => 'relationships',
        'me and my partner' => 'relationships',
        'me and my husband' => 'relationships',
        'me and my wife' => 'relationships',
        // Child/teen therapy signals
        'my kid needs help' => 'child_therapy',
        'my child needs' => 'child_therapy',
        'child behavior' => 'child_therapy',
        'teen issues' => 'child_therapy',
        'teenager problems' => 'child_therapy',
        'my teenager' => 'child_therapy',
        'my adolescent' => 'child_therapy',
        'my kid have problem' => 'child_therapy',
        'my son need help' => 'child_therapy',
        'my daughter need help' => 'child_therapy',
        // Substance abuse signals
        'drinking problem' => 'substance_abuse',
        'drug problem' => 'substance_abuse',
        'struggling with alcohol' => 'substance_abuse',
        'struggling with drugs' => 'substance_abuse',
        'drink too much' => 'substance_abuse',
        'cannot stop drinking' => 'substance_abuse',
        // ── Expanded help-seeking / distress (added 2026-04-29) ──
        'i feel alone' => 'mental_health',
        'i feel lonely' => 'mental_health',
        'no one understands' => 'mental_health',
        'i feel lost' => 'mental_health',
        'everything feels hard' => 'mental_health',
        "i can't do this anymore" => 'mental_health',
        'nothing feels right' => 'mental_health',
        'i feel stuck' => 'mental_health',
        'i feel empty' => 'mental_health',
        'i feel numb' => 'mental_health',
        'life is hard' => 'mental_health',
        'nothing matters' => 'mental_health',
        'no motivation' => 'mental_health',
        "can't focus" => 'mental_health',
        "can't concentrate" => 'mental_health',
        'losing my mind' => 'mental_health',
        'going crazy' => 'mental_health',
        'losing it' => 'mental_health',
        'falling apart' => 'mental_health',
        'at my wits end' => 'mental_health',
        "at my wit's end" => 'mental_health',
        'hit rock bottom' => 'mental_health',
        'at the end of my rope' => 'mental_health',
        'breaking point' => 'mental_health',
        'i am not okay' => 'mental_health',
        "i'm not okay" => 'mental_health',
        'i am not ok' => 'mental_health',
        "i'm not ok" => 'mental_health',
        'not myself lately' => 'mental_health',
        "haven't been myself" => 'mental_health',
        'feeling sad' => 'mental_health',
        'crying all the time' => 'mental_health',
        "can't stop crying" => 'mental_health',
        'crying for no reason' => 'mental_health',
        'scared all the time' => 'mental_health',
        'panicking' => 'mental_health',
        'panic attacks' => 'mental_health',
        'having panic' => 'mental_health',
        'chest tightness' => 'mental_health',
        "can't breathe" => 'mental_health',
        'racing thoughts' => 'mental_health',
        "can't sleep at night" => 'mental_health',
        'trouble sleeping' => 'mental_health',
        'waking up anxious' => 'mental_health',
        'nightmares' => 'mental_health',
        'flashbacks' => 'mental_health',
        'triggered' => 'mental_health',
        'trust issues' => 'mental_health',
        'abusive relationship' => 'mental_health',
        'domestic violence' => 'mental_health',
        // ── Expanded search / help-seeking verbs ──
        'i wanna find' => 'search',
        'i want to find' => 'search',
        'i gotta find' => 'search',
        'help me get' => 'search',
        'connect me with' => 'search',
        'point me to' => 'search',
        'point me toward' => 'search',
        'send me to' => 'search',
        'set me up with' => 'search',
        'hook me up with' => 'search',
        'i could use' => 'search',
        'i could really use' => 'search',
        'i might need' => 'search',
        'looking to find' => 'search',
        'trying to find' => 'search',
        'trying to locate' => 'search',
        'hoping to find' => 'search',
        'wondering if there' => 'search',
        'is there someone' => 'search',
        'is there anybody' => 'search',
        'does anyone know' => 'search',
        'can anyone recommend' => 'search',
        'has anyone tried' => 'search',
        'how do i get' => 'search',
        'where do i go' => 'search',
        'who should i see' => 'search',
        'who should i talk to' => 'search',
        'what kind of doctor' => 'search',
        'what type of therapist' => 'search',
        'do you know a good' => 'search',
        'do you know of a' => 'search',
        'any recommendations for' => 'search',
        'any suggestions for' => 'search',
        'i need a referral' => 'search',
        'i need a recommendation' => 'search',
        'good therapist' => 'search',
        'good counselor' => 'search',
        'good doctor' => 'search',
        'good nutritionist' => 'search',
        'good massage' => 'search',
        'good chiropractor' => 'search',
        'good acupuncturist' => 'search',
        'good personal trainer' => 'search',
        'good yoga' => 'search',
        'good life coach' => 'search',
        'best therapist' => 'search',
        'best counselor' => 'search',
        'best massage' => 'search',
        'best nutritionist' => 'search',
        'affordable therapy' => 'search',
        'affordable counselor' => 'search',
        'cheap therapy' => 'search',
        'low cost therapy' => 'search',
        'free counseling' => 'search',
        'sliding scale' => 'search',
        'teletherapy' => 'search',
        'online therapy' => 'search',
        'virtual therapy' => 'search',
        'telehealth' => 'search',
        'video therapy' => 'search',
        'phone therapy' => 'search',
        // ── Category-specific search paraphrases ──
        'my back hurts' => 'search',
        'back pain' => 'search',
        'neck pain' => 'search',
        'chronic pain' => 'search',
        'body hurts' => 'search',
        'in pain' => 'search',
        'sore muscles' => 'search',
        'sports injury' => 'search',
        'need a massage' => 'search',
        'tense muscles' => 'search',
        'knots in my back' => 'search',
        'want to relax' => 'search',
        'need to de-stress' => 'search',
        'need relaxation' => 'search',
        'want to get fit' => 'search',
        'want to get in shape' => 'search',
        'need to exercise' => 'search',
        'want to start yoga' => 'search',
        'looking for a gym' => 'search',
        'want to lose weight' => 'search',
        'need help with food' => 'search',
        'want to eat better' => 'search',
        'need a meal plan' => 'search',
        'food allergies' => 'search',
        'allergy testing' => 'search',
        'want to try acupuncture' => 'search',
        'interested in holistic' => 'search',
        'looking for natural' => 'search',
        'alternative to medication' => 'search',
        'want a second opinion' => 'search',
        'need a checkup' => 'search',
        'need a physical' => 'search',
        'want a life coach' => 'search',
        'need career advice' => 'search',
        'career change help' => 'search',
        'work life balance help' => 'search',
        'need a sleep doctor' => 'search',
        "can't sleep" => 'search',
        'trouble sleeping' => 'search',
        // Natural phrase starters
        'who can help with' => 'extract_condition',
        "i'm looking for" => 'extract_target',
        'do you have anyone who' => 'search',
        'what kind of' => 'category_explore',
        'how much does' => 'pricing_question',
        'is it free' => 'pricing_question',
        'does it cost' => 'pricing_question',
        'take insurance' => 'insurance_question',
        'accept insurance' => 'insurance_question',
        // Spanglish mixed signals
        'need terapeuta' => 'search',
        'find terapeuta' => 'search',
        'busco therapist' => 'search',
        'need consejero' => 'search',
        'find consejero' => 'search',
        'need ayuda' => 'search',
        'i need ayuda' => 'search',
        'tengo anxiety' => 'mental_health',
        'tengo depression' => 'mental_health',
        'tengo ansiedad' => 'mental_health',
        'i have ansiedad' => 'mental_health',
        'me siento bad' => 'mental_health',
        'me siento sad' => 'mental_health',
    );

    /**
     * @var array Specialty synonym expansions for better matching
     */
    private $specialty_synonym_map = array(
        // ── Therapist / Counseling / Mental Health ──
        'shrink' => array('psychiatrist', 'psychologist', 'therapist'),
        'head doctor' => array('psychiatrist', 'psychologist', 'therapist'),
        'pill doctor' => array('psychiatrist'),
        'medication management' => array('psychiatrist', 'medication management'),
        'therapist' => array('therapist', 'counselor', 'psychotherapist', 'mental health professional'),
        'counseling' => array('counseling', 'therapy', 'talk therapy', 'mental health support'),
        'counselling' => array('counseling', 'therapy'),
        'psychotherapy' => array('psychotherapy', 'therapy', 'talk therapy'),
        'talk therapy' => array('talk therapy', 'therapy', 'psychotherapy', 'counseling'),
        'cbt' => array('cbt', 'cognitive behavioral therapy', 'cognitive behavioral', 'cognitive therapy'),
        'dbt' => array('dbt', 'dialectical behavior therapy', 'dialectical behavioral'),
        'emdr' => array('emdr', 'eye movement desensitization', 'trauma therapy'),
        'mental health' => array('mental health', 'behavioral health', 'emotional health', 'psychological'),
        'behavioral health' => array('behavioral health', 'mental health'),
        // ── Massage / Recovery / Spa ──
        'massage' => array('massage therapy', 'massage therapist', 'bodywork', 'deep tissue'),
        'bodywork' => array('bodywork', 'massage therapy', 'therapeutic touch'),
        'deep tissue' => array('deep tissue massage', 'massage therapy', 'sports massage'),
        'sports massage' => array('sports massage', 'massage therapy', 'athletic recovery'),
        'swedish massage' => array('swedish massage', 'relaxation massage', 'massage therapy'),
        'chiropractor' => array('chiropractor', 'chiropractic', 'spinal adjustment'),
        'chiropractic' => array('chiropractic', 'chiropractor', 'spinal health'),
        'physical therapy' => array('physical therapy', 'physiotherapy', 'rehabilitation', 'pt'),
        'physiotherapy' => array('physiotherapy', 'physical therapy', 'rehabilitation'),
        'recovery' => array('recovery therapy', 'massage therapy', 'rehabilitation'),
        'spa' => array('spa', 'wellness spa', 'day spa', 'med spa'),
        // ── Nutrition / Diet / Food Support ──
        'nutrition' => array('nutrition', 'nutritional counseling', 'dietary support', 'food guidance'),
        'nutritionist' => array('nutritionist', 'dietitian', 'nutrition counselor'),
        'dietitian' => array('dietitian', 'registered dietitian', 'nutritionist', 'nutrition expert'),
        'diet' => array('dietary support', 'nutrition counseling', 'eating plan'),
        'meal plan' => array('nutrition counseling', 'dietary support', 'meal planning'),
        'weight loss' => array('weight management', 'nutrition counseling', 'healthy eating'),
        'food allergy' => array('food allergy', 'nutrition counseling', 'dietary support'),
        'eating healthy' => array('nutrition counseling', 'healthy eating', 'dietary guidance'),
        // ── Fitness / Yoga / Movement ──
        'fitness' => array('fitness', 'exercise', 'workout', 'physical training'),
        'yoga' => array('yoga', 'yoga instructor', 'yoga class', 'yoga therapy'),
        'pilates' => array('pilates', 'core exercise', 'movement therapy'),
        'personal trainer' => array('personal trainer', 'fitness coach', 'exercise specialist'),
        'exercise' => array('exercise', 'fitness', 'workout', 'physical activity'),
        'workout' => array('workout', 'exercise', 'fitness training'),
        'gym' => array('gym', 'fitness center', 'fitness facility'),
        'crossfit' => array('crossfit', 'high intensity training', 'fitness'),
        'tai chi' => array('tai chi', 'gentle movement', 'mindful movement'),
        'dance' => array('dance therapy', 'movement therapy', 'dance class'),
        'barre' => array('barre', 'barre class', 'fitness class'),
        // ── Integrative / Holistic / Acupuncture / Functional Medicine ──
        'holistic' => array('holistic health', 'holistic therapy', 'whole person care', 'integrative'),
        'integrative' => array('integrative medicine', 'integrative health', 'holistic'),
        'acupuncture' => array('acupuncture', 'acupuncturist', 'traditional chinese medicine'),
        'acupuncturist' => array('acupuncturist', 'acupuncture', 'chinese medicine'),
        'naturopath' => array('naturopath', 'naturopathy', 'natural medicine'),
        'functional medicine' => array('functional medicine', 'integrative medicine', 'root cause medicine'),
        'alternative medicine' => array('alternative medicine', 'complementary medicine', 'holistic'),
        'herbal medicine' => array('herbal medicine', 'botanical medicine', 'herbalist'),
        'homeopathic' => array('homeopathy', 'homeopathic medicine', 'natural remedy'),
        'reiki' => array('reiki', 'energy healing', 'energy work'),
        'ayurveda' => array('ayurveda', 'ayurvedic medicine', 'ayurvedic'),
        'traditional chinese medicine' => array('traditional chinese medicine', 'tcm', 'chinese medicine'),
        // ── Medical / Physical Therapy / Care ──
        'doctor' => array('doctor', 'physician', 'medical doctor', 'md'),
        'physician' => array('physician', 'doctor', 'medical provider'),
        'primary care' => array('primary care', 'family medicine', 'general practitioner'),
        'pediatrician' => array('pediatrician', 'child doctor', 'kids doctor'),
        'nurse practitioner' => array('nurse practitioner', 'np', 'advanced practice nurse'),
        'urgent care' => array('urgent care', 'walk in clinic', 'immediate care'),
        'clinic' => array('clinic', 'medical center', 'health center'),
        'rehab' => array('rehabilitation', 'physical therapy', 'recovery center'),
        'rehabilitation' => array('rehabilitation', 'rehab', 'physical therapy'),
        // ── Coaching / Personal Growth / Work-Life ──
        'life coach' => array('life coach', 'life coaching', 'personal development coach', 'wellness coach'),
        'career coach' => array('career coach', 'career coaching', 'professional coach'),
        'executive coach' => array('executive coach', 'leadership coach', 'professional development'),
        'wellness coach' => array('wellness coach', 'health coach', 'lifestyle coach'),
        'health coach' => array('health coach', 'wellness coach', 'lifestyle coach'),
        'personal growth' => array('personal growth', 'personal development', 'self-improvement'),
        'work life balance' => array('work-life balance', 'work life harmony', 'burnout prevention'),
        'self improvement' => array('self-improvement', 'personal development', 'personal growth'),
        // ── Eating Disorders ──
        'eating disorder' => array('eating disorder', 'anorexia', 'bulimia', 'binge eating', 'food issues'),
        'anorexia' => array('eating disorder', 'anorexia'),
        'bulimia' => array('eating disorder', 'bulimia'),
        // ── PTSD / Trauma ──
        'ptsd' => array('trauma', 'ptsd', 'post traumatic'),
        'trauma' => array('trauma', 'ptsd', 'emdr'),
        // ── OCD ──
        'ocd' => array('ocd', 'obsessive', 'obsessive compulsive'),
        'obsessive' => array('ocd', 'obsessive compulsive'),
        // ── ADHD ──
        'adhd' => array('adhd', 'attention deficit', 'focus issues', 'concentration'),
        'attention deficit' => array('adhd', 'attention deficit', 'focus issues'),
        // ── Bipolar ──
        'bipolar' => array('bipolar', 'mood disorder', 'manic depression'),
        // ── Anger ──
        'anger issues' => array('anger management', 'anger', 'rage'),
        'anger management' => array('anger management', 'anger', 'rage'),
        // ── Grief ──
        'grief' => array('grief', 'loss', 'bereavement', 'mourning'),
        'loss' => array('grief', 'loss', 'bereavement'),
        // ── Sleep ──
        'insomnia' => array('insomnia', 'sleep disorder', 'trouble sleeping', 'sleep issues'),
        "can't sleep" => array('insomnia', 'sleep issues', 'trouble sleeping'),
        // ── Substance / Addiction ──
        'addiction' => array('addiction', 'substance abuse', 'dependency', 'recovery'),
        'alcoholism' => array('alcoholism', 'alcohol addiction', 'drinking problem'),
        'sober' => array('sobriety', 'recovery', 'addiction recovery'),
    );

    /**
     * @var array Extended Spanish keywords for Spanglish handling
     */
    private $spanglish_signals = array(
        'necesito ayuda' => 'search',
        'ayuda' => 'search',
        'terapeuta' => 'mental-health-therapy',
        'consejero' => 'mental-health-therapy',
        // ── Expanded Spanish search signals ──
        'buscar' => 'search', 'busco' => 'search', 'buscando' => 'search',
        'encontrar' => 'search', 'necesito' => 'search',
        'quiero' => 'search', 'deseo' => 'search',
        'donde encuentro' => 'search', 'dónde encuentro' => 'search',
        'donde hay' => 'search', 'dónde hay' => 'search',
        'hay alguien' => 'search', 'alguien sabe' => 'search',
        'alguien conoce' => 'search', 'me recomiendan' => 'search',
        'recomiéndame' => 'search', 'recomiendame' => 'search',
        'alguien bueno' => 'search', 'el mejor' => 'search',
        'la mejor' => 'search', 'bueno' => 'search',
        'economico' => 'search', 'económico' => 'search',
        'barato' => 'search', 'gratis' => 'search',
        'cerca de' => 'search', 'cercano' => 'search',
        // ── Spanish category keywords ──
        'terapia' => 'mental-health-therapy', 'terapéutico' => 'mental-health-therapy',
        'psicólogo' => 'mental-health-therapy', 'psicologo' => 'mental-health-therapy',
        'psiquiatra' => 'mental-health-therapy', 'salud mental' => 'mental-health-therapy',
        'ansiedad' => 'mental-health-therapy', 'depresión' => 'mental-health-therapy',
        'depresion' => 'mental-health-therapy', 'estrés' => 'mental-health-therapy',
        'estres' => 'mental-health-therapy', 'trauma' => 'mental-health-therapy',
        'duelo' => 'mental-health-therapy', 'adiccion' => 'mental-health-therapy',
        'adicción' => 'mental-health-therapy', 'pareja' => 'mental-health-therapy',
        'matrimonio' => 'mental-health-therapy', 'familia' => 'mental-health-therapy',
        'masaje' => 'massage-spa-recovery', 'masajista' => 'massage-spa-recovery',
        'masajé' => 'massage-spa-recovery', 'spa' => 'massage-spa-recovery',
        'quiropráctico' => 'massage-spa-recovery', 'quiropractico' => 'massage-spa-recovery',
        'recuperación' => 'massage-spa-recovery', 'recuperacion' => 'massage-spa-recovery',
        'nutrición' => 'nutrition-dietary-support', 'nutricion' => 'nutrition-dietary-support',
        'nutricionista' => 'nutrition-dietary-support', 'dietista' => 'nutrition-dietary-support',
        'dieta' => 'nutrition-dietary-support', 'comer sano' => 'nutrition-dietary-support',
        'bajAR de peso' => 'nutrition-dietary-support',
        'ejercicio' => 'fitness-yoga-movement', 'gimnasio' => 'fitness-yoga-movement',
        'yoga' => 'fitness-yoga-movement', 'pilates' => 'fitness-yoga-movement',
        'entrenador personal' => 'fitness-yoga-movement',
        'acupuntura' => 'integrative-holistic-therapies',
        'acupuntor' => 'integrative-holistic-therapies',
        'holístico' => 'integrative-holistic-therapies', 'holistico' => 'integrative-holistic-therapies',
        'medicina alternativa' => 'integrative-holistic-therapies',
        'naturópata' => 'integrative-holistic-therapies', 'naturopata' => 'integrative-holistic-therapies',
        'reiki' => 'integrative-holistic-therapies',
        'doctor' => 'primary-care-medical-services', 'doctora' => 'primary-care-medical-services',
        'médico' => 'primary-care-medical-services', 'medico' => 'primary-care-medical-services',
        'clínica' => 'primary-care-medical-services', 'clinica' => 'primary-care-medical-services',
        'pediatra' => 'primary-care-medical-services',
        'coach' => 'personal-growth-learning', 'entrenador' => 'personal-growth-learning',
        'desarrollo personal' => 'personal-growth-learning',
        'dormir' => 'sleep-rest-wellness', 'insomnio' => 'sleep-rest-wellness',
        'no puedo dormir' => 'sleep-rest-wellness',
        // ── Expanded Spanglish signals ──
        'need terapeuta' => 'search', 'find terapeuta' => 'search',
        'busco therapist' => 'search', 'need consejero' => 'search',
        'find consejero' => 'search', 'need ayuda' => 'search',
        'i need ayuda' => 'search', 'tengo anxiety' => 'mental_health',
        'tengo depression' => 'mental_health', 'tengo ansiedad' => 'mental_health',
        'i have ansiedad' => 'mental_health', 'me siento bad' => 'mental_health',
        'me siento sad' => 'mental_health',
        'necesito therapist' => 'search', 'busco counselor' => 'search',
        'quiero un massage' => 'search', 'i need masaje' => 'search',
        'looking for nutricionista' => 'search',
        'i want acupuntura' => 'search', 'busco holistic' => 'search',
        'me duele la espalda' => 'search', 'tengo dolor' => 'search',
        'i feel mal' => 'mental_health', 'me siento overwhelmed' => 'mental_health',
        'estoy depressed' => 'mental_health', 'estoy stressed' => 'mental_health',
        'i feel triste' => 'mental_health', 'tengo panic' => 'mental_health',
        'tengo trauma' => 'mental_health', 'my pareja' => 'mental_health',
        'mi hijo need help' => 'mental_health', 'mi familia need' => 'search',
        'quiero yoga' => 'search', 'quiero ejercicio' => 'search',
        'busco doctor' => 'search', 'i need un doctor' => 'search',
        'quiero un coach' => 'search', 'busco life coach' => 'search',
        // ── Spanish provider-side signals ──
        'reclamar mi perfil' => 'claim', 'reclamo mi perfil' => 'claim',
        'reclamar mi listado' => 'claim', 'editar mi perfil' => 'claim',
        'actualizar mi perfil' => 'claim', 'ese soy yo' => 'claim',
        'es mi consultorio' => 'claim', 'ya estoy listado' => 'claim',
        'mi perfil ya está' => 'claim', 'agregar mi práctica' => 'add',
        'añadir mi práctica' => 'add', 'anadir mi practica' => 'add',
        'listar mi negocio' => 'add', 'quiero listar' => 'add',
        'soy proveedor' => 'add', 'soy terapeuta' => 'add',
        'anunciar mi práctica' => 'add', 'quiero sponsor' => 'sponsor',
        'patrocinio' => 'sponsor', 'publicidad' => 'sponsor',
        'mejorar mi listado' => 'upgrade', 'listado premium' => 'upgrade',
        'listado destacado' => 'upgrade', 'más visibilidad' => 'upgrade',
        // ── Original Spanglish signals preserved ──
        'consejera' => 'mental-health-therapy',
        'psicologo' => 'mental-health-therapy',
        'psicologa' => 'mental-health-therapy',
        'psiquiatra' => 'mental-health-therapy',
        'salud mental' => 'mental-health-therapy',
        'ansiedad' => 'mental_health',
        'depresion' => 'mental_health',
        'estres' => 'mental_health',
        'cerca de mi' => 'near_me',
        'cerca de mí' => 'near_me',
    );

    /** @var array Spanish crisis patterns — checked alongside English patterns */
    private $crisis_patterns_es = array(
        'quiero morir', 'me quiero matar', 'suicidarme', 'suicidio',
        'hacerme daño', 'hacerme dano', 'lastimarme', 'quitarme la vida',
        'no quiero vivir', 'no vale la pena vivir', 'mejor muerto',
        'pensando en morir', 'planeo suicidarme', 'acabar con todo',
        'terminar con mi vida', 'matarme', 'cortarme', 'quemarme',
        'tomas todas las pastillas', 'sobredosis', 'me estan maltratando',
        'violencia domestica', 'alguien me esta lastimando', 'me golpean',
        'abuso sexual', 'violada', 'no estoy seguro en casa',
        'tengo miedo por mi vida', 'peligro inmediato', 'crisis grave',
        'ayuda urgente', 'necesito ayuda ya',
    );

    /** @var array Spanish category keyword map — maps Spanish terms to internal slugs */
    private $category_map_es = array(
        'mental-health-therapy' => array(
            'terapeuta', 'terapia', 'consejero', 'consejera', 'consejería',
            'psicólogo', 'psicóloga', 'psiquiatra', 'salud mental',
            'ansiedad', 'depresión', 'estres', 'trauma', 'duelo',
            'terapia de pareja', 'terapia matrimonial', 'adicción',
            'autoestima', 'control de ira', 'pánico', 'fobia',
            'psicoterapia', 'terapia cognitiva', 'trastorno',
        ),
        'primary-care-medical-services' => array(
            'médico', 'medico', 'doctor', 'doctora', 'clínica', 'clinica',
            'medicina familiar', 'medicina interna', 'pediatra',
        ),
        'fitness-yoga-movement' => array(
            'entrenador', 'entrenadora', 'coach fitness', 'yoga',
            'ejercicio', 'gimnasio', 'pilates', 'baile', 'movimiento',
        ),
        'nutrition-dietary-support' => array(
            'nutrición', 'nutricion', 'nutricionista', 'dietista',
            'dieta', 'plan alimenticio', 'peso saludable',
        ),
        'massage-spa-recovery' => array(
            'masaje', 'masajista', 'spa', 'quiropráctico', 'quiropractico',
            'recuperación física', 'recuperacion fisica',
        ),
        'sleep-rest-wellness' => array(
            'dormir', 'insomnio', 'especialista del sueño', 'descanso',
        ),
        'financial-wellness-planning' => array(
            'asesor financiero', 'planeación financiera', 'finanzas',
            'deuda', 'presupuesto',
        ),
        'career-work-life-coaching' => array(
            'coach de carrera', 'orientación profesional', 'trabajo',
            'equilibrio trabajo-vida',
        ),
        'integrative-holistic-therapies' => array(
            'holístico', 'holistico', 'acupuntura', 'naturopatía',
            'medicina funcional', 'reiki', 'ayurveda',
        ),
        'meditation-mindfulness-spirituality' => array(
            'meditación', 'meditacion', 'mindfulness', 'atención plena',
            'espiritualidad', 'consciencia',
        ),
        'personal-growth-learning' => array(
            'crecimiento personal', 'desarrollo personal', 'life coach',
            'superación', 'superacion', 'transformación',
        ),
    );

    /** @var array Spanish specialty keyword map */
    private $specialty_map_es = array(
        'Anxiety'           => array('ansiedad', 'ansioso', 'ansiosa', 'preocupación', 'preocupado', 'nervioso', 'ataque de pánico', 'pánico', 'fobia', 'nervios', 'inquietud'),
        'Depression'        => array('depresión', 'deprimido', 'deprimida', 'triste', 'desesperanza', 'tristeza', 'melancolía', 'sin ánimos', 'bajón'),
        'Trauma & PTSD'     => array('trauma', 'tept', 'estres postraumático', 'traumático', 'abuso', 'violación', 'accidente trauma'),
        'Relationships'     => array('pareja', 'matrimonio', 'relación', 'relaciones', 'divorcio', 'terapia familiar', 'consejería de pareja', 'problemas de pareja', 'problemas matrimoniales'),
        'Life Transitions'  => array('duelo', 'pérdida', 'transición', 'cambio de vida', 'jubilación', 'nuevo bebé', 'mudanza', 'pérdida de empleo'),
        'Addiction'         => array('adicción', 'alcohol', 'drogas', 'recuperación', 'sobriedad', 'alcoholismo', 'problemas con alcohol', 'adicción drogas'),
        'Eating Disorders'  => array('trastorno alimenticio', 'anorexia', 'bulimia', 'imagen corporal', 'problemas con comida', 'atracones'),
        'ADHD'              => array('tdah', 'déficit de atención', 'hiperactividad', 'problemas de concentración', 'distracción'),
        'LGBTQ+ Issues'     => array('lgbtq', 'gay', 'lesbiana', 'transgénero', 'identidad de género', 'salir del closet', 'no binario'),
        'Stress Management' => array('estrés', 'estres', 'agobio', 'burnout', 'quemado', 'agotamiento', 'presión laboral'),
        'Self-Esteem'       => array('autoestima', 'confianza', 'inseguridad', 'dudas de uno mismo', 'no me siento suficiente'),
        'Anger Management'  => array('ira', 'enojo', 'rabia', 'frustración', 'problemas de ira', 'perder los estribos'),
        'Sleep Issues'      => array('dormir', 'insomnio', 'pesadillas', 'no puedo dormir', 'problemas para dormir', 'desvelo'),
        'Chronic Illness'   => array('enfermedad crónica', 'dolor crónico', 'fibromialgia', 'enfermedad larga duración'),
        'Mindfulness'       => array('mindfulness', 'atención plena', 'meditación', 'respiración consciente'),
        // Additional specialties
        'OCD'               => array('toc', 'obsesivo compulsivo', 'pensamientos obsesivos', 'comportamiento compulsivo'),
        'Bipolar Disorder'  => array('bipolar', 'trastorno bipolar', 'cambios de humor extremos', 'mania'),
        'Postpartum'        => array('postparto', 'depresión postparto', 'ansiedad postparto', 'bebé recién nacido', 'mamá primeriza'),
        'Parenting'         => array('crianza', 'problemas con hijos', 'comportamiento infantil', 'niño desafiante', 'padre soltero'),
    );

    /** @var array Spanish search signal keywords */
    private $search_signals_es = array(
        'buscar', 'necesito', 'ayuda con', 'muéstrame', 'muestrame',
        'recomendación', 'recomendacion', 'quién puede', 'quien puede',
        'dónde puedo', 'donde puedo', 'hay algún', 'hay algun',
        'cerca de mí', 'cerca de mi', 'cerca', 'en raleigh', 'en durham',
        'quiero encontrar', 'encuentra', 'mostrar', 'enséñame', 'ensename',
        'opciones', 'otra opción', 'otra opcion', 'otra categoría', 'otra categoria',
    );

    /** @var array Spanish provider-side intent patterns */
    private $provider_add_patterns_es = array(
        'agregar mi práctica', 'agregar mi consulta', 'listar mi negocio',
        'quiero listar', 'soy proveedor', 'soy terapeuta',
        'cómo agregó mi', 'como agrego mi', 'registrarme',
        'anunciar mi práctica', 'añadir mi práctica',
        'agregar mi practica', 'anadir mi practica', 'anunciar mi practica',
    );

    private $provider_claim_patterns_es = array(
        'reclamar mi perfil', 'reclamo mi perfil', 'reclamar mi listado',
        'mi perfil ya está', 'ya estoy listado', 'editar mi perfil',
        'actualizar mi perfil', 'ese soy yo', 'es mi consultorio',
        'cómo reclamo', 'como reclamo',
        // Voice-style Spanish claim phrases
        'reclamar mi práctica', 'reclamar mi consulta', 'reclamar mi negocio',
        'gestionar mi perfil', 'gestionar mi práctica', 'gestionar mi negocio',
        'verificar mi práctica', 'verificar mi listado', 'verificar mi perfil',
        'ese listado es mío', 'ese perfil es mío', 'ese perfil soy yo',
        'quiero reclamar', 'ayuda para reclamar', 'como puedo reclamar',
        'soy el proveedor', 'soy ese proveedor', 'yo soy ese terapeuta',
        // ── Claim-after-seeing-list: referential Spanish phrases ──
        'ese soy yo', 'ese soy yo mismo', 'el primero soy yo', 'la primera soy yo',
        'el segundo soy yo', 'la segunda soy yo', 'ese es mi consultorio',
        'ese es mi negocio', 'ese es mi perfil', 'esa es mi práctica',
        'veo mi nombre', 'mi nombre está ahí', 'mi nombre está en la lista',
        'soy el que está listado', 'soy ese profesional',
        'el primer resultado soy yo', 'ese resultado es mío',
        'ese listado es mi consultorio', 'ese perfil es mi consultorio',
        // ── Claim-after-seeing-list: want to claim a specific listed provider ──
        'reclamar ese', 'reclamar el primero', 'reclamar el segundo',
        'reclamar el tercero', 'reclamar ese proveedor', 'reclamar ese listado',
        'reclamar ese perfil', 'quiero reclamar ese', 'quiero reclamar el primero',
        'quiero reclamar el segundo', 'puedo reclamar ese',
        'puedo reclamar el primero', 'necesito reclamar ese',
        'cómo reclamo ese perfil', 'cómo reclamo el primero',
        'déjenme reclamar ese', 'déjame reclamar ese',
        // ── Take-over / access language (Spanish) ──
        'tomar control de mi perfil', 'tomar control de mi listado',
        'tomar posesión de mi perfil', 'acceso a mi perfil', 'acceso a mi listado',
        'activar mi perfil', 'activar mi listado', 'desbloquear mi perfil',
        // ── Correction as claim proxy (Spanish) ──
        'la información de mi perfil está mal', 'los datos de mi listado están mal',
        'mi teléfono está mal en', 'corregir la información de mi perfil',
        'necesito actualizar el teléfono', 'necesito actualizar la información',
        // ── Identity assertion (Spanish) ──
        'estoy en la lista', 'estoy listado ahí', 'ya estoy en su sitio',
        'mi perfil ya está en', 'mi consultorio ya está en',
        'soy ese profesional', 'soy el que aparece ahí',
        'ese soy yo de verdad', 'ese realmente soy yo',
        'ese es mi listado ahí', 'ese es mi perfil ahí',
    );

    private $provider_upgrade_patterns_es = array(
        'mejorar mi listado', 'listado premium', 'más visibilidad', 'mas visibilidad',
        'promocionar mi perfil', 'listado destacado', 'más pacientes', 'mas pacientes',
        'más clientes', 'mas clientes', 'perfil mejorado',
        'pulse lite', 'pulse pro', 'pulse upgrade',
        'mejorar a pulse lite', 'mejorar a pulse pro',
        'qué es pulse lite', 'que es pulse lite', 'qué es pulse pro', 'que es pulse pro',
    );

    private $sponsor_patterns_es = array(
        'patrocinio', 'patrocinar', 'publicidad', 'anunciar',
        'asociación empresarial', 'asociacion comercial', 'colaboración',
    );

    /**
     * Crisis patterns — checked FIRST, overrides everything.
     * Specific phrases to avoid false positives (e.g. "substance abuse" won't trigger).
     */
    private $crisis_patterns = array(
        'suicidal', 'suicide', 'kill myself', 'end my life', 'want to die',
        "don't want to live", 'no reason to live', 'better off dead',
        'thinking about ending it', 'plan to end it', 'going to kill myself',
        'wish i was dead', 'wish i were dead', 'rather be dead',
        'self-harm', 'self harm', 'cutting myself', 'hurt myself',
        'hurting myself', 'harm myself', 'harming myself',
        'thinking about hurting myself', 'thinking of hurting myself',
        'burning myself', 'injuring myself',
        'overdose', 'pills to end', 'take all my pills',
        'being abused', 'domestic violence', 'someone is hurting me',
        'abusing me', 'violent partner', 'hitting me', 'beating me',
        'sexually assaulted', 'raped', 'molested',
        'not safe at home', 'fear for my life', 'going to hurt me',
        'emergency crisis', 'severe crisis', 'in immediate danger',
    );

    /** Category keyword map: slug => [name, keywords[]] */
    private $category_map = array(
        'mental-health-therapy' => array(
            'name' => 'Mental Health & Therapy',
            'keywords' => array(
                'therapist', 'therapy', 'counselor', 'counseling', 'counselling',
                'psychologist', 'psychiatrist', 'mental health', 'anxiety',
                'depression', 'depressed', 'trauma', 'ptsd',
                'couples therapy', 'couples counseling', 'marriage counseling',
                'grief', 'grief counseling', 'addiction counseling',
                'self-esteem', 'anger management', 'ocd', 'adhd',
                'eating disorder', 'panic', 'bipolar', 'emotional support',
                'talk therapy', 'cbt', 'dbt', 'emdr', 'psychotherapy',
                'behavioral health', 'phobia', 'social anxiety', 'postpartum',
                'mood disorder', 'family therapy', 'child therapist',
                'teen therapist', 'substance abuse counselor',
                'addiction therapist',
                // Expanded natural language variations
                'shrink', 'head doctor', 'pill doctor', 'medication management',
                'someone to talk to', 'need to talk', 'talk to someone',
                'feeling down', 'feeling low', 'not doing well',
                'going through a hard time', 'having a rough time',
                'stressed out', 'burned out', 'burnt out', 'burnout',
                'overwhelmed', "can't cope", 'can not cope',
                'marriage problems', 'relationship issues', 'relationship problems',
                'having problems with my partner', 'having problems with my spouse',
                'my kid needs help', 'my child needs', 'child behavior',
                'teen issues', 'teenager problems', 'my teenager', 'my adolescent',
                'drinking problem', 'drug problem', 'struggling with alcohol',
                'struggling with drugs', 'anger issues',
                'mental health professional', 'behavioral health provider',
            ),
        ),
        'primary-care-medical-services' => array(
            'name' => 'Primary Care & Medical Services',
            'keywords' => array(
                'doctor', 'physician', 'primary care', 'medical',
                'family medicine', 'internal medicine', 'pediatrician',
                'clinic', 'checkup', 'physical exam',
                'general practitioner', 'urgent care', 'nurse practitioner',
                'primary care doctor', 'family doctor', 'regular doctor',
                'annual physical', 'health checkup', 'medical checkup',
            ),
        ),
        'fitness-yoga-movement' => array(
            'name' => 'Fitness, Yoga & Movement',
            'keywords' => array(
                'fitness', 'yoga', 'pilates', 'personal trainer', 'gym',
                'exercise', 'workout', 'movement', 'dance', 'stretching',
                'strength training', 'cardio', 'crossfit', 'barre', 'tai chi',
                'get in shape', 'lose weight exercise', 'fitness coach',
                'yoga instructor', 'yoga class', 'fitness class',
            ),
        ),
        'nutrition-dietary-support' => array(
            'name' => 'Nutrition & Dietary Support',
            'keywords' => array(
                'nutrition', 'nutritionist', 'dietitian', 'diet', 'meal plan',
                'eating healthy', 'weight loss', 'weight management',
                'dietary', 'registered dietitian', 'food allergy',
                'nutrition coach', 'healthy eating', 'diet plan',
                'food sensitivities', 'nutritional counseling',
            ),
        ),
        'massage-spa-recovery' => array(
            'name' => 'Massage, Spa & Recovery',
            'keywords' => array(
                'massage', 'massage therapist', 'spa', 'med spa', 'medspa',
                'medical spa', 'aesthetic spa', 'aesthetics', 'facial',
                'injectables', 'botox', 'skin care', 'skincare', 'body work', 'bodywork',
                'deep tissue', 'relaxation massage', 'sports massage',
                'chiropractor', 'chiropractic', 'physical therapy',
                'therapeutic massage', 'swedish massage', 'prenatal massage',
                'recovery therapy', 'pain relief massage',
            ),
        ),
        'sleep-rest-wellness' => array(
            'name' => 'Sleep & Rest Wellness',
            'keywords' => array(
                'sleep specialist', 'sleep coach', 'sleep apnea',
                'sleep hygiene', 'insomnia specialist', 'sleep doctor',
                'sleep wellness', 'rest wellness',
                'can not sleep', "can't sleep", 'trouble sleeping',
                'sleep problems', 'sleep issues', 'better sleep',
                'sleep help', 'insomnia help',
            ),
        ),
        'social-connections-support-groups' => array(
            'name' => 'Social Connections & Support Groups',
            'keywords' => array(
                'support group', 'community group', 'peer support',
                'group therapy', 'social wellness', 'connection group',
                'grief group', 'recovery group',
                'support circle', 'peer group', 'group meeting',
                'community support', 'mutual support',
            ),
        ),
        'financial-wellness-planning' => array(
            'name' => 'Financial Wellness & Planning',
            'keywords' => array(
                'financial advisor', 'financial planning', 'financial therapy',
                'financial wellness', 'money coach', 'debt counseling',
                'budgeting help', 'financial counselor',
                'money problems', 'debt help', 'financial stress',
                'retirement planning', 'investment advice',
            ),
        ),
        'career-work-life-coaching' => array(
            'name' => 'Career & Work-Life Coaching',
            'keywords' => array(
                'career coach', 'executive coach', 'career coaching',
                'work-life balance', 'job coaching', 'workplace wellness',
                'leadership coach', 'professional development coach',
                'job change', 'career change', 'job search help',
                'work stress', 'career guidance', 'professional coach',
            ),
        ),
        'integrative-holistic-therapies' => array(
            'name' => 'Integrative & Holistic Therapies',
            'keywords' => array(
                'holistic', 'integrative', 'acupuncture', 'acupuncturist',
                'naturopath', 'naturopathy', 'functional medicine',
                'alternative medicine', 'herbal medicine', 'homeopathic',
                'energy healing', 'reiki', 'ayurveda',
                'natural healing', 'whole body wellness', 'complementary medicine',
                'eastern medicine', 'traditional chinese medicine',
            ),
        ),
        'meditation-mindfulness-spirituality' => array(
            'name' => 'Meditation, Mindfulness & Spirituality',
            'keywords' => array(
                'meditation teacher', 'mindfulness coach', 'spiritual director',
                'spiritual wellness', 'contemplative', 'meditation class',
                'mindfulness training', 'spirituality guide',
                'learn to meditate', 'meditation help', 'mindfulness practice',
                'spiritual guidance', 'meditation coach',
            ),
        ),
        'personal-growth-learning' => array(
            'name' => 'Personal Growth & Learning',
            'keywords' => array(
                'life coach', 'life coaching', 'personal development',
                'personal growth', 'self-improvement', 'transformation coach',
                'mentoring', 'self-help',
                'wellness coach', 'health coach', 'motivational coach',
                'confidence building', 'personal transformation',
                'become a better person', 'life improvement',
            ),
        ),
    );

    /** Specialty keyword map for mental health sub-categories */
    private $specialty_map = array(
        'General Counseling' => array('general counseling', 'general counsellor', 'general counselor', 'general therapy support', 'talk therapy'),
        'Med Spa'            => array('med spa', 'medspa', 'medical spa', 'aesthetic spa', 'aesthetics', 'facial', 'injectables', 'botox', 'skin care', 'skincare'),
        'Anxiety'           => array('anxiety', 'anxious', 'worry', 'worried', 'nervous', 'panic attack', 'panic disorder', 'phobia', 'ocd', 'obsessive', 'anxiety disorder', 'generalized anxiety', 'social anxiety', 'separation anxiety'),
        'Depression'        => array('depression', 'depressed', 'sad', 'hopeless', 'low mood', 'postpartum', 'seasonal affective', 'major depression', 'clinical depression', 'feeling empty', 'no motivation'),
        'Trauma & PTSD'     => array('trauma', 'ptsd', 'childhood trauma', 'emdr', 'complex ptsd', 'traumatic', 'post traumatic', 'traumatic stress', 'abuse survivor', 'assault', 'accident trauma'),
        'Relationships'     => array('couples', 'relationship', 'marriage', 'family therapy', 'divorce', 'premarital', 'couples counseling', 'couples therapy', 'marriage counseling', 'marriage problems', 'relationship issues', 'partner issues', 'intimacy issues', 'communication problems'),
        'Life Transitions'  => array('grief', 'loss', 'bereavement', 'career change', 'life transition', 'aging', 'retirement', 'empty nest', 'new parent', 'new baby', 'job loss', 'relocation', 'moving'),
        'Addiction'         => array('addiction', 'substance abuse', 'alcohol', 'drug', 'recovery', 'sober', 'sobriety', 'substance use', 'drinking problem', 'alcoholism', 'drug dependency', 'addict', 'twelve step', '12 step'),
        'Eating Disorders'  => array('eating disorder', 'anorexia', 'bulimia', 'binge eating', 'body image', 'food issues', 'disordered eating', 'compulsive eating', 'purging', 'restricting', 'body dysmorphia'),
        'ADHD'              => array('adhd', 'attention deficit', 'focus issues', 'hyperactivity', 'inattention', 'concentration problems', 'easily distracted', 'trouble focusing', 'adhd assessment', 'add'),
        'LGBTQ+ Issues'     => array('lgbtq', 'gay', 'lesbian', 'transgender', 'queer', 'gender identity', 'coming out', 'gender dysphoria', 'transitioning', 'nonbinary', 'sexual identity'),
        'Stress Management' => array('stress', 'stressed', 'burnout', 'overwhelmed', 'pressure', 'work stress', 'stressed out', 'burned out', 'burnt out', 'work life balance', 'time management', 'overworked'),
        'Self-Esteem'       => array('self-esteem', 'self-worth', 'confidence', 'self-image', 'insecurity', 'low self esteem', 'self confidence', 'self doubt', 'not good enough', 'imposter syndrome'),
        'Anger Management'  => array('anger', 'angry', 'rage', 'frustration', 'temper', 'irritable', 'anger issues', 'anger problems', 'blowing up', 'losing temper', 'aggression'),
        'Sleep Issues'      => array('sleep', 'insomnia', 'nightmare', 'sleep problem', "can't sleep", 'can not sleep', 'trouble sleeping', 'sleep disorder', 'sleep apnea', 'restless sleep', 'waking up at night'),
        'Chronic Illness'   => array('chronic illness', 'chronic pain', 'chronic condition', 'pain management', 'fibromyalgia', 'autoimmune', 'long term illness', 'medical trauma', 'coping with illness'),
        'Mindfulness'       => array('mindfulness', 'mindful', 'present moment', 'meditation practice', 'mindfulness meditation', 'grounding techniques', 'breathing exercises'),
        // Additional specialties
        'OCD'               => array('ocd', 'obsessive compulsive', 'obsessive thoughts', 'compulsive behavior', 'checking behavior', 'intrusive thoughts'),
        'Bipolar Disorder'  => array('bipolar', 'manic depression', 'mood swings', 'mania', 'hypomania', 'cyclothymia', 'bipolar disorder'),
        'Postpartum'        => array('postpartum', 'postnatal', 'new mom depression', 'baby blues', 'postpartum anxiety', 'postpartum depression', 'ppd'),
        'Parenting'         => array('parenting', 'parent support', 'child behavior', 'defiant child', 'behavior problems', 'raising kids', 'single parent', 'co parenting'),
    );

    /** Known Triangle-area cities */
    private $known_cities = array(
        'raleigh', 'durham', 'chapel hill', 'cary', 'apex',
        'morrisville', 'holly springs', 'fuquay-varina', 'wake forest',
        'garner', 'knightdale', 'carrboro',
    );

    /** Provider-side intent keyword patterns */
    private $provider_add_patterns = array(
        'add my practice', 'list my practice', 'add my listing',
        'submit my practice', 'register my practice', 'join the directory',
        'become a provider', 'sign up as provider', 'get listed',
        'add my business', 'list my business', "i'm a provider",
        'i am a provider', "i'm a therapist and want", 'provider sign up',
        'how do i list', 'how to add my', 'want to list my',
        // ── Expanded add-listing patterns ──
        'add my clinic', 'add my office', 'create my profile',
        'put my practice on', 'register my clinic', 'register my business',
        'join your directory', 'join your platform', 'list my services',
        'get my practice listed', 'i want to be listed', 'want to be on the directory',
        'i want to be on your site', 'add myself as a provider',
        'sign up my practice', 'enroll my practice', 'create a listing',
        'make a profile', 'start a profile', 'set up my page',
        'i am a therapist looking', 'i am a counselor and',
        'i am a doctor and want', 'i am a coach and want',
        'how can i join', 'how to get listed', 'how do i get on',
        'put me on the directory', 'put me on your site',
        'new provider registration', 'new listing',
        'i want to be a provider', 'want to be a provider',
    );

    private $provider_claim_patterns = array(
        'claim my profile', 'claim my listing', 'claim listing',
        'claiming my list', 'claim my list',
        'how do i claim', 'i see my profile', "that's my listing",
        "i'm already listed", 'take ownership', 'manage my listing',
        'update my profile', 'edit my listing', 'my profile is already',
        'my profile is there', 'my profile is on there', 'my profile is on your site',
        'my profile is on your', 'my listing is there', 'my listing is on your',
        'claim this profile', 'claim your free profile', 'this is my profile',
        'that profile is mine',
        'own this listing', 'correct my profile', 'change my profile',
        'fix my listing', 'update my contact info',
        // Voice-style / provider-owner phrases
        'claim my provider', 'claim provider', 'claim my practice',
        'claim my business', 'claim my page', 'claim my provider page',
        'manage my profile', 'manage my provider', 'manage my provider page',
        'manage my practice', 'manage my business',
        'verify my listing', 'verify my practice', 'verify my profile',
        'my business is listed', 'my practice is listed', 'my listing is on',
        'that listing is mine', 'that profile is me', 'that is my practice',
        'that is my business', 'that is my profile', 'i own that listing',
        'i own that profile', 'this is my business', 'this is my practice',
        'help me claim', 'help with claiming', 'help claiming',
        'how do i own', 'how do i manage', 'how do i verify',
        'i am a provider and', 'im a provider and',
        'i want to own my', 'i want to manage my',
        // ── Claim-after-seeing-list: referential phrases ──
        "that's me", 'that is me', 'that one is me', 'that one is mine',
        'the first one is me', 'the second one is me', 'the first one is mine',
        'that is my practice', 'that is my business right there',
        'that provider is me', "that's my practice", "that's my business",
        'i see my name', "that's my name on there", 'my name is on there',
        "i'm the one listed", "i'm that provider", 'the first result is me',
        'that result is mine', 'that listing is my practice',
        'that profile is my practice', 'the top one is mine',
        // ── Claim-after-seeing-list: want to claim a specific listed provider ──
        'claim that one', 'claim the first one', 'claim the second one',
        'claim the third one', 'claim that provider', 'claim that listing',
        'claim that profile', 'claim that result',
        'i want to claim that', "i'd like to claim that", 'i wanna claim that',
        'i want to claim the first', 'i want to claim the second',
        'can i claim that', 'can i claim the first', 'can i claim the second',
        'let me claim that', 'let me claim the first one',
        'how do i claim that one', 'how do i claim the first one',
        'need to claim that', 'need to claim the first',
        // ── Take-over / access language ──
        'take over my profile', 'take over my listing', 'take over that profile',
        'take over that listing', 'take ownership of my profile',
        'take ownership of that profile', 'take ownership of that listing',
        'get access to my profile', 'get access to my listing',
        'access to my profile', 'access my profile', 'access my listing',
        'i want control of my', 'control of my profile', 'control of my listing',
        'unlock my profile', 'activate my profile', 'activate my listing',
        // ── Correction as claim proxy ──
        'correct the info on my profile', 'correct info on my listing',
        'the info on my listing is wrong', 'the information on my profile is wrong',
        'my phone number is wrong on', 'wrong info on my profile',
        'wrong information on my listing', 'need to correct my',
        'i need to update the phone', 'i need to update the info',
        // ── Identity assertion (seeing themselves in results) ──
        'i am on there', "i'm on there", 'i am listed on there',
        'i am already on your site', "i'm already on your site",
        'my profile is already on', 'my practice is already on',
        'i am that provider', "i'm that provider",
        'i am the one listed', "i'm the one listed",
        'that is actually me', "that's actually me",
        'that is my listing right there', 'that is my profile right there',
        // ── Expanded claim patterns ──
        'that is my office', 'that is my clinic', "that's my office",
        'my information is wrong', 'wrong address on my',
        'wrong phone on my', 'update my hours',
        'change my phone number', 'update my address',
        'i need to edit', 'i need to update my',
        'how to edit my profile', 'how to update my listing',
        'modify my listing', 'modify my profile',
        'refresh my profile', 'renew my listing',
        'my page needs updating', 'my info needs updating',
        "that listing is mine", "i own it",
        'i own that listing', 'i own this listing',
    );

    private $provider_upgrade_patterns = array(
        'upgrade my listing', 'premium listing', 'featured listing',
        'get more visibility', 'promote my listing', 'boost my profile',
        'paid listing', 'what do i get if i upgrade', 'upgrade benefits',
        'premium features', 'more leads', 'more patients', 'more clients',
        'sponsored listing', 'enhanced profile',
        'pulse lite', 'pulse pro', 'pulse upgrade',
        'upgrade to pulse lite', 'upgrade to pulse pro',
        'tell me about pulse', 'what is pulse lite', 'what is pulse pro',
        // ── Expanded upgrade patterns ──
        'make my listing stand out', 'stand out from competitors',
        'get more views', 'increase my visibility', 'get more calls',
        'get more bookings', 'attract more clients', 'grow my practice',
        'grow my business', 'increase my reach', 'get found more',
        'highlight my listing', 'feature my practice',
        'premium profile', 'enhanced listing', 'priority listing',
        'top of the list', 'first page', 'above the competition',
        'better placement', 'improve my ranking',
        'what are the plans', 'what are the tiers', 'pricing plans',
        'compare plans', 'paid options', 'paid features',
        'cost to upgrade', 'how much to upgrade', 'upgrade pricing',
        'is it worth upgrading', 'benefits of premium',
        'listing more visible', 'listing stand out',
    );

    private $sponsor_patterns = array(
        'sponsor', 'sponsorship', 'partnership opportunity',
        'advertise on your', 'advertising opportunity', 'corporate partnership',
        'company interested in sponsoring', 'brand partnership', 'media kit',
        'business to business', 'bulk listing', 'enterprise partnership',
        // ── Expanded sponsor patterns ──
        'become a sponsor', 'want to sponsor', 'sponsor a category',
        'sponsor the directory', 'advertising rates', 'ad prices',
        'banner ad', 'display ad', 'promote my brand',
        'partner with', 'collaboration opportunity', 'joint venture',
        'affiliate program', 'referral partnership',
        'corporate account', 'multi location listing',
        'group practice listing', 'organization listing',
        'franchise listing', 'network of providers',
        'health system partnership', 'insurance company partnership',
        'employER wellness program', 'company wellness',
    );

    // ===========================================================
    //  MAIN PIPELINE
    // ===========================================================

    /**
     * Normalize text by correcting common typos and misspellings.
     * Applies the typo normalization map to improve matching robustness.
     *
     * @param string $message_lower Lowercase message to normalize.
     * @return string Normalized message with typos corrected.
     */
    private function normalize_text($message_lower) {
        $normalized = strtolower(trim((string) $message_lower));

        // Speech-first cleanup: remove hesitation/filler tokens and repeated
        // starts before intent detection. This helps accented or careful speech
        // such as "um, I need, I need my profile" preserve the real intent.
        $normalized = preg_replace('/[,.!?;:]+/', ' ', $normalized);
        $normalized = preg_replace('/\b(?:um+|uh+|erm+|er+|ah+|eh+|hmm+|mm+|emm+|amm+|em+|este+|pues|o\s+sea|bueno|you know|ya know)\b/i', ' ', $normalized);
        $normalized = preg_replace('/\b(?:like)\b(?=\s+(?:i|can|could|need|want|my|claim|provider|profile|listing)\b)/i', ' ', $normalized);
        $normalized = preg_replace('/\b(i\s+need|i\s+want|i\s+am|i\'m|my\s+profile|my\s+listing|claim\s+my|add\s+my)(?:\s+\1\b)+/i', '$1', $normalized);
        $normalized = preg_replace('/\b(necesito|quiero|mi\s+perfil|mi\s+listado|reclamar\s+mi|agregar\s+mi|añadir\s+mi|anadir\s+mi)(?:\s+\1\b)+/iu', '$1', $normalized);
        $normalized = preg_replace('/\b(\w+)(?:\s+\1\b)+/i', '$1', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = trim($normalized);

        // Repair common broken-English / ASR sequences before typo mapping.
        $normalized = preg_replace('/\bi\s+need\s+the\s+my\b/i', 'i need my', $normalized);
        $normalized = preg_replace('/\bi\s+(?:need|want)\s+claim\s+my\b/i', 'i need to claim my', $normalized);
        $normalized = preg_replace('/\bneed\s+claim\s+my\b/i', 'need to claim my', $normalized);
        $normalized = preg_replace('/\bmy\s+(profile|listing|provider profile|provider page)\s+claim\b/i', 'claim my $1', $normalized);
        $normalized = preg_replace('/\b(profile|listing)\s+mine\b/i', 'my $1', $normalized);
        $normalized = preg_replace('/\bi\s+need\s+my\s+(profile|proof|listing|provider profile|provider proof|provider page)\s+i\s+need(?:\s+\w+)?\s*$/i', 'i need my $1', $normalized);
        $normalized = preg_replace('/\bi\s+need\s+(?:the\s+)?my\s+proof\b/i', 'i need my profile', $normalized);
        $normalized = preg_replace('/\bmy\s+provider\s+proof\b/i', 'my provider profile', $normalized);
        $normalized = preg_replace('/\bplay\s+(?:music|mindless|minus|my\s+less|me\s+this|me\s+his|me\s+is)\s+(provider profile|provider page|profile|listing|provider|proof|practice|business)\b/i', 'claim my $1', $normalized);
        $normalized = preg_replace('/\b(?:you|u)\s+(?:signed|sign|side|send|sent)\s+(provider profile|provider page|profile|listing|provider)\b/i', 'claim my $1', $normalized);
        $normalized = preg_replace('/\b(?:yeah\s+)?(?:(?:it|this|that)\s+)?(?:could\s+be\s+)?(?:for\s+)?claiming\s+(?:or\s*)?$/i', 'claim my profile', $normalized);
        $normalized = preg_replace('/\bnecesito\s+el\s+mi\b/iu', 'necesito mi', $normalized);
        $normalized = preg_replace('/\b(?:necesito|quiero)\s+reclamar\s+(?:el\s+)?mi\b/iu', 'necesito reclamar mi', $normalized);
        $normalized = preg_replace('/\breclamo\s+mi\b/iu', 'reclamar mi', $normalized);
        $normalized = preg_replace('/\bclamar\s+mi\b/iu', 'reclamar mi', $normalized);
        $normalized = preg_replace('/\breclama\s+mi\b/iu', 'reclamar mi', $normalized);
        $normalized = preg_replace('/\bmi\s+(perfil|listado|práctica|practica|consulta|negocio)\s+reclamar\b/iu', 'reclamar mi $1', $normalized);
        $normalized = preg_replace('/\b(perfil|listado)\s+m[ií]o\b/iu', 'mi $1', $normalized);
        $normalized = preg_replace('/\bnecesito\s+mi\s+(perfil|perfile|listado|perfil de proveedor|p[áa]gina de proveedor)\s+necesito(?:\s+\w+)?\s*$/iu', 'necesito mi $1', $normalized);
        $normalized = preg_replace('/\bmi\s+perfile\b/iu', 'mi perfil', $normalized);
        $normalized = preg_replace('/\bmi\s+por\s+fin\b/iu', 'mi perfil', $normalized);
        $normalized = preg_replace('/\bmi\s+perf[ií]l\b/iu', 'mi perfil', $normalized);
        $normalized = preg_replace('/\bprovedor\b/iu', 'proveedor', $normalized);
        $normalized = preg_replace('/\bprove[eé]dor\b/iu', 'proveedor', $normalized);

        // Provider-search/listing speech repairs for accented or broken English.
        $normalized = preg_replace('/\b(?:play|place|plate|please)\s+(raleigh|durham|charlotte|cary|apex|greensboro|chapel hill|wake forest|gastonia|hillsborough|ahoskie|louisburg)\b/i', 'find providers in $1', $normalized);
        $normalized = preg_replace('/\b(?:made|mate|meta|mid)\s+(?:a\s+|the\s+)?spa\b/i', 'med spa', $normalized);
        $normalized = preg_replace('/\bmedspa\b/i', 'med spa', $normalized);
        $normalized = preg_replace('/\btherapy\s+(?:doctor|person|people|provider)\b/i', 'therapist', $normalized);
        $normalized = preg_replace('/\btherapist\s+(?:doctor|person|people)\b/i', 'therapist', $normalized);
        $normalized = preg_replace('/\bmental\s+(?:help|support|doctor|person|people)\b/i', 'mental health provider', $normalized);
        $normalized = preg_replace('/\b(?:stress|anxiety|depression|trauma|grief)\s+(?:doctor|person|people|provider)\b/i', '$1 therapist', $normalized);
        $normalized = preg_replace('/\bdoctor\s+for\s+(anxiety|depression|stress|trauma|grief)\b/i', '$1 therapist', $normalized);
        $normalized = preg_replace('/\b(?:counseling|counselling)\s+(?:people|person|doctor)\b/i', 'counseling providers', $normalized);
        $normalized = preg_replace('/\bshow\s+(?:counseling|therapy)\s+people\b/i', 'show counseling providers', $normalized);
        $normalized = preg_replace('/\bi\s+(?:need|want)\s+(?:mental\s+)?help\s+(?:for\s+)?(anxiety|depression|stress|trauma|grief)\b/i', 'i need $1 therapist', $normalized);
        $normalized = preg_replace('/\bi\s+(?:need|want)\s+(anxiety|depression|stress|trauma|grief)\s+help\b/i', 'i need $1 therapist', $normalized);
        $normalized = preg_replace('/\bfind\s+(?:me\s+)?(?:therapy|counseling|mental health)\s+(?:person|people)\b/i', 'find therapists', $normalized);
        $normalized = preg_replace('/\b(?:find|show|search)\s+(?:a\s+)?(?:dinner|diner|dinder|winner|thinner)\b/i', 'find a provider', $normalized);

        // Spanish provider-search/listing speech repairs.
        $normalized = preg_replace('/\bayuda\s+(ansiedad|depresi[oó]n|estres|estr[eé]s|trauma|duelo)\b/iu', 'ayuda con $1', $normalized);
        $normalized = preg_replace('/\bnecesito\s+ayuda\s+(ansiedad|depresi[oó]n|estres|estr[eé]s|trauma|duelo)\b/iu', 'necesito ayuda con $1', $normalized);
        $normalized = preg_replace('/\b(?:doctor|doctora|persona|gente)\s+(?:de|para)\s+(ansiedad|depresi[oó]n|estres|estr[eé]s|trauma|duelo)\b/iu', 'terapeuta para $1', $normalized);
        $normalized = preg_replace('/\b(?:persona|gente)\s+de\s+(?:terapia|consejer[ií]a|salud mental)\b/iu', 'proveedores de terapia', $normalized);
        $normalized = preg_replace('/\bmu[eé]strame\s+(?:personas|gente)\s+de\s+(?:terapia|consejer[ií]a)\b/iu', 'muestrame terapeutas', $normalized);
        $normalized = preg_replace('/\bbusco\s+(?:ayuda\s+)?(ansiedad|depresi[oó]n|estres|estr[eé]s|trauma|duelo)\b/iu', 'busco terapeuta para $1', $normalized);
        $normalized = preg_replace('/\bquiero\s+psic[oó]logo\s+([a-záéíóúñ ]+)\b/iu', 'quiero psicologo en $1', $normalized);

        // "hola" → provider name "OLA" disambiguation.
        // Chrome ASR frequently mishears the provider name "OLA" as "hola"
        // (the Spanish greeting).  When "hola" co-occurs with provider-search
        // context words, it is almost certainly the provider name, not a
        // greeting.  Normalize to uppercase "OLA" so the backend searches
        // for a provider by that name and language detection stays English.
        if (preg_match('/\bhola\b/i', $normalized)) {
            $hola_provider_context = preg_match('/\b(?:find|show|search|look(?:ing)?|need|want|provider|providers|therapist|therapists|counselor|counselors|therapy|about|tell\s+me|near|city|in\s+(?:raleigh|durham|charlotte|cary|chapel\s+hill|greensboro|winston))\b/i', $normalized);
            if ($hola_provider_context) {
                $normalized = preg_replace('/\bhola\b/i', 'OLA', $normalized);
            }
        }

        foreach ($this->typo_normalization_map as $typo => $correction) {
            if ($typo === '') {
                // Skip empty-key entries (filler cleanup) — they have
                // already been handled by the preg_replace filler stage above.
                continue;
            }
            // Use word-boundary-aware replacement to prevent substring
            // corruption (e.g. "listin"→"listing" would otherwise mangle
            // the valid word "listing" into "listingg").
            $pattern = '/\b' . preg_quote($typo, '/') . '\b/i';
            $normalized = preg_replace($pattern, $correction, $normalized);
        }
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));
        if ($normalized !== $message_lower) {
            $this->trace('text_normalized', array('original' => $message_lower, 'normalized' => $normalized));
        }
        return $normalized;
    }

    /**
     * Check for paraphrase signals and return the detected signal type.
     *
     * @param string $message_lower Lowercase message to check.
     * @return string|null Signal type if detected, null otherwise.
     */
    private function detect_paraphrase_signal($message_lower) {
        foreach ($this->paraphrase_signals as $phrase => $signal) {
            if (strpos($message_lower, $phrase) !== false) {
                $this->trace('paraphrase_detected', array('phrase' => $phrase, 'signal' => $signal));
                return $signal;
            }
        }
        return null;
    }

    /**
     * Expand specialty keywords using synonym map for better matching.
     *
     * @param string $message_lower Lowercase message to expand.
     * @return string Message with expanded specialty terms.
     */
    private function expand_specialty_synonyms($message_lower) {
        $expanded = $message_lower;
        foreach ($this->specialty_synonym_map as $term => $synonyms) {
            if (strpos($message_lower, $term) !== false) {
                // Append synonyms to message for matching (without duplicating original)
                $expanded .= ' ' . implode(' ', $synonyms);
                $this->trace('synonym_expanded', array('term' => $term, 'synonyms' => $synonyms));
            }
        }
        return $expanded;
    }

    /**
     * Check for Spanglish patterns and mixed-language signals.
     *
     * @param string $message_lower Lowercase message to check.
     * @return string|null Detected signal type, null if none.
     */
    private function detect_spanglish_signals($message_lower) {
        foreach ($this->spanglish_signals as $phrase => $signal) {
            if (strpos($message_lower, $phrase) !== false) {
                $this->trace('spanglish_detected', array('phrase' => $phrase, 'signal' => $signal));
                return $signal;
            }
        }
        return null;
    }

    /**
     * Process a user message through the full intent pipeline.
     *
     * @param string $message       Raw user message.
     * @param array  $context       Directory context from get_directory_context().
     * @param array  $prior_context Optional. Prior search criteria carried forward from conversation.
     *                              Keys: primary_category, primary_category_name, specialty, city, provider_type.
     * @return array Processed result with keys: intent, criteria, providers, response, fallback_used, broadened.
     */
    public function process($message, $context, $prior_context = array()) {
        $this->debug_trace = array();
        $this->prior_context = $prior_context;
        $this->near_me_detected = false;
        $this->last_rotation_meta = array();
        $this->last_provider_exposure = array();
        $message_raw = strtolower(trim($message));
        $this->trace('input', $message);
        if (!empty($prior_context)) {
            $this->trace('prior_context', $prior_context);
        }

        // STEP 0a: Normalize text (typo correction) - applied BEFORE all other processing
        $message_normalized = $this->normalize_text($message_raw);
        $message_lower = $message_normalized;

        // STEP 0: Detect language (before any other processing)
        $this->detected_language = $this->detect_language($message_lower);
        $this->trace('detected_language', $this->detected_language);

        // Inherit language from prior context if still unknown
        if ($this->detected_language === 'unknown' && !empty($prior_context['language'])) {
            $this->detected_language = $prior_context['language'];
            $this->trace('language_inherited', $this->detected_language);
        }

        // STEP 1: Crisis detection (HARD OVERRIDE — runs first, always)
        // Checks BOTH English and Spanish crisis patterns
        if ($this->detect_crisis($message_lower)) {
            $this->trace('intent', self::INTENT_CRISIS);
            return array(
                'intent'           => self::INTENT_CRISIS,
                'criteria'         => array('language' => $this->detected_language),
                'providers'        => array(),
                'response'         => $this->build_crisis_response(),
                'fallback_used'    => false,
                'broadened'        => null,
                'near_me_detected' => false,
                'detected_language' => $this->detected_language,
            );
        }

        // STEP 2: Intent classification
        $intent = $this->classify_intent($message_lower);
        $this->trace('intent', $intent);

        // STEP 3: Route by intent
        switch ($intent) {
            case self::INTENT_PROVIDER_ADD:
                // Preserve user-entered capitalization for practice locations and
                // names while classification continues to use normalized text.
                return $this->build_provider_add_result($message);

            case self::INTENT_PROVIDER_CLAIM:
                return $this->wrap_deterministic($intent, $this->build_provider_claim_response());

            case self::INTENT_PROVIDER_OWNER:
                return $this->wrap_deterministic($intent, $this->build_provider_owner_response());

            case self::INTENT_PROVIDER_UPGRADE:
                return $this->wrap_deterministic($intent, $this->build_upgrade_response());

            case self::INTENT_SPONSOR:
                return $this->wrap_deterministic($intent, $this->build_sponsor_response());

            case self::INTENT_CONSUMER_SEARCH:
                return $this->handle_consumer_search($message_lower, $context);

            case self::INTENT_GENERAL:
            default:
                return array(
                    'intent'           => self::INTENT_GENERAL,
                    'criteria'         => array('language' => $this->detected_language),
                    'providers'        => array(),
                    'response'         => null,
                    'fallback_used'    => false,
                    'broadened'        => null,
                    'near_me_detected' => $this->near_me_detected,
                    'detected_language' => $this->detected_language,
                );
        }
    }

    /**
     * Run only the deterministic crisis gate without classification or retrieval.
     *
     * This is used before AI transcript normalization so explicit high-risk
     * language can never be rewritten into a lower-priority intent.
     */
    public function get_crisis_override($message, $prior_context = array()) {
        $message_lower = $this->normalize_text(strtolower(trim((string) $message)));
        $this->detected_language = $this->detect_language($message_lower);

        if ($this->detected_language === 'unknown' && !empty($prior_context['language'])) {
            $this->detected_language = $prior_context['language'];
        }

        if (!$this->detect_crisis($message_lower)) {
            return null;
        }

        return array(
            'intent'            => self::INTENT_CRISIS,
            'criteria'          => array('language' => $this->detected_language),
            'providers'         => array(),
            'response'          => $this->build_crisis_response(),
            'fallback_used'     => false,
            'broadened'         => null,
            'near_me_detected'  => false,
            'detected_language' => $this->detected_language,
        );
    }

    /**
     * Rebuild ranked providers from known criteria without re-running intent classification.
     */
    public function get_ranked_providers_from_context($criteria, $context) {
        if (empty($criteria) || !is_array($criteria)) {
            return array();
        }

        $normalized = array(
            'primary_category'      => $criteria['primary_category'] ?? null,
            'primary_category_name' => $criteria['primary_category_name'] ?? null,
            'specialty'             => $criteria['specialty'] ?? null,
            'city'                  => $criteria['city'] ?? null,
            'provider_type'         => $criteria['provider_type'] ?? null,
            'modality'              => $criteria['modality'] ?? null,
            'language_preference'   => $criteria['language_preference'] ?? null,
            'insurance'             => $criteria['insurance'] ?? null,
            'session_type'          => $criteria['session_type'] ?? null,
            'explicit_primary_category' => !empty($criteria['explicit_primary_category']),
            'explicit_specialty'        => !empty($criteria['explicit_specialty']),
            'explicit_city'             => !empty($criteria['explicit_city']),
            'explicit_provider_type'    => !empty($criteria['explicit_provider_type']),
            'explicit_modality'         => !empty($criteria['explicit_modality']),
            'explicit_language_preference' => !empty($criteria['explicit_language_preference']),
            'explicit_insurance'        => !empty($criteria['explicit_insurance']),
            'explicit_session_type'     => !empty($criteria['explicit_session_type']),
        );

        $search = $this->search_and_rank($normalized, $context);
        return $search['providers'] ?? array();
    }

    /**
     * Predictive prefetch may inspect the next ranked result set, but it must not
     * count an unseen result as an exposure or advance shared rotation state.
     */
    public function set_predictive_preview_mode($enabled) {
        $this->predictive_preview_mode = (bool) $enabled;
    }

    public function get_last_provider_exposure() {
        return is_array($this->last_provider_exposure) ? $this->last_provider_exposure : array();
    }

    private function wrap_deterministic($intent, $response) {
        return array(
            'intent'           => $intent,
            'criteria'         => array('language' => $this->detected_language),
            'providers'        => array(),
            'response'         => $response,
            'fallback_used'    => false,
            'broadened'        => null,
            'near_me_detected' => false,
            'detected_language' => $this->detected_language,
        );
    }

    // ===========================================================
    //  1. LANGUAGE DETECTION
    // ===========================================================

    /**
     * Detect language using lightweight heuristic patterns.
     * Returns 'en', 'es', or 'unknown'.
     */
    private function detect_language($message_lower) {
        // Common Spanish indicators (high specificity)
        $es_high = array(
            ' necesito ', ' quiero ', ' cómo ', ' como ', ' qué ', ' que ',
            ' dónde ', ' donde ', ' cuándo ', ' cuando ', ' por qué ', ' porque ',
            ' estoy ', ' tengo ', ' me puede ', ' puede ayudarme',
            ' búsqueda ', ' buscar ', ' busco ', ' encontrar ', ' muéstrame ', ' muestrame ',
            ' terapeuta ', ' consejero ', ' psicólogo ', ' psiquiatra ',
            ' salud mental ', ' ansiedad ', ' depresión ', ' estrés ', ' estres ',
            ' cerca de mí', ' cerca de mi', 'cerca de',
            ' patrocinio ', ' patrocinarme', 'agregar mi práctica', 'listar mi ',
            ' reclamar ', ' reclamo ', ' mi perfil ', ' mi listado ', ' proveedor ',
            ' gestionar ', ' verificar ', ' actualizar ', ' editar mi ',
            ' soy proveedor ', ' soy terapeuta ', ' práctica ', ' practica ',
            ' interesado en ', ' interesada en ', ' ayudar ',
            ' morir ', ' matar ', ' suicidio ', ' lastimarme ', ' hacerme ',
        );

        // "hola" and Spanish greetings are WEAK indicators only — they are
        // commonly used as bilingual greetings in English conversation
        // ("Hola, can you find me a therapist?").  Scored separately at +1
        // instead of +2 in $es_high, so English context words can override.
        $es_greeting_only = array(
            ' hola ', 'buenos dias', 'buenas tardes', 'buenas noches',
        );

        // Common English indicators (high specificity)
        $en_high = array(
            ' i need ', ' i want ', ' how ', ' what ', ' where ', ' when ', ' why ',
            ' i am ', " i'm ", ' i have ', ' can you ', ' could you ',
            ' find ', ' search ', ' looking for ', ' show me ',
            ' therapist ', ' therapy ', ' counselor ', ' psychologist ', ' psychiatrist ',
            ' mental health ', ' anxiety ', ' depression ', ' stress ',
            ' near me ', ' nearby ', ' close to ',
            ' sponsor ', ' add my practice ', ' list my ',
            ' kill myself ', ' want to die ', ' end my life ',
        );

        $es_score = 0;
        $en_score = 0;

        foreach ($es_high as $p) {
            if (strpos($message_lower, $p) !== false) $es_score += 2;
        }
        // Spanish greetings score only +1 (weak indicator)
        foreach ($es_greeting_only as $p) {
            if (strpos($message_lower, $p) !== false) { $es_score += 1; break; }
        }
        foreach ($en_high as $p) {
            if (strpos($message_lower, $p) !== false) $en_score += 2;
        }

        // Spanish-specific character patterns
        if (preg_match('/[áéíóúñü¿¡]/i', $message_lower)) {
            $es_score += 3;
        }

        // Additional Spanish verb patterns (me + verb)
        if (preg_match('/\bme quiero\b|\bquiero morir\b|\bbusco\b|\bnecesito\b|\bquiero\b|\breclamar\b|\bmi perfil\b|\bmi listado\b|\bsoy proveedor\b/i', $message_lower)) {
            $es_score += 3;
        }
        if (preg_match('/\b(?:find|show|search|looking|provider|providers|therapist|therapy|counselor|mental health|anxiety|depression|stress|near me|nearby)\b/i', $message_lower)) {
            $en_score += 2;
        }

        // Spanglish detection: if BOTH languages score, check whether the
        // Spanish words are genuine health/identity terms or just "hola".
        // Previously, any $has_es_health_word + $en_score > 0 forced 'es',
        // which caused "Hola, find me a therapist" to be detected as
        // Spanish.  Now only force Spanish when the Spanish health words
        // are strong (not just greetings or overlap words).
        $has_es_health_word = preg_match('/\b(terapeuta|consejero|consejera|psic[oó]logo|psiquiatra|ansiedad|depres[ií][oó]n|salud mental|ayuda|busco|necesito|quiero|reclamar|perfil|listado|pr[aá]ctica)\b/i', $message_lower);
        // "terapia" and "proveedor" overlap with English contexts — only
        // count them as Spanish health words when no strong English context
        // is present.
        $has_es_overlap_word = preg_match('/\b(terapia|proveedor)\b/i', $message_lower);
        if ($has_es_health_word) {
            $es_score += 2;
        }
        if ($has_es_overlap_word && !$has_es_health_word) {
            $es_score += 1;  // weaker than genuine Spanish health words
        }
        // Only force Spanish for genuine Spanglish (Spanish health terms
        // + English words), NOT for "hola" + English words.
        if ($has_es_health_word && $en_score > 0) {
            // Mixed Spanglish: user is bilingual or learning — respond in Spanish
            return 'es';
        }

        // Detect based on scores
        if ($es_score > $en_score && $es_score >= 2) return 'es';
        if ($en_score > $es_score && $en_score >= 2) return 'en';
        if ($es_score === $en_score && $es_score >= 2) {
            // Tie-breaker 1: presence of Spanish accent marks
            if (preg_match('/[áéíóúñ]/i', $message_lower)) return 'es';
            // Tie-breaker 2: Spanish health term present → prefer Spanish
            if ($has_es_health_word) return 'es';
            return 'en';
        }

        // Default to 'en' for short English-like phrases with common patterns
        if (preg_match('/\bi want\b|\bi need\b|\bi have\b|\bcan you\b|\bhelp me\b/i', $message_lower)) {
            return 'en';
        }

        return 'unknown';
    }

    // ===========================================================
    //  2. CRISIS DETECTION
    // ===========================================================

    private function detect_crisis($message_lower) {
        // Check English crisis patterns
        foreach ($this->crisis_patterns as $pattern) {
            if (strpos($message_lower, $pattern) !== false) {
                $this->detected_language = 'en';
                $this->trace('crisis_trigger', $pattern . ' (en)');
                return true;
            }
        }
        // Check Spanish crisis patterns
        foreach ($this->crisis_patterns_es as $pattern) {
            if (strpos($message_lower, $pattern) !== false) {
                $this->detected_language = 'es';
                $this->trace('crisis_trigger', $pattern . ' (es)');
                return true;
            }
        }
        return false;
    }

    // ===========================================================
    //  2. INTENT CLASSIFICATION
    // ===========================================================

    /**
     * Detect an explicit provider-owner topic switch while a claim flow is active.
     */
    private function detect_claim_context_topic_switch($message_lower) {
        $add_pattern = '/\b(?:(?:(?:can|could|may)\s+i|how\s+(?:do|can)\s+i|i\s+(?:want|need|would\s+like|\'d\s+like)\s+to|please)\s+)?(?:add|list|submit|register|create|start|set\s+up)\s+(?:(?:my|our|a|an|new)\s+)?(?:practice|business|clinic|office|listing|profile|provider\s+page)\b/i';
        $add_pattern_es = '/\b(?:(?:puedo|podr[ií]a|c[oó]mo\s+puedo|quiero|necesito)\s+)?(?:agregar|a[ñn]adir|listar|registrar|crear)\s+(?:(?:mi|nuestra|una|un|nueva|nuevo)\s+)?(?:pr[aá]ctica|consulta|negocio|cl[ií]nica|oficina|listado|perfil)\b/iu';
        $not_listed_pattern = '/\b(?:it|my\s+(?:practice|business|clinic|office|profile|listing))\s+(?:isn[\'’]?t|is\s+not|wasn[\'’]?t|was\s+not|doesn[\'’]?t|does\s+not)\s+(?:already\s+)?(?:listed|there|in\s+the\s+directory)\b|\b(?:couldn[\'’]?t|could\s+not|can[\'’]?t|cannot|didn[\'’]?t|did\s+not)\s+find\s+(?:it|my\s+(?:practice|business|profile|listing))\b/iu';
        $not_listed_pattern_es = '/\b(?:no\s+aparece|no\s+est[aá]\s+(?:listad[oa]|en\s+el\s+directorio)|no\s+(?:lo|la)\s+encontr[eé])\b/iu';
        if (preg_match($add_pattern, $message_lower) || preg_match($add_pattern_es, $message_lower) || preg_match($not_listed_pattern, $message_lower) || preg_match($not_listed_pattern_es, $message_lower)) {
            return self::INTENT_PROVIDER_ADD;
        }

        foreach ($this->provider_upgrade_patterns as $pattern) {
            if (strpos($message_lower, $pattern) !== false) {
                return self::INTENT_PROVIDER_UPGRADE;
            }
        }
        foreach ($this->provider_upgrade_patterns_es as $pattern) {
            if (strpos($message_lower, $pattern) !== false) {
                return self::INTENT_PROVIDER_UPGRADE;
            }
        }
        foreach ($this->sponsor_patterns as $pattern) {
            if (strpos($message_lower, $pattern) !== false) {
                return self::INTENT_SPONSOR;
            }
        }
        foreach ($this->sponsor_patterns_es as $pattern) {
            if (strpos($message_lower, $pattern) !== false) {
                return self::INTENT_SPONSOR;
            }
        }

        return null;
    }

    /**
     * Identify in-scope educational questions without mistaking a service name
     * for a request to search the directory.
     */
    public static function is_basic_education_question($message) {
        $message = trim((string) $message);
        if ($message === '') {
            return false;
        }

        // Natural spoken clarifications often describe the relationship
        // between two services without using a textbook "what is" shape.
        // Keep these conversational follow-ups educational unless the user
        // explicitly asks for directory records.
        $conceptual_relationship = preg_match(
            '/\b(?:belong(?:s|ed)?\s+to|part\s+of|same\s+as|(?:a\s+)?type\s+of|(?:a\s+)?kind\s+of)\b|(?:\b(?:did|do|would)\s+you\s+|\byou\s+)(?:suggest(?:ed)?|recommend(?:ed)?|mean|said?|tell)\b/iu',
            $message
        );
        $conceptual_domain = preg_match(
            '/\b(?:general\s+counsel(?:ing|ling)|counsel(?:ing|ling)|psychotherap(?:y|ist)|therap(?:y|ist)|cbt|dbt|emdr|sleep\s+coach(?:ing)?|consejer[ií]a|psicoterapia|terapia|tcc)\b/iu',
            $message
        );
        $explicit_directory_action = preg_match(
            '/\b(?:find|search|show|recommend|suggest|buscar|busco|encuentra|mu[eé]strame|recomienda)\b[\s\S]{0,80}\b(?:providers?|profiles?|listings?|therapists?|counselors?|proveedores?|perfiles?|listados?|terapeutas?|consejeros?)\b|\b(?:providers?|profiles?|listings?|proveedores?|perfiles?|listados?)\b[\s\S]{0,80}\b(?:find|search|show|recommend|suggest|buscar|encuentra|recomienda)\b/iu',
            $message
        );
        if ($conceptual_relationship && $conceptual_domain && !$explicit_directory_action) {
            return true;
        }

        $search_is_explicitly_deferred = preg_match(
            '/\b(?:do\s+not|don[\'’]?t|please\s+don[\'’]?t)\s+(?:search|find|show|recommend|suggest)\b(?:\s+(?:yet|now))?|\b(?:no|without)\s+(?:provider\s+)?(?:suggestions?|recommendations?|results?)\s+(?:yet|now)|\bfirst\s+(?:explain|describe|compare|help\s+me\s+understand|tell\s+me\s+about)\b|\b(?:no\s+busques|no\s+buscar|todav[ií]a\s+no|primero\s+(?:explica|describe|compara|ay[uú]dame\s+a\s+entender))\b/iu',
            $message
        );
        $asks_for_directory_data = preg_match(
            '/\b(?:find|search|show\s+me|recommend|suggest|near\s+me|nearby|book|schedule|appointment|available|availability|accepting|location|address|phone|website|how\s+many|do\s+you\s+have|are\s+there(?:\s+any)?|which\s+providers?(?!\s+(?:type|role))|who\s+(?:can|should)\s+i\s+see|(?:provider|listing|profile)\s+(?:name|names|link|links|details?)|buscar|busco|encuentra|mu[eé]strame|recomienda|cerca\s+de|reservar|cita|disponible|disponibilidad|cu[aá]ntos?|hay\s+alg[uú]n|(?:nombre|enlace|detalles?)\s+de\s+(?:proveedores?|listados?|perfiles?))\b/iu',
            $message
        );
        if ($asks_for_directory_data && !$search_is_explicitly_deferred) {
            return false;
        }

        if (preg_match('/\b(?:what\s+can\s+you\s+do|how\s+can\s+you\s+help|what\s+do\s+you\s+help\s+with|qu[eé]\s+puedes\s+hacer|c[oó]mo\s+puedes\s+ayudar|en\s+qu[eé]\s+ayudas)\b/iu', $message)) {
            return true;
        }

        $education_shape = preg_match(
            '/(?:\bwhat\s+(?:is|are|does|do|can)\b|\bwhat\s+kind\s+of\s+(?:help|support|care|service)|\bwhat\b[\s\S]{0,80}\b(?:cover|covers|include|includes|involve|involves|mean|means)\b|\bwhat\s+makes\b[\s\S]{0,90}\bdifferent\b|\b(?:can|could)\s+you\s+(?:explain|describe|tell\s+me)\b|\btell\s+me\s+(?:more\s+)?about\b|\b(?:explain|describe|compare)\b|\bhow\s+does\b|\bhow\s+(?:is|are)\b[\s\S]{0,90}\bdifferent\b|\bdifference\s+between\b|\bmeaning\s+of\b|\bwho\s+is\b[\s\S]{0,50}\bfor\b|\b(?:can|could|would)\b[\s\S]{0,90}\bhelp\s+with\b|\b(?:which|what)\s+(?:of\s+those\s+)?(?:provider\s+type|professional|role)\b|\bwhich\s+of\s+those\b[\s\S]{0,60}\bprescribe\b|\bwhen\s+(?:(?:should|would)\s+)?(?:someone|a\s+person|people|i|we)\b[\s\S]{0,90}\b(?:seek|get|use)\b|\bqu[eé]\s+(?:es|son|hace|hacen|cubre|cubren|incluye|incluyen|significa)\b|\bqu[eé]\s+diferencia\s+hay\s+entre\b|\bdiferencia\s+hay\s+entre\b|\ben\s+qu[eé]\s+consiste\b|\bpara\s+qu[eé]\s+sirve\b|\b(?:puede|podr[ií]a)\b[\s\S]{0,90}\bayudar\s+con\b|\bcu[aá]ndo\s+deber[ií]a\b[\s\S]{0,90}\bbuscar\b|\b(?:explica|expl[ií]came|describe|descr[ií]beme|cu[eé]ntame|compara)\b|\bdiferencia\s+entre\b|\ben\s+qu[eé]\s+se\s+diferencia\b)/iu',
            $message
        );
        if (!$education_shape) {
            return false;
        }

        return (bool) preg_match(
            '/\b(?:general\s+counsel(?:ing|ling)|counsel(?:ing|ling|or|lor)|therap(?:y|ist)|mental\s+health|psychotherap(?:y|ist)|talk\s+therapy|couples?\s+counsel(?:ing|ling)|family\s+therapy|play\s+therapy|cbt|cognitive\s+behavio(?:u)?ral\s+therapy|dbt|dialectical\s+behavio(?:u)?ral\s+therapy|emdr|anxiety|relationship\s+conflict|prescrib(?:e|ing)|medication|emergency\s+help|directory\s+support|primary\s+care|medical\s+services?|family\s+medicine|internal\s+medicine|pediatrics?|preventive\s+care|urgent\s+care|medication\s+management|fitness|yoga|movement|pilates|personal\s+train(?:er|ing)|strength\s+training|mobility|tai\s+chi|barre|nutrition|diet(?:ary|itian)?|nutritionist|nutrition(?:al)?\s+counseling|food\s+allerg(?:y|ies)|weight\s+management|massage|spa|recovery|bodywork|physical\s+therapy|physiotherapy|chiropract(?:ic|or)|med\s+spa|sleep|rest\s+wellness|sleep\s+(?:coaching|medicine|hygiene|apnea)|insomnia|support\s+groups?|social\s+connections?|peer\s+support|community\s+support|financial\s+wellness|financial\s+(?:planning|therapy)|money\s+coaching|budgeting|debt\s+counseling|retirement\s+planning|career\s+coaching|work[-\s]?life\s+coaching|executive\s+coaching|leadership\s+coaching|professional\s+development|job\s+transition|integrative|holistic|acupuncture|functional\s+medicine|naturopath(?:y|ic)?|traditional\s+chinese\s+medicine|reiki|ayurveda|herbal\s+medicine|meditation|mindfulness|spirituality|breathwork|spiritual\s+direction|personal\s+growth|personal\s+development|life\s+coaching|wellness\s+coaching|health\s+coaching|mentoring|self[-\s]?improvement|license\s+types?|lcsw|lmft|lpc|psychologist|psychiatrist|consejer[ií]a|consejer[oa]|terapia|terapeuta|salud\s+mental|psicoterapia|terapia\s+de\s+pareja|terapia\s+familiar|atenci[oó]n\s+primaria|servicios\s+m[eé]dicos|medicina\s+familiar|medicina\s+interna|pediatr[ií]a|atenci[oó]n\s+preventiva|nutrici[oó]n|dietista|masaje|recuperaci[oó]n|fisioterapia|quiropr[aá]ctica|sue[nñ]o|grupos?\s+de\s+apoyo|apoyo\s+entre\s+pares|bienestar\s+financiero|planificaci[oó]n\s+financiera|terapia\s+financiera|coaching\s+profesional|coaching\s+de\s+vida|terapias?\s+hol[ií]sticas?|medicina\s+funcional|naturopat[ií]a|acupuntura|meditaci[oó]n|respiraci[oó]n\s+consciente|direcci[oó]n\s+espiritual|crecimiento\s+personal|desarrollo\s+personal|tipos?\s+de\s+licencia)\b/iu',
            $message
        );
    }

    private function classify_intent($message_lower) {
        // Keep provider onboarding stateful. An ordinary follow-up in an active
        // add flow must not become a consumer search merely because it mentions
        // information, a city, or another form field.
        $active_owner_mode = $this->prior_context['provider_owner_mode'] ?? '';
        if ($active_owner_mode === self::INTENT_PROVIDER_UPGRADE &&
            preg_match('/\b(?:price|prices|pricing|cost|monthly|month|plan|plans|tier|tiers|their|they|them|difference|compare|benefit|benefits|feature|features|visibility|visible|placement|action|actions|pulse|lite|pro|upgrade|upgrading)\b|\bhow\s+much\b/iu', $message_lower)) {
            $this->trace('provider_upgrade_context_continuation', $message_lower);
            return self::INTENT_PROVIDER_UPGRADE;
        }
        if ($active_owner_mode === self::INTENT_PROVIDER_ADD) {
            if (preg_match('/^\s*(?:cancel|stop|never\s*mind|nevermind|forget\s+it|exit|end|new\s+chat|start\s+over|cancelar|detener|olvida(?:lo)?|salir|terminar|nuevo\s+chat|empezar\s+de\s+nuevo)\s*[.!?]*\s*$/iu', $message_lower)) {
                $this->trace('provider_add_context_cancelled', $message_lower);
                return self::INTENT_GENERAL;
            }

            $explicit_search = preg_match('/\b(?:find|search|show|looking\s+for|help\s+me\s+find|buscar|busco|encuentra|mu[eé]strame)\b[\s\S]{0,80}\b(?:provider|providers|therapist|therapists|counselor|counselors|doctor|doctors|psychiatrist|psychologist|massage|nutrition|dietitian|acupuncture|proveedor|proveedores|terapeuta|terapeutas|consejero|consejeros|m[eé]dico|psic[oó]logo|psiquiatra)\b/iu', $message_lower);
            if ($explicit_search) {
                $this->trace('provider_add_context_topic_switch', self::INTENT_CONSUMER_SEARCH);
                return self::INTENT_CONSUMER_SEARCH;
            }

            if (preg_match('/\b(?:claim|reclaim|take\s+over|access)\b[\s\S]{0,50}\b(?:profile|listing|practice|provider|business)\b|\b(?:reclamar|gestionar)\b[\s\S]{0,50}\b(?:perfil|listado|pr[aá]ctica|proveedor)\b/iu', $message_lower)) {
                $this->trace('provider_add_context_topic_switch', self::INTENT_PROVIDER_CLAIM);
                return self::INTENT_PROVIDER_CLAIM;
            }

            $owner_switch = $this->detect_claim_context_topic_switch($message_lower);
            if ($owner_switch && $owner_switch !== self::INTENT_PROVIDER_ADD) {
                $this->trace('provider_add_context_topic_switch', $owner_switch);
                return $owner_switch;
            }

            $this->trace('provider_add_context_continuation', $message_lower);
            return self::INTENT_PROVIDER_ADD;
        }

        // ── PRIORITY -1: Claim-flow continuation ──
        // When the user is in an active provider_owner_mode (set after a claim/add/owner
        // intent), their follow-up messages like "yes", a provider name, or city are
        // continuations of the claim conversation — NOT new consumer searches.
        // Without this, "John Smith in Raleigh" after "claim my profile" gets routed
        // to consumer_provider_search because it contains a name + city pattern.
        $has_claim_candidates = !empty($this->prior_context['claim_disambiguation_providers']) &&
            is_array($this->prior_context['claim_disambiguation_providers']);
        if (!empty($this->prior_context['provider_owner_mode']) || $has_claim_candidates) {
            $owner_mode = $this->prior_context['provider_owner_mode'] ?? '';
            $is_claim_context = in_array($owner_mode, array(
                self::INTENT_PROVIDER_CLAIM,
                self::INTENT_PROVIDER_OWNER,
            ), true) || $has_claim_candidates;
            if ($is_claim_context) {
                $topic_switch = $this->detect_claim_context_topic_switch($message_lower);
                if ($topic_switch) {
                    $this->trace('claim_context_topic_switch', $topic_switch);
                    return $topic_switch;
                }

                // "yes" / "yeah" / "correct" / "that's right" = confirm claim
                if (preg_match('/^\s*(?:yes|yeah|yep|correct|right|that\'?s?\s*right|sure|ok|okay|exactly|absolutely)\s*[!.?]*\s*$/i', trim($message_lower))) {
                    return self::INTENT_PROVIDER_CLAIM;
                }
                // Name + city / "my name is" / "practice name" = claim follow-up
                if (preg_match('/\bmy\s+name\s+is\b/i', $message_lower) ||
                    preg_match('/\b(?:practice|business|provider|clinic)\s+name\s+is\b/i', $message_lower) ||
                    preg_match('/\b(?:i\'?m|my name|my practice|my business)\s+/i', $message_lower)) {
                    return self::INTENT_PROVIDER_CLAIM;
                }
                // A proper name + city without search verbs is likely claim context
                if (!preg_match('/\b(?:find|search|show|look|need|want|help)\b/i', $message_lower) &&
                    preg_match('/\b(?:in|at|from)\s+\w+/i', $message_lower) &&
                    !preg_match('/\b(?:therapist|counselor|therapy|provider|doctor|psychiatrist|psychologist)\b/i', $message_lower)) {
                    return self::INTENT_PROVIDER_CLAIM;
                }
                // Name-only without city (e.g. "Dr. Smith", "Smith", "Johnson")
                // In claim context, a short non-search, non-provider-type phrase is
                // likely the user answering Mia's question about their name.
                $trimmed = trim($message_lower);
                $word_count = str_word_count($trimmed);
                // Ordinal / deictic selection: "the first one", "number 1",
                // "the third", "that one" — picking from the numbered list.
                // Must match BEFORE the short-phrase catch-all below so that
                // phrases like "first one" are explicitly recognized as list
                // selections rather than bare name attempts.
                if (preg_match('/\b(?:number\s*|#)\s*\d+\b/i', $trimmed) ||
                    preg_match('/\b(?:number|option|profile|listing)\s+(?:one|two|three|four|five|six|seven|eight|nine|ten)\b/i', $trimmed) ||
                    preg_match('/\b(?:that\'?s|that\s+is|this\s+is|it\'?s|it\s+is|ok(?:ay)?|alright|all\s+right|yes|yeah|yep|sure)\s+(?:the\s+)?(?:number\s+)?(?:\d+|one|two|three|four|five|six|seven|eight|nine|ten|first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|1st|2nd|3rd|4th|5th|6th|7th|8th|9th|10th)\b/i', $trimmed) ||
                    preg_match('/\b\d(?:st|nd|rd|th)\b/i', $trimmed) ||
                    preg_match('/\b(?:first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|1st|2nd|3rd|4th|5th|6th|7th|8th|9th|10th)\b/i', $trimmed) ||
                    preg_match('/\b(?:that|this|the)\s+(?:one|profile|listing|account)\b/i', $trimmed)) {
                    return self::INTENT_PROVIDER_CLAIM;
                }
                if ($word_count >= 1 && $word_count <= 6 &&
                    !preg_match('/\b(?:find|search|show|look|need|want|help|i\s+need|i\s+want|can\s+you|could\s+you)\b/i', $trimmed) &&
                    !preg_match('/\b(?:therapist|counselor|therapy|provider|doctor|psychiatrist|psychologist|nearby|near\s+me|counseling)\b/i', $trimmed) &&
                    !preg_match('/^\s*(?:no|nope|never|stop|cancel|forget|never\s*mind)\b/i', $trimmed)) {
                    return self::INTENT_PROVIDER_CLAIM;
                }
                // Specialty / number / practice disambiguation in claim context
                // When Mia asked "which one?", answers like "anxiety", "number 1",
                // "couples therapy", or a practice name should stay in claim context.
                if (!empty($this->prior_context['claim_disambiguation_providers'])) {
                    // Number selection: "number 1", "#2", "the first one"
                    if (preg_match('/\b(?:number\s*|#)\s*\d+\b/i', $trimmed) ||
                        preg_match('/\b(?:number|option|profile|listing)\s+(?:one|two|three|four|five|six|seven|eight|nine|ten)\b/i', $trimmed) ||
                        preg_match('/\b\d(?:st|nd|rd|th)\s+(?:one|profile|listing)\b/i', $trimmed) ||
                        preg_match('/\b(?:the\s+)?(?:first|second|third|fourth|fifth|sixth|1st|2nd|3rd|4th|5th|6th)\s+(?:one|profile|listing)\b/i', $trimmed)) {
                        return self::INTENT_PROVIDER_CLAIM;
                    }
                    // Specialty keyword match (e.g. "anxiety", "couples", "trauma")
                    if (preg_match('/\b(?:anxiety|depression|trauma|ptsd|couples|marriage|family|addiction|substance|grief|lgbtq|eating|bipolar|mindfulness|chronic|ocd|stress|anger|ptsd|abuse|domestic)\b/i', $trimmed)) {
                        return self::INTENT_PROVIDER_CLAIM;
                    }
                    // Short phrase that could be a practice name (2-4 words, no search verbs)
                    if ($word_count >= 2 && $word_count <= 4 &&
                        !preg_match('/\b(?:find|search|show|look|need|want|help|i\s+need|i\s+want|can\s+you|could\s+you)\b/i', $trimmed)) {
                        return self::INTENT_PROVIDER_CLAIM;
                    }
                }
            }
        }

        // ── PRIORITY 0: Provider-side intents (claim/add/upgrade/sponsor) ──
        // These must run BEFORE paraphrase/search checks so that phrases like
        // "help me claim my profile" are not swallowed by general search signals
        // such as "help me find" or "i need help".
        $owner_intro_pattern = '/^(?:i(?:\'|’)m|im|i am)\s+(?:a|an)?\s*(?:provider|therapist|counselor|counsellor|doctor|clinician|practice owner|business owner)\s*[?.!]*$/i';
        if (preg_match($owner_intro_pattern, trim($message_lower))) {
            return self::INTENT_PROVIDER_OWNER;
        }
        $owner_intro_pattern_es = '/^(?:soy|yo\s+soy)\s+(?:un|una)?\s*(?:proveedor|proveedora|terapeuta|psic[oó]logo|psic[oó]loga|psiquiatra|consejero|consejera|due[ñn]o\s+de\s+pr[aá]ctica|due[ñn]a\s+de\s+pr[aá]ctica)\s*$/iu';
        if (preg_match($owner_intro_pattern_es, trim($message_lower))) {
            return self::INTENT_PROVIDER_OWNER;
        }
        if (preg_match('/\b(?:yes,\s*)?claim(?:ing)?\b.*\b(?:profile|listing|provider|practice|business|page|mine|my|this|that|one|the\s+(?:first|second|third|1st|2nd|3rd))\b/i', $message_lower) ||
            preg_match('/\byes\s+claim\b/i', $message_lower) ||
            preg_match('/\bclaim\s+(?:that|the\s+(?:first|second|third|1st|2nd|3rd|top|bottom|last|next))\b/i', $message_lower) ||
            preg_match('/\b(?:i\s+(?:want|wanna|d\s+like|would\s+like)\s+to\s+claim|can\s+i\s+claim|let\s+me\s+claim|need\s+to\s+claim)\b/i', $message_lower)) {
            $this->trace('claim_after_list', 'claim_ordinal_or_want');
            return self::INTENT_PROVIDER_CLAIM;
        }
        // ── PRIORITY 0.1: Add / Upgrade pattern checks BEFORE the broad claim regex ──
        // The broad "my listing/practice/profile" regex below catches any phrase containing
        // those words and routes to CLAIM — but add/upgrade intents also contain them.
        // Running add & upgrade patterns first ensures correct routing.
        if (preg_match('/\b(?:add|list|submit|register|create|set\s+up)\b[\s\S]{0,35}\b(?:my|our|a|an)?\s*(?:practice|business|clinic|office|listing|profile|provider(?:\s+(?:details?|information|profile|listing|page))?)\b/iu', $message_lower) ||
            preg_match('/\b(?:agregar|a[ñn]adir|listar|registrar|crear)\b[\s\S]{0,35}\b(?:mi|nuestra|un|una)?\s*(?:pr[aá]ctica|negocio|cl[ií]nica|oficina|listado|perfil|proveedor(?:\s+(?:datos|informaci[oó]n|perfil|listado|p[aá]gina))?)\b/iu', $message_lower)) {
            return self::INTENT_PROVIDER_ADD;
        }
        // Provider-add patterns (English)
        foreach ($this->provider_add_patterns as $p) {
            if (strpos($message_lower, $p) !== false) return self::INTENT_PROVIDER_ADD;
        }
        // Provider-add patterns (Spanish)
        foreach ($this->provider_add_patterns_es as $p) {
            if (strpos($message_lower, $p) !== false) return self::INTENT_PROVIDER_ADD;
        }
        // Upgrade patterns (English)
        if (preg_match('/\b(?:upgrade|upgrading)\b[\s\S]{0,70}\b(?:provider|practice|profile|listing|visibility|placement|plan|tier|feature|pulse|lite|pro)\b|\b(?:provider|practice|profile|listing|visibility|placement|plan|tier|feature|pulse|lite|pro)\b[\s\S]{0,70}\b(?:upgrade|upgrading)\b/iu', $message_lower)) {
            return self::INTENT_PROVIDER_UPGRADE;
        }
        foreach ($this->provider_upgrade_patterns as $p) {
            if (strpos($message_lower, $p) !== false) return self::INTENT_PROVIDER_UPGRADE;
        }
        // Upgrade patterns (Spanish)
        foreach ($this->provider_upgrade_patterns_es as $p) {
            if (strpos($message_lower, $p) !== false) return self::INTENT_PROVIDER_UPGRADE;
        }
        // Sponsor patterns (English)
        foreach ($this->sponsor_patterns as $p) {
            if (strpos($message_lower, $p) !== false) return self::INTENT_SPONSOR;
        }
        // Sponsor patterns (Spanish)
        foreach ($this->sponsor_patterns_es as $p) {
            if (strpos($message_lower, $p) !== false) return self::INTENT_SPONSOR;
        }
        // ── PRIORITY 0.2: Broad claim regex (must run AFTER add/upgrade/sponsor) ──
        if (preg_match('/\b(?:i\s+)?(?:need|want|have|manage|update|edit|fix|help(?:\s+with)?|take\s+over|get\s+access\s+to|access|activate|unlock|control|correct)\b.*\bmy\s+(?:profile|provider profile|provider page|listing|practice profile|practice|business)\b/i', $message_lower) ||
            preg_match('/\bmy\s+(?:profile|provider profile|provider page|listing|practice profile|practice|business)\b/i', $message_lower) ||
            preg_match('/\b(?:take\s+over|take\s+ownership\s+of|get\s+access\s+to|access|activate|unlock)\s+(?:that|the|my)\s+(?:profile|listing|practice|business|provider)\b/i', $message_lower)) {
            $this->trace('claim_after_list', 'takeover_or_access');
            return self::INTENT_PROVIDER_CLAIM;
        }
        // ── Claim-after-seeing-list: identity assertion patterns ──
        // "that's me", "the first one is me/mine", "that is my practice", etc.
        if (preg_match('/\b(?:that\'?s?\s+(?:is\s+)?(?:me|mine)|that\s+one\s+(?:is\s+)?(?:me|mine)|the\s+(?:first|second|third|1st|2nd|3rd|top|bottom|last)\s+(?:one|result|provider|listing|profile)?\s+(?:is\s+)?(?:me|mine)|that\s+(?:provider|result|listing|profile)\s+(?:is\s+)?(?:me|mine)|i\s+(?:am|\'?m)\s+(?:that\s+)?(?:provider|the\s+one|listed|on\s+there)|i\s+see\s+my\s+(?:name|profile)|that\'?s?\s+my\s+(?:name|practice|business|profile|listing))\b/i', $message_lower)) {
            $this->trace('claim_after_list', 'identity_assertion');
            return self::INTENT_PROVIDER_CLAIM;
        }
        // ── Correction as claim proxy: "info on my listing is wrong", etc. ──
        if (preg_match('/\b(?:wrong|incorrect|outdated|old|bad)\s+(?:info(?:rmation)?|data|details?|number|phone|address)\s+(?:on|in|at)\s+my\s+(?:profile|listing|practice|page)\b/i', $message_lower) ||
            preg_match('/\b(?:need\s+to\s+correct|need\s+to\s+update\s+the)\s+(?:info|information|phone|number|address|details?)\s+(?:on|in)\s+my\b/i', $message_lower)) {
            $this->trace('claim_after_list', 'correction_proxy');
            return self::INTENT_PROVIDER_CLAIM;
        }
        if (preg_match('/\b(?:necesito|quiero|tengo|gestionar|actualizar|editar|arreglar|verificar|ayuda(?:\s+con)?|ay[úu]dame(?:\s+a)?|tomar\s+control\s+de|activar|desbloquear|acceder\s+a)\b.*\bmi\s+(?:perfil|perfil de proveedor|p[áa]gina de proveedor|listado|pr[aá]ctica|consulta|negocio)\b/iu', $message_lower) ||
            preg_match('/\bmi\s+(?:perfil|perfil de proveedor|p[áa]gina de proveedor|listado|pr[aá]ctica|consulta|negocio)\b/iu', $message_lower)) {
            return self::INTENT_PROVIDER_CLAIM;
        }
        // ── Spanish claim-after-seeing-list patterns ──
        // "ese soy yo", "el primero soy yo", "quiero reclamar ese", etc.
        if (preg_match('/\b(?:el\s+(?:primero|segundo|tercero)|la\s+(?:primera|segunda|tercera))\s+soy\s+yo\b/iu', $message_lower) ||
            preg_match('/\b(?:ese|esa|este|esta)\s+(?:es\s+)?mi\s+(?:consultorio|negocio|perfil|pr[aá]ctica|listado|proveedor)\b/iu', $message_lower) ||
            preg_match('/\b(?:ese|esa|este|esta)\s+(?:consultorio|negocio|perfil|pr[aá]ctica|listado|proveedor)\s+es\s+m[ií][oa]\b/iu', $message_lower) ||
            preg_match('/\b(?:ese|esa)\s+soy\s+yo\b/iu', $message_lower) ||
            preg_match('/\b(?:quiero|puedo|necesito)\s+reclamar\s+(?:ese|esa|el\s+primero|el\s+segundo|el\s+tercero|la\s+primera|la\s+segunda)\b/iu', $message_lower) ||
            preg_match('/\b(?:reclamar|tomar)\s+(?:ese|esa|el\s+primero|el\s+segundo)\b/iu', $message_lower) ||
            preg_match('/\b(?:c[oó]mo)\s+reclamo\s+(?:ese|esa|el\s+primero|el\s+segundo)\b/iu', $message_lower)) {
            $this->trace('claim_after_list', 'spanish_identity_or_claim');
            return self::INTENT_PROVIDER_CLAIM;
        }
        if (preg_match('/^\s*i\s+need\s+my\s*$/i', $message_lower) ||
            preg_match('/^\s*i\s+need\s+the\s+my\s*$/i', $message_lower)) {
            return self::INTENT_PROVIDER_OWNER;
        }
        if (preg_match('/^\s*necesito\s+mi\s*$/iu', $message_lower) ||
            preg_match('/^\s*quiero\s+mi\s*$/iu', $message_lower)) {
            return self::INTENT_PROVIDER_OWNER;
        }
        // Claim fragments: "claim my" (trailing, ASR cut off before noun),
        // "I want to claim" (without specifying what), "claim that/this/it/the"
        // These are incomplete but clearly carry claim intent → route to provider_owner
        // so the chatbot can ask "which profile?" instead of treating as generic chat.
        if (preg_match('/\b(?:claim|claiming)\s+my\s*$/i', $message_lower) ||
            preg_match('/\b(?:claim|claiming)\s+(?:that|this|it|the)\s*$/i', $message_lower) ||
            preg_match('/\b(?:i\s+(?:want|wanna|d\s+like|would\s+like)\s+to\s+claim|can\s+i\s+claim|let\s+me\s+claim|need\s+to\s+claim)\s*$/i', $message_lower)) {
            $this->trace('claim_fragment', 'incomplete_claim_intent');
            return self::INTENT_PROVIDER_OWNER;
        }

        // Provider-claim patterns (English)
        foreach ($this->provider_claim_patterns as $p) {
            if (strpos($message_lower, $p) !== false) return self::INTENT_PROVIDER_CLAIM;
        }
        // Provider-claim patterns (Spanish)
        foreach ($this->provider_claim_patterns_es as $p) {
            if (strpos($message_lower, $p) !== false) return self::INTENT_PROVIDER_CLAIM;
        }
        // (Add, Upgrade, Sponsor pattern loops have already run at PRIORITY 0.1 above)

        // ── PRIORITY 0.5: Scope boundary — out-of-scope requests ──
        // These MUST run BEFORE paraphrase/emotional signal detection because
        // phrases like "what medication should I take for anxiety" contain both
        // medical-advice intent AND condition keywords ("anxiety").  Without this
        // guard, detect_paraphrase_signal() matches "anxiety" → extract_condition
        // → consumer_search, and the boundary refusal never fires.
        // Expanded 2026-04-27: added coping strategies, jokes/entertainment,
        // supplement/dosage, meal/exercise plans, and general advice-seeking.
        // (Test C1/C2 audit 2026-04-27.)
        $out_of_scope_patterns = array(
            '/\b(?:what\s+medication|which\s+medication|should\s+i\s+take|what\s+(?:should|can)\s+i\s+take\s+for|do\s+i\s+have|diagnos|prescrib|what\s+dosage|recommend\s+(?:a\s+)?medic|medical\s+advice|therapy\s+technique|how\s+(?:to\s+)?treat\s+my|cure\s+my)\b/i',
            '/\b(?:coping\s+strateg|coping\s+skill|how\s+do\s+i\s+cope|how\s+to\s+deal\s+with|how\s+to\s+handle\s+my|self\s*-?\s*help?\s+tip|tip\s+for\s+(?:my|dealing)|tip\s+on\s+(?:managing|dealing|handling))/i',
            '/\b(?:what\s+should\s+i\s+do\s+about|what\s+can\s+i\s+do\s+about|give\s+me\s+advice\s+(?:on|about)|advise\s+me\s+on)\b/i',
            '/\b(?:tell\s+me\s+(?:a\s+)?joke|say\s+something\s+funny|make\s+me\s+laugh|riddle|trivia|fun\s+fact|entertain\s+me)\b/i',
            '/\b(?:what\s+(?:dose|dosage)|supplement\s+(?:should|recommend)|what\s+herb|natural\s+remedy)\b/i',
            '/\b(?:meal\s+plan|exercise\s+routine|workout\s+plan|diet\s+plan|nutrition\s+plan|fitness\s+routine)\b/i',
        );
        foreach ($out_of_scope_patterns as $oos_pattern) {
            if (preg_match($oos_pattern, $message_lower)) {
                return self::INTENT_GENERAL;
            }
        }

        // Definitions and explanations are one of Mia's three core jobs. Run
        // this before search signals and taxonomy matching so a phrase such as
        // "what does general counseling cover?" does not inherit a prior search.
        if (self::is_basic_education_question($message_lower)) {
            $this->trace('basic_education_question', $message_lower);
            return self::INTENT_GENERAL;
        }

        // ── PRIORITY 1: Paraphrase / emotional consumer search signals ──
        // Check for paraphrase signals first (natural language patterns)
        $paraphrase_signal = $this->detect_paraphrase_signal($message_lower);
        if ($paraphrase_signal === 'search' || $paraphrase_signal === 'extract_condition' ||
            $paraphrase_signal === 'extract_target' || $paraphrase_signal === 'mental_health' ||
            $paraphrase_signal === 'relationships' || $paraphrase_signal === 'child_therapy' ||
            $paraphrase_signal === 'substance_abuse') {
            return self::INTENT_CONSUMER_SEARCH;
        }

        // Check for Spanglish signals (mixed language handling)
        $spanglish_signal = $this->detect_spanglish_signals($message_lower);
        if ($spanglish_signal === 'search' || $spanglish_signal === 'mental-health-therapy' ||
            $spanglish_signal === 'mental_health') {
            return self::INTENT_CONSUMER_SEARCH;
        }
        if ($spanglish_signal === 'near_me') {
            $this->near_me_detected = true;
        }

        // Capability / education questions should stay in the support path,
        // not become provider searches just because they contain "help with".
        if (preg_match('/\bwhat\s+(?:kind|kinds|type|types)\s+of\s+services?\s+(?:(?:do\s+you|does\s+mia)\s+)?(?:have|offer|provide)\b|\bwhat\s+services?\s+(?:(?:do\s+you|does\s+mia)\s+)?(?:have|offer|provide)\b|\bqu[eé]\s+(?:tipo|tipos)\s+de\s+servicios?\s+(?:tienes|ofreces|brindas|proporcionas)\b|\bqu[eé]\s+servicios?\s+(?:tienes|ofreces|brindas|proporcionas)\b/iu', $message_lower)) {
            return self::INTENT_GENERAL;
        }
        if (preg_match('/\b(what can you help with|what do you help with|what can you do|how can you help|what do you do|who are you|what is mia|basic education|what are the categories|which categories|all (?:the )?categories|12 wellness categories|wellness categories|what (?:do you|does mia) cover|what services (?:do you cover|are available)|everything you cover|qu[eé]\s+puedes\s+hacer|c[oó]mo\s+puedes\s+ayudar|en\s+qu[eé]\s+ayudas|qu[eé] (?:cubres|cubre mia)|qu[eé] servicios (?:cubres|hay disponibles)|todas las categor[ií]as|categor[ií]as de bienestar)\b/iu', $message_lower)) {
            return self::INTENT_GENERAL;
        }

        // (Provider-side pattern checks have already run above in PRIORITY 0 —
        //  no need to repeat them here.)

        // Consumer search signals (English) - expanded with more natural phrases
        $search_signals = array(
            'find', 'search', 'looking for', 'need a', 'help with',
            'show me', 'recommend', 'suggestion', 'who can help',
            'where can i', 'do you have', 'are there any', 'any providers',
            'near me', 'nearby', 'close to', 'around',
            'in raleigh', 'in durham', 'in chapel hill', 'in cary',
            'in apex', 'in morrisville', 'in cary', 'in wake forest',
            // Additional natural phrases
            'i want', 'i would like', 'can you find', 'could you find',
            'do you know', 'any good', 'best', 'top rated',
            'specializes in', 'expert in', 'help for',
            // ── Expanded search signals ──
            'i need', 'i wanna', 'i gotta', 'gimme', 'give me',
            'connect me', 'point me', 'send me', 'set me up',
            'hook me up', 'can anyone', 'does anyone',
            'who does', 'where is', 'where are',
            'how about', 'what about', 'how much',
            'does anybody', 'know of', 'heard of',
            'taking new patients', 'accepting patients', 'accepting new',
            'taking clients', 'accepting clients', 'available now',
            'open today', 'open on weekends', 'evening hours',
            'weekend availability', 'same day', 'walk in',
            'in network', 'out of network', 'takes my insurance',
            'accepts medicaid', 'accepts medicare', 'takes blue cross',
            'sliding scale', 'free consultation', 'pro bono',
            // Specialty-specific
            'for anxiety', 'for depression', 'for trauma', 'for ptsd',
            'for couples', 'for marriage', 'for grief', 'for addiction',
            'for ocd', 'for adhd', 'for bipolar', 'for eating disorder',
            'for stress', 'for anger', 'for sleep',
            'for back pain', 'for neck pain', 'for chronic pain',
            'for weight loss', 'for nutrition', 'for fitness',
            'spanish speaking', 'bilingual', 'latinx',
            'black therapist', 'therapist of color', 'lgbtq friendly',
            'christian counseling', 'faith based', 'religious',
            'male therapist', 'female therapist', 'nonbinary therapist',
            // Category-natural
            'someone who does', 'person who does', 'place that does',
            'somewhere to', 'anywhere to', 'where to get',
            'how to find', 'need to find', 'want to find',
            'trying to find', 'looking to get',
        );
        foreach ($search_signals as $sig) {
            if (strpos($message_lower, $sig) !== false) return self::INTENT_CONSUMER_SEARCH;
        }
        // Consumer search signals (Spanish)
        foreach ($this->search_signals_es as $sig) {
            if (strpos($message_lower, $sig) !== false) return self::INTENT_CONSUMER_SEARCH;
        }

        // Expand message with specialty synonyms for better matching
        $message_expanded = $this->expand_specialty_synonyms($message_lower);

        // Check category keywords — if any match, it is a provider search
        foreach ($this->category_map as $slug => $data) {
            foreach ($data['keywords'] as $kw) {
                if (strlen($kw) <= 3) {
                    if (preg_match('/\b' . preg_quote($kw, '/') . '\b/', $message_expanded)) {
                        return self::INTENT_CONSUMER_SEARCH;
                    }
                } else {
                    if (strpos($message_expanded, $kw) !== false) return self::INTENT_CONSUMER_SEARCH;
                }
            }
        }
        // Check Spanish category keywords
        foreach ($this->category_map_es as $slug => $keywords) {
            foreach ($keywords as $kw) {
                if (strlen($kw) <= 3) {
                    if (preg_match('/\b' . preg_quote($kw, '/') . '\b/', $message_lower)) {
                        return self::INTENT_CONSUMER_SEARCH;
                    }
                } else {
                    if (strpos($message_lower, $kw) !== false) return self::INTENT_CONSUMER_SEARCH;
                }
            }
        }

        if ($this->looks_like_search_refinement_fragment($message_lower)) {
            return self::INTENT_CONSUMER_SEARCH;
        }

        // ── PRIORITY 10: Semantic fallback — fuzzy keyword matching ──
        // If no rule matched, check whether the message contains enough
        // wellness-related terms to qualify as a provider search.
        // This catches natural phrases that none of the strict patterns cover.
        $semantic_score = 0;
        $wellness_terms = array(
            // Provider types
            'therapist', 'counselor', 'psychologist', 'psychiatrist', 'doctor',
            'coach', 'nutritionist', 'dietitian', 'trainer', 'instructor',
            'acupuncturist', 'chiropractor', 'masseuse', 'masseur', 'healer',
            'practitioner', 'specialist', 'clinician', 'mentor', 'guide',
            // Conditions / needs
            'anxiety', 'depression', 'trauma', 'ptsd', 'stress', 'grief',
            'addiction', 'ocd', 'adhd', 'bipolar', 'panic', 'phobia',
            'insomnia', 'anger', 'eating', 'relationship', 'marriage',
            'pain', 'recovery', 'rehab', 'detox', 'sobriety',
            // Service types
            'therapy', 'counseling', 'massage', 'acupuncture', 'yoga',
            'meditation', 'coaching', 'training', 'reiki', 'hypnosis',
            'nutrition', 'fitness', 'pilates', 'chiropractic', 'bodywork',
            // Modalities
            'cbt', 'dbt', 'emdr', 'holistic', 'integrative', 'naturopathic',
            'homeopathic', 'ayurvedic',
            // Wellness intent
            'wellness', 'mental', 'emotional', 'behavioral', 'psychological',
            'physical', 'spiritual', 'support', 'healing', 'treatment',
            'session', 'appointment', 'consultation', 'evaluation',
        );
        $msg_words = array_unique(preg_split('/\s+/', $message_lower, -1, PREG_SPLIT_NO_EMPTY));
        foreach ($wellness_terms as $term) {
            if (in_array($term, $msg_words, true)) {
                $semantic_score++;
            }
        }
        // 2+ wellness terms = likely a search intent (e.g. "massage back pain",
        // "yoga for stress relief", "nutrition coach raleigh")
        if ($semantic_score >= 2) {
            $this->trace('semantic_fallback_triggered', array(
                'score' => $semantic_score,
                'message' => substr($message_lower, 0, 80),
            ));
            return self::INTENT_CONSUMER_SEARCH;
        }

        return self::INTENT_GENERAL;
    }

    private function looks_like_search_refinement_fragment($message_lower) {
        $has_prior_search = !empty($this->prior_context['recent_providers'])
            || !empty($this->prior_context['specialty'])
            || !empty($this->prior_context['primary_category'])
            || !empty($this->prior_context['city']);

        if (!$has_prior_search) {
            return false;
        }

        // ── Escape: "More providers" / "show me more" are NOT follow-ups ──
        $is_more_providers_request = preg_match('/\b(?:show\s+me\s+more|more\s+(?:providers?|options?|results?|matches?|choices?|listings?|names?|therapists?|counselors?)|any\s+(?:other|more)\s+(?:providers?|options?|ones?|results?|choices?)|other\s+(?:providers?|options?|choices?|results?|matches?)|another\s+(?:set|batch|group|round|page)\s+of\s+(?:providers?|results?|options?)|see\s+more|next\s+(?:page|set|batch|group|round|four|4)|anything\s+else|who\s+else|anyone\s+else|what\s+else\s+is\s+there|are\s+there\s+(?:any\s+)?(?:more|other|additional)|i\s+want\s+to\s+see\s+more|give\s+me\s+more|keep\s+going|more\s+like\s+these|similar\s+providers?|different\s+(?:ones?|providers?|options?)|m[aá]s\s+(?:proveedores?|opciones?|resultados?|terapeutas?|consejeros?|nombres?|listados?)|mu[eé]strame\s+m[aá]s|ver\s+m[aá]s|otros?\s+(?:proveedores?|opciones?|resultados?)|otra\s+(?:p[aá]gina|ronda|vez)|hay\s+m[aá]s|qui[eé]n\s+m[aá]s|alguien\s+m[aá]s|algo\s+m[aá]s|siguientes?|m[aá]s\s+como\s+estos|proveedores?\s+similares?|dame\s+m[aá]s|sigue|otro\s+grupo)\b/i', $message_lower);
        if ($is_more_providers_request) {
            $this->trace('more_providers_escape', 'detected — bypassing follow-up guard, routing to search refinement');
        }

        // ── Guard: Provider follow-up phrases must NOT be classified as search refinement ──
        // Phrases like "tell me more about John", "more about the first one", "details on
        // that provider", etc. are referential follow-ups about a specific shown provider —
        // NOT requests for new/different search results. Without this guard, the bare word
        // "more" in the regex below matches "tell me MORE about …" and routes to a fresh
        // search, causing Mia to return another provider list instead of the provider detail.
        //
        // Covers: tell me about, more about/on, details/info/information on/about/for,
        // who was/is that, which one was, remind me about, about the first/second/etc.,
        // can/could you tell me about, I want/wanna/d like to know about, give me info/details,
        // describe the first/etc, explain about/the first/etc, I'm interested in the first/etc,
        // how about the first/etc, let me know about, the first/second result, the top/bottom one,
        // the one you mentioned/said/just showed, go with the first/etc, I'll take the first/etc,
        // what about him/her/them/that/this, the top/first/bottom option,
        // plus Spanish equivalents: más sobre, dime más, quiero saber sobre, cuéntame sobre,
        // información sobre, describe el primero/etc, me interesa el primero, etc.
        if (preg_match('/' .
            '\b(?:tell\s+me\s+(?:more\s+)?about|more\s+(?:about|on|info(?:rmation)?\s+(?:on|about|for))|details?\s+(?:on|about|for)|info(?:rmation)?\s+(?:on|about|for))\b' .
            '|\b(?:who\s+(?:was|is|are)\s+(?:that|this|the|those)|which\s+one\s+was|remind\s+me\s+about)\b' .
            '|\b(?:can|could)\s+you\s+(?:tell\s+me\s+(?:more\s+)?about|give\s+me\s+(?:more\s+)?(?:info|details|information)\s+(?:on|about|for)|explain\s+(?:about|the))\b' .
            '|\b(?:i\s+(?:want\s+to|wanna|d\s+like\s+to|would\s+like\s+to)\s+(?:know\s+(?:more\s+)?about|hear\s+(?:more\s+)?about|learn\s+(?:more\s+)?about))\b' .
            '|\b(?:let\s+me\s+(?:know|hear)\s+about|give\s+me\s+(?:more\s+)?(?:info|details)\s+(?:on|about|for))\b' .
            '|\b(?:describe\s+(?:the\s+)?(?:first|second|third|1st|2nd|3rd|fourth|fifth|last|top|bottom|next)|explain\s+(?:the\s+)?(?:first|second|third|1st|2nd|3rd|fourth|fifth|last))\b' .
            '|\b(?:i\'?m\s+interested\s+in\s+(?:the\s+)?(?:first|second|third|1st|2nd|3rd|fourth|fifth|last)|interested\s+in\s+(?:the\s+)?(?:first|second|third|1st|2nd|3rd))\b' .
            '|\b(?:how\s+about\s+(?:the\s+)?(?:first|second|third|1st|2nd|3rd|fourth|fifth|last)|what\s+do\s+you\s+know\s+about|what\'?s\s+the\s+info\s+on)\b' .
            '|\b(?:go\s+with\s+(?:the\s+)?(?:first|second|third|1st|2nd|3rd|fourth|fifth|last)|i\'?ll\s+take\s+(?:the\s+)?(?:first|second|third|1st|2nd|3rd|fourth|fifth|last)|let\'?s\s+go\s+with\s+(?:the\s+)?(?:first|second|third|1st|2nd|3rd))\b' .
            '|\b(?:the\s+(?:first|second|third|1st|2nd|3rd|fourth|fifth|last|top|bottom|next)\s+(?:one|result|option|provider|choice|listing|match)|the\s+one\s+(?:you\s+(?:mentioned|said|just\s+(?:said|showed|listed))|at\s+the\s+top))\b' .
            '|\b(?:about\s+the\s+(?:first|second|third|1st|2nd|3rd|fourth|fifth|last|top|bottom)|what\s+about\s+(?:the\s+)?(?:first|second|third|1st|2nd|3rd|last|him|her|them|that\s+one|this\s+one|that|this))\b' .
            '|\b(?:the\s+(?:top|bottom)\s+one|i\s+like\s+(?:the\s+)?(?:first|second|third|1st|2nd|3rd|last))\b' .
            // Spanish followup patterns
            '|\b(?:m[aá]s\s+sobre|m[aá]s\s+(?:del|de\s+la|de)\s+(?:el\s+)?(?:primero|segundo|tercero|[uú]ltimo)|dime\s+m[aá]s)\b' .
            '|\b(?:quiero\s+saber\s+(?:m[aá]s\s+)?(?:sobre|de)|puedes\s+decirme\s+(?:m[aá]s\s+)?(?:sobre|de)|cu[eé]ntame\s+(?:m[aá]s\s+)?(?:sobre|de))\b' .
            '|\b(?:informaci[oó]n\s+(?:sobre|de)|describe\s+(?:el|la)\s+(?:primero|primera|segundo|segunda|tercero|tercera|[uú]ltimo)|me\s+interesa\s+(?:el|la)\s+(?:primero|primera|segundo|segunda))\b' .
            '|\b(?:expl[ií]came\s+(?:el|la|sobre)|vamos\s+con\s+(?:el|la)\s+(?:primero|primera|segundo|segunda)|me\s+quedo\s+con\s+(?:el|la)\s+(?:primero|primera|segundo|segunda))\b' .
            // ── Provider-offer followup phrases ──
            // After Mia says "I can compare this provider with another option
            // or help you check insurance, booking, or location next" the user
            // may respond with one of these specific topics. They are follow-ups
            // about the current provider set, NOT new search requests.
            '|\b(?:compar(?:e|ed|ing|isons?)?|versus|vs\.?|difference\s+between|how\s+do\s+they\s+(?:compare|differ)|stack\s+up|comparar|compar[aá]los|diferencia|put\s+(?:them|the\s+providers)\s+(?:side\s+by\s+side|together)|head\s+to\s+head|side\s+by\s+side|pros?\s+and\s+cons?|which\s+(?:one\s+)?(?:is\s+)?(?:better|best|right|worse|for)|who\s+(?:is\s+)?(?:better|best|worse)|pick\s+between|choose\s+between|one\s+or\s+the\s+other)\b' .
            '|\b(?:insurance|coverage|covered|accepts?\s+insurance|takes?\s+insurance|bcbs|blue\s+cross|aetna|cigna|united|medicaid|medicare|what\s+insurance|do\s+they\s+(?:take|accept)|seguro|cobertura|acepta\s+seguro)\b' .
            '|\b(?:book(?:ing)?|appointment|schedule|availab|openings?|taking\s+new\s+patients|accepting\s+new\s+patients|how\s+soon|earliest|when\s+can\s+i|can\s+i\s+(?:book|schedule)|set\s+up|make\s+an\s+appointment|reservar|cita|disponibilidad|agendar)\b' .
            '|\b(?:location|their\s+(?:location|address|insurance|office)|the\s+(?:location|insurance|booking|address)|office\s+location|clinic\s+location|where\s+(?:are\s+they|is\s+(?:it|that|this|the))|how\s+to\s+get|how\s+far|directions|nearby|close\s+to|drive|distance|map|office|phone|telephone|contact|call|website|address|ubicaci[oó]n|direcci[oó]n|tel[eé]fono|donde\s+est[aá]n|c[oó]mo\s+llegar)\b' .
            '/i', $message_lower) && !$is_more_providers_request) {
            $this->trace('refinement_guard', 'skipped: provider follow-up phrase detected');
            return false;
        }

        if ($this->match_city($message_lower)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(?:different|another|other|else|instead|more|category|categories|specialty|specialties|kind|type|city|area|location|nearby|near|around)\b|' .
            '\b(?:diferente|otra|otro|mas|más|categor[ií]a|categor[ií]as|especialidad|especialidades|ciudad|zona|cerca|pareja|familia|opci[oó]n|opciones)\b/iu',
            $message_lower
        );
    }

    /**
     * Extract a provider name from a user message.
     *
     * Detects patterns like:
     *  - "Dr. Smith", "Dr Smith"
     *  - "Can I see Angela Green"
     *  - "looking for Ashley Almon"
     *  - "I need Dr. Jones"
     *  - "Do you have Sarah Johnson"
     *  - Bare name: "Angla Green" (2-4 capitalized words not matching known categories)
     *
     * Returns the extracted name string or empty string if none found.
     */
    private function extract_provider_name($message_lower) {
        $name = '';

        // 1. "Dr./Doctor + LastName" pattern
        //    Stop at prepositions (in, at, from, near, etc.)
        if (preg_match('/\b(?:dr\.?|doctor)\s+([a-z]+(?:\s+(?!\b(?:in|at|from|near|nearby|around|of|for|by|on|with|who|that|which|a|an|the)\b)[a-z]+)?)\b/i', $message_lower, $m)) {
            $name = trim($m[1]);
        }

        // 2. "see/book/appointment with + Name" pattern
        //    Stop at prepositions (in, at, from, near, etc.)
        if (empty($name) && preg_match('/\b(?:see|book|appointment\s+with|visit|with|schedule\s+with)\s+([a-z]+(?:\s+(?!\b(?:in|at|from|near|nearby|around|of|for|by|on|with|who|that|who|which|a|an|the)\b)[a-z]+){0,2})\b/i', $message_lower, $m)) {
            $candidate = trim($m[1]);
            // Filter out generic words that aren't names
            if (!preg_match('/\b(?:a|an|the|my|me|some|any|this|that|one|provider|providers|therapist|therapists|counselor|counselors|doctor|psychiatrist|psychologist|someone)\b/i', $candidate)) {
                $name = $candidate;
            }
        }

        // 3. "looking for / need / want / find + Name" pattern
        //    Stop at prepositions
        if (empty($name) && preg_match('/\b(?:looking\s+for|need\s+to\s+(?:see|talk|visit)|want\s+to\s+see|want\s+(?:to\s+)?(?:see|book|visit)|find|searching\s+for|search\s+for)\s+([a-z]+(?:\s+(?!\b(?:in|at|from|near|nearby|around|of|for|by|on|with|who|that|which|a|an|the)\b)[a-z]+){0,2})\b/i', $message_lower, $m)) {
            $candidate = trim($m[1]);
            if (!preg_match('/\b(?:a|an|the|my|me|some|any|this|that|one|provider|providers|therapist|therapists|counselor|counselors|doctor|psychiatrist|psychologist|someone)\b/i', $candidate)) {
                $name = $candidate;
            }
        }

        // 4. "Do you have / Is there + Name" pattern
        //    Stop at prepositions
        if (empty($name) && preg_match('/\b(?:do\s+you\s+have|is\s+there|have\s+you)\s+([a-z]+(?:\s+(?!\b(?:in|at|from|near|nearby|around|of|for|by|on|with|who|that|which|a|an|the)\b)[a-z]+){0,2})\b/i', $message_lower, $m)) {
            $candidate = trim($m[1]);
            if (!preg_match('/\b(?:a|an|the|my|me|some|any|this|that|one|provider|providers|therapist|therapists|counselor|counselors|doctor|psychiatrist|psychologist|someone)\b/i', $candidate)) {
                $name = $candidate;
            }
        }

        // 5. Name-like pattern: 2-4 words that look like a proper name
        //    (starts with capital letter in original, contains at least one common name part)
        //    Only activate if the message is short enough to be a name-focused query
        if (empty($name)) {
            $word_count = str_word_count($message_lower);
            if ($word_count >= 2 && $word_count <= 8) {
                // Extract words that aren't common search verbs, cities, or category terms
                $skip_words = array(
                    'i', 'a', 'an', 'the', 'my', 'me', 'we', 'you', 'your', 'our',
                    'in', 'at', 'on', 'for', 'to', 'of', 'with', 'from', 'by', 'near', 'nearby', 'around',
                    'need', 'want', 'find', 'search', 'look', 'see', 'show', 'get', 'give', 'tell', 'book',
                    'looking', 'searching', 'trying', 'can', 'could', 'would', 'do', 'does', 'is', 'are',
                    'have', 'has', 'there', 'here', 'who', 'what', 'where', 'when', 'how',
                    'therapist', 'therapists', 'counselor', 'counselors', 'provider', 'providers', 'doctor', 'psychiatrist', 'psychologist',
                    'therapy', 'counseling', 'mental', 'health', 'anxiety', 'depression', 'trauma',
                    'appointment', 'session', 'help', 'please', 'thanks', 'thank', 'yes', 'no',
                    'raleigh', 'durham', 'chapel', 'hill', 'cary', 'morrisville', 'wake', 'forest',
                    'apex', 'holly', 'springs', 'charlotte', 'greensboro', 'winston', 'salem',
                    'fort', 'worth', 'clt', 'rdu', 'triad', 'triangle',
                );
                $words = array_filter(preg_split('/\s+/', $message_lower), function($w) use ($skip_words) {
                    return strlen($w) >= 2 && !in_array($w, $skip_words);
                });
                $words = array_values($words);
                // If we have 2-4 remaining words, they might be a name
                if (count($words) >= 2 && count($words) <= 4) {
                    // Check that at least 2 words look like name parts (not specialty/category terms)
                    $name_like_count = 0;
                    foreach ($words as $w) {
                        // Common name-like patterns: not a known specialty or category term
                        if (!preg_match('/^(?:therap|counsel|psych|social|marriage|family|child|adolescent|addiction|substance|group|couples|individual|general|clinical|licensed|board|certified|registered|associate)/i', $w)) {
                            $name_like_count++;
                        }
                    }
                    if ($name_like_count >= 2) {
                        $name = implode(' ', $words);
                    }
                }
            }
        }

        // Clean up the extracted name
        $name = trim(preg_replace('/[^a-z\s.\'-]/i', '', $name));
        $name = preg_replace('/\s+/', ' ', $name);

        if (preg_match('/^(?:me\s+|any\s+|some\s+)?(?:tv\s+)?(?:therapy\s+|therapist\s+|mental\s+health\s+|counseling\s+|counselor\s+)?providers?$/i', $name) ||
            preg_match('/^(?:therapy|therapist|therapists|counselor|counselors|mental health)$/i', $name)) {
            return '';
        }

        // Skip if it's too short or just a single common word
        if (strlen($name) < 3 || str_word_count($name) < 1) {
            return '';
        }

        // Skip if the name matches a known city, specialty, or category
        $skip_full = array('mental health', 'general counseling', 'clinical social', 'marriage family',
            'substance abuse', 'child adolescent', 'licensed clinical');
        foreach ($skip_full as $sf) {
            if (strpos($name, $sf) !== false) {
                return '';
            }
        }

        return $name;
    }

    private function looks_like_explicit_provider_name_query($message_lower, $provider_name) {
        $message_lower = strtolower(trim((string) $message_lower));
        $provider_name = strtolower(trim((string) $provider_name));
        if ($message_lower === '' || $provider_name === '') {
            return false;
        }

        if (preg_match('/\b(?:dr\.?|doctor)\s+[a-z]/i', $message_lower)) {
            return true;
        }

        if (preg_match('/\b(?:named|called|by\s+the\s+name\s+of|name\s+is)\s+[a-z]/i', $message_lower)) {
            return true;
        }

        if (preg_match('/\b(?:ashley|angela|dania|pamela|jamie|shannon|daniel|marguerite|nailah|robyn|tamara)\b/i', $provider_name)) {
            return true;
        }

        if (str_word_count($provider_name) >= 2 && preg_match('/\b(?:find|looking\s+for|search(?:ing)?\s+for|see|book|appointment\s+with|with)\s+' . preg_quote($provider_name, '/') . '\b/i', $message_lower)) {
            return true;
        }

        return false;
    }

    // ===========================================================
    //  3. STRUCTURED MATCH EXTRACTION
    // ===========================================================

    private function extract_search_criteria($message_lower) {
        $criteria = array(
            'primary_category'      => null,
            'primary_category_name' => null,
            'specialty'             => null,
            'city'                  => null,
            'provider_type'         => null,
            'modality'              => null,
            'language_preference'   => null,
            'insurance'             => null,
            'session_type'          => null,
            'provider_name'         => null,   // extracted provider name for name-based search
            'confidence'            => 0.0,
            'gender_preference'     => null, // 'female' or 'male' only when explicitly requested
        );

        $message_focus = $this->extract_positive_search_focus($message_lower);

        // Expand message with synonyms for better matching
        $message_expanded = $this->expand_specialty_synonyms($message_focus);

        // Category
        $cat = $this->match_category($message_expanded);
        $explicit_category = false;
        if ($cat) {
            $criteria['primary_category']      = $cat['slug'];
            $criteria['primary_category_name'] = $cat['name'];
            $criteria['confidence']           += 0.3;
            $explicit_category = true;
        }

        // Specialty (primarily within mental health, but also detect regardless)
        $spec = $this->match_specialty($message_expanded);
        $explicit_specialty = false;
        if ($spec) {
            $criteria['specialty']   = $spec;
            $criteria['confidence'] += 0.25;
            $explicit_specialty = true;
            // Infer mental health if no explicit category matched
            if (!$criteria['primary_category']) {
                $criteria['primary_category']      = 'mental-health-therapy';
                $criteria['primary_category_name'] = 'Mental Health & Therapy';
                $criteria['confidence']           += 0.15;
            }
        }

        // City
        $city = $this->match_city($message_lower);
        $explicit_city = false;
        if ($city) {
            $criteria['city']        = $city;
            $criteria['confidence'] += 0.25;
            $explicit_city = true;
        }

        // Provider type / license
        $ptype = $this->match_provider_type($message_expanded);
        $explicit_provider_type = false;
        if ($ptype) {
            $criteria['provider_type'] = $ptype;
            $criteria['confidence']   += 0.2;
            $explicit_provider_type = true;
        }

        $modality = $this->match_modality($message_expanded);
        $explicit_modality = false;
        if ($modality) {
            $criteria['modality'] = $modality;
            $criteria['confidence'] += 0.2;
            $explicit_modality = true;
            if (!$criteria['primary_category']) {
                $criteria['primary_category'] = 'mental-health-therapy';
                $criteria['primary_category_name'] = 'Mental Health & Therapy';
            }
        }

        $language_preference = $this->match_provider_language_preference($message_lower);
        $explicit_language_preference = false;
        if ($language_preference) {
            $criteria['language_preference'] = $language_preference;
            $criteria['confidence'] += 0.15;
            $explicit_language_preference = true;
        }

        $insurance = $this->match_insurance_preference($message_lower);
        $explicit_insurance = false;
        if ($insurance) {
            $criteria['insurance'] = $insurance;
            $criteria['confidence'] += 0.15;
            $explicit_insurance = true;
        }

        $session_type = $this->match_session_type_preference($message_lower);
        $explicit_session_type = false;
        if ($session_type) {
            $criteria['session_type'] = $session_type;
            $criteria['confidence'] += 0.15;
            $explicit_session_type = true;
        }

        // Gender preference — only when explicitly requested
        // (e.g. "I want a female therapist", "male counselor", "she", "a woman")
        // We use name-based heuristics later to filter; we do NOT volunteer this.
        if (preg_match('/\b(?:female|woman|women|she|her|una\s+mujer|una\s+therapist\s+mujer)\b/i', $message_lower)) {
            $criteria['gender_preference'] = 'female';
        } elseif (preg_match('/\b(?:male|man|men|he|him|his|un\s+hombre|un\s+therapist\s+hombre)\b/i', $message_lower)) {
            $criteria['gender_preference'] = 'male';
        }
        // Only set when the user explicitly linked gender to provider search
        // (e.g. "female therapist" → set; "she said" → don't set)
        if ($criteria['gender_preference'] && !preg_match('/\b(?:therapist|counselor|counselor|provider|doctor|psychiatrist|psychologist|therapists|counselors|providers|one|someone|person|practice)\b/i', $message_lower)) {
            $criteria['gender_preference'] = null; // Likely not about provider gender
        }

        // Provider name extraction — detect when user names a specific provider
        // Patterns: "Dr. Smith", "Angela Green", "Can I see Ashley Almon",
        //           "looking for John Smith", "need Dr. Jones"
        $provider_name = $this->extract_provider_name($message_lower);
        if ($provider_name) {
            $criteria['provider_name'] = $provider_name;
            $criteria['confidence'] += 0.3; // strong signal: user knows a name
            $this->trace('provider_name_extracted', $provider_name);
        }

        // Check for paraphrase signals that indicate strong search intent
        $paraphrase_signal = $this->detect_paraphrase_signal($message_lower);
        if ($paraphrase_signal === 'search' || $paraphrase_signal === 'extract_condition' ||
            $paraphrase_signal === 'extract_target') {
            $criteria['confidence'] += 0.15;
        }
        if ($paraphrase_signal === 'mental_health' || $paraphrase_signal === 'relationships' ||
            $paraphrase_signal === 'child_therapy' || $paraphrase_signal === 'substance_abuse') {
            $criteria['confidence'] += 0.2;
            // Infer mental health category if not already set
            if (!$criteria['primary_category']) {
                $criteria['primary_category']      = 'mental-health-therapy';
                $criteria['primary_category_name'] = 'Mental Health & Therapy';
            }
        }

        // Enhanced confidence scoring: boost when multiple signals present
        $signal_count = 0;
        if ($criteria['primary_category']) $signal_count++;
        if ($criteria['specialty']) $signal_count++;
        if ($criteria['city']) $signal_count++;
        if ($criteria['provider_type']) $signal_count++;
        if ($criteria['modality']) $signal_count++;
        if ($criteria['language_preference']) $signal_count++;
        if ($criteria['insurance']) $signal_count++;
        if ($criteria['session_type']) $signal_count++;

        // Boost confidence for multiple matching signals (compound intent)
        if ($signal_count >= 3) {
            $criteria['confidence'] += 0.2; // Strong compound query
            $this->trace('confidence_boost', 'multiple_signals_' . $signal_count);
        } elseif ($signal_count >= 2) {
            $criteria['confidence'] += 0.1; // Moderate compound query
        }

        // High confidence boost: condition + location is strong consumer search signal
        if (($criteria['specialty'] || $criteria['primary_category']) && $criteria['city']) {
            $criteria['confidence'] += 0.15;
            $this->trace('confidence_boost', 'condition_plus_location');
        }

        $reset_fields = $this->build_context_reset_fields(
            $message_focus,
            $criteria,
            array(
                'category'      => $explicit_category,
                'specialty'     => $explicit_specialty,
                'city'          => $explicit_city,
                'provider_type' => $explicit_provider_type,
                'modality'      => $explicit_modality,
                'language_preference' => $explicit_language_preference,
                'insurance' => $explicit_insurance,
                'session_type' => $explicit_session_type,
            )
        );
        // "Near me" asks for the user's current location, not the last city
        // remembered from an earlier search. Do not silently reuse stale city
        // context when no explicit city is present in this turn.
        if ($this->near_me_detected && !$explicit_city) {
            $reset_fields[] = 'city';
            $reset_fields[] = 'recent_providers';
            $reset_fields[] = 'last_provider_focus';
            $reset_fields[] = 'search_context_updated_at';
            $reset_fields = array_values(array_unique($reset_fields));
        }
        if (!empty($reset_fields)) {
            $this->trace('context_reset_fields', implode(', ', $reset_fields));
        }

        // ── Follow-up context carry-forward ─────────────────────
        // If prior context exists, inherit fields the user didn't
        // explicitly override in this message.
        if (!empty($this->prior_context)) {
            $inherited = array();
            $reset_lookup = array_fill_keys($reset_fields, true);
            if (
                !$criteria['primary_category'] &&
                empty($reset_lookup['primary_category']) &&
                !empty($this->prior_context['primary_category'])
            ) {
                $criteria['primary_category']      = $this->prior_context['primary_category'];
                $criteria['primary_category_name'] = $this->prior_context['primary_category_name'] ?? null;
                $criteria['confidence']           += 0.1;
                $inherited[] = 'category';
            }
            if (
                !$criteria['specialty'] &&
                empty($reset_lookup['specialty']) &&
                !empty($this->prior_context['specialty'])
            ) {
                $criteria['specialty']   = $this->prior_context['specialty'];
                $criteria['confidence'] += 0.1;
                $inherited[] = 'specialty';
            }
            if (
                !$criteria['city'] &&
                empty($reset_lookup['city']) &&
                !empty($this->prior_context['city'])
            ) {
                $criteria['city']        = $this->prior_context['city'];
                $criteria['confidence'] += 0.1;
                $inherited[] = 'city';
            }
            if (
                !$criteria['provider_type'] &&
                empty($reset_lookup['provider_type']) &&
                !empty($this->prior_context['provider_type'])
            ) {
                $criteria['provider_type'] = $this->prior_context['provider_type'];
                $criteria['confidence']   += 0.05;
                $inherited[] = 'provider_type';
            }
            if (
                !$criteria['modality'] &&
                empty($reset_lookup['modality']) &&
                !empty($this->prior_context['modality'])
            ) {
                $criteria['modality'] = $this->prior_context['modality'];
                $criteria['confidence'] += 0.05;
                $inherited[] = 'modality';
            }
            if (
                !$criteria['language_preference'] &&
                empty($reset_lookup['language_preference']) &&
                !empty($this->prior_context['language_preference'])
            ) {
                $criteria['language_preference'] = $this->prior_context['language_preference'];
                $criteria['confidence'] += 0.05;
                $inherited[] = 'language_preference';
            }
            if (
                !$criteria['insurance'] &&
                empty($reset_lookup['insurance']) &&
                !empty($this->prior_context['insurance'])
            ) {
                $criteria['insurance'] = $this->prior_context['insurance'];
                $criteria['confidence'] += 0.05;
                $inherited[] = 'insurance';
            }
            if (
                !$criteria['session_type'] &&
                empty($reset_lookup['session_type']) &&
                !empty($this->prior_context['session_type'])
            ) {
                $criteria['session_type'] = $this->prior_context['session_type'];
                $criteria['confidence'] += 0.05;
                $inherited[] = 'session_type';
            }
            if (!empty($inherited)) {
                $this->trace('context_inherited', implode(', ', $inherited));
            }
        }

        $criteria['explicit_primary_category'] = $explicit_category;
        $criteria['explicit_specialty'] = $explicit_specialty;
        $criteria['explicit_city'] = $explicit_city;
        $criteria['explicit_provider_type'] = $explicit_provider_type;
        $criteria['explicit_modality'] = $explicit_modality;
        $criteria['explicit_language_preference'] = $explicit_language_preference;
        $criteria['explicit_insurance'] = $explicit_insurance;
        $criteria['explicit_session_type'] = $explicit_session_type;
        $criteria['reset_fields'] = $reset_fields;
        $criteria['context_shifted'] = !empty($reset_fields);
        $criteria['confidence'] = min(1.0, $criteria['confidence']);
        $this->trace('extraction', $criteria);
        return $criteria;
    }

    private function has_negative_search_language($message_lower) {
        return (bool) preg_match(
            '/\b(?:i\s+)?(?:do\s+not|don\'t|dont|did\s+not|didn\'t|didnt|no)\s+(?:need|want|like|mean|asked?\s+for|look(?:ing)?\s+for)\b|' .
            '\b(?:not|no)\s+(?:that|this|those|general\s+counsel(?:ing|ling|or)?|counsel(?:ing|ling|or)?|therapy|therapist|provider|option|one)\b|' .
            '\b(?:that|this|those)\s+(?:is|are|was|were)\s+not\s+(?:what\s+)?(?:i\s+)?(?:need|want|meant|asked?\s+for|look(?:ing)?\s+for)\b|' .
            '\b(?:eso|esa|ese|estos|estas)\s+no\s+(?:es|son)\s+lo\s+que\s+(?:necesito|quiero|busco)\b|' .
            '\bno\s+(?:necesito|quiero|busco)\b|' .
            '\b(?:pero|en\s+cambio|sino|mejor|ahora)\b.*\b(?:busco|necesito|quiero|mu[eé]strame|mostrar|encuentra)\b/u',
            $message_lower
        );
    }

    private function extract_positive_search_focus($message_lower) {
        $message_lower = trim((string) $message_lower);
        if ($message_lower === '' || !$this->has_negative_search_language($message_lower)) {
            return $message_lower;
        }

        $segments = preg_split('/[.?!;]+/', $message_lower, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($segments)) {
            for ($i = count($segments) - 1; $i >= 0; $i--) {
                $segment = trim((string) $segments[$i]);
                if ($segment === '') {
                    continue;
                }
                if (preg_match('/\b(?:do\s+not|don\'t|dont|did\s+not|didn\'t|didnt|no)\s+(?:need|want|like|mean|asked?\s+for|look(?:ing)?\s+for)\b/i', $segment)) {
                    continue;
                }
                if (preg_match('/\b(?:need|want|looking\s+for|look\s+for|find|show|search|couples?|relationship|marriage|family|anxiety|depression|trauma|general\s+counsel(?:ing|ling|or)?|therap(?:y|ist)|counsel(?:ing|or)|provider|providers|psychologist|psychiatrist|support|help|busco|necesito|quiero|mu[eé]strame|mostrar|encuentra|terapeuta|consejer[oa]|psic[oó]logo|psiquiatra|pareja|matrimonio|familia|charlotte|categor[ií]a)\b/iu', $segment)) {
                    $this->trace('search_focus', $segment);
                    return $segment;
                }
            }
        }

        if (preg_match('/\b(?:but|instead|rather|except|actually|now|pero|en\s+cambio|sino|mejor|ahora)\b(.+)$/iu', $message_lower, $match)) {
            $focus = trim((string) ($match[1] ?? ''));
            if ($focus !== '') {
                $this->trace('search_focus', $focus);
                return $focus;
            }
        }

        return $message_lower;
    }

    private function build_context_reset_fields($message_lower, $criteria, $explicit_flags = array()) {
        if (empty($this->prior_context) || !is_array($this->prior_context)) {
            return array();
        }

        $reset_fields = array();
        $prior_category = strtolower((string) ($this->prior_context['primary_category'] ?? ''));
        $prior_specialty = strtolower((string) ($this->prior_context['specialty'] ?? ''));
        $prior_city = strtolower((string) ($this->prior_context['city'] ?? ''));
        $prior_provider_type = strtolower((string) ($this->prior_context['provider_type'] ?? ''));
        $prior_modality = strtolower((string) ($this->prior_context['modality'] ?? ''));
        $prior_language_preference = strtolower((string) ($this->prior_context['language_preference'] ?? ''));
        $prior_insurance = strtolower((string) ($this->prior_context['insurance'] ?? ''));
        $prior_session_type = strtolower((string) ($this->prior_context['session_type'] ?? ''));

        $fresh_search_signal = (bool) preg_match(
            '/\b(?:start over|new search|forget that|forget it|never mind|nevermind|something else|something different|anything else|else instead|different (?:kind|type)|another (?:kind|type)|switch it up)\b|' .
            '\b(?:olvida eso|otra cosa|algo diferente|empezar de nuevo|nueva busqueda|nueva búsqueda|otra categor[ií]a|otra opci[oó]n|otra ciudad)\b/iu',
            $message_lower
        );
        $different_city_without_explicit = empty($explicit_flags['city']) && (bool) preg_match(
            '/\b(?:different|another|other|new)\s+(?:city|cities|area|areas|location|locations|place|places)\b|' .
            '\b(?:remove|clear|drop|forget|without|no)\s+(?:the\s+)?(?:city|location|area)\b|' .
            '\b(?:other|different|another)\s+options?\s+(?:in|with|from|around|near)\s+(?:other|different|another|new)\s+(?:city|cities|area|areas|location|locations|place|places)\b|' .
            '\b(?:change|switch)\s+(?:the\s+)?(?:city|area|location|place)\b|' .
            '\b(?:otra|otro|otras|otros|diferente|diferentes|nueva|nuevo|nuevas|nuevos)\s+(?:ciudad|ciudades|zona|zonas|ubicaci[oó]n|ubicaciones|lugar|lugares)\b|' .
            '\b(?:cambiar|cambia)\s+(?:de\s+)?(?:ciudad|zona|ubicaci[oó]n|lugar)\b/iu',
            $message_lower
        );
        $different_specialty_without_explicit = empty($explicit_flags['specialty']) && (bool) preg_match(
            '/\b(?:different|another|other|new)\s+(?:specialt(?:y|ies)|service|services|therapy\s+style|therapy\s+styles|support\s+type|support\s+types)\b|' .
            '\b(?:other|different|another)\s+options?\s+(?:for|with|in)\s+(?:other|different|another|new)\s+(?:specialt(?:y|ies)|service|services|therapy\s+style|therapy\s+styles|support\s+type|support\s+types)\b|' .
            '\b(?:change|switch)\s+(?:the\s+)?(?:specialt(?:y|ies)|service|therapy\s+style|support\s+type)\b|' .
            '\b(?:otra|otro|otras|otros|diferente|diferentes|nueva|nuevo|nuevas|nuevos)\s+(?:especialidad|especialidades|servicio|servicios|tipo\s+de\s+apoyo|tipos\s+de\s+apoyo)\b|' .
            '\b(?:cambiar|cambia)\s+(?:de\s+)?(?:especialidad|servicio|tipo\s+de\s+apoyo)\b/iu',
            $message_lower
        );

        if ($fresh_search_signal) {
            $reset_fields = array_merge($reset_fields, array(
                'primary_category',
                'primary_category_name',
                'specialty',
                'provider_type',
                'modality',
                'language_preference',
                'insurance',
                'session_type',
                'recent_providers',
                'last_provider_focus',
                'search_context_updated_at',
            ));
        }

        if (
            !empty($explicit_flags['category']) &&
            $prior_category !== '' &&
            strtolower((string) ($criteria['primary_category'] ?? '')) !== $prior_category
        ) {
            $reset_fields = array_merge($reset_fields, array(
                'specialty',
                'provider_type',
                'modality',
                'recent_providers',
                'last_provider_focus',
                'search_context_updated_at',
            ));
        }

        if (
            !empty($explicit_flags['specialty']) &&
            $prior_specialty !== '' &&
            strtolower((string) ($criteria['specialty'] ?? '')) !== $prior_specialty
        ) {
            $reset_fields = array_merge($reset_fields, array(
                'recent_providers',
                'last_provider_focus',
                'search_context_updated_at',
            ));
        }

        if (
            !empty($explicit_flags['city']) &&
            $prior_city !== '' &&
            strtolower((string) ($criteria['city'] ?? '')) !== $prior_city
        ) {
            $reset_fields = array_merge($reset_fields, array(
                'recent_providers',
                'last_provider_focus',
                'search_context_updated_at',
            ));
        }

        if (
            !empty($explicit_flags['provider_type']) &&
            $prior_provider_type !== '' &&
            strtolower((string) ($criteria['provider_type'] ?? '')) !== $prior_provider_type
        ) {
            $reset_fields = array_merge($reset_fields, array(
                'recent_providers',
                'last_provider_focus',
                'search_context_updated_at',
            ));
        }

        if (
            !empty($explicit_flags['modality']) &&
            $prior_modality !== '' &&
            strtolower((string) ($criteria['modality'] ?? '')) !== $prior_modality
        ) {
            $reset_fields = array_merge($reset_fields, array(
                'recent_providers',
                'last_provider_focus',
                'search_context_updated_at',
            ));
        }

        if (
            !empty($explicit_flags['language_preference']) &&
            $prior_language_preference !== '' &&
            strtolower((string) ($criteria['language_preference'] ?? '')) !== $prior_language_preference
        ) {
            $reset_fields = array_merge($reset_fields, array(
                'recent_providers',
                'last_provider_focus',
                'search_context_updated_at',
            ));
        }

        if (
            !empty($explicit_flags['insurance']) &&
            $prior_insurance !== '' &&
            strtolower((string) ($criteria['insurance'] ?? '')) !== $prior_insurance
        ) {
            $reset_fields = array_merge($reset_fields, array(
                'recent_providers',
                'last_provider_focus',
                'search_context_updated_at',
            ));
        }

        if (
            !empty($explicit_flags['session_type']) &&
            $prior_session_type !== '' &&
            strtolower((string) ($criteria['session_type'] ?? '')) !== $prior_session_type
        ) {
            $reset_fields = array_merge($reset_fields, array(
                'recent_providers',
                'last_provider_focus',
                'search_context_updated_at',
            ));
        }

        if ($different_city_without_explicit) {
            $reset_fields = array_merge($reset_fields, array(
                'city',
                'recent_providers',
                'last_provider_focus',
                'search_context_updated_at',
            ));
        }

        if ($different_specialty_without_explicit) {
            $reset_fields = array_merge($reset_fields, array(
                'specialty',
                'provider_type',
                'modality',
                'recent_providers',
                'last_provider_focus',
                'search_context_updated_at',
            ));
        }

        if (preg_match('/\b(?:any\s+insurance|no\s+insurance\s+preference)\b|\b(?:remove|clear|forget)\b[\s\S]{0,24}\b(?:insurance|coverage|plan)\b|\b(?:cualquier\s+seguro|sin\s+preferencia\s+de\s+seguro)\b/iu', $message_lower)) {
            $reset_fields = array_merge($reset_fields, array('insurance', 'recent_providers', 'last_provider_focus', 'search_context_updated_at'));
        }
        if (preg_match('/\b(?:any\s+(?:session|visit)\s+type|no\s+(?:session|visit)\s+preference)\b|\beither\b[\s\S]{0,40}\b(?:works|fine|okay|ok)\b|\b(?:remove|clear|forget)\b[\s\S]{0,24}\b(?:virtual|in[-\s]?person|session|visit)\b|\b(?:cualquier\s+tipo\s+de\s+sesi[oó]n|sin\s+preferencia\s+de\s+sesi[oó]n)\b/iu', $message_lower)) {
            $reset_fields = array_merge($reset_fields, array('session_type', 'recent_providers', 'last_provider_focus', 'search_context_updated_at'));
        }

        return array_values(array_unique($reset_fields));
    }

    private function match_category($message_lower) {
        $best = null;
        $best_score = 0;

        // Check English category keywords
        foreach ($this->category_map as $slug => $data) {
            $score = 0;
            foreach ($data['keywords'] as $kw) {
                if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($kw, '/') . '(?![\p{L}\p{N}])/iu', $message_lower)) {
                    $score += strlen($kw);
                }
            }
            if ($score > $best_score) {
                $best_score = $score;
                $best = array('slug' => $slug, 'name' => $data['name']);
            }
        }

        // Check Spanish category keywords (map to same slugs)
        foreach ($this->category_map_es as $slug => $keywords) {
            $score = 0;
            foreach ($keywords as $kw) {
                if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($kw, '/') . '(?![\p{L}\p{N}])/iu', $message_lower)) {
                    $score += strlen($kw);
                }
            }
            if ($score > $best_score) {
                $best_score = $score;
                // Get English name for the slug
                $en_name = isset($this->category_map[$slug]['name']) ? $this->category_map[$slug]['name'] : $slug;
                $best = array('slug' => $slug, 'name' => $en_name);
            }
        }

        $this->trace('category_match', $best ? $best['name'] . " (score:{$best_score})" : 'none');
        return $best;
    }

    private function match_specialty($message_lower) {
        $best = null;
        $best_score = 0;

        // Check English specialty keywords
        foreach ($this->specialty_map as $name => $keywords) {
            $score = 0;
            foreach ($keywords as $kw) {
                if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($kw, '/') . '(?![\p{L}\p{N}])/iu', $message_lower)) {
                    $score += strlen($kw);
                }
            }
            if ($score > $best_score) {
                $best_score = $score;
                $best = $name;
            }
        }

        // Check Spanish specialty keywords (map to same English specialty names)
        foreach ($this->specialty_map_es as $name => $keywords) {
            $score = 0;
            foreach ($keywords as $kw) {
                if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($kw, '/') . '(?![\p{L}\p{N}])/iu', $message_lower)) {
                    $score += strlen($kw);
                }
            }
            if ($score > $best_score) {
                $best_score = $score;
                $best = $name;
            }
        }

        $this->trace('specialty_match', $best ?: 'none');
        return $best;
    }

    private function match_city($message_lower) {
        static $city_lookup = null;

        if ($city_lookup === null) {
            $city_lookup = array();
            foreach ($this->known_cities as $city) {
                $normalized = strtolower(trim((string) $city));
                if ($normalized !== '') {
                    $city_lookup[$normalized] = ucwords($normalized);
                }
            }

            $terms = get_terms(array(
                'taxonomy'   => 'myhh_city',
                'hide_empty' => false,
            ));
            if (!is_wp_error($terms) && is_array($terms)) {
                foreach ($terms as $term) {
                    $name = trim((string) ($term->name ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $city_lookup[strtolower($name)] = $name;
                }
            }
        }

        $city_names = array_keys($city_lookup);
        usort($city_names, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        foreach ($city_names as $city) {
            if (preg_match('/\b' . preg_quote($city, '/') . '\b/u', $message_lower)) {
                $proper = $city_lookup[$city] ?? ucwords($city);
                $this->trace('city_match', $proper);
                return $proper;
            }
        }
        // English "near me" / "nearby"
        if (strpos($message_lower, 'near me') !== false || strpos($message_lower, 'nearby') !== false) {
            $this->trace('city_match', 'near_me_no_specific_city');
            $this->near_me_detected = true;
        }
        // Spanish "cerca de mí" / "cerca"
        if (strpos($message_lower, 'cerca de mí') !== false || strpos($message_lower, 'cerca de mi') !== false || strpos($message_lower, 'cerca') !== false) {
            $this->trace('city_match', 'cerca_de_mi_no_specific_city');
            $this->near_me_detected = true;
        }
        return null;
    }

    private function match_provider_type($message_lower) {
        $map = array(
            'LCMHC'       => array('lcmhc', 'licensed clinical mental health counselor'),
            'LPC'          => array('lpc', 'licensed professional counselor'),
            'LCSW'         => array('lcsw', 'licensed clinical social worker', 'social worker'),
            'LMFT'         => array('lmft', 'licensed marriage and family therapist'),
            'Psychologist' => array('psychologist', 'psyd'),
            'Psychiatrist' => array('psychiatrist', 'psychiatric'),
            'LCAS'         => array('lcas', 'addiction specialist'),
        );
        foreach ($map as $type => $keywords) {
            foreach ($keywords as $kw) {
                if (strpos($message_lower, $kw) !== false) return $type;
            }
        }
        return null;
    }

    private function match_modality($message_lower) {
        $map = array(
            'CBT' => array('cbt', 'cognitive behavioral therapy', 'cognitive behavioural therapy'),
            'DBT' => array('dbt', 'dialectical behavior therapy', 'dialectical behavioural therapy'),
            'EMDR' => array('emdr', 'eye movement desensitization and reprocessing'),
        );
        foreach ($map as $name => $keywords) {
            foreach ($keywords as $keyword) {
                if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $message_lower)) {
                    return $name;
                }
            }
        }
        return null;
    }

    private function match_provider_language_preference($message_lower) {
        if (preg_match('/\b(?:spanish[-\s]?speaking|speaks?\s+spanish|bilingual|biling[uü]e|(?:hable|habla|hablen)\s+espa[ñn]ol)\b|\b(?:provider|therapist|counselor|sessions?|care|proveedor|terapeuta|sesiones?|atenci[oó]n)\b[^.!?]{0,40}\b(?:in\s+spanish|en\s+espa[ñn]ol)\b/iu', $message_lower)) {
            return 'Spanish';
        }
        return null;
    }

    private function match_insurance_preference($message_lower) {
        $plans = array(
            'Blue Cross Blue Shield' => array('blue cross blue shield', 'blue cross', 'blue shield', 'bcbs'),
            'UnitedHealthcare' => array('unitedhealthcare', 'united healthcare', 'united health care', 'uhc'),
            'Aetna' => array('aetna'),
            'Cigna' => array('cigna'),
            'Humana' => array('humana'),
            'Medicaid' => array('medicaid'),
            'Medicare' => array('medicare'),
            'TRICARE' => array('tricare'),
        );
        foreach ($plans as $name => $aliases) {
            foreach ($aliases as $alias) {
                if (preg_match('/\b' . preg_quote($alias, '/') . '\b/iu', $message_lower)) {
                    return $name;
                }
            }
        }
        if (preg_match('/\b(?:self[-\s]?pay|private\s+pay|cash\s+pay|pago\s+particular)\b/iu', $message_lower)) {
            return 'Self-Pay';
        }
        if (preg_match('/\b(?:sliding\s+scale|escala\s+m[oó]vil)\b/iu', $message_lower)) {
            return 'Sliding Scale';
        }
        return null;
    }

    private function match_session_type_preference($message_lower) {
        if (preg_match('/\b(?:any\s+(?:session|visit)\s+type|no\s+(?:session|visit)\s+preference)\b|\beither\b[\s\S]{0,40}\b(?:works|fine|okay|ok)\b/iu', $message_lower)) {
            return null;
        }
        if (preg_match('/\b(?:hybrid|h[ií]brid[oa])\b/iu', $message_lower)) {
            return 'Hybrid';
        }
        if (preg_match('/\b(?:virtual|online|telehealth|teletherapy|video\s+(?:visit|session)|remote|telesalud|en\s+l[ií]nea|por\s+video)\b/iu', $message_lower)) {
            return 'Virtual';
        }
        if (preg_match('/\b(?:in[-\s]?person|face[-\s]?to[-\s]?face|at\s+(?:their|the)\s+office|presencial|en\s+persona)\b/iu', $message_lower)) {
            return 'In-Person';
        }
        return null;
    }

    // ===========================================================
    //  4. DIRECTORY RETRIEVAL + RANKING
    // ===========================================================

    private function handle_consumer_search($message_lower, $context) {
        $criteria = $this->extract_search_criteria($message_lower);

        if ($this->near_me_detected && empty($criteria['city'])) {
            $criteria['needs_location'] = true;
            $response = $this->detected_language === 'es'
                ? 'Para buscar cerca de ti, permite la ubicacion del navegador o dime tu ciudad. Sin una ubicacion de referencia puedo mostrar opciones generales, pero no afirmar cuales estan cerca.'
                : 'To search near you, allow browser location or tell me your city. Without a location reference I can show broader options, but I cannot truthfully say which providers are nearby.';
            return array(
                'intent'            => self::INTENT_CONSUMER_SEARCH,
                'criteria'          => array_merge($criteria, array('language' => $this->detected_language)),
                'providers'         => array(),
                'response'          => $response,
                'fallback_used'     => false,
                'broadened'         => null,
                'near_me_detected'  => true,
                'detected_language' => $this->detected_language,
            );
        }

        // Very vague message — no concrete search criteria.
        // Do not invent a broad default category and show random providers.
        // Ask one clarifying question so the next search respects what the
        // user actually needs.
        $has_no_topic_criteria = empty($criteria['primary_category']) && empty($criteria['specialty'])
            && empty($criteria['city']) && empty($criteria['provider_type'])
            && empty($criteria['modality']) && empty($criteria['language_preference']);
        $weak_provider_name = !empty($criteria['provider_name'])
            && $has_no_topic_criteria
            && !$this->looks_like_explicit_provider_name_query($message_lower, $criteria['provider_name']);
        $is_vague_search = ($has_no_topic_criteria && empty($criteria['provider_name']) && !$this->near_me_detected) || $weak_provider_name;
        if ($is_vague_search) {
            if ($weak_provider_name) {
                $this->trace('weak_provider_name_blocked', $criteria['provider_name']);
                $criteria['provider_name'] = null;
                $criteria['confidence'] = 0.0;
            }
            $criteria['needs_clarification'] = true;
            $this->trace('search_readiness_gate', 'Blocked vague provider search; asking for category/city');
            return array(
                'intent'           => self::INTENT_CONSUMER_SEARCH,
                'criteria'         => array_merge($criteria, array('language' => $this->detected_language)),
                'providers'        => array(),
                'response'         => $this->build_vague_search_clarification($criteria),
                'fallback_used'    => false,
                'broadened'        => null,
                'near_me_detected' => $this->near_me_detected,
                'detected_language' => $this->detected_language,
            );
        }

        $search = $this->search_and_rank($criteria, $context);
        $response = $this->build_search_response($criteria, $search, $context);

        return array(
            'intent'           => self::INTENT_CONSUMER_SEARCH,
            'criteria'         => array_merge($criteria, array('language' => $this->detected_language)),
            'providers'        => $search['providers'],
            'response'         => $response,
            'fallback_used'    => $search['fallback_used'],
            'broadened'        => $search['broadened'],
            'near_me_detected' => $this->near_me_detected,
            'detected_language' => $this->detected_language,
        );
    }

    /**
     * Multi-pass search with progressive broadening and ranking.
     */
    private function search_and_rank($criteria, $context) {
        $providers = array();
        $fallback  = false;
        $broadened = null;
        $name_suggestions = array(); // for "did you mean" fuzzy matches

        // ── Pass 0: Name-based search (when user named a specific provider) ──
        // If the user gave a provider name, search by that name first.
        // This takes priority over taxonomy-based search because the user
        // has a specific provider in mind.
        $has_name_results = false;
        if (!empty($criteria['provider_name'])) {
            $name_results = $this->query_providers_by_name($criteria['provider_name'], $criteria['city']);
            if (!empty($name_results)) {
                $providers = $name_results;
                $has_name_results = true;
                $this->trace('search_pass_0_name', count($providers) . ' results for name: ' . $criteria['provider_name']);
            } else {
                // Exact name search failed — try fuzzy matching for "did you mean" suggestions
                $name_suggestions = $this->fuzzy_match_provider_name($criteria['provider_name'], $criteria['city'], 4);
                if (!empty($name_suggestions)) {
                    $providers = $name_suggestions;
                    $has_name_results = true;
                    $broadened = 'No exact name match — showing similar providers ("did you mean?")';
                    $fallback = true;
                    $this->trace('search_pass_0_name_fuzzy', count($providers) . ' fuzzy results for: ' . $criteria['provider_name']);
                } else {
                    $this->trace('search_pass_0_name_miss', 'No results for name: ' . $criteria['provider_name']);
                }
            }
        }

        // Pass 1: Exact (category + specialty + city + license)
        // Skip taxonomy search when name-based results exist (they take priority)
        if (!$has_name_results) {
            $providers = $this->query_providers(
                $criteria['primary_category'], $criteria['specialty'],
                $criteria['city'], $criteria['provider_type']
            );
        }
        $this->trace('search_pass_1_exact', count($providers) . ' results');

        $strict_city = !empty($criteria['explicit_city']);
        $strict_specialty = !empty($criteria['explicit_specialty']);

        // Pass 2: Relax city
        if (!$has_name_results && count($providers) < 3 && $criteria['city'] && !$strict_city) {
            $broader = $this->query_providers(
                $criteria['primary_category'], $criteria['specialty'], null, $criteria['provider_type']
            );
            $merged = $this->merge_providers($providers, $broader);
            if (count($merged) > count($providers)) {
                $broadened = 'Expanded beyond ' . $criteria['city'] . ' to include nearby areas';
                $fallback  = true;
                $providers = $merged;
            }
            $this->trace('search_pass_2_no_city', count($providers) . ' results');
        }

        // Pass 3: Relax specialty
        if (!$has_name_results && count($providers) < 3 && $criteria['specialty'] && !$strict_specialty) {
            $broader = $this->query_providers(
                $criteria['primary_category'], null, $criteria['city'], null
            );
            $merged = $this->merge_providers($providers, $broader);
            if (count($merged) > count($providers)) {
                $broadened = $broadened
                    ? $broadened . ' and broadened specialty'
                    : 'Broadened from ' . $criteria['specialty'] . ' to all specialties in ' . ($criteria['primary_category_name'] ?: 'the category');
                $fallback  = true;
                $providers = $merged;
            }
            $this->trace('search_pass_3_no_specialty', count($providers) . ' results');
        }

        // Category identity is a hard constraint. Returning providers from a
        // different category lets the response layer falsely describe them as
        // matches for the user's requested service.
        if (!$has_name_results && count($providers) < 1 && !empty($criteria['primary_category'])) {
            $this->trace('search_category_lock_empty', (string) $criteria['primary_category']);
        }

        // Imported taxonomy can contain legacy misclassifications. Reject only
        // clear semantic contradictions (for example Physical Therapy tagged as
        // Primary Care); retain records with compatible or inconclusive text.
        if (!$has_name_results && !empty($criteria['primary_category']) && !empty($providers)) {
            $before_semantic_guard = count($providers);
            $providers = $this->filter_semantic_category_mismatches($providers, $criteria['primary_category']);
            if (count($providers) !== $before_semantic_guard) {
                $this->trace('search_semantic_category_guard', array(
                    'category' => $criteria['primary_category'],
                    'before' => $before_semantic_guard,
                    'after' => count($providers),
                ));
            }
        }

        // Apply profile-backed preferences only when at least one candidate
        // explicitly contains the requested fact. If no listing documents the
        // preference, keep the broader candidates but report that gap honestly.
        $preference_gaps = array();
        foreach (array('modality' => 'modalities', 'language_preference' => 'languages', 'insurance' => 'insurance_accepted', 'session_type' => 'session_type') as $criterion => $provider_field) {
            if (empty($criteria[$criterion]) || empty($providers)) {
                continue;
            }
            $filtered = array_values(array_filter($providers, function($provider) use ($criteria, $criterion, $provider_field) {
                if ($criterion === 'session_type') {
                    return $this->provider_session_type_matches($provider[$provider_field] ?? '', $criteria[$criterion]);
                }
                return $this->provider_list_field_matches($provider[$provider_field] ?? array(), $criteria[$criterion]);
            }));
            if (!empty($filtered)) {
                $providers = $filtered;
            } else {
                $preference_gaps[] = $criterion;
            }
        }

        // Rank, diversify, and limit
        $candidate_count = count($providers);
        $providers = $this->rank_providers(array_values($providers), $criteria);

        // Apply gender preference filter (name-based heuristic, only when explicitly requested)
        if (!empty($criteria['gender_preference'])) {
            $filtered = $this->filter_by_gender_preference($providers, $criteria['gender_preference']);
            if (!empty($filtered)) {
                $providers = $filtered;
                $this->trace('gender_filter', $criteria['gender_preference'] . ': ' . count($filtered) . '/' . count($providers) . ' matched');
            } else {
                $this->trace('gender_filter', $criteria['gender_preference'] . ': 0 matched, keeping all');
                // Keep all results if gender filter eliminates everyone
            }
        }

        $providers = array_slice($providers, 0, 4);
        $this->capture_provider_exposure($providers, $criteria, $candidate_count);
        $this->trace('candidate_provider_count', $candidate_count . ' candidates before final slice');
        $this->trace('final_provider_count', count($providers));

        return array(
            'providers' => $providers,
            'fallback_used' => $fallback,
            'broadened' => $broadened,
            'name_suggestions' => $name_suggestions,
            'preference_gaps' => $preference_gaps,
        );
    }

    private function filter_semantic_category_mismatches($providers, $requested_category) {
        $requested_category = sanitize_title((string) $requested_category);
        if ($requested_category === '') {
            return array_values((array) $providers);
        }

        $patterns = array(
            'primary-care-medical-services' => '/\b(?:primary\s+care|family\s+medicine|internal\s+medicine|general\s+practitioner|medical\s+(?:care|clinic|services?)|physician|pediatric(?:ian|s)|urgent\s+care|nurse\s+practitioner|annual\s+physical|health\s+checkup)\b/i',
            'mental-health-therapy' => '/\b(?:general\s+counsel(?:ing|ling)|counsel(?:or|ing|ling)|psycholog(?:y|ist)|psychiatr(?:y|ist)|psychotherap(?:y|ist)|mental\s+health|behavioral\s+health|anxiety|depression|trauma|grief|cbt|dbt|emdr|couples?\s+therapy|family\s+therapy)\b/i',
            'fitness-yoga-movement' => '/\b(?:fitness|yoga|pilates|movement|personal\s+train(?:er|ing)|strength\s+training|exercise|workout|dance|barre|tai\s+chi)\b/i',
            'nutrition-dietary-support' => '/\b(?:nutrition|nutritionist|dietitian|dietician|dietary|meal\s+plan|weight\s+management|healthy\s+eating)\b/i',
            'massage-spa-recovery' => '/\b(?:physical\s+therap(?:y|ist)|physiotherap(?:y|ist)|massage|bodywork|spa|recovery|chiropract(?:ic|or)|pain\s+relief\s+massage)\b/i',
            'sleep-rest-wellness' => '/\b(?:sleep|insomnia|rest\s+wellness|sleep\s+coach)\b/i',
            'social-connections-support-groups' => '/\b(?:support\s+group|peer\s+support|social\s+connection|community\s+support)\b/i',
            'financial-wellness-planning' => '/\b(?:financial\s+(?:wellness|planning|planner|therapy)|money\s+coach|budget(?:ing)?)\b/i',
            'career-work-life-coaching' => '/\b(?:career|work[-\s]?life|executive\s+coach|job\s+coach|leadership\s+coach)\b/i',
            'integrative-holistic-therapies' => '/\b(?:acupuncture|acupuncturist|reiki|ayurved(?:a|ic)|naturopath|holistic|integrative)\b/i',
            'meditation-mindfulness-spirituality' => '/\b(?:meditation|mindfulness|spiritual(?:ity)?|breathwork)\b/i',
            'personal-growth-learning' => '/\b(?:personal\s+growth|life\s+coach|learning|self[-\s]?development)\b/i',
        );
        if (!isset($patterns[$requested_category])) {
            return array_values((array) $providers);
        }

        $filtered = array();
        foreach (array_values((array) $providers) as $provider) {
            if (!is_array($provider)) {
                continue;
            }
            $semantic_text = html_entity_decode(implode(' ', array_filter(array_merge(
                array((string) ($provider['name'] ?? '')),
                array_map('strval', (array) ($provider['specialties'] ?? array()))
            ))), ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
            $detected_categories = array();
            foreach ($patterns as $category_slug => $pattern) {
                if (preg_match($pattern, $semantic_text)) {
                    $detected_categories[] = $category_slug;
                }
            }

            if (empty($detected_categories) || in_array($requested_category, $detected_categories, true)) {
                $filtered[] = $provider;
            }
        }

        return $filtered;
    }

    private function query_providers($cat_slug, $specialty_name, $city_name, $license_type) {
        $base_key = md5(
            'profile-preferences-v3|' .
            (string) $cat_slug . '|' . (string) $specialty_name .
            '|' . (string) $city_name . '|' . (string) $license_type
        );
        $page_key = 'mia_prov_page_' . $base_key;
        $page_counter = intval(get_transient($page_key));
        if (!$this->predictive_preview_mode) {
            set_transient($page_key, $page_counter + 1, 30 * MINUTE_IN_SECONDS);
        }
        $candidate_page = $page_counter % 10;

        // Cache each rotating candidate page briefly. This avoids locking Mia to
        // one fixed top set while still protecting the database from repeated hits.
        $cache_key = 'mia_prov_' . $base_key . '_p' . $candidate_page;
        $cached = get_transient($cache_key);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $candidate_limit = 72;
        $args = array(
            'post_type'      => 'gd_place',
            'post_status'    => 'publish',
            'posts_per_page' => $candidate_limit,
            'no_found_rows'  => false,
        );

        $tax = array();

        if ($cat_slug) {
            $term = $this->resolve_taxonomy_term('myhh_primary_category', $cat_slug, true);
            if (!$term) {
                $this->trace('query_filter_missing', 'category:' . $cat_slug);
                return array();
            }
            $tax[] = array('taxonomy' => 'myhh_primary_category', 'field' => 'term_id', 'terms' => $term->term_id);
        }
        if ($specialty_name) {
            $term = $this->resolve_taxonomy_term('myhh_specialty', $specialty_name);
            if (!$term) {
                $this->trace('query_filter_missing', 'specialty:' . $specialty_name);
                return array();
            }
            $tax[] = array('taxonomy' => 'myhh_specialty', 'field' => 'term_id', 'terms' => $term->term_id);
        }
        if ($city_name) {
            $term = $this->resolve_taxonomy_term('myhh_city', $city_name);
            if (!$term) {
                $this->trace('query_filter_missing', 'city:' . $city_name);
                return array();
            }
            $tax[] = array('taxonomy' => 'myhh_city', 'field' => 'term_id', 'terms' => $term->term_id);
        }
        if ($license_type) {
            $term = $this->resolve_taxonomy_term('myhh_license_type', $license_type);
            if (!$term) {
                $this->trace('query_filter_missing', 'license:' . $license_type);
                return array();
            }
            $tax[] = array('taxonomy' => 'myhh_license_type', 'field' => 'term_id', 'terms' => $term->term_id);
        }

        if (!empty($tax)) {
            $args['tax_query'] = array_merge(array('relation' => 'AND'), $tax);
        }

        $query     = new WP_Query($args);
        $providers = array();
        $queries   = array($query);

        $total_found = intval($query->found_posts);
        if ($total_found > $candidate_limit) {
            $offset = ($candidate_page * $candidate_limit) % $total_found;
            if ($offset > 0) {
                $rotating_args = $args;
                $rotating_args['offset'] = $offset;
                $rotating_args['no_found_rows'] = true;
                $queries[] = new WP_Query($rotating_args);
            }
        }

        foreach ($queries as $provider_query) {
            if (!$provider_query->have_posts()) {
                continue;
            }
            foreach ($provider_query->posts as $post) {
                $pid = $post->ID;
                $providers[$pid] = array(
                    'id'           => $pid,
                    'name'         => $this->get_prov_display_name($pid),
                    'url'          => get_permalink($pid),
                    'city'         => $this->get_prov_city($pid),
                    'phone'        => $this->get_prov_phone($pid),
                    'website'      => $this->get_prov_website($pid),
                    'street'       => $this->get_prov_street($pid),
                    'zip'          => $this->get_prov_zip($pid),
                    'address'      => $this->get_prov_address($pid),
                    'categories'   => $this->get_prov_categories($pid),
                    'specialties'  => $this->get_prov_specialties($pid),
                    'modalities'   => $this->get_prov_meta_list($pid, 'modalities'),
                    'languages'    => $this->get_prov_meta_list($pid, 'languages_spoken'),
                    'insurance_accepted' => $this->get_prov_meta_list($pid, 'insurance_accepted'),
                    'session_type' => sanitize_text_field(get_post_meta($pid, 'session_type', true)),
                    'license_type' => $this->get_prov_license($pid),
                    'verification_status' => get_post_meta($pid, 'verification_status', true),
                    'claimed'      => (bool) get_post_meta($pid, '_myhh_claimed', true),
                    'listing_tier' => get_post_meta($pid, 'listing_tier', true),
                    'has_booking_link' => !empty(get_post_meta($pid, 'booking_link', true)),
                    'profile_completeness' => $this->get_profile_completeness_score($pid),
                    'rating_average' => $this->get_provider_rating_average($pid),
                    'rating_count' => $this->get_provider_rating_count($pid),
                    'rank_score'   => 0,
                );
            }
        }
        wp_reset_postdata();
        // Store this rotating page briefly. The next request advances the page
        // counter, giving other eligible providers exposure without random noise.
        set_transient($cache_key, $providers, 2 * MINUTE_IN_SECONDS);
        return $providers;
    }

    private function resolve_taxonomy_term($taxonomy, $label, $treat_as_slug = false) {
        $taxonomy = (string) $taxonomy;
        $label = trim((string) $label);
        if ($taxonomy === '' || $label === '') {
            return null;
        }

        static $cache = array();
        $cache_key = $taxonomy . '|' . $label . '|' . ($treat_as_slug ? 'slug' : 'name');
        if (array_key_exists($cache_key, $cache)) {
            return $cache[$cache_key];
        }

        $term = null;
        if ($treat_as_slug) {
            $term = get_term_by('slug', sanitize_title($label), $taxonomy);
        } else {
            $term = get_term_by('name', $label, $taxonomy);
            if (!$term || is_wp_error($term)) {
                $term = get_term_by('slug', sanitize_title($label), $taxonomy);
            }
        }

        if ($term && !is_wp_error($term)) {
            $cache[$cache_key] = $term;
            return $term;
        }

        $terms = get_terms(array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'name__like' => $label,
            'number'     => 25,
        ));
        if (is_wp_error($terms) || empty($terms)) {
            $cache[$cache_key] = null;
            return null;
        }

        $normalized_label = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $label)));
        $best_term = null;
        $best_score = -1;
        foreach ($terms as $candidate) {
            $candidate_name = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', (string) ($candidate->name ?? ''))));
            if ($candidate_name === '') {
                continue;
            }
            $score = 0;
            if ($candidate_name === $normalized_label) {
                $score += 100;
            }
            if (strpos($candidate_name, $normalized_label) !== false || strpos($normalized_label, $candidate_name) !== false) {
                $score += 40;
            }
            similar_text($candidate_name, $normalized_label, $percent);
            $score += $percent / 5;

            if ($score > $best_score) {
                $best_score = $score;
                $best_term = $candidate;
            }
        }

        $cache[$cache_key] = $best_term ?: null;
        return $cache[$cache_key];
    }

    /**
     * Score and sort providers by relevance.
     *
     * Priority: category match (+3) → specialty (+2) → city (+2)
     *           → verification/claim/tier trust signals → profile completeness
     */
    private function rank_providers($providers, $criteria) {
        foreach ($providers as &$p) {
            $score = 0;
            $explicit_category = !empty($criteria['explicit_primary_category']);
            $explicit_specialty = !empty($criteria['explicit_specialty']);
            $explicit_city = !empty($criteria['explicit_city']);
            $explicit_provider_type = !empty($criteria['explicit_provider_type']);

            if ($criteria['primary_category_name'] && in_array($criteria['primary_category_name'], $p['categories'])) {
                $score += $explicit_category ? 5 : 3;
            } elseif ($explicit_category) {
                $score -= 4;
            }
            if ($criteria['specialty'] && in_array($criteria['specialty'], $p['specialties'])) {
                $score += $explicit_specialty ? 7 : 3;
            } elseif ($explicit_specialty) {
                $score -= 6;
            }
            if ($criteria['city'] && strcasecmp($p['city'], $criteria['city']) === 0) {
                $score += $explicit_city ? 6 : 2;
            } elseif ($explicit_city) {
                $score -= 5;
            }
            if ($criteria['provider_type'] && !empty($p['license_type']) && strcasecmp($p['license_type'], $criteria['provider_type']) === 0) {
                $score += $explicit_provider_type ? 4 : 2;
            } elseif ($explicit_provider_type) {
                $score -= 3;
            }
            if (!empty($criteria['modality']) && $this->provider_list_field_matches($p['modalities'] ?? array(), $criteria['modality'])) {
                $score += !empty($criteria['explicit_modality']) ? 8 : 4;
            }
            if (!empty($criteria['language_preference']) && $this->provider_list_field_matches($p['languages'] ?? array(), $criteria['language_preference'])) {
                $score += !empty($criteria['explicit_language_preference']) ? 7 : 3;
            }
            if (!empty($criteria['insurance']) && $this->provider_list_field_matches($p['insurance_accepted'] ?? array(), $criteria['insurance'])) {
                $score += !empty($criteria['explicit_insurance']) ? 7 : 3;
            }
            if (!empty($criteria['session_type']) && $this->provider_session_type_matches($p['session_type'] ?? '', $criteria['session_type'])) {
                $score += !empty($criteria['explicit_session_type']) ? 7 : 3;
            }
            if (!empty($p['license_type'])) {
                $score += 1;
            }
            if (count($p['specialties']) > 1) {
                $score += 1;
            }
            $score += $this->get_verification_rank_bonus($p['verification_status'] ?? '');
            if (!empty($p['claimed'])) {
                $score += 2;
            }
            $score += $this->get_listing_tier_bonus($p['listing_tier'] ?? '');
            if (!empty($p['has_booking_link'])) {
                $score += 1;
            }
            $score += $p['profile_completeness'] ?? 0;
            $score += $this->get_rating_score_bonus($p['rating_average'] ?? 0, $p['rating_count'] ?? 0);
            // Boost name-matched providers (similarity_score from fuzzy matching)
            if (!empty($p['similarity_score'])) {
                $score += min(intval($p['similarity_score']) / 50, 20); // up to +20 for near-exact name matches
            }
            $p['rank_score'] = $score;
        }
        unset($p);

        usort($providers, function ($a, $b) {
            return $b['rank_score'] - $a['rank_score'];
        });

        return $this->rotate_top_matches($providers, $criteria);
    }

    private function provider_list_field_matches($values, $requested) {
        $requested = strtolower(trim((string) $requested));
        if ($requested === '') {
            return false;
        }
        $aliases = array($requested);
        if ($requested === 'cbt') $aliases[] = 'cognitive behavioral';
        if ($requested === 'dbt') $aliases[] = 'dialectical behavior';
        if ($requested === 'emdr') $aliases[] = 'eye movement desensitization';
        if ($requested === 'blue cross blue shield') $aliases = array_merge($aliases, array('blue cross', 'blue shield', 'bcbs'));
        if ($requested === 'unitedhealthcare') $aliases = array_merge($aliases, array('united healthcare', 'united health care', 'uhc'));
        if ($requested === 'self-pay') $aliases = array_merge($aliases, array('self pay', 'private pay', 'cash pay'));
        if ($requested === 'sliding scale') $aliases[] = 'sliding-scale';
        foreach ((array) $values as $value) {
            $value = strtolower(trim((string) $value));
            foreach ($aliases as $alias) {
                if ($value !== '' && strpos($value, $alias) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    private function provider_session_type_matches($documented, $requested) {
        $documented = strtolower(trim((string) $documented));
        $requested = strtolower(trim((string) $requested));
        if ($documented === '' || $requested === '') {
            return false;
        }
        if ($requested === 'virtual') {
            return in_array($documented, array('virtual', 'hybrid'), true);
        }
        if ($requested === 'in-person') {
            return in_array($documented, array('in-person', 'in person', 'hybrid'), true);
        }
        return $documented === $requested;
    }

    /**
     * Filter providers by gender preference using name-based heuristics.
     * Only called when the user explicitly requested a female or male provider.
     * If the filter eliminates all results, returns empty array (caller keeps originals).
     *
     * IMPORTANT: This is a best-effort heuristic. We infer likely gender from
     * first name patterns. We do NOT store or expose gender data. If uncertain,
     * the provider is kept in results rather than excluded.
     *
     * @param array  $providers Ranked provider list.
     * @param string $preference 'female' or 'male'.
     * @return array Filtered providers.
     */
    private function filter_by_gender_preference($providers, $preference) {
        $filtered = array();
        foreach ($providers as $p) {
            $name = $p['name'] ?? '';
            $inferred = $this->infer_gender_from_name($name);
            if ($inferred === $preference || $inferred === 'unknown') {
                // Keep if matched or uncertain (don't exclude uncertain ones)
                $filtered[] = $p;
            }
        }
        return $filtered;
    }

    /**
     * Infer likely gender from a provider's first name using common name patterns.
     * Returns 'female', 'male', or 'unknown'.
     *
     * This is heuristic-only — no gender field exists in the database.
     * When uncertain, returns 'unknown' so the provider is never wrongly excluded.
     */
    private function infer_gender_from_name($full_name) {
        // Extract first name (skip titles like Dr., Mr., Mrs., Ms.)
        $name = trim(preg_replace('/^(?:Dr\.|Mr\.|Mrs\.|Ms\.|Prof\.|Rev\.)\s*/i', '', $full_name));
        $parts = preg_split('/\s+/', $name);
        $first = strtolower(trim($parts[0] ?? ''));
        if (empty($first)) return 'unknown';

        // Common female first names (US-context, mental health providers)
        $female_names = array(
            'mary', 'patricia', 'jennifer', 'linda', 'elizabeth', 'barbara',
            'susan', 'jessica', 'sarah', 'karen', 'lisa', 'nancy', 'betty',
            'margaret', 'sandra', 'ashley', 'dorothy', 'kimberly', 'emily',
            'donna', 'michelle', 'carol', 'amanda', 'melissa', 'deborah',
            'stephanie', 'rebecca', 'sharon', 'laura', 'cynthia', 'kathleen',
            'amy', 'angela', 'shirley', 'anna', 'brenda', 'pamela', 'emma',
            'nicole', 'helen', 'samantha', 'katherine', 'christine', 'debra',
            'rachel', 'carolyn', 'janet', 'catherine', 'maria', 'heather',
            'diane', 'ruth', 'julie', 'olivia', 'joyce', 'virginia', 'victoria',
            'kelly', 'lauren', 'christina', 'joan', 'evelyn', 'judith', 'megan',
            'andrea', 'cheryl', 'hannah', 'jacqueline', 'martha', 'gloria',
            'teresa', 'ann', 'sara', 'madison', 'frances', 'kathryn', 'janice',
            'jean', 'abby', 'alexandra', 'jasmine', 'tiffany', 'carry', 'carrie',
            'marilyn', 'tanya', 'tara', 'tracy', 'wendy', 'gail', 'erin',
            'lakisha', 'shanice', 'aisha', 'keisha', 'tamika', 'latoya',
            ' Lakesha', 'dominique', 'monique', 'chantal',
            'sofia', 'isabella', 'camila', 'valentina', 'lucia', 'daniela',
            'alejandra', 'mariana', 'carolina', 'gabriela', 'natalia', 'patricia',
            'veronica', 'adriana', 'carmen', 'rosa', 'lupita', 'guadalupe',
            'yolanda', 'consuelo', 'teresa', 'gloria', 'irene',
        );

        // Common male first names
        $male_names = array(
            'james', 'john', 'robert', 'michael', 'william', 'david', 'richard',
            'joseph', 'thomas', 'charles', 'christopher', 'daniel', 'matthew',
            'anthony', 'mark', 'donald', 'steven', 'paul', 'andrew', 'joshua',
            'kenneth', 'kevin', 'brian', 'george', 'timothy', 'ronald', 'edward',
            'jason', 'jeffrey', 'ryan', 'jacob', 'gary', 'nicholas', 'eric',
            'jonathan', 'stephen', 'larry', 'justin', 'scott', 'brandon',
            'benjamin', 'samuel', 'raymond', 'gregory', 'frank', 'alexander',
            'patrick', 'jack', 'dennis', 'jerry', 'tyler', 'aaron', 'nathan',
            'henry', 'peter', 'adam', 'douglas', 'zachary', 'walter', 'harold',
            'kyle', 'carl', 'arthur', 'gerald', 'roger', 'keith', 'jeremy',
            'terry', 'lawrence', 'sean', 'christian', 'albert', 'joe', 'ethan',
            'austin', 'jesse', 'willie', 'billy', 'bryan', 'bruce', 'ralph',
            'roy', 'eugene', 'wayne', 'marcus', 'derek', 'jerome', 'lance',
            'tyrone', 'dwayne', 'devon', 'malik', 'andre', 'leroy', 'darnell',
            'antwan', 'kwame', 'jamal', 'hassan', 'omar', 'akil',
            'carlos', 'jose', 'miguel', 'luis', 'antonio', 'manuel', 'pedro',
            'francisco', 'diego', 'alejandro', 'jorge', 'rafael', 'fernando',
            'roberto', 'eduardo', 'hugo', 'ricardo', 'arturo', 'gabriel',
            'david', 'marco', 'ernesto', 'alberto', 'enrique', 'pablo',
        );

        if (in_array($first, $female_names)) return 'female';
        if (in_array($first, $male_names)) return 'male';

        // Title-based heuristic (Mrs., Ms. → female; Mr. → male)
        if (preg_match('/\b(?:mrs\.|ms\.)\s/i', $full_name)) return 'female';
        if (preg_match('/\b(?:mr\.)\s/i', $full_name)) return 'male';

        // Name-ending heuristic for common patterns
        // Names ending in -a, -ia, -ina, -ella → more likely female
        if (preg_match('/(?:a|ia|ina|ella|ette|ique)$/', $first)) {
            // But some male names end in -a too (Joshua, Ira, etc.)
            if (!in_array($first, array('joshua', 'ira', 'andrea', 'luca', 'nicola', 'andrea'))) {
                return 'female';
            }
        }
        // Names ending in -son, -ld, -rd, -us → more likely male
        if (preg_match('/(?:son|ld|rd|us|on|en|er)$/', $first) && strlen($first) > 3) {
            if (!in_array($first, array('allison', 'maddison', 'cameron', 'harper', 'piper', 'payton'))) {
                return 'male';
            }
        }

        return 'unknown';
    }

    private function merge_providers($existing, $new) {
        foreach ($new as $id => $provider) {
            if (!isset($existing[$id])) {
                $existing[$id] = $provider;
            }
        }
        return $existing;
    }

    // ===========================================================
    //  Provider-field helpers
    // ===========================================================

    private function get_prov_city($pid) {
        $terms = get_the_terms($pid, 'myhh_city');
        if ($terms && !is_wp_error($terms)) {
            return $terms[0]->name;
        }
        return $this->get_prov_meta($pid, array('city', 'post_city', '_myhh_city'));
    }

    private function get_prov_meta($pid, $keys) {
        foreach ((array) $keys as $key) {
            $value = '';
            if (function_exists('geodir_get_post_meta')) {
                $value = geodir_get_post_meta($pid, $key, true);
            }
            if ($value === '' || $value === null) {
                $value = get_post_meta($pid, $key, true);
            }
            if ($value !== '' && $value !== null) {
                return sanitize_text_field((string) $value);
            }
        }
        return '';
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

    private function get_prov_display_name($pid) {
        $candidates = array(
            get_the_title($pid),
            $this->get_prov_meta($pid, array(
                'business_name',
                'practice_name',
                'provider_name',
                'organization_name',
                'company_name',
                '_myhh_business_name',
                '_myhh_practice_name',
                '_myhh_provider_name',
            )),
        );

        foreach ($candidates as $candidate) {
            $name = sanitize_text_field((string) $candidate);
            if ($name !== '' && !$this->is_platform_provider_name($name)) {
                return $name;
            }
        }

        $city = $this->get_prov_city($pid);
        return $city ? "Provider in {$city}" : 'Provider';
    }

    private function get_prov_phone($pid) {
        return $this->get_prov_meta($pid, array('phone', 'post_phone', '_myhh_phone'));
    }

    private function get_prov_website($pid) {
        $website = $this->get_prov_meta($pid, array('website', 'post_website', '_myhh_website'));
        return $website ? esc_url_raw($website) : '';
    }

    private function get_prov_street($pid) {
        return $this->get_prov_meta($pid, array('street', 'post_address', 'address', '_myhh_address'));
    }

    private function get_prov_zip($pid) {
        return $this->get_prov_meta($pid, array('zip', 'post_zip', '_myhh_zip'));
    }

    private function get_prov_address($pid) {
        $street = $this->get_prov_street($pid);
        $city   = $this->get_prov_city($pid);
        $zip    = $this->get_prov_zip($pid);
        $parts  = array_filter(array($street, $city, $zip));
        return implode(', ', $parts);
    }

    private function get_prov_categories($pid) {
        $terms = get_the_terms($pid, 'myhh_primary_category');
        $out   = array();
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $t) $out[] = $t->name;
        }
        return $out;
    }

    private function get_prov_specialties($pid) {
        $terms = get_the_terms($pid, 'myhh_specialty');
        $out   = array();
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $t) $out[] = $t->name;
        }
        return $out;
    }

    private function get_prov_meta_list($pid, $key) {
        $value = get_post_meta($pid, $key, true);
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[,|;\r\n]+/', (string) $value);
        }
        return array_values(array_filter(array_map(function($item) {
            return trim(sanitize_text_field((string) $item));
        }, (array) $items)));
    }

    private function get_prov_license($pid) {
        $terms = get_the_terms($pid, 'myhh_license_type');
        return ($terms && !is_wp_error($terms)) ? $terms[0]->name : '';
    }

    private function get_verification_rank_bonus($verification_status) {
        switch ($verification_status) {
            case 'board_confirmed':
                return 4;
            case 'core_verified':
                return 3;
            case 'source_only':
                return 1;
            default:
                return 0;
        }
    }

    private function get_listing_tier_bonus($listing_tier) {
        $tier = strtolower((string) $listing_tier);

        switch ($tier) {
            case 'pulse-pro':
            case 'pulse pro':
            case 'pro':
                return 2;
            case 'pulse-lite':
            case 'pulse lite':
            case 'lite':
                return 1;
            default:
                return 0;
        }
    }

    private function get_profile_completeness_score($pid) {
        $score = 0;
        $fields = array(
            'provider_credentials',
            'short_bio',
            'full_bio',
            'insurance_accepted',
            'languages_spoken',
            'website',
            'phone',
            'email',
        );

        foreach ($fields as $field) {
            $value = get_post_meta($pid, $field, true);
            if (!empty($value)) {
                $score++;
            }
        }

        return min(3, $score);
    }

    private function get_provider_rating_average($pid) {
        if (!function_exists('myhh_get_listing_rating')) {
            return 0;
        }

        $rating = myhh_get_listing_rating($pid);
        if (!$rating || empty($rating['average'])) {
            return 0;
        }

        return floatval($rating['average']);
    }

    private function get_provider_rating_count($pid) {
        if (!function_exists('myhh_get_listing_rating')) {
            return 0;
        }

        $rating = myhh_get_listing_rating($pid);
        if (!$rating || empty($rating['count'])) {
            return 0;
        }

        return intval($rating['count']);
    }

    private function get_rating_score_bonus($average, $count) {
        if ($count <= 0 || $average <= 0) {
            return 0;
        }

        $score = 0;

        if ($average >= 4.8) {
            $score += 3;
        } elseif ($average >= 4.5) {
            $score += 2;
        } elseif ($average >= 4.0) {
            $score += 1;
        }

        if ($count >= 10) {
            $score += 2;
        } elseif ($count >= 5) {
            $score += 1;
        }

        return $score;
    }

    /**
     * Rotate equally strong top matches so Mia doesn't keep surfacing the same
     * providers first for the exact same search every time.
     *
     * We only rotate providers within a narrow score band near the top to keep
     * recommendations high quality while improving diversity.
     */
    private function rotate_top_matches($providers, $criteria) {
        if (count($providers) < 2) {
            $this->last_rotation_meta = array(
                'rotation_key' => 'myhh_mia_rotation_' . $this->build_provider_search_key($criteria),
                'recent_ids_before' => array(),
                'rotation_pool_size' => count($providers),
                'rotation_offset' => 0,
                'rotated' => false,
            );
            return $providers;
        }

        $top_score = $providers[0]['rank_score'] ?? 0;
        $rotation_pool = array();
        $score_band = 5.5;

        foreach ($providers as $index => $provider) {
            $score = $provider['rank_score'] ?? 0;
            if (($top_score - $score) <= $score_band) {
                $rotation_pool[] = $provider;
                continue;
            }
            break;
        }

        $rotation_pool = array_slice($rotation_pool, 0, 24);

        if (count($rotation_pool) < 2) {
            $this->last_rotation_meta = array(
                'rotation_key' => 'myhh_mia_rotation_' . $this->build_provider_search_key($criteria),
                'recent_ids_before' => array(),
                'rotation_pool_size' => count($rotation_pool),
                'rotation_offset' => 0,
                'rotated' => false,
            );
            return $providers;
        }

        usort($rotation_pool, function ($a, $b) {
            $score_compare = ($b['rank_score'] ?? 0) <=> ($a['rank_score'] ?? 0);
            if ($score_compare !== 0) {
                return $score_compare;
            }

            return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
        });

        $rotation_key = 'myhh_mia_rotation_' . md5(implode('|', array(
            strtolower((string) ($criteria['primary_category'] ?? '')),
            strtolower((string) ($criteria['specialty'] ?? '')),
            strtolower((string) ($criteria['city'] ?? '')),
            strtolower((string) ($criteria['provider_type'] ?? '')),
            strtolower((string) ($criteria['modality'] ?? '')),
            strtolower((string) ($criteria['language_preference'] ?? '')),
            strtolower((string) ($criteria['insurance'] ?? '')),
            strtolower((string) ($criteria['session_type'] ?? '')),
        )));
        $recent_key = $rotation_key . '_recent';

        $recent_ids = get_transient($recent_key);
        if (!is_array($recent_ids)) {
            $recent_ids = array();
        }
        $recent_ids_before = array_values(array_filter(array_map('intval', $recent_ids)));

        $recent_position = array_flip(array_map('intval', $recent_ids));

        usort($rotation_pool, function ($a, $b) use ($recent_position) {
            $a_id = intval($a['id'] ?? 0);
            $b_id = intval($b['id'] ?? 0);
            $a_recent_penalty = array_key_exists($a_id, $recent_position) ? (count($recent_position) - intval($recent_position[$a_id])) : 0;
            $b_recent_penalty = array_key_exists($b_id, $recent_position) ? (count($recent_position) - intval($recent_position[$b_id])) : 0;

            $a_effective = floatval($a['rank_score'] ?? 0) - ($a_recent_penalty * 1.35);
            $b_effective = floatval($b['rank_score'] ?? 0) - ($b_recent_penalty * 1.35);

            if (abs($a_effective - $b_effective) > 0.001) {
                return ($b_effective <=> $a_effective);
            }

            $score_compare = ($b['rank_score'] ?? 0) <=> ($a['rank_score'] ?? 0);
            if ($score_compare !== 0) {
                return $score_compare;
            }

            return $a_id <=> $b_id;
        });

        $rotation_counter = intval(get_transient($rotation_key));
        $rotation_offset = $rotation_counter % count($rotation_pool);

        if ($rotation_offset > 0) {
            $rotation_pool = array_merge(
                array_slice($rotation_pool, $rotation_offset),
                array_slice($rotation_pool, 0, $rotation_offset)
            );
        }

        $next_recent_ids = array_values(array_unique(array_merge(
            array_slice(array_map(function ($provider) {
                return intval($provider['id'] ?? 0);
            }, array_slice($rotation_pool, 0, 4)), 0, 4),
            $recent_ids
        )));
        $next_recent_ids = array_slice(array_filter($next_recent_ids), 0, 12);
        if (!$this->predictive_preview_mode) {
            set_transient($rotation_key, $rotation_counter + 1, 15 * MINUTE_IN_SECONDS);
            set_transient($recent_key, $next_recent_ids, 45 * MINUTE_IN_SECONDS);
        }
        $this->last_rotation_meta = array(
            'rotation_key' => $rotation_key,
            'recent_ids_before' => $recent_ids_before,
            'rotation_pool_size' => count($rotation_pool),
            'rotation_offset' => $rotation_offset,
            'rotated' => true,
            'predictive_preview' => $this->predictive_preview_mode,
        );

        $remainder = array_slice($providers, count($rotation_pool));
        $this->trace('diversity_rotation', count($rotation_pool) . ' eligible top candidates rotated');
        return array_merge($rotation_pool, $remainder);
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

    private function capture_provider_exposure($providers, $criteria, $candidate_count) {
        $shown_providers = array_values(array_slice(is_array($providers) ? $providers : array(), 0, 4));
        $provider_ids = array();
        $rank_scores = array();

        foreach ($shown_providers as $provider) {
            $provider_id = intval($provider['id'] ?? 0);
            if ($provider_id <= 0) {
                continue;
            }
            $provider_ids[] = $provider_id;
            $rank_scores[] = array(
                'id' => $provider_id,
                'score' => round(floatval($provider['rank_score'] ?? 0), 3),
            );
        }

        $recent_before = array_values(array_filter(array_map('intval', $this->last_rotation_meta['recent_ids_before'] ?? array())));
        $recent_lookup = array_fill_keys($recent_before, true);
        $recently_seen = array_values(array_filter($provider_ids, function($provider_id) use ($recent_lookup) {
            return isset($recent_lookup[$provider_id]);
        }));

        $this->last_provider_exposure = array(
            'search_key' => $this->build_provider_search_key($criteria),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'criteria' => array(
                'primary_category' => $criteria['primary_category'] ?? '',
                'specialty' => $criteria['specialty'] ?? '',
                'city' => $criteria['city'] ?? '',
                'provider_type' => $criteria['provider_type'] ?? '',
                'modality' => $criteria['modality'] ?? '',
                'language_preference' => $criteria['language_preference'] ?? '',
                'insurance' => $criteria['insurance'] ?? '',
                'session_type' => $criteria['session_type'] ?? '',
            ),
            'candidate_count' => intval($candidate_count),
            'provider_ids_shown' => $provider_ids,
            'rank_scores' => $rank_scores,
            'recently_seen' => $recently_seen,
            'rotation' => array(
                'rotation_key' => $this->last_rotation_meta['rotation_key'] ?? ('myhh_mia_rotation_' . $this->build_provider_search_key($criteria)),
                'rotation_pool_size' => intval($this->last_rotation_meta['rotation_pool_size'] ?? 0),
                'rotation_offset' => intval($this->last_rotation_meta['rotation_offset'] ?? 0),
                'rotated' => !empty($this->last_rotation_meta['rotated']),
            ),
        );
        $this->trace('provider_exposure', $this->last_provider_exposure);
    }

    // ===========================================================
    //  5. RESPONSE TEMPLATES
    // ===========================================================

    private function build_crisis_response() {
        if ($this->detected_language === 'es') {
            return "Puedo sentir que estás pasando por algo muy difícil, y me alegra que lo estés compartiendo. Tu seguridad es lo más importante ahora mismo.\n\n" .
                "Por favor, comunícate con alguien que pueda ayudarte ahora mismo:\n\n" .
                "**Línea Nacional de Suicidio y Crisis**: Llama o envía texto al **988** (disponible 24/7, gratuito y confidencial)\n" .
                "**Línea de Texto de Crisis**: Envía **HOME** al **741741**\n" .
                "**Emergencias**: Llama al **911**\n\n" .
                "Hay consejeros capacitados disponibles ahora mismo que quieren escucharte. No tienes que pasar por esto solo/a.\n\n" .
                "Cuando te sientas listo/a, también puedo ayudarte a encontrar un terapeuta en nuestro directorio: [Ver Proveedores](/listings/).";
        }
        // English (default)
        return "I can hear that you're going through something really heavy, and I'm glad you're talking about it. Your safety matters most right now.\n\n" .
            "Please reach out to someone who can help you immediately:\n\n" .
            "**National Suicide & Crisis Lifeline**: Call or text **988** (available 24/7, free and confidential)\n" .
            "**Crisis Text Line**: Text **HOME** to **741741**\n" .
            "**Emergency**: Call **911**\n\n" .
            "There are trained counselors ready to listen right now — you don't have to go through this alone.\n\n" .
            "When you're ready, I can also help you find a therapist in our directory: [Browse Providers](/listings/).";
    }

    private function build_provider_add_result($message) {
        $city = $this->extract_provider_add_city($message);
        $field = $this->detect_provider_add_field($message);
        $step = $this->detect_provider_add_step($message, $city, $field);
        $result = $this->wrap_deterministic(
            self::INTENT_PROVIDER_ADD,
            $this->build_provider_add_response($step, $city, $field)
        );
        $result['criteria']['provider_workflow_step'] = $step;
        if ($city !== '') {
            $result['criteria']['provider_workflow_city'] = $city;
        }
        if ($field !== '') {
            $result['criteria']['provider_workflow_field'] = $field;
        }
        $result['provider_workflow'] = array(
            'type' => 'provider_add',
            'status' => 'active',
            'step' => $step,
            'city' => $city,
            'field' => $field,
        );
        return $result;
    }

    private function detect_provider_add_step($message, $city = '', $field = '') {
        $value = strtolower(trim((string) $message));
        if ($city !== '') {
            return 'location';
        }
        if (preg_match('/\b(?:basic|free)\s+(?:provider\s+)?listing\b[\s\S]{0,35}\b(?:cost|free|price|anything)|\b(?:does|is)\b[\s\S]{0,20}\b(?:listing|profile)\b[\s\S]{0,25}\b(?:cost|free)|\bcuesta\b[\s\S]{0,25}\b(?:listado|perfil)\b/iu', $value)) {
            return 'cost';
        }
        if (preg_match('/\b(?:what|which)\b[\s\S]{0,50}\b(?:information|info|details|fields|requirements|required|need|provide|requested)\b|\b(?:what\s+do\s+i\s+need|what\s+should\s+i\s+(?:provide|include)|required\s+information|form\s+requirements)\b/iu', $value) ||
            preg_match('/\b(?:qu[eé]|cu[aá]l(?:es)?)\b[\s\S]{0,50}\b(?:informaci[oó]n|datos|detalles|campos|requisitos|necesito|solicitan)\b|\b(?:qu[eé]\s+necesito|qu[eé]\s+debo\s+incluir)\b/iu', $value)) {
            return 'requirements';
        }
        if ($field !== '') {
            return 'field_help';
        }
        if (preg_match('/\b(?:open|show|visit|start|begin|take\s+me\s+to|send\s+me|give\s+me)\b[\s\S]{0,40}\b(?:form|page|link|application)\b|\b(?:ready\s+to\s+(?:start|add|submit))\b/iu', $value) ||
            preg_match('/\b(?:abre|muestra|visita|empezar|comenzar|ll[eé]vame|env[ií]ame)\b[\s\S]{0,40}\b(?:formulario|p[aá]gina|enlace|solicitud)\b/iu', $value)) {
            return 'open_form';
        }
        if (preg_match('/^\s*(?:(?:ok|okay|got\s+it|sounds\s+good|great|perfect|thanks|thank\s+you|alright|sure|gracias|entendido|perfecto|bien|claro)[,!.]?\s*)+$/iu', $value)) {
            return 'acknowledgement';
        }
        if (($this->prior_context['provider_owner_mode'] ?? '') === self::INTENT_PROVIDER_ADD) {
            return 'continue';
        }
        return 'start';
    }

    private function detect_provider_add_field($message) {
        $value = strtolower(trim((string) $message));
        $fields = array(
            'address' => '/\b(?:street\s+address|office\s+address|practice\s+address|which\s+address|what\s+address|direcci[oó]n)\b/iu',
            'bio' => '/\b(?:bio|biography|description|about\s+(?:me|us|the\s+practice)|practice\s+summary|write\s+about|descripci[oó]n|biograf[ií]a|sobre\s+(?:m[ií]|nosotros|la\s+pr[aá]ctica))\b/iu',
            'category' => '/\b(?:primary\s+category|wellness\s+category|which\s+category|categor[ií]a\s+principal|qu[eé]\s+categor[ií]a)\b/iu',
            'specialty' => '/\b(?:specialty|specialties|specialism|especialidad|especialidades)\b/iu',
            'credentials' => '/\b(?:credential|credentials|license|licence|certification|degree|credencial|credenciales|licencia|certificaci[oó]n|t[ií]tulo)\b/iu',
            'website' => '/\b(?:website|web\s*site|site\s+url|web\s+address|sitio\s+web|p[aá]gina\s+web)\b/iu',
            'booking' => '/\b(?:booking\s+link|appointment\s+link|scheduling\s+link|booking|enlace\s+de\s+reserva|reservas|citas)\b/iu',
            'insurance' => '/\b(?:insurance|coverage|accepted\s+plans|seguro|cobertura|planes\s+aceptados)\b/iu',
            'session_type' => '/\b(?:session\s+type|in[-\s]?person|virtual|hybrid|telehealth|tipo\s+de\s+sesi[oó]n|presencial|h[ií]brido|telesalud)\b/iu',
            'phone' => '/\b(?:phone|telephone|mobile|tel[eé]fono|celular)\b/iu',
            'email' => '/\b(?:email|e-mail|correo|correo\s+electr[oó]nico)\b/iu',
            'practice_name' => '/\b(?:practice\s+name|provider\s+name|business\s+name|clinic\s+name|nombre\s+de\s+(?:la\s+)?(?:pr[aá]ctica|proveedor|negocio|cl[ií]nica))\b/iu',
            'full_name' => '/\b(?:full\s+name|my\s+name|your\s+name|nombre\s+completo|mi\s+nombre)\b/iu',
            'city' => '/\b(?:city|area|location|service\s+area|ciudad|[aá]rea|ubicaci[oó]n|zona\s+de\s+servicio)\b/iu',
            'notes' => '/\b(?:additional\s+notes|other\s+information|notes|notas\s+adicionales|otra\s+informaci[oó]n)\b/iu',
        );
        foreach ($fields as $field => $pattern) {
            if (preg_match($pattern, $value)) {
                return $field;
            }
        }
        return '';
    }

    private function extract_provider_add_city($message) {
        $value = trim((string) $message);
        $patterns = array(
            '/^\s*(?:in|at|near|around|from)\s+([\p{L}][\p{L} .\'’-]{1,70})[.!?]*$/iu',
            '/\b(?:i\s+am|i[\'’]m|we\s+are|we[\'’]re|my\s+(?:practice|office|clinic)\s+is|our\s+(?:practice|office|clinic)\s+is|the\s+(?:practice|office|clinic)\s+is|located|based)\s+(?:in|at|near|around|from)\s+([\p{L}][\p{L} .\'’-]{1,70})[.!?]*$/iu',
            '/\b(?:city|area|location|service\s+area)\s+(?:is|would\s+be|will\s+be)\s+([\p{L}][\p{L} .\'’-]{1,70})[.!?]*$/iu',
            '/\b(?:we\s+serve|i\s+serve|serving)\s+([\p{L}][\p{L} .\'’&-]{1,70})[.!?]*$/iu',
            '/\b(?:estoy|estamos|mi\s+pr[aá]ctica\s+est[aá]|nuestra\s+pr[aá]ctica\s+est[aá]|ubicad[oa]s?|trabajo)\s+(?:en|cerca\s+de)\s+([\p{L}][\p{L} .\'’-]{1,70})[.!?]*$/iu',
            '/\b(?:ciudad|[aá]rea|ubicaci[oó]n|zona\s+de\s+servicio)\s+(?:es|ser[ií]a)\s+([\p{L}][\p{L} .\'’-]{1,70})[.!?]*$/iu',
            '/\b(?:atiendo|atendemos|servimos)\s+(?:en\s+)?([\p{L}][\p{L} .\'’&-]{1,70})[.!?]*$/iu',
        );
        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $value, $matches)) {
                continue;
            }
            $city = trim((string) ($matches[1] ?? ''));
            $city = preg_replace('/\s*,?\s*(?:NC|North\s+Carolina)(?:\s+\d{5}(?:-\d{4})?)?$/iu', '', $city);
            $city = preg_replace('/\s+\d{5}(?:-\d{4})?$/', '', $city);
            $city = trim($city, " \t\n\r\0\x0B,.;:!?");
            if ($city === '' || str_word_count($city) > 8 || preg_match('/\b(?:need|want|help|find|search|provider|therapist|question|information|necesito|quiero|ayuda|buscar|proveedor|terapeuta)\b/iu', $city)) {
                continue;
            }
            return sanitize_text_field($city);
        }
        return '';
    }

    private function build_provider_add_response($step = 'start', $city = '', $field = '') {
        $is_spanish = $this->detected_language === 'es';
        $form_link = $is_spanish
            ? '[Abrir Agregar tu Práctica](/add-your-practice/)'
            : '[Open Add Your Practice](/add-your-practice/)';

        if ($step === 'requirements') {
            if ($is_spanish) {
                return "Para enviar una práctica, el formulario solicita:\n\n" .
                    "**Obligatorio:** nombre completo, nombre de la práctica o proveedor, correo electrónico, ciudad o área, categoría principal, especialidad principal, sitio web, tipo de sesión y una descripción breve.\n\n" .
                    "**Opcional:** teléfono, credenciales o licencias, enlace de reservas, seguros aceptados y notas adicionales.\n\n" .
                    "La solicitud se revisa antes de publicarse. {$form_link}";
            }
            return "The practice form asks for:\n\n" .
                "**Required:** your full name, practice or provider name, email, city or area, primary category, primary specialty, website, session type, and a short bio.\n\n" .
                "**Optional:** phone, credentials or licenses, booking link, accepted insurance, and additional notes.\n\n" .
                "The submission is reviewed before publication. {$form_link}";
        }

        if ($step === 'location' && $city !== '') {
            if ($is_spanish) {
                return "Entendido: **{$city}** es el área de tu práctica en esta conversación. En el formulario, selecciónala en **Ciudad/Área**; si no aparece, elige **Otro** y escríbela en las notas. Esto no completa ni envía el formulario automáticamente. {$form_link}";
            }
            return "Got it: **{$city}** is your practice area for this conversation. On the form, select it under **City/Area**; if it is not listed, choose **Other** and add it in the notes. This does not automatically complete or submit the form. {$form_link}";
        }

        if ($step === 'cost') {
            return $is_spanish
                ? "El listado basico es **gratis**. Completa el formulario y el envio se revisara antes de publicarse. {$form_link}"
                : "The basic listing is **free**. Complete the form and the submission will be reviewed before publication. {$form_link}";
        }

        if ($step === 'field_help' && $field !== '') {
            $english_help = array(
                'address' => 'Use the professional location where you see clients. If you only offer virtual care, provide your service area rather than a private address. The form asks for City/Area; put any additional location details in the notes.',
                'bio' => 'For the short bio, explain who you help, your services or approach, and what a new client can expect. Keep it clear and avoid unsupported claims.',
                'category' => 'Choose the one primary wellness category that best represents the main service you provide. You can use the specialty field and notes for additional focus areas.',
                'specialty' => 'Choose your main specialty. If the exact option is unavailable, select Other and describe the specialty in the notes.',
                'credentials' => 'List current professional credentials or licenses exactly as issued, such as LCSW, LMFT, LPC, PhD, or MD.',
                'website' => 'Use a working professional website URL that represents you or the practice.',
                'booking' => 'The booking link is optional. Add the direct scheduling URL clients should use, if you have one.',
                'insurance' => 'Insurance is optional. List only plans you currently accept and include self-pay if applicable.',
                'session_type' => 'Choose In-Person, Virtual, or Hybrid based on how you currently provide services.',
                'phone' => 'Phone is optional. Use the professional number clients should call for the practice.',
                'email' => 'Use a professional email address you monitor because it may be used for submission follow-up.',
                'practice_name' => 'Enter the public practice or provider name exactly as you want it displayed.',
                'full_name' => 'Enter the full name of the person authorized to submit the practice.',
                'city' => 'Choose the primary city or area. If it is not listed, choose Other and provide the location in the notes.',
                'notes' => 'Use additional notes for another location, extra specialties, or anything the review team should know.',
            );
            $spanish_help = array(
                'address' => 'Usa la ubicación profesional donde atiendes a los clientes. Si solo ofreces atención virtual, indica la zona de servicio en lugar de una dirección privada. Si el formulario solo solicita ciudad, añade otros detalles en las notas.',
                'bio' => 'En la descripción breve, explica a quién ayudas, tus servicios o enfoque y qué puede esperar un cliente nuevo. Evita afirmaciones que no puedas verificar.',
                'category' => 'Elige la categoría principal que mejor represente tu servicio central. Usa la especialidad y las notas para otros enfoques.',
                'specialty' => 'Elige tu especialidad principal. Si no aparece la opción exacta, selecciona Otro y descríbela en las notas.',
                'credentials' => 'Escribe tus credenciales o licencias vigentes exactamente como fueron emitidas.',
                'website' => 'Usa una dirección web profesional y activa que represente al proveedor o la práctica.',
                'booking' => 'El enlace de reservas es opcional. Agrega la dirección directa para programar citas, si tienes una.',
                'insurance' => 'El seguro es opcional. Incluye solamente los planes que aceptas actualmente y pago directo si corresponde.',
                'session_type' => 'Elige Presencial, Virtual o Híbrido según cómo prestas tus servicios actualmente.',
                'phone' => 'El teléfono es opcional. Usa el número profesional que deben llamar los clientes.',
                'email' => 'Usa un correo profesional que revises, porque puede utilizarse para dar seguimiento a la solicitud.',
                'practice_name' => 'Escribe el nombre público del proveedor o de la práctica tal como deseas que aparezca.',
                'full_name' => 'Escribe el nombre completo de la persona autorizada para enviar la práctica.',
                'city' => 'Elige la ciudad o área principal. Si no aparece, selecciona Otro y escribe la ubicación en las notas.',
                'notes' => 'Usa las notas para otra ubicación, especialidades adicionales o información útil para el equipo de revisión.',
            );
            $help = $is_spanish ? ($spanish_help[$field] ?? '') : ($english_help[$field] ?? '');
            return $help . "\n\n" . $form_link;
        }

        if ($step === 'open_form') {
            return $is_spanish
                ? "Claro. Abre el formulario aquí: {$form_link}. La solicitud es gratuita y se revisa antes de publicarse."
                : "Absolutely. Open the form here: {$form_link}. Submission is free and it will be reviewed before publication.";
        }

        if ($step === 'acknowledgement') {
            return $is_spanish
                ? "De nada. Seguimos con tu nueva práctica. Puedo mostrarte la lista de requisitos, ayudarte con un campo o abrir el formulario."
                : "You're welcome. We are still working on your new practice. I can show the requirements, help with a field, or open the form.";
        }

        if ($step === 'continue') {
            return $is_spanish
                ? "Seguimos con tu nueva práctica. Puedo explicar los campos obligatorios, ayudarte con la categoría o descripción, o abrir el formulario. ¿Qué necesitas?"
                : "We are still working on your new practice. I can explain the required fields, help with the category or bio, or open the form. What do you need?";
        }

        return $is_spanish
            ? "Claro. Agregar una práctica es gratuito. Completarás los datos profesionales en {$form_link} y la solicitud se revisará antes de publicarse. Puedo mostrarte todos los requisitos o ayudarte con un campo específico."
            : "Absolutely. Adding a practice is free. You will enter the professional details on {$form_link}, and the submission will be reviewed before publication. I can show you the full requirements or help with a specific field.";
    }

    private function build_provider_claim_response() {
        if ($this->detected_language === 'es') {
            return "Si tu practica ya aparece en el directorio, puedes reclamar el perfil gratis para controlar la informacion.\n\n" .
                "**Camino rapido:**\n" .
                "1. Busca tu nombre en nuestra [página de Proveedores](/listings/)\n" .
                "2. Abre tu perfil y haz clic en **\"Claim Your Free Profile\"**\n" .
                "3. Envia tu nombre, email y un enlace de prueba, como tu sitio web, perfil profesional o licencia\n" .
                "4. Recibiras confirmacion por email y podras mantener actualizados tus datos, especialidades y contacto\n\n" .
                "**¿No encuentras tu perfil?** Entonces crea uno desde cero aqui: [Add Your Practice](/add-your-practice/).";
        }
        // English (default)
        return "If your practice is already listed, you can claim the profile for free and take control of the information.\n\n" .
            "**Fast path:**\n" .
            "1. Search for your name on our [Providers page](/listings/)\n" .
            "2. Open your profile and click **\"Claim Your Free Profile\"**\n" .
            "3. Submit your name, email, and a proof link, such as your website, professional profile, or license page\n" .
            "4. You’ll get an email confirmation and can keep your details, specialties, and contact info updated\n\n" .
            "**Can’t find your profile?** Create one from scratch here: [Add Your Practice](/add-your-practice/).";
    }

    private function build_provider_owner_response() {
        if ($this->detected_language === 'es') {
            return "Entendido. ¿Quieres **agregar una practica nueva**, **reclamar un perfil que ya aparece**, o **mejorar/actualizar tu perfil**?\n\n" .
                "Si ya ves tu perfil en el directorio, dime **\"reclamar mi perfil\"**. Si aun no existe, dime **\"agregar mi practica\"**. Tambien puedes darme el nombre de tu practica y ciudad para guiarte mejor.";
        }

        return "Got it. Are you trying to **add a new practice**, **claim a profile that is already listed**, or **upgrade/manage your profile**?\n\n" .
            "If your profile is already on the site, say **\"claim my profile\"**. If it is not listed yet, say **\"add my practice\"**. You can also tell me your practice name and city, and I’ll guide you from there.";
    }

    private function build_upgrade_response() {
        if ($this->detected_language === 'es') {
            return "¡Mejorar tu listado es una excelente manera de destacar! Tenemos dos planes disponibles:\n\n" .
                "**Pulse Lite** — solo \$97/mes\n" .
                "• **Mayor visibilidad** — Apareces más arriba en búsquedas y páginas de categorías\n" .
                "• **Insignia verificada** — Los clientes saben que tu perfil está autenticado\n" .
                "• **Perfil enriquecido** — Agrega fotos, descripción extendida y más detalles\n\n" .
                "**Pulse Pro** — solo \$197/mes\n" .
                "• Todo lo de Pulse Lite, más:\n" .
                "• **Ubicación premium** — Máxima visibilidad en resultados de búsqueda\n" .
                "• **Soporte prioritario** — Actualizaciones y asistencia más rápidas\n\n" .
                "**¿Listo para empezar?**\n" .
                "• [Quiero Pulse Lite](/contact/?upgrade=pulse-lite)\n" .
                "• [Quiero Pulse Pro](/contact/?upgrade=pulse-pro)\n\n" .
                "¿Aún no tienes un listado básico? [Agrega tu práctica gratis](/add-your-practice/) primero — luego podemos hablar de mejoras. ¿Qué te gustaría saber?";
        }
        // English (default)
        return "Upgrading is a great way to stand out! We offer two plans:\n\n" .
            "**Pulse Lite** — only \$97/month\n" .
            "• **Higher visibility** — Show up higher in search results and category pages\n" .
            "• **Verified badge** — Clients know your profile is authentic\n" .
            "• **Richer profile** — Add photos, an extended description, and more details\n\n" .
            "**Pulse Pro** — only \$197/month\n" .
            "• Everything in Pulse Lite, plus:\n" .
            "• **Premium placement** — Maximum visibility in search results\n" .
            "• **Priority support** — Faster listing updates and assistance\n\n" .
            "**Ready to get started?**\n" .
            "• [Upgrade Now — Pulse Lite](/contact/?upgrade=pulse-lite)\n" .
            "• [Upgrade to Pulse Pro](/contact/?upgrade=pulse-pro)\n\n" .
            "Don't have a basic listing yet? Start with a [free listing](/add-your-practice/) first — then we can talk upgrades. What would you like to know?";
    }

    private function build_sponsor_response() {
        if ($this->detected_language === 'es') {
            return "¡Gracias por tu interés en colaborar con MyHealthHub! Nos encanta conectar con organizaciones " .
                "que comparten nuestra misión de ayudar a las personas a encontrar el bienestar.\n\n" .
                "**Tipos de colaboración:**\n" .
                "• Patrocinios de categorías\n" .
                "• Publicidad en el directorio\n" .
                "• Asociaciones empresariales\n" .
                "• Listados corporativos múltiples\n\n" .
                "Cuéntanos más sobre lo que tienes en mente a través de nuestra " .
                "[página de Contacto](/contact/). Nuestro equipo te responderá en 1-2 días hábiles.\n\n" .
                "¡Esperamos saber de ti!";
        }
        // English (default)
        return "Thanks for your interest in partnering with MyHealthHub! We love connecting with organizations " .
            "that share our mission of helping people find wellness.\n\n" .
            "**Partnership opportunities:**\n" .
            "• Category sponsorships\n" .
            "• Directory advertising\n" .
            "• Business partnerships\n" .
            "• Multi-location corporate listings\n\n" .
            "Tell us more about what you have in mind through our [Contact page](/contact/). " .
            "Our team will get back to you within 1-2 business days.\n\n" .
            "We look forward to hearing from you!";
    }

    // --- Consumer search response builders ---

    /**
     * Build a helpful clarification reply when the user's search is too vague
     * to produce results (no category, no specialty, no city detected).
     * Instead of returning null (which triggers a generic "say that once more"),
     * this asks the user what kind of support they want and where they are.
     */
    private function build_vague_search_clarification($criteria) {
        $is_spanish = $this->detected_language === 'es';

        if ($is_spanish) {
            return "Con gusto te ayudo a buscar. Para encontrar al profesional adecuado, dime:\n\n" .
                "- **Que tipo de apoyo** buscas — por ejemplo, terapia, consejeria, psiquiatria\n" .
                "- **En que ciudad** te encuentras — como Raleigh, Durham o Chapel Hill\n\n" .
                "Por ejemplo, puedes decir: \"necesito un terapeuta en Raleigh\".";
        }

        $reply = "I'd love to help you find the right provider! To narrow it down, could you tell me:\n\n" .
            "- **What kind of support** you're looking for — for example, therapy, counseling, or psychiatry\n" .
            "- **Which city** you're in — like Raleigh, Durham, or Chapel Hill\n\n" .
            "For example, you could say: \"I need a therapist in Raleigh\" or \"anxiety counselor near Durham\".";

        if (!empty($criteria['city'])) {
            $reply = "I can help you find providers! You mentioned you're in **{$criteria['city']}**. " .
                "What kind of support are you looking for? For example:\n\n" .
                "- **Therapy or counseling** for anxiety, depression, or relationships\n" .
                "- **Psychiatry** for medication management\n" .
                "- **Substance use** support\n\n" .
                "Just tell me what you need and I'll search for providers near you.";
        }

        return $reply;
    }

    private function build_search_response($criteria, $search, $context) {
        $providers = $search['providers'];
        $broadened = $search['broadened'];
        $criteria['preference_gaps'] = $search['preference_gaps'] ?? array();
        $name_suggestions = $search['name_suggestions'] ?? array();
        $cat_name  = $criteria['primary_category_name'];

        // Is the target category sparse?
        $sparse = false;
        if ($cat_name) {
            foreach ($context['categories'] as $c) {
                if ($c['name'] === $cat_name && $c['count'] < 3) {
                    $sparse = true;
                    break;
                }
            }
        }

        // A: Strong matches
        if (count($providers) >= 2 && !$sparse) {
            return $this->format_provider_results($criteria, $providers, $broadened, $name_suggestions);
        }

        // B: Sparse / no results
        if ($sparse || count($providers) === 0) {
            return $this->build_sparse_response($criteria, $providers, $context);
        }

        // C: Some results but thin
        $resp  = $this->format_provider_results($criteria, $providers, $broadened, $name_suggestions);
        $resp .= "\n\nWe're actively expanding our directory — check back soon for more options, or [browse all providers](/listings/).";
        return $resp;
    }

    private function format_provider_results($criteria, $providers, $broadened, $name_suggestions = array()) {
        $parts = array();

        // Opening — customize for broad browse (no filters provided)
        $is_broad_browse = !empty($criteria['is_broad_browse']);
        if ($is_broad_browse) {
            $parts[] = "Here are some providers from our directory:";
            $parts[] = "_You can narrow results by telling me a **specialty** (like anxiety, depression), a **city** (like Raleigh, Charlotte), or a **category** (like Couples Therapy, Psychiatry)._";
        } else {
            $opening = 'Here are some providers';
            if ($criteria['specialty'])             $opening .= ' specializing in **' . $criteria['specialty'] . '**';
            elseif ($criteria['primary_category_name']) $opening .= ' in **' . $criteria['primary_category_name'] . '**';
            if ($criteria['city'])                  $opening .= ' near **' . $criteria['city'] . '**';
            $parts[] = $opening . ':';
        }

        if ($broadened) $parts[] = "_({$broadened})_";

        $preference_gaps = (array) ($criteria['preference_gaps'] ?? array());
        if (in_array('modality', $preference_gaps, true) && !empty($criteria['modality'])) {
            $parts[] = '_No current profile in this result set explicitly lists **' . $criteria['modality'] . '**, so these are broader matches rather than confirmed modality matches._';
        }
        if (in_array('language_preference', $preference_gaps, true) && !empty($criteria['language_preference'])) {
            $parts[] = '_No current profile in this result set explicitly confirms **' . $criteria['language_preference'] . '**, so please verify language availability on the profile or with the provider._';
        }
        if (in_array('insurance', $preference_gaps, true) && !empty($criteria['insurance'])) {
            $parts[] = '_No current profile in this result set explicitly confirms **' . $criteria['insurance'] . '**, so please verify coverage with the provider and your plan._';
        }
        if (in_array('session_type', $preference_gaps, true) && !empty($criteria['session_type'])) {
            $parts[] = '_No current profile in this result set explicitly confirms **' . $criteria['session_type'] . '** sessions, so please verify availability with the provider._';
        }

        // Gender preference disclaimer (only shown when user explicitly requested)
        if (!empty($criteria['gender_preference'])) {
            $gender_label = $criteria['gender_preference'] === 'female' ? 'female' : 'male';
            $parts[] = "_Showing likely {$gender_label} providers based on name. We don't store gender data, so results are best-effort._";
        }

        // Provider list
        $is_fuzzy_match = !empty($criteria['provider_name']) && !empty($name_suggestions);
        if ($is_fuzzy_match) {
            $parts[] = "I couldn't find an exact match for **" . $criteria['provider_name'] . "**. Did you mean:";
        }

        foreach ($providers as $p) {
            $line    = "• **[{$p['name']}]({$p['url']})**";
            $details = array();
            if ($p['city'])         $details[] = $p['city'];
            if ($p['license_type']) $details[] = $p['license_type'];
            if (!empty($p['specialties'])) $details[] = implode(', ', array_slice($p['specialties'], 0, 3));
            if (!empty($criteria['modality']) && $this->provider_list_field_matches($p['modalities'] ?? array(), $criteria['modality'])) {
                $details[] = $criteria['modality'];
            }
            if (!empty($criteria['language_preference']) && $this->provider_list_field_matches($p['languages'] ?? array(), $criteria['language_preference'])) {
                $details[] = $criteria['language_preference'];
            }
            if (!empty($criteria['insurance']) && $this->provider_list_field_matches($p['insurance_accepted'] ?? array(), $criteria['insurance'])) {
                $details[] = $criteria['insurance'];
            }
            if (!empty($criteria['session_type']) && $this->provider_session_type_matches($p['session_type'] ?? '', $criteria['session_type'])) {
                $details[] = $p['session_type'] ?: $criteria['session_type'];
            }
            if (!empty($details))   $line .= ' — ' . implode(' | ', $details);
            $parts[] = $line;
        }

        $parts[] = "\nVisit their profile to learn more about their practice and how to connect.";
        $parts[] = "[Browse All Providers](/listings/)";
        return implode("\n", $parts);
    }

    private function build_sparse_response($criteria, $providers, $context) {
        $cat = $criteria['primary_category_name'] ?: 'this area';

        $resp = "We're actively building our **{$cat}** category and expanding our provider network.\n\n";

        if (count($providers) > 0) {
            $resp .= "Here's what we have so far:\n";
            foreach ($providers as $p) {
                $resp .= "• **[{$p['name']}]({$p['url']})**";
                if ($p['city']) $resp .= " — {$p['city']}";
                $resp .= "\n";
            }
            $resp .= "\n";
        }

        // Suggest populated alternatives
        $populated = array();
        foreach ($context['categories'] as $c) {
            if ($c['count'] >= 5) $populated[] = $c['name'];
        }
        if (!empty($populated)) {
            $resp .= "In the meantime, we have strong coverage in:\n";
            foreach (array_slice($populated, 0, 3) as $pop) {
                $resp .= "• **{$pop}**\n";
            }
            $resp .= "\n";
        }

        $resp .= "You can also [browse all listings](/listings/) or check back soon as new providers join regularly.";
        $resp .= "\n\nAre you a provider in {$cat}? [List your practice for free!](/add-your-practice/)";
        return $resp;
    }

    // ===========================================================
    //  7b. NAME-BASED SEARCH + FUZZY MATCHING
    // ===========================================================

    /**
     * Search for providers by name using WP_Query.
     *
     * Uses the 's' parameter (WordPress search) to find providers
     * whose post title contains the given name.
     *
     * @param string $name     The provider name to search for.
     * @param string $city     Optional city to narrow results.
     * @return array Provider data arrays.
     */
    /** Exact-profile lookup for a named detail request; published listings only. */
    public function find_named_providers($name) {
        return array_values($this->query_providers_by_name(mb_substr(sanitize_text_field($name), 0, 80)));
    }

    private function query_providers_by_name($name, $city = '') {
        if (empty($name)) return array();

        $args = array(
            'post_type'      => 'gd_place',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'no_found_rows'  => true,
            's'              => sanitize_text_field($name),
        );

        // Add city filter if provided
        if (!empty($city)) {
            $term = $this->resolve_taxonomy_term('myhh_city', $city);
            if ($term) {
                $args['tax_query'] = array(array(
                    'taxonomy' => 'myhh_city',
                    'field'    => 'term_id',
                    'terms'    => $term->term_id,
                ));
            }
        }

        $query = new WP_Query($args);
        $providers = array();

        foreach ($query->posts as $post) {
            $pid = $post->ID;
            $providers[$pid] = $this->format_provider_from_post($pid);
        }
        wp_reset_postdata();

        return array_filter($providers);
    }

    /**
     * Fuzzy-match a provider name against all published providers using
     * Levenshtein distance and metaphone (phonetic) similarity.
     *
     * @param string $search_name  The name the user provided (e.g. "Angla Green").
     * @param string $search_city  Optional city to narrow the candidate pool.
     * @param int    $limit        Max number of suggestions to return (default 3).
     * @return array  Array of provider data arrays with an added 'similarity_score' key.
     */
    private function fuzzy_match_provider_name($search_name, $search_city = '', $limit = 3) {
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
            'posts_per_page' => 200,
            'no_found_rows'  => true,
            'fields'         => 'ids',
        );

        if (!empty($search_city)) {
            $term = $this->resolve_taxonomy_term('myhh_city', $search_city);
            if ($term) {
                $args['tax_query'] = array(array(
                    'taxonomy' => 'myhh_city',
                    'field'    => 'term_id',
                    'terms'    => $term->term_id,
                ));
            }
        }

        $query = new WP_Query($args);
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

            // 1. Exact match
            if ($provider_lower === $search_name_lower) {
                $score = 1000;
            }
            // 2. Metaphone (phonetic) similarity
            elseif ($provider_meta === $search_name_meta) {
                $score = 800;
            }
            // 3. Per-word metaphone match
            elseif (count($search_words) >= 2 && count($provider_words) >= 2) {
                $search_metas   = array_map('metaphone', $search_words);
                $provider_metas = array_map('metaphone', $provider_words);
                $meta_match_count = count(array_intersect($search_metas, $provider_metas));
                if ($meta_match_count >= 2) {
                    $score = 700;
                } elseif ($meta_match_count === 1) {
                    $score = 400;
                }
            }

            // 4. Levenshtein distance
            if ($score < 700) {
                $lev_full = levenshtein($search_name_lower, $provider_lower);
                $max_len = max(strlen($search_name_lower), strlen($provider_lower), 1);
                if ($lev_full <= $max_len && $lev_full <= 5) {
                    $score = max($score, 600 - ($lev_full * 40));
                } elseif ($lev_full <= $max_len && $lev_full <= 10) {
                    $score = max($score, 300 - ($lev_full * 20));
                }

                // 5. Per-word Levenshtein (cumulative: sum best match per search word)
                if (count($search_words) >= 2 && count($provider_words) >= 2 && $score < 500) {
                    $cumulative_word_score = 0;
                    foreach ($search_words as $sw) {
                        $best_for_this_word = 0;
                        foreach ($provider_words as $pw) {
                            $wl = levenshtein($sw, $pw);
                            $wmax = max(strlen($sw), strlen($pw), 1);
                            if ($wl === 0) {
                                $best_for_this_word = max($best_for_this_word, 300); // exact word match
                            } elseif ($wl <= 2 && $wl <= $wmax) {
                                $best_for_this_word = max($best_for_this_word, 250 - ($wl * 50));
                            } elseif ($wl <= 3 && $wl <= $wmax) {
                                $best_for_this_word = max($best_for_this_word, 150 - ($wl * 30));
                            }
                        }
                        $cumulative_word_score += $best_for_this_word;
                    }
                    $score = max($score, $cumulative_word_score);
                }

                // 6. Contains match
                if ($score < 300) {
                    if (strpos($provider_lower, $search_name_lower) !== false ||
                        strpos($search_name_lower, $provider_lower) !== false) {
                        $score = max($score, 350);
                    }
                }
            }

            // Only keep candidates with meaningful similarity (score >= 150)
            if ($score >= 150) {
                $provider_data = $this->format_provider_from_post($pid);
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

        return array_slice($scored, 0, $limit);
    }

    /**
     * Format a single provider from a post ID (reused by name search methods).
     */
    private function format_provider_from_post($pid) {
        return array(
            'id'           => $pid,
            'name'         => $this->get_prov_display_name($pid),
            'url'          => get_permalink($pid),
            'city'         => $this->get_prov_city($pid),
            'phone'        => $this->get_prov_phone($pid),
            'website'      => $this->get_prov_website($pid),
            'street'       => $this->get_prov_street($pid),
            'zip'          => $this->get_prov_zip($pid),
            'address'      => $this->get_prov_address($pid),
            'categories'   => $this->get_prov_categories($pid),
            'specialties'  => $this->get_prov_specialties($pid),
            'modalities'   => $this->get_prov_meta_list($pid, 'modalities'),
            'languages'    => $this->get_prov_meta_list($pid, 'languages_spoken'),
            'insurance_accepted' => $this->get_prov_meta_list($pid, 'insurance_accepted'),
            'session_type' => sanitize_text_field(get_post_meta($pid, 'session_type', true)),
            'license_type' => $this->get_prov_license($pid),
            'verification_status' => get_post_meta($pid, 'verification_status', true),
            'claimed'      => (bool) get_post_meta($pid, '_myhh_claimed', true),
            'has_booking_link' => !empty(get_post_meta($pid, 'booking_link', true)),
            'rating_average' => $this->get_provider_rating_average($pid),
            'rating_count' => $this->get_provider_rating_count($pid),
            'rank_score'   => 0,
        );
    }

    // ===========================================================
    //  8. DEBUG TRACE
    // ===========================================================

    private function trace($key, $value) {
        $this->debug_trace[] = array('step' => $key, 'value' => $value);
    }

    /** @return array Full debug trace for the current request. */
    public function get_debug_trace() {
        return $this->debug_trace;
    }
}
