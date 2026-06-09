<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms;

/**
 * Turns a Breeze `/api/forms/list_form_entries` payload into clean intake
 * records, joined against a field-id→label map built from `list_form_fields`.
 *
 * The network fetch (the thin, credentialed edge) and the storage (the
 * fc_intake CPT) live in inc/intake-reader.php; this is the pure transform so
 * it can be tested without a key, a server, or a database — mirroring `Sync`.
 *
 * Breeze keys each entry's answers by *field id* (`response[<field_id>]`), so a
 * raw entry is meaningless without the form's field definitions. `field_map()`
 * builds `id => {label,type}` from a `list_form_fields` payload (the same
 * endpoint the descriptions sync already calls), and `normalize()` joins the
 * two into readable {label,value} pairs plus an extracted contact block.
 *
 * Record shape:
 *   [
 *     'entry_id'   => string,
 *     'created_on' => string,
 *     'contact'    => ['name' => string, 'email' => string, 'phone' => string],
 *     'responses'  => array<int,array{label:string,value:string}>,
 *     'title'      => string,
 *   ]
 *
 * Defensive by design: the exact Breeze response shape (assoc name/address
 * objects, list-valued checkboxes, whether `details=1` embeds field defs) is
 * confirmed against a live entry during rollout, so unknown shapes degrade to a
 * flattened string rather than throwing.
 */
final class Entries
{
    /** Field types that are instructional chrome, not answers — never surfaced as a Q&A row. */
    private const INSTRUCTIONAL = ['paragraph', 'header', 'section'];

    /** Breeze field types that identify the submitter's contact details. */
    private const NAME_TYPES  = ['name'];
    private const EMAIL_TYPES = ['email', 'email_address'];
    private const PHONE_TYPES = ['phone', 'phone_number'];

    /**
     * Label substrings that mark a single_line field as an email/phone capture.
     * Breeze types both as `single_line`, so the field type alone doesn't tell
     * us — these forms label them "Email"/"Phone", so the label does.
     */
    private const EMAIL_LABELS = ['email', 'e-mail'];
    private const PHONE_LABELS = ['phone', 'mobile', 'cell'];

    /** Bookkeeping keys Breeze rides along on a name object — never part of the name. */
    private const NAME_JUNK_KEYS = ['id', 'oid', 'person_id', 'created_on'];

    /**
     * Parse a raw list_form_entries response body into entry rows.
     *
     * @return array<int,array<string,mixed>>|null null signals an unparseable
     *         body (decode failure or non-array) so the caller treats it as a
     *         failed fetch rather than "zero entries" — same contract as
     *         Sync::from_json.
     */
    public static function from_json(string $json): ?array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Build a field-id → {label, type, options} map from a list_form_fields payload.
     *
     * Entry responses are keyed by Breeze's `field_id` (e.g. "2147341367"), NOT
     * the field-definition row `id` (e.g. "75906239") — those are different
     * numbers. Key on `field_id` so the join with an entry's `response` actually
     * matches; fall back to `id` only for payloads that omit `field_id`.
     *
     * Choice fields (multiple_choice/checkbox/dropdown) answer with only an
     * option id in their `{value}` payload, so we also fold in the field's
     * `options` as an `option_id => label` map for normalize() to resolve.
     *
     * @param array<int,array<string,mixed>> $fieldsPayload
     * @return array<string,array{label:string,type:string,options:array<string,string>}>
     */
    public static function field_map(array $fieldsPayload): array
    {
        $map = [];
        foreach ($fieldsPayload as $f) {
            if (!is_array($f)) {
                continue;
            }
            $id = trim((string) ($f['field_id'] ?? $f['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            // The field's prompt may carry HTML/entities (same cleanup as
            // Sync::lead_description) — tags → spaces, decode, collapse.
            $label = preg_replace('/<[^>]+>/', ' ', (string) ($f['name'] ?? ''));
            $label = html_entity_decode((string) $label, ENT_QUOTES, 'UTF-8');
            $label = trim((string) preg_replace('/\s+/', ' ', $label));

            // option_id → label, for turning a choice answer's bare option id
            // (Breeze sends {"value":"972","name":null}) into its readable text.
            $options = [];
            if (isset($f['options']) && is_array($f['options'])) {
                foreach ($f['options'] as $opt) {
                    if (!is_array($opt)) {
                        continue;
                    }
                    $oid = trim((string) ($opt['option_id'] ?? $opt['id'] ?? ''));
                    if ($oid === '') {
                        continue;
                    }
                    $options[$oid] = trim((string) ($opt['name'] ?? ''));
                }
            }

            $map[$id] = [
                'label'   => $label,
                'type'    => strtolower(trim((string) ($f['field_type'] ?? ''))),
                'options' => $options,
            ];
        }

        return $map;
    }

    /**
     * Join entries with their field map into clean intake records.
     *
     * @param array<int,array<string,mixed>>                  $rawEntries Decoded list_form_entries data.
     * @param array<string,array{label:string,type:string}>  $fieldMap   From field_map().
     * @param string                                          $formName   For the title fallback.
     * @return array<int,array{entry_id:string,created_on:string,contact:array{name:string,email:string,phone:string},responses:array<int,array{label:string,value:string}>,title:string}>
     */
    public static function normalize(array $rawEntries, array $fieldMap, string $formName = ''): array
    {
        $records = [];

        foreach ($rawEntries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entryId = trim((string) ($entry['id'] ?? ''));
            if ($entryId === '') {
                continue;
            }

            $response  = isset($entry['response']) && is_array($entry['response']) ? $entry['response'] : [];
            $contact   = ['name' => '', 'email' => '', 'phone' => ''];
            $responses = [];

            foreach ($response as $fieldId => $value) {
                $meta    = $fieldMap[(string) $fieldId] ?? ['label' => (string) $fieldId, 'type' => '', 'options' => []];
                $type    = $meta['type'];
                $options = $meta['options'] ?? [];
                $lcLabel = strtolower($meta['label']);

                if (in_array($type, self::NAME_TYPES, true)) {
                    $name = self::format_name($value);
                    if ($name !== '') {
                        $contact['name'] = $name;
                    }
                    continue;
                }
                // Instructional chrome is dropped before label-based contact
                // sniffing, so a "…email, and website publicity" paragraph can't
                // be mistaken for the submitter's email field.
                if (in_array($type, self::INSTRUCTIONAL, true)) {
                    continue;
                }
                // Email/phone come back as field_type single_line on these forms,
                // so type alone won't catch them — fall back to the field label.
                if ($contact['email'] === '' && (in_array($type, self::EMAIL_TYPES, true) || self::label_matches($lcLabel, self::EMAIL_LABELS))) {
                    $contact['email'] = self::flatten_value($value, $options);
                    continue;
                }
                if ($contact['phone'] === '' && (in_array($type, self::PHONE_TYPES, true) || self::label_matches($lcLabel, self::PHONE_LABELS))) {
                    $contact['phone'] = self::flatten_value($value, $options);
                    continue;
                }

                $flat = self::flatten_value($value, $options);
                if ($flat === '') {
                    continue;
                }
                $responses[] = [
                    'label' => $meta['label'] !== '' ? $meta['label'] : (string) $fieldId,
                    'value' => $flat,
                ];
            }

            $records[] = [
                'entry_id'   => $entryId,
                'created_on' => trim((string) ($entry['created_on'] ?? '')),
                'contact'    => $contact,
                'responses'  => $responses,
                'title'      => self::derive_title($responses, $contact, $formName, trim((string) ($entry['created_on'] ?? ''))),
            ];
        }

        return $records;
    }

    /**
     * Flatten a Breeze answer value to a single trimmed string. Strings pass
     * through; numbers stringify; arrays join their non-empty parts with ", ".
     *
     * Choice answers arrive as `{"value":"<option_id>","name":null}` (single) or
     * a list of those (checkbox). When `$options` (option_id → label, from
     * field_map) is supplied we resolve the option id to its readable label;
     * otherwise we fall back to any embedded `name`, then the bare id.
     *
     * @param mixed                 $value
     * @param array<string,string>  $options option_id → label for the field.
     */
    public static function flatten_value($value, array $options = []): string
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            // A single choice object: {value: <option_id>, name: <label|null>}.
            if (array_key_exists('value', $value) && !is_array($value['value'])) {
                $optId = trim((string) $value['value']);
                if ($optId === '') {
                    return '';
                }
                $name = isset($value['name']) ? trim((string) $value['name']) : '';
                if ($name !== '') {
                    return $name;
                }
                return $options[$optId] ?? $optId;
            }
            // Otherwise a list (checkbox) or assoc object — flatten each part.
            $parts = [];
            foreach ($value as $v) {
                $s = self::flatten_value($v, $options);
                if ($s !== '') {
                    $parts[] = $s;
                }
            }
            return implode(', ', $parts);
        }

        return '';
    }

    /** True if $lcLabel (already lowercased) contains any of the given substrings. */
    private static function label_matches(string $lcLabel, array $needles): bool
    {
        if ($lcLabel === '') {
            return false;
        }
        foreach ($needles as $needle) {
            if (strpos($lcLabel, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Format a Breeze name value. A name field is an assoc object that, on the
     * live API, looks like `{id, oid, first_name, last_name, created_on}` — so
     * we drop the bookkeeping keys and join the name parts in conventional order
     * ("Elisabeth Ellis", not "21193406, 57833, Elisabeth, Ellis, …"). Falls
     * back to flatten_value for a plain string.
     *
     * @param mixed $value
     */
    private static function format_name($value): string
    {
        if (is_array($value)) {
            $keyed = array_change_key_case($value, CASE_LOWER);
            foreach (self::NAME_JUNK_KEYS as $junk) {
                unset($keyed[$junk]);
            }
            // Accept both the short (`first`/`last`) and live (`first_name`/
            // `last_name`) key spellings.
            $order = ['title', 'prefix', 'first', 'first_name', 'middle', 'middle_name', 'last', 'last_name', 'maiden', 'suffix'];
            $parts = [];
            if (array_intersect($order, array_keys($keyed))) {
                foreach ($order as $k) {
                    if (!array_key_exists($k, $keyed)) {
                        continue;
                    }
                    $s = self::flatten_value($keyed[$k]);
                    if ($s !== '') {
                        $parts[] = $s;
                    }
                }
            } else {
                foreach ($keyed as $v) {
                    $s = self::flatten_value($v);
                    if ($s !== '') {
                        $parts[] = $s;
                    }
                }
            }
            return trim((string) preg_replace('/\s+/', ' ', implode(' ', $parts)));
        }

        return self::flatten_value($value);
    }

    /**
     * Pick a human title for the intake item: the answer to an event-name /
     * title / subject field if one exists, else "<Form> — <submitter> — <date>".
     *
     * @param array<int,array{label:string,value:string}> $responses
     * @param array{name:string,email:string,phone:string} $contact
     */
    private static function derive_title(array $responses, array $contact, string $formName, string $createdOn): string
    {
        foreach ($responses as $row) {
            $label = strtolower($row['label']);
            if (
                strpos($label, 'event name') !== false
                || strpos($label, 'name of event') !== false
                || strpos($label, 'title') !== false
                || strpos($label, 'subject') !== false
                || strpos($label, 'name of the event') !== false
            ) {
                if ($row['value'] !== '') {
                    return $row['value'];
                }
            }
        }

        $who  = $contact['name'] !== '' ? $contact['name'] : 'submission';
        $date = $createdOn !== '' ? substr($createdOn, 0, 10) : '';
        $bits = array_filter([$formName !== '' ? $formName : 'Intake', $who, $date], static fn ($s) => $s !== '');

        return implode(' — ', $bits);
    }
}
