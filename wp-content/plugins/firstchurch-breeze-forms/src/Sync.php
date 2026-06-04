<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms;

/**
 * Turns a Breeze `/api/forms/list_forms` payload into clean internal records.
 *
 * The network fetch (the thin, credentialed edge) lives elsewhere; this is the
 * pure transform so it can be tested without a key or a server. Every form's
 * slug is validated through Url so a malformed slug can never reach the page,
 * and forms missing an id or a usable slug are dropped rather than half-stored.
 *
 * Record shape: ['id' => string, 'slug' => string, 'name' => string, 'folder_id' => string]
 */
final class Sync
{
    /**
     * @param array<int,array<string,mixed>> $raw Decoded list_forms data.
     * @return array<int,array{id:string,slug:string,name:string,folder_id:string}>
     */
    public static function normalize(array $raw): array
    {
        $records = [];

        foreach ($raw as $form) {
            $id   = trim((string) ($form['id'] ?? ''));
            $slug = Url::for_slug((string) ($form['url_slug'] ?? ''));

            if ($id === '' || $slug === null) {
                continue;
            }

            $records[] = [
                'id'        => $id,
                'slug'      => trim((string) $form['url_slug']),
                'name'      => trim((string) ($form['name'] ?? '')),
                'folder_id' => trim((string) ($form['folder_id'] ?? '')),
            ];
        }

        usort($records, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $records;
    }

    /**
     * Parse a raw list_forms response body into records.
     *
     * @return array<int,array{id:string,slug:string,name:string,folder_id:string}>|null
     *         null signals an unparseable body (decode failure or non-array) so the
     *         caller can treat it as a failed sync rather than "zero forms".
     */
    public static function from_json(string $json): ?array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? self::normalize($decoded) : null;
    }
}
