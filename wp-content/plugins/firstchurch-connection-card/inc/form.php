<?php
/**
 * Connection Card — form contract + pure mapping/validation.
 *
 * The dependency-light core of the plugin: the Breeze field-id constants, the
 * option maps, request validation, the honeypot test, and the params → Breeze
 * `inputs` projection. No HTTP and no WordPress state — so it is unit-tested
 * outside WordPress (the test bootstrap shims the handful of sanitize_* and
 * is_email helpers used here). The main plugin file is the WP/HTTP shell.
 *
 * @package FirstChurch\ConnectionCard
 */

if (!defined('ABSPATH')) {
    exit;
}

// Breeze form field ids (form 320238). These are the contract with Breeze —
// the OptionsTest guards them so a stray edit can't silently break submission.
const FCC_F_ATTENDED    = '2147340711';
const FCC_F_NAME        = '2147340458';
const FCC_F_EMAIL       = '2147340459';
const FCC_F_NEWSLETTER  = '2147340460';
const FCC_F_PHONE       = '2147340461';
const FCC_F_ADDRESS     = '2147340462';
const FCC_F_CHANGE_INFO = '2147340463';
const FCC_F_I_AM_A      = '2147340464';
const FCC_F_HEARD_FROM  = '2147340465';
const FCC_F_LEARN_MORE  = '2147340466';
const FCC_F_PASTOR      = '2147340467';
const FCC_F_COMMENTS    = '2147340468';

function fcc_options(): array {
    return [
        'attended'    => ['online' => '316', 'in-person' => '317'],
        'i_am_a'      => ['first-time' => '241', 'second-time' => '242', 'regular' => '243', 'member' => '244'],
        'newsletter'  => '239',
        'change_info' => '240',
        'learn_more'  => ['245','246','247','248','249','250','251','252','253','863'],
        'pastor'      => ['254','255'],
    ];
}

function fcc_learn_more_choices(): array {
    return [
        '245' => "First Church's mission and values",
        '246' => 'Becoming a church member',
        '247' => 'Children & Youth programming',
        '248' => 'Adult spiritual enrichment classes',
        '249' => "Vintners gatherings (20's & 30's fellowship)",
        '250' => 'Pride + Faith gatherings',
        '251' => 'Sanctuary Choir + Bell Choir',
        '252' => 'Pub Theology',
        '253' => 'Volunteering for Shared Breakfast',
        '863' => 'Church & Society / Social Justice Committee',
    ];
}

/**
 * Honeypot — bots fill hidden fields ('website'/'url'); humans leave them empty.
 */
function fcc_is_honeypot(array $params): bool {
    return !empty($params['website']) || !empty($params['url']);
}

/**
 * Validate the required fields. Returns a list of human-readable error strings
 * (empty when the submission is valid).
 */
function fcc_validate(array $params, array $opts): array {
    $first    = trim((string) ($params['first_name'] ?? ''));
    $last     = trim((string) ($params['last_name']  ?? ''));
    $email    = sanitize_email((string) ($params['email'] ?? ''));
    $attended = (string) ($params['attended'] ?? '');
    $i_am_a   = (string) ($params['i_am_a']   ?? '');

    $errors = [];
    if ($first === '' || $last === '')        $errors[] = 'Please provide your first and last name.';
    if ($email === '' || !is_email($email))   $errors[] = 'Please provide a valid email address.';
    if (!isset($opts['attended'][$attended])) $errors[] = 'Please choose Online or In-person.';
    if (!isset($opts['i_am_a'][$i_am_a]))     $errors[] = 'Please choose how you relate to First Church.';
    return $errors;
}

function fcc_build_inputs(array $params, string $first, string $last, string $email, string $attended, string $i_am_a, array $opts): array {
    $inputs = [];

    $inputs[] = [
        'field_id'   => FCC_F_ATTENDED,
        'response'   => $opts['attended'][$attended],
        'field_type' => 'radio',
    ];

    // The save_person_meta() switch falls through, so value/part reflect the
    // last name input the browser iterates -- mirror that here.
    $inputs[] = [
        'field_id'   => FCC_F_NAME,
        'response'   => 'undefined',
        'field_type' => 'name',
        'details'    => [
            'first_name' => $first,
            'last_name'  => $last,
            'value'      => $last,
            'part'       => 'last_name',
            'person_id'  => 0,
        ],
    ];

    $inputs[] = ['field_id' => FCC_F_EMAIL, 'response' => $email, 'field_type' => 'text'];

    if (!empty($params['newsletter'])) {
        $inputs[] = ['field_id' => FCC_F_NEWSLETTER, 'response' => $opts['newsletter'], 'field_type' => 'checkbox'];
    }

    $phone = trim((string) ($params['phone'] ?? ''));
    if ($phone !== '') {
        $inputs[] = ['field_id' => FCC_F_PHONE, 'response' => sanitize_text_field($phone), 'field_type' => 'text'];
    }

    $address = $params['address'] ?? null;
    if (is_array($address)) {
        $details = array_filter([
            'street_address' => sanitize_text_field((string) ($address['street'] ?? '')),
            'city'           => sanitize_text_field((string) ($address['city']   ?? '')),
            'state'          => sanitize_text_field((string) ($address['state']  ?? '')),
            'zip'            => sanitize_text_field((string) ($address['zip']    ?? '')),
        ], 'strlen');
        if ($details) {
            $inputs[] = [
                'field_id'   => FCC_F_ADDRESS,
                'response'   => 'undefined',
                'field_type' => 'address',
                'details'    => $details,
            ];
        }
    }

    if (!empty($params['change_of_info'])) {
        $inputs[] = ['field_id' => FCC_F_CHANGE_INFO, 'response' => $opts['change_info'], 'field_type' => 'checkbox'];
    }

    $inputs[] = ['field_id' => FCC_F_I_AM_A, 'response' => $opts['i_am_a'][$i_am_a], 'field_type' => 'checkbox'];

    $heard_from = trim((string) ($params['heard_from'] ?? ''));
    if ($heard_from !== '') {
        $inputs[] = ['field_id' => FCC_F_HEARD_FROM, 'response' => sanitize_textarea_field($heard_from), 'field_type' => 'textarea'];
    }

    foreach ((array) ($params['learn_more'] ?? []) as $opt) {
        if (in_array($opt, $opts['learn_more'], true)) {
            $inputs[] = ['field_id' => FCC_F_LEARN_MORE, 'response' => $opt, 'field_type' => 'checkbox'];
        }
    }

    foreach ((array) ($params['pastor_contact'] ?? []) as $opt) {
        if (in_array($opt, $opts['pastor'], true)) {
            $inputs[] = ['field_id' => FCC_F_PASTOR, 'response' => $opt, 'field_type' => 'checkbox'];
        }
    }

    $notes = fcc_merge_notes(
        (string) ($params['prayer_request'] ?? ''),
        (string) ($params['comments'] ?? '')
    );
    if ($notes !== '') {
        $inputs[] = ['field_id' => FCC_F_COMMENTS, 'response' => $notes, 'field_type' => 'textarea'];
    }

    return $inputs;
}

/**
 * Breeze's connection form has a single free-text "Comments" field, but the
 * card asks members for a prayer request and a general comment as two separate
 * inputs. Merge them into one block — labeled only when both are present, so a
 * lone comment stays byte-identical to the pre-prayer-field behavior — so the
 * pastoral team sees both halves in the one field Breeze gives us.
 */
function fcc_merge_notes(string $prayer, string $comments): string {
    $prayer   = trim($prayer);
    $comments = trim($comments);

    $parts = [];
    if ($prayer !== '') {
        $parts[] = "Prayer request:\n" . sanitize_textarea_field($prayer);
    }
    if ($comments !== '') {
        $parts[] = $prayer !== ''
            ? "Comments:\n" . sanitize_textarea_field($comments)
            : sanitize_textarea_field($comments);
    }

    return implode("\n\n", $parts);
}
