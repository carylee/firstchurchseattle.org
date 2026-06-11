<?php
/**
 * Mode 3 — the WordPress/HTTP shell behind native Breeze submission.
 *
 * The pure logic (render/validate/inputs) lives in src/Native.php; this file is
 * the part that can't be unit-tested without WordPress or the network:
 *
 *   - the baked native-form map loader (data/native-forms.json)
 *   - fcbf_render_native(): build the WP context (REST endpoint, nonce,
 *     Turnstile sitekey), enqueue assets, and emit the form
 *   - the REST route firstchurch/v1/breeze-native and its handler
 *   - the Breeze private-endpoint bridge: seed a session, mint an entry id,
 *     then save the section — the exact three-step dance the connection-card
 *     plugin reverse-engineered from Breeze's own form JavaScript.
 *
 * No Breeze credentials are involved: like the public form's browser JS, we
 * just carry the session cookie + CSRF token across the two AJAX calls.
 *
 * @package FirstChurch\BreezeForms
 */

if (!defined('ABSPATH')) {
    exit;
}

use FirstChurch\BreezeForms\Native;

/** Breeze form base — the public origin every form (and AJAX call) lives under. */
const FCBF_NATIVE_BASE = 'https://firstchurchseattle.breezechms.com';

/** Cloudflare Turnstile verify endpoint (used only when the keys are defined). */
const FCBF_TURNSTILE_VERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

/**
 * The baked native-form contracts (data/native-forms.json), id => contract.
 * Cached per-request; an unreadable/!array file degrades to an empty map (so
 * mode="native" simply finds no contract and the caller falls back).
 *
 * @return array<string,array<string,mixed>>
 */
function fcbf_native_forms(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $file = __DIR__ . '/../data/native-forms.json';
    $data = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
    $cache = is_array($data) ? $data : [];
    return $cache;
}

/** Turnstile is shared with the connection-card; reuse its constants if set. */
function fcbf_native_turnstile_sitekey(): string
{
    return defined('FCC_TURNSTILE_SITEKEY') ? (string) FCC_TURNSTILE_SITEKEY : '';
}

function fcbf_native_turnstile_secret(): string
{
    return defined('FCC_TURNSTILE_SECRET') ? (string) FCC_TURNSTILE_SECRET : '';
}

function fcbf_native_turnstile_enabled(): bool
{
    return fcbf_native_turnstile_sitekey() !== '' && fcbf_native_turnstile_secret() !== '';
}

/**
 * Log a Breeze submission failure for ops visibility (the visitor only ever
 * sees a generic error). Mirrors the connection-card's fcc_log hook.
 */
function fcbf_native_log(string $stage, string $detail): void
{
    do_action('fcbf_native_failure', $stage, $detail);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("[breeze-native] {$stage}: {$detail}");
    }
}

/**
 * Render a native form for the given shortcode/block atts, or '' when the form
 * has no native contract (the caller then falls back to button/embed).
 *
 * @param array<string,mixed> $atts
 */
function fcbf_render_native(array $atts): string
{
    $contract = Native::resolve(
        (string) ($atts['id'] ?? ''),
        (string) ($atts['slug'] ?? ''),
        fcbf_native_forms()
    );
    if ($contract === null) {
        return '';
    }

    fcbf_enqueue_native_assets();

    return Native::render($contract, [
        'endpoint'          => esc_url_raw(rest_url('firstchurch/v1/breeze-native')),
        'nonce'             => wp_create_nonce('wp_rest'),
        'turnstile_sitekey' => fcbf_native_turnstile_sitekey(),
        'heading'           => (string) ($atts['title'] ?? ''),
    ]);
}

/** Enqueue the native form's script + the shared stylesheet (and Turnstile). */
function fcbf_enqueue_native_assets(): void
{
    wp_enqueue_style('firstchurch-breeze-forms');
    wp_enqueue_script('firstchurch-breeze-forms-native');
    if (fcbf_native_turnstile_enabled()) {
        wp_enqueue_script(
            'cloudflare-turnstile',
            'https://challenges.cloudflare.com/turnstile/v0/api.js',
            [],
            null,
            true
        );
    }
}

/** Register the native form script so the enqueue above can attach it. */
add_action('init', function (): void {
    wp_register_script(
        'firstchurch-breeze-forms-native',
        plugin_dir_url(__DIR__) . 'assets/native.js',
        [],
        FCBF_VERSION,
        true
    );
});

/* -------------------------------------------------------------------------
 * REST route + the Breeze bridge.
 * ---------------------------------------------------------------------- */

add_action('rest_api_init', function (): void {
    register_rest_route('firstchurch/v1', '/breeze-native', [
        'methods'             => 'POST',
        'callback'            => 'fcbf_native_submit',
        // The nonce proves the request came from a page on this site that
        // rendered the form. Logged-out visitors get a valid rest nonce too, so
        // real anti-spam is the honeypot + rate limit (+ optional Turnstile).
        'permission_callback' => function (WP_REST_Request $req) {
            $nonce = $req->get_header('X-WP-Nonce');
            return $nonce && wp_verify_nonce($nonce, 'wp_rest');
        },
    ]);
});

function fcbf_native_submit(WP_REST_Request $request)
{
    $params = $request->get_json_params() ?: $request->get_body_params();
    if (!is_array($params)) {
        $params = [];
    }

    // Honeypot — bots fill the hidden field; pretend success so they don't retry.
    if (Native::is_honeypot($params)) {
        return new WP_REST_Response(['ok' => true], 200);
    }

    $contract = Native::resolve((string) ($params['form_id'] ?? ''), '', fcbf_native_forms());
    if ($contract === null) {
        return new WP_Error('unknown_form', 'Unknown form.', ['status' => 400]);
    }

    $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key  = 'fcbf_native_rl_' . md5($ip);
    $hits = (int) get_transient($key);
    if ($hits >= 5) {
        return new WP_Error('rate_limited', 'Too many submissions from this address. Try again later.', ['status' => 429]);
    }
    set_transient($key, $hits + 1, 10 * MINUTE_IN_SECONDS);

    $errors = Native::validate($contract, $params);
    if ($errors) {
        return new WP_Error('validation', implode(' ', $errors), ['status' => 400]);
    }

    $verified = fcbf_native_verify_turnstile($params);
    if (is_wp_error($verified)) {
        return $verified;
    }

    // Breeze's new_entry_id call wants the submitter's name/email up front; pull
    // them out of the first name/email fields the contract declares.
    [$first, $last, $email] = fcbf_native_identity($contract, $params);

    $slug    = (string) $contract['slug'];
    $form_id = (string) $contract['id'];

    $cookies = fcbf_native_seed_session($slug);
    if (is_wp_error($cookies)) {
        return $cookies;
    }

    $csrf      = 'js' . fcbf_native_random_string(50);
    $cookies[] = new WP_Http_Cookie([
        'name'   => 'x-csrf-token',
        'value'  => $csrf,
        'domain' => 'firstchurchseattle.breezechms.com',
        'path'   => '/',
    ]);

    $entry_id = fcbf_native_mint_entry_id($slug, $form_id, $cookies, $csrf, $first, $last, $email);
    if (is_wp_error($entry_id)) {
        return $entry_id;
    }

    $saved = fcbf_native_save_section($slug, $cookies, $csrf, $entry_id, Native::inputs($contract, $params));
    if (is_wp_error($saved)) {
        return $saved;
    }

    return new WP_REST_Response(['ok' => true, 'entry_id' => $entry_id], 200);
}

/**
 * Extract first/last/email from the contract's first name + email fields.
 *
 * @return array{0:string,1:string,2:string}
 */
function fcbf_native_identity(array $contract, array $params): array
{
    $first = $last = $email = '';
    foreach (Native::fields($contract) as $field) {
        $value = $params[$field['key']] ?? null;
        if ($field['type'] === 'name' && is_array($value) && $first === '' && $last === '') {
            $first = trim((string) ($value['first'] ?? ''));
            $last  = trim((string) ($value['last'] ?? ''));
        } elseif ($field['type'] === 'email' && $email === '' && !is_array($value)) {
            $email = sanitize_email((string) $value);
        }
    }
    return [$first, $last, $email];
}

/** Headers Breeze's AJAX endpoints expect on the two POSTs. */
function fcbf_native_headers(string $slug, string $csrf): array
{
    return [
        'X-CSRF-Token'       => $csrf,
        'X-SETUP-RAN'        => 'true',
        'X-SECURITY-VERSION' => 'beta',
        'X-Requested-With'   => 'XMLHttpRequest',
        'Referer'            => FCBF_NATIVE_BASE . '/form/' . $slug,
    ];
}

/** Step 0 — GET the public form to seed Breeze's session cookies. */
function fcbf_native_seed_session(string $slug)
{
    $resp = wp_remote_get(FCBF_NATIVE_BASE . '/form/' . $slug, [
        'timeout'    => 30,
        'user-agent' => FCBF_USER_AGENT,
    ]);
    if (is_wp_error($resp)) {
        fcbf_native_log('breeze_unreachable', $resp->get_error_message());
        return new WP_Error('breeze_unreachable', 'Could not reach Breeze.', ['status' => 502]);
    }
    return wp_remote_retrieve_cookies($resp);
}

/** Step 1 — POST /ajax/new_entry_id to mint a numeric entry id. */
function fcbf_native_mint_entry_id(string $slug, string $form_id, array $cookies, string $csrf, string $first, string $last, string $email)
{
    $resp = wp_remote_post(FCBF_NATIVE_BASE . '/ajax/new_entry_id', [
        'timeout'    => 30,
        'user-agent' => FCBF_USER_AGENT,
        'cookies'    => $cookies,
        'headers'    => fcbf_native_headers($slug, $csrf),
        'body'       => [
            'form_id'    => $form_id,
            'token'      => '',
            'processor'  => 'stripe',
            'amount'     => '',
            'first_name' => $first,
            'last_name'  => $last,
            'email'      => $email,
        ],
    ]);
    if (is_wp_error($resp)) {
        fcbf_native_log('breeze_step1', $resp->get_error_message());
        return new WP_Error('breeze_step1', 'Breeze submission failed (step 1).', ['status' => 502]);
    }
    $body = trim((string) wp_remote_retrieve_body($resp));
    if (!ctype_digit($body)) {
        fcbf_native_log('breeze_step1_bad', substr($body, 0, 200));
        return new WP_Error('breeze_step1_bad', 'Unexpected Breeze response.', ['status' => 502]);
    }
    return $body;
}

/** Step 2 — POST /ajax/person_save_section with the projected inputs. */
function fcbf_native_save_section(string $slug, array $cookies, string $csrf, string $entry_id, array $inputs)
{
    $resp = wp_remote_post(FCBF_NATIVE_BASE . '/ajax/person_save_section', [
        'timeout'    => 30,
        'user-agent' => FCBF_USER_AGENT,
        'cookies'    => $cookies,
        'headers'    => fcbf_native_headers($slug, $csrf),
        'body'       => [
            'person_id'   => '0',
            'entry_id'    => $entry_id,
            'redirect'    => 'form',
            'inputs_json' => wp_json_encode($inputs),
        ],
    ]);
    if (is_wp_error($resp)) {
        fcbf_native_log('breeze_step2', $resp->get_error_message());
        return new WP_Error('breeze_step2', 'Breeze submission failed (step 2).', ['status' => 502]);
    }
    if (wp_remote_retrieve_response_code($resp) !== 200) {
        fcbf_native_log('breeze_step2_bad', 'HTTP ' . wp_remote_retrieve_response_code($resp));
        return new WP_Error('breeze_step2_bad', 'Breeze returned an error.', ['status' => 502]);
    }
    return true;
}

/** Verify the Cloudflare Turnstile token when the feature is enabled. */
function fcbf_native_verify_turnstile(array $params)
{
    if (!fcbf_native_turnstile_enabled()) {
        return true;
    }
    $token = (string) ($params['cf-turnstile-response'] ?? '');
    if ($token === '') {
        return new WP_Error('turnstile', 'Please complete the verification challenge.', ['status' => 400]);
    }
    $resp = wp_remote_post(FCBF_TURNSTILE_VERIFY, [
        'timeout' => 15,
        'body'    => [
            'secret'   => fcbf_native_turnstile_secret(),
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ],
    ]);
    if (is_wp_error($resp)) {
        fcbf_native_log('turnstile_unreachable', $resp->get_error_message());
        return new WP_Error('turnstile', 'Could not verify the challenge. Please try again.', ['status' => 502]);
    }
    $body = json_decode((string) wp_remote_retrieve_body($resp), true);
    if (empty($body['success'])) {
        return new WP_Error('turnstile', 'Verification failed. Please try again.', ['status' => 400]);
    }
    return true;
}

function fcbf_native_random_string(int $length): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $max      = strlen($alphabet) - 1;
    $out      = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}
