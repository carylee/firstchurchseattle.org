<?php
/**
 * Plugin Name: First Church Connection Card
 * Description: Server-side bridge that posts the Check-in & Connection Card to Breeze (form 320238).
 * Version:     0.3.0
 * Author:      First Church Seattle
 */

if (!defined('ABSPATH')) {
    exit;
}

const FCC_VERSION     = '0.3.0';
const FCC_BREEZE_BASE = 'https://firstchurchseattle.breezechms.com';
const FCC_FORM_ID     = '320238';
const FCC_FORM_SLUG   = '603d6c';
const FCC_USER_AGENT  = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36';

// Cloudflare Turnstile (optional). Define FCC_TURNSTILE_SITEKEY +
// FCC_TURNSTILE_SECRET in wp-config.php to switch it on; left undefined the
// widget never renders and verification is skipped, so the form works as-is
// with no secret in the repo. The site already fronts on Cloudflare.
const FCC_TURNSTILE_VERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

// Pure form contract + mapping/validation (unit-tested outside WordPress).
require_once __DIR__ . '/inc/form.php';

/**
 * The configured Turnstile sitekey, or '' when the feature is off.
 */
function fcc_turnstile_sitekey(): string {
    return defined('FCC_TURNSTILE_SITEKEY') ? (string) FCC_TURNSTILE_SITEKEY : '';
}

function fcc_turnstile_secret(): string {
    return defined('FCC_TURNSTILE_SECRET') ? (string) FCC_TURNSTILE_SECRET : '';
}

function fcc_turnstile_enabled(): bool {
    return fcc_turnstile_sitekey() !== '' && fcc_turnstile_secret() !== '';
}

/**
 * Log a Breeze submission failure for ops visibility (the failure paths are
 * otherwise silent — the visitor just sees a generic error). Fires an action so
 * a monitor can hook it, and writes to the PHP error log under WP_DEBUG.
 */
function fcc_log(string $stage, string $detail): void {
    do_action('fcc_breeze_failure', $stage, $detail);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("[connection-card] {$stage}: {$detail}");
    }
}

add_action('rest_api_init', function () {
    register_rest_route('firstchurch/v1', '/connection-card', [
        'methods'             => 'POST',
        'callback'            => 'fcc_submit',
        // Nonce ensures the request came from a page on this WP site that
        // rendered the form. Anonymous users still pass — WP issues nonces to
        // logged-out visitors via the rest cookie. Real anti-spam still relies
        // on the honeypot + rate limit (+ optionally CAPTCHA).
        'permission_callback' => function (WP_REST_Request $req) {
            $nonce = $req->get_header('X-WP-Nonce');
            return $nonce && wp_verify_nonce($nonce, 'wp_rest');
        },
    ]);
});

add_shortcode('firstchurch_connection_card', 'fcc_render_shortcode');

function fcc_render_shortcode($atts = []): string {
    $atts = shortcode_atts(['heading' => 'Check-in & Connection Card'], $atts, 'firstchurch_connection_card');

    fcc_enqueue_assets();

    $endpoint = esc_url_raw(rest_url('firstchurch/v1/connection-card'));
    $nonce    = wp_create_nonce('wp_rest');

    ob_start();
    ?>
    <form class="fcc-form"
          data-endpoint="<?php echo esc_attr($endpoint); ?>"
          data-nonce="<?php echo esc_attr($nonce); ?>"
          novalidate>
        <?php if (!empty($atts['heading'])) : ?>
            <h2 class="fcc-form__heading"><?php echo esc_html($atts['heading']); ?></h2>
        <?php endif; ?>

        <p class="fcc-form__intro">
            <strong>Note for in-person worship attendees:</strong><br>
            <em>Please take a moment to fill out the contact information below.</em>
        </p>

        <fieldset class="fcc-fieldset fcc-fieldset--inline" data-required>
            <legend>I attended today's service <span class="fcc-required" aria-hidden="true">*</span></legend>
            <label class="fcc-radio"><input type="radio" name="attended" value="in-person" required> In-person</label>
            <label class="fcc-radio"><input type="radio" name="attended" value="online"> Online</label>
        </fieldset>

        <div class="fcc-row fcc-row--split">
            <div class="fcc-field">
                <label for="fcc-first">First name <span class="fcc-required" aria-hidden="true">*</span></label>
                <input id="fcc-first" name="first_name" type="text" required autocomplete="given-name">
            </div>
            <div class="fcc-field">
                <label for="fcc-last">Last name <span class="fcc-required" aria-hidden="true">*</span></label>
                <input id="fcc-last" name="last_name" type="text" required autocomplete="family-name">
            </div>
        </div>

        <div class="fcc-field">
            <label for="fcc-email">Email <span class="fcc-required" aria-hidden="true">*</span></label>
            <input id="fcc-email" name="email" type="email" required autocomplete="email">
        </div>

        <label class="fcc-checkbox">
            <input type="checkbox" name="newsletter" value="1">
            I'd like to receive the weekly E-Newsletter
        </label>

        <div class="fcc-field">
            <label for="fcc-phone">Phone <span class="fcc-optional">(optional)</span></label>
            <input id="fcc-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel">
        </div>

        <details class="fcc-details">
            <summary>Address <span class="fcc-optional">(optional)</span></summary>
            <div class="fcc-field">
                <label for="fcc-street">Street</label>
                <input id="fcc-street" name="address[street]" type="text" autocomplete="street-address">
            </div>
            <div class="fcc-row fcc-row--address">
                <div class="fcc-field fcc-field--grow">
                    <label for="fcc-city">City</label>
                    <input id="fcc-city" name="address[city]" type="text" autocomplete="address-level2">
                </div>
                <div class="fcc-field">
                    <label for="fcc-state">State</label>
                    <input id="fcc-state" name="address[state]" type="text" maxlength="2" autocomplete="address-level1" size="3">
                </div>
                <div class="fcc-field">
                    <label for="fcc-zip">Zip</label>
                    <input id="fcc-zip" name="address[zip]" type="text" autocomplete="postal-code" inputmode="numeric" size="6">
                </div>
            </div>
        </details>

        <label class="fcc-checkbox">
            <input type="checkbox" name="change_of_info" value="1">
            This is a change of contact information
        </label>

        <fieldset class="fcc-fieldset" data-required>
            <legend>I am a <span class="fcc-required" aria-hidden="true">*</span></legend>
            <label class="fcc-radio"><input type="radio" name="i_am_a" value="first-time"> First-time visitor</label>
            <label class="fcc-radio"><input type="radio" name="i_am_a" value="second-time"> Second-time visitor</label>
            <label class="fcc-radio"><input type="radio" name="i_am_a" value="regular"> Regular attendee</label>
            <label class="fcc-radio"><input type="radio" name="i_am_a" value="member"> Member</label>
        </fieldset>

        <div class="fcc-field">
            <label for="fcc-heard-from">For visitors — I heard about First Church from:</label>
            <textarea id="fcc-heard-from" name="heard_from" rows="2"></textarea>
        </div>

        <fieldset class="fcc-fieldset">
            <legend>I would like to learn more about</legend>
            <?php foreach (fcc_learn_more_choices() as $value => $label) : ?>
                <label class="fcc-checkbox">
                    <input type="checkbox" name="learn_more[]" value="<?php echo esc_attr($value); ?>">
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <fieldset class="fcc-fieldset fcc-fieldset--inline">
            <legend>I would like a phone call or email from a pastor</legend>
            <label class="fcc-checkbox"><input type="checkbox" name="pastor_contact[]" value="254"> Phone call</label>
            <label class="fcc-checkbox"><input type="checkbox" name="pastor_contact[]" value="255"> Email</label>
        </fieldset>

        <div class="fcc-field">
            <label for="fcc-comments">Comments</label>
            <textarea id="fcc-comments" name="comments" rows="3"></textarea>
        </div>

        <div class="fcc-honeypot" aria-hidden="true">
            <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <?php if (fcc_turnstile_enabled()) : ?>
            <div class="cf-turnstile" data-sitekey="<?php echo esc_attr(fcc_turnstile_sitekey()); ?>"></div>
        <?php endif; ?>

        <div class="fcc-form__status" role="status" aria-live="polite"></div>

        <button class="fcc-submit maranatha-button" type="submit">Submit</button>
    </form>
    <?php
    return (string) ob_get_clean();
}

function fcc_enqueue_assets(): void {
    $base = plugin_dir_url(__FILE__);
    wp_enqueue_style(
        'firstchurch-connection-card',
        $base . 'assets/connection-card.css',
        [],
        FCC_VERSION
    );
    wp_enqueue_script(
        'firstchurch-connection-card',
        $base . 'assets/connection-card.js',
        [],
        FCC_VERSION,
        true
    );
    if (fcc_turnstile_enabled()) {
        wp_enqueue_script(
            'cloudflare-turnstile',
            'https://challenges.cloudflare.com/turnstile/v0/api.js',
            [],
            null,
            true
        );
    }
}

function fcc_submit(WP_REST_Request $request) {
    $params = $request->get_json_params() ?: $request->get_body_params();

    // Honeypot — bots fill in hidden fields; humans don't. Pretend success.
    if (fcc_is_honeypot($params)) {
        return new WP_REST_Response(['ok' => true], 200);
    }

    $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = 'fcc_rl_' . md5($ip);
    $hits = (int) get_transient($key);
    if ($hits >= 5) {
        return new WP_Error('rate_limited', 'Too many submissions from this address. Try again later.', ['status' => 429]);
    }
    set_transient($key, $hits + 1, 10 * MINUTE_IN_SECONDS);

    $opts   = fcc_options();
    $errors = fcc_validate($params, $opts);
    if ($errors) {
        return new WP_Error('validation', implode(' ', $errors), ['status' => 400]);
    }

    $verified = fcc_verify_turnstile($params);
    if (is_wp_error($verified)) {
        return $verified;
    }

    $first    = trim((string) ($params['first_name'] ?? ''));
    $last     = trim((string) ($params['last_name']  ?? ''));
    $email    = sanitize_email((string) ($params['email'] ?? ''));
    $attended = (string) ($params['attended'] ?? '');
    $i_am_a   = (string) ($params['i_am_a']   ?? '');

    $session_cookies = fcc_seed_session();
    if (is_wp_error($session_cookies)) {
        return $session_cookies;
    }

    $csrf  = 'js' . fcc_random_string(50);
    $session_cookies[] = new WP_Http_Cookie([
        'name'   => 'x-csrf-token',
        'value'  => $csrf,
        'domain' => 'firstchurchseattle.breezechms.com',
        'path'   => '/',
    ]);

    $entry_id = fcc_mint_entry_id($session_cookies, $csrf, $first, $last, $email);
    if (is_wp_error($entry_id)) {
        return $entry_id;
    }

    $inputs = fcc_build_inputs($params, $first, $last, $email, $attended, $i_am_a, $opts);

    $saved = fcc_save_section($session_cookies, $csrf, $entry_id, $inputs);
    if (is_wp_error($saved)) {
        return $saved;
    }

    return new WP_REST_Response(['ok' => true, 'entry_id' => $entry_id], 200);
}

function fcc_seed_session() {
    $resp = wp_remote_get(FCC_BREEZE_BASE . '/form/' . FCC_FORM_SLUG, [
        'timeout'    => 30,
        'user-agent' => FCC_USER_AGENT,
    ]);
    if (is_wp_error($resp)) {
        fcc_log('breeze_unreachable', $resp->get_error_message());
        return new WP_Error('breeze_unreachable', 'Could not reach Breeze.', ['status' => 502]);
    }
    return wp_remote_retrieve_cookies($resp);
}

function fcc_breeze_headers(string $csrf): array {
    return [
        'X-CSRF-Token'        => $csrf,
        'X-SETUP-RAN'         => 'true',
        'X-SECURITY-VERSION'  => 'beta',
        'X-Requested-With'    => 'XMLHttpRequest',
        'Referer'             => FCC_BREEZE_BASE . '/form/' . FCC_FORM_SLUG,
    ];
}

function fcc_mint_entry_id(array $cookies, string $csrf, string $first, string $last, string $email) {
    $resp = wp_remote_post(FCC_BREEZE_BASE . '/ajax/new_entry_id', [
        'timeout'    => 30,
        'user-agent' => FCC_USER_AGENT,
        'cookies'    => $cookies,
        'headers'    => fcc_breeze_headers($csrf),
        'body'       => [
            'form_id'    => FCC_FORM_ID,
            'token'      => '',
            'processor'  => 'stripe',
            'amount'     => '',
            'first_name' => $first,
            'last_name'  => $last,
            'email'      => $email,
        ],
    ]);
    if (is_wp_error($resp)) {
        fcc_log('breeze_step1', $resp->get_error_message());
        return new WP_Error('breeze_step1', 'Breeze submission failed (step 1).', ['status' => 502]);
    }
    $body = trim(wp_remote_retrieve_body($resp));
    if (!ctype_digit($body)) {
        fcc_log('breeze_step1_bad', substr($body, 0, 200));
        return new WP_Error('breeze_step1_bad', 'Unexpected Breeze response: ' . substr($body, 0, 200), ['status' => 502]);
    }
    return $body;
}

function fcc_save_section(array $cookies, string $csrf, string $entry_id, array $inputs) {
    $resp = wp_remote_post(FCC_BREEZE_BASE . '/ajax/person_save_section', [
        'timeout'    => 30,
        'user-agent' => FCC_USER_AGENT,
        'cookies'    => $cookies,
        'headers'    => fcc_breeze_headers($csrf),
        'body'       => [
            'person_id'   => '0',
            'entry_id'    => $entry_id,
            'redirect'    => 'form',
            'inputs_json' => wp_json_encode($inputs),
        ],
    ]);
    if (is_wp_error($resp)) {
        fcc_log('breeze_step2', $resp->get_error_message());
        return new WP_Error('breeze_step2', 'Breeze submission failed (step 2).', ['status' => 502]);
    }
    if (wp_remote_retrieve_response_code($resp) !== 200) {
        fcc_log('breeze_step2_bad', 'HTTP ' . wp_remote_retrieve_response_code($resp));
        return new WP_Error('breeze_step2_bad', 'Breeze returned ' . wp_remote_retrieve_response_code($resp), ['status' => 502]);
    }
    return true;
}

/**
 * Verify the Cloudflare Turnstile token, when the feature is enabled. A no-op
 * (returns true) when no sitekey/secret is configured.
 */
function fcc_verify_turnstile(array $params) {
    if (!fcc_turnstile_enabled()) {
        return true;
    }
    $token = (string) ($params['cf-turnstile-response'] ?? '');
    if ($token === '') {
        return new WP_Error('turnstile', 'Please complete the verification challenge.', ['status' => 400]);
    }
    $resp = wp_remote_post(FCC_TURNSTILE_VERIFY, [
        'timeout' => 15,
        'body'    => [
            'secret'   => fcc_turnstile_secret(),
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ],
    ]);
    if (is_wp_error($resp)) {
        fcc_log('turnstile_unreachable', $resp->get_error_message());
        return new WP_Error('turnstile', 'Could not verify the challenge. Please try again.', ['status' => 502]);
    }
    $body = json_decode((string) wp_remote_retrieve_body($resp), true);
    if (empty($body['success'])) {
        return new WP_Error('turnstile', 'Verification failed. Please try again.', ['status' => 400]);
    }
    return true;
}

function fcc_random_string(int $length): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $max      = strlen($alphabet) - 1;
    $out      = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}
