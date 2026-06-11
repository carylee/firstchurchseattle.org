<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms;

/**
 * Mode 3 — native in-theme rendering + submission of a Breeze form.
 *
 * Modes 1 & 2 (button/embed) are pure markup pointing at the public Breeze
 * form, so they need no field knowledge. Mode 3 renders the form *in our own
 * theme* and posts it server-side to Breeze's private AJAX endpoints — the
 * approach pioneered by the firstchurch-connection-card plugin. To do that we
 * need each form's field contract (the numeric Breeze field ids, types, and
 * option-value ids). Those live in a baked map (data/native-forms.json), keyed
 * by Breeze form id; this class is the pure logic over one such contract:
 *
 *   - resolve()  — pick the contract for an id/slug (null when none is native)
 *   - render()   — emit the escaped in-theme <form> markup
 *   - validate() — required-field checks (human-readable error strings)
 *   - inputs()   — project submitted params → Breeze's `inputs` array
 *   - is_honeypot() / client_fields() — anti-spam + the JS serialization hint
 *
 * No HTTP and no WordPress state (only the escaping/sanitizing primitives the
 * test bootstrap shims), so every branch is unit-tested outside WordPress. The
 * REST route and the cookie→new_entry_id→person_save_section bridge that
 * actually talks to Breeze live in inc/native-submit.php (the WP/HTTP shell).
 */
final class Native
{
    /**
     * Pick the native contract matching an id (preferred) or slug.
     *
     * @param array<string,array<string,mixed>> $map id => contract (baked).
     * @return array<string,mixed>|null The contract with its 'id' folded in.
     */
    public static function resolve(string $id, string $slug, array $map): ?array
    {
        $id   = trim($id);
        $slug = trim($slug);

        if ($id !== '' && isset($map[$id]) && is_array($map[$id])) {
            return ['id' => $id] + $map[$id];
        }

        if ($slug !== '') {
            foreach ($map as $form_id => $contract) {
                if (is_array($contract) && (string) ($contract['slug'] ?? '') === $slug) {
                    return ['id' => (string) $form_id] + $contract;
                }
            }
        }

        return null;
    }

    /**
     * The field list a contract declares (each an array with at least
     * key/type/field_id), filtered to well-formed entries.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function fields(array $contract): array
    {
        $out = [];
        foreach ((array) ($contract['fields'] ?? []) as $field) {
            if (is_array($field) && isset($field['key'], $field['type'], $field['field_id'])) {
                $out[] = $field;
            }
        }
        return $out;
    }

    /**
     * A compact [{key,type}] descriptor the front-end script uses to serialize
     * the form (so client and server agree on each field's shape).
     *
     * @return array<int,array{key:string,type:string}>
     */
    public static function client_fields(array $contract): array
    {
        return array_map(
            static fn (array $f) => ['key' => (string) $f['key'], 'type' => (string) $f['type']],
            self::fields($contract)
        );
    }

    /**
     * Honeypot — bots fill hidden fields ('website'/'url'); humans leave them
     * empty. Mirrors the connection-card test.
     */
    public static function is_honeypot(array $params): bool
    {
        return !empty($params['website']) || !empty($params['url']);
    }

    /**
     * Validate required fields. Returns human-readable error strings (empty when
     * the submission is valid).
     *
     * @return array<int,string>
     */
    public static function validate(array $contract, array $params): array
    {
        $errors = [];
        foreach (self::fields($contract) as $field) {
            if (empty($field['required'])) {
                continue;
            }
            $value = $params[$field['key']] ?? null;
            $label = (string) ($field['label'] ?? $field['key']);

            switch ((string) $field['type']) {
                case 'name':
                    $first = is_array($value) ? trim((string) ($value['first'] ?? '')) : '';
                    $last  = is_array($value) ? trim((string) ($value['last']  ?? '')) : '';
                    if ($first === '' || $last === '') {
                        $errors[] = 'Please provide your first and last name.';
                    }
                    break;

                case 'email':
                    $email = sanitize_email((string) (is_array($value) ? '' : $value));
                    if ($email === '' || !is_email($email)) {
                        $errors[] = 'Please provide a valid email address.';
                    }
                    break;

                case 'checkbox':
                case 'radio':
                    if (self::chosen_options($field, $value) === []) {
                        $errors[] = "Please answer “{$label}”.";
                    }
                    break;

                case 'address':
                    $street = is_array($value) ? trim((string) ($value['street'] ?? '')) : '';
                    if ($street === '') {
                        $errors[] = "Please provide your {$label}.";
                    }
                    break;

                default: // text, phone, textarea, …
                    if (is_array($value) || trim((string) $value) === '') {
                        $errors[] = "Please provide {$label}.";
                    }
            }
        }
        return $errors;
    }

    /**
     * Project submitted params into Breeze's `inputs` array — the same shape the
     * connection-card builds, one entry per answered field (choice fields emit
     * one entry per selected option). Field order follows the contract.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function inputs(array $contract, array $params): array
    {
        $inputs = [];
        foreach (self::fields($contract) as $field) {
            $field_id = (string) $field['field_id'];
            $value    = $params[$field['key']] ?? null;

            switch ((string) $field['type']) {
                case 'name':
                    $first = is_array($value) ? trim((string) ($value['first'] ?? '')) : '';
                    $last  = is_array($value) ? trim((string) ($value['last']  ?? '')) : '';
                    if ($first === '' && $last === '') {
                        break;
                    }
                    // Breeze's save_person_meta() falls through the name parts,
                    // so value/part reflect the last input iterated — mirror it.
                    $inputs[] = [
                        'field_id'   => $field_id,
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
                    break;

                case 'email':
                    $email = sanitize_email((string) (is_array($value) ? '' : $value));
                    if ($email !== '') {
                        $inputs[] = ['field_id' => $field_id, 'response' => $email, 'field_type' => 'text'];
                    }
                    break;

                case 'textarea':
                    $text = is_array($value) ? '' : sanitize_textarea_field((string) $value);
                    if (trim($text) !== '') {
                        $inputs[] = ['field_id' => $field_id, 'response' => $text, 'field_type' => 'textarea'];
                    }
                    break;

                case 'checkbox':
                    foreach (self::chosen_options($field, $value) as $opt) {
                        $inputs[] = ['field_id' => $field_id, 'response' => $opt, 'field_type' => 'checkbox'];
                    }
                    break;

                case 'radio':
                    $chosen = self::chosen_options($field, $value);
                    if ($chosen !== []) {
                        $inputs[] = ['field_id' => $field_id, 'response' => $chosen[0], 'field_type' => 'radio'];
                    }
                    break;

                case 'address':
                    if (is_array($value)) {
                        $details = array_filter([
                            'street_address' => sanitize_text_field((string) ($value['street'] ?? '')),
                            'city'           => sanitize_text_field((string) ($value['city']   ?? '')),
                            'state'          => sanitize_text_field((string) ($value['state']  ?? '')),
                            'zip'            => sanitize_text_field((string) ($value['zip']    ?? '')),
                        ], 'strlen');
                        if ($details) {
                            $inputs[] = [
                                'field_id'   => $field_id,
                                'response'   => 'undefined',
                                'field_type' => 'address',
                                'details'    => $details,
                            ];
                        }
                    }
                    break;

                default: // text, phone
                    $text = is_array($value) ? '' : sanitize_text_field((string) $value);
                    if (trim($text) !== '') {
                        $inputs[] = ['field_id' => $field_id, 'response' => $text, 'field_type' => 'text'];
                    }
            }
        }
        return $inputs;
    }

    /**
     * The submitted option-values for a choice field, keeping only ids the
     * field actually offers (defends against a forged value posted directly).
     *
     * @return array<int,string>
     */
    private static function chosen_options(array $field, $value): array
    {
        $allowed = [];
        foreach ((array) ($field['options'] ?? []) as $opt) {
            if (is_array($opt) && isset($opt['value'])) {
                $allowed[] = (string) $opt['value'];
            }
        }
        $submitted = is_array($value) ? $value : ($value === null ? [] : [$value]);

        $out = [];
        foreach ($submitted as $v) {
            $v = (string) $v;
            if (in_array($v, $allowed, true) && !in_array($v, $out, true)) {
                $out[] = $v;
            }
        }
        return $out;
    }

    /**
     * Render the in-theme <form>. `$ctx` carries the WP-coupled bits the pure
     * core can't know: the REST endpoint, the nonce, an optional Turnstile
     * sitekey, and an optional heading override.
     *
     * @param array{endpoint:string,nonce:string,turnstile_sitekey?:string,heading?:string} $ctx
     */
    public static function render(array $contract, array $ctx): string
    {
        $id       = (string) ($contract['id'] ?? '');
        $heading  = trim((string) ($ctx['heading'] ?? '')) !== ''
            ? (string) $ctx['heading']
            : (string) ($contract['name'] ?? '');
        $intro    = (string) ($contract['intro'] ?? '');
        $success  = (string) ($contract['success'] ?? 'Thank you — your submission has been received.');
        $submit   = (string) ($contract['submit_label'] ?? 'Submit');
        $sitekey  = (string) ($ctx['turnstile_sitekey'] ?? '');

        $client_fields = wp_json_encode(self::client_fields($contract));

        $html  = '<form class="fcbf-native"'
            . ' data-endpoint="' . esc_attr((string) $ctx['endpoint']) . '"'
            . ' data-nonce="' . esc_attr((string) $ctx['nonce']) . '"'
            . ' data-success="' . esc_attr($success) . '"'
            . ' data-fields="' . esc_attr((string) $client_fields) . '"'
            . ' novalidate>';

        $html .= '<input type="hidden" name="form_id" value="' . esc_attr($id) . '">';

        if ($heading !== '') {
            $html .= '<h2 class="fcbf-native__heading">' . esc_html($heading) . '</h2>';
        }
        if ($intro !== '') {
            $html .= '<p class="fcbf-native__intro">' . esc_html($intro) . '</p>';
        }

        foreach (self::fields($contract) as $field) {
            $html .= self::render_field($field);
        }

        // Honeypot — visually hidden, off the tab order; bots fill it, humans don't.
        $html .= '<div class="fcbf-native__honeypot" aria-hidden="true">'
            . '<label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>'
            . '</div>';

        if ($sitekey !== '') {
            $html .= '<div class="cf-turnstile" data-sitekey="' . esc_attr($sitekey) . '"></div>';
        }

        $html .= '<div class="fcbf-native__status" role="status" aria-live="polite"></div>';
        $html .= '<button class="fcbf-native__submit maranatha-button" type="submit">' . esc_html($submit) . '</button>';
        $html .= '</form>';

        return $html;
    }

    /** Render one field by type. */
    private static function render_field(array $field): string
    {
        $key      = (string) $field['key'];
        $type     = (string) $field['type'];
        $label    = (string) ($field['label'] ?? '');
        $required = !empty($field['required']);
        $help     = (string) ($field['help'] ?? '');
        $req_mark = $required ? ' <span class="fcbf-required" aria-hidden="true">*</span>' : '';
        $opt_mark = $required ? '' : ' <span class="fcbf-optional">(optional)</span>';
        $reqd     = $required ? ' required' : '';

        switch ($type) {
            case 'name':
                return '<div class="fcbf-native__field fcbf-native__field--name">'
                    . '<span class="fcbf-native__label">' . esc_html($label) . $req_mark . '</span>'
                    . '<div class="fcbf-native__row">'
                    . '<input type="text" name="' . esc_attr($key) . '_first" placeholder="First name"'
                    . ' autocomplete="given-name"' . $reqd . '>'
                    . '<input type="text" name="' . esc_attr($key) . '_last" placeholder="Last name"'
                    . ' autocomplete="family-name"' . $reqd . '>'
                    . '</div>'
                    . self::help($help)
                    . '</div>';

            case 'email':
                return self::input_field($key, $label, 'email', $req_mark, $opt_mark, $reqd, 'email', $help);

            case 'phone':
                return self::input_field($key, $label, 'tel', $req_mark, $opt_mark, $reqd, 'tel', $help);

            case 'textarea':
                $rows = (int) ($field['rows'] ?? 4);
                return '<div class="fcbf-native__field">'
                    . '<label for="fcbf-' . esc_attr($key) . '">' . esc_html($label) . $req_mark . $opt_mark . '</label>'
                    . '<textarea id="fcbf-' . esc_attr($key) . '" name="' . esc_attr($key) . '"'
                    . ' rows="' . max(2, $rows) . '"' . $reqd . '></textarea>'
                    . self::help($help)
                    . '</div>';

            case 'checkbox':
            case 'radio':
                return self::choice_field($field, $type, $label, $req_mark, $required);

            case 'address':
                return self::address_field($key, $label, $req_mark, $opt_mark);

            default: // text
                return self::input_field($key, $label, 'text', $req_mark, $opt_mark, $reqd, '', $help);
        }
    }

    private static function input_field(
        string $key,
        string $label,
        string $input_type,
        string $req_mark,
        string $opt_mark,
        string $reqd,
        string $autocomplete,
        string $help
    ): string {
        $ac = $autocomplete !== '' ? ' autocomplete="' . esc_attr($autocomplete) . '"' : '';
        return '<div class="fcbf-native__field">'
            . '<label for="fcbf-' . esc_attr($key) . '">' . esc_html($label) . $req_mark . $opt_mark . '</label>'
            . '<input id="fcbf-' . esc_attr($key) . '" name="' . esc_attr($key) . '"'
            . ' type="' . esc_attr($input_type) . '"' . $ac . $reqd . '>'
            . self::help($help)
            . '</div>';
    }

    private static function choice_field(array $field, string $type, string $label, string $req_mark, bool $required): string
    {
        $key         = (string) $field['key'];
        $instruction = (string) ($field['instruction'] ?? '');
        $input_type  = $type === 'radio' ? 'radio' : 'checkbox';
        // A checkbox group posts as an array (key[]); a radio group posts one value (key).
        $name        = $type === 'radio' ? $key : $key . '[]';
        // The group's requiredness rides on the fieldset (the inputs can't carry
        // a single HTML `required` for a "pick at least one" group); the client
        // reads data-required to know to validate it.
        $req_attr    = $required ? ' data-required' : '';

        $html = '<fieldset class="fcbf-native__fieldset" data-key="' . esc_attr($key) . '"' . $req_attr . '>'
            . '<legend>' . esc_html($label) . $req_mark . '</legend>';
        if ($instruction !== '') {
            $html .= '<p class="fcbf-native__instruction">' . esc_html($instruction) . '</p>';
        }
        foreach ((array) ($field['options'] ?? []) as $opt) {
            if (!is_array($opt) || !isset($opt['value'])) {
                continue;
            }
            $html .= '<label class="fcbf-native__choice">'
                . '<input type="' . $input_type . '" name="' . esc_attr($name) . '"'
                . ' value="' . esc_attr((string) $opt['value']) . '"> '
                . esc_html((string) ($opt['label'] ?? $opt['value']))
                . '</label>';
        }
        return $html . '</fieldset>';
    }

    private static function address_field(string $key, string $label, string $req_mark, string $opt_mark): string
    {
        $part = static fn (string $p, string $ph, string $ac) =>
            '<input type="text" name="' . esc_attr($key . '_' . $p) . '" placeholder="' . esc_attr($ph) . '"'
            . ' autocomplete="' . esc_attr($ac) . '">';

        return '<div class="fcbf-native__field fcbf-native__field--address">'
            . '<span class="fcbf-native__label">' . esc_html($label) . $req_mark . $opt_mark . '</span>'
            . $part('street', 'Street', 'street-address')
            . '<div class="fcbf-native__row">'
            . $part('city', 'City', 'address-level2')
            . $part('state', 'State', 'address-level1')
            . $part('zip', 'Zip', 'postal-code')
            . '</div>'
            . '</div>';
    }

    private static function help(string $help): string
    {
        return $help !== '' ? '<small class="fcbf-native__help">' . esc_html($help) . '</small>' : '';
    }
}
