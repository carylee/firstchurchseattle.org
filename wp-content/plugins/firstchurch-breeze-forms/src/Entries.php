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
     * Build a field-id → {label, type} map from a list_form_fields payload.
     *
     * @param array<int,array<string,mixed>> $fieldsPayload
     * @return array<string,array{label:string,type:string}>
     */
    public static function field_map(array $fieldsPayload): array
    {
        $map = [];
        foreach ($fieldsPayload as $f) {
            if (!is_array($f)) {
                continue;
            }
            $id = trim((string) ($f['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            // The field's prompt may carry HTML/entities (same cleanup as
            // Sync::lead_description) — tags → spaces, decode, collapse.
            $label = preg_replace('/<[^>]+>/', ' ', (string) ($f['name'] ?? ''));
            $label = html_entity_decode((string) $label, ENT_QUOTES, 'UTF-8');
            $label = trim((string) preg_replace('/\s+/', ' ', $label));

            $map[$id] = [
                'label' => $label,
                'type'  => strtolower(trim((string) ($f['field_type'] ?? ''))),
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
                $meta  = $fieldMap[(string) $fieldId] ?? ['label' => (string) $fieldId, 'type' => ''];
                $type  = $meta['type'];

                if (in_array($type, self::NAME_TYPES, true)) {
                    $name = self::format_name($value);
                    if ($name !== '') {
                        $contact['name'] = $name;
                    }
                    continue;
                }
                if (in_array($type, self::EMAIL_TYPES, true) && $contact['email'] === '') {
                    $contact['email'] = self::flatten_value($value);
                    continue;
                }
                if (in_array($type, self::PHONE_TYPES, true) && $contact['phone'] === '') {
                    $contact['phone'] = self::flatten_value($value);
                    continue;
                }
                if (in_array($type, self::INSTRUCTIONAL, true)) {
                    continue;
                }

                $flat = self::flatten_value($value);
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
     * through; numbers stringify; arrays (assoc name/address objects or
     * list-valued checkboxes) join their non-empty parts with ", ".
     *
     * @param mixed $value
     */
    public static function flatten_value($value): string
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $v) {
                $s = self::flatten_value($v);
                if ($s !== '') {
                    $parts[] = $s;
                }
            }
            return implode(', ', $parts);
        }

        return '';
    }

    /**
     * Format a Breeze name value. A name field is often an assoc object
     * (`{first, last, ...}`) — join its parts with a space so we get
     * "Jane Doe", not "Jane, Doe". Falls back to flatten_value for a string.
     *
     * @param mixed $value
     */
    private static function format_name($value): string
    {
        if (is_array($value)) {
            $order = ['title', 'first', 'middle', 'last', 'suffix'];
            $parts = [];
            // Prefer the conventional name order when present; otherwise take the values as given.
            $keyed = array_change_key_case($value, CASE_LOWER);
            if (array_intersect($order, array_keys($keyed))) {
                foreach ($order as $k) {
                    $s = self::flatten_value($keyed[$k] ?? '');
                    if ($s !== '') {
                        $parts[] = $s;
                    }
                }
            } else {
                foreach ($value as $v) {
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
