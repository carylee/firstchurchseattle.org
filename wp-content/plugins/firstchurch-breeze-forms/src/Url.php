<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms;

/**
 * Builds (and validates) public Breeze form URLs.
 *
 * Every public Breeze form lives at `<base>/form/<slug>`, where the slug is
 * either an opaque token (e.g. `603d6c56`) or a custom human-readable slug an
 * admin set (e.g. `event-request`). Validation here is the security boundary: a
 * slug becomes part of a URL we hand to the browser, so we accept only
 * `[A-Za-z0-9_-]` — enough for both slug styles — and reject anything that could
 * inject a path segment, query, fragment, or scheme (`/ . : space "` …).
 */
final class Url
{
    public const BASE = 'https://firstchurchseattle.breezechms.com/form/';

    /**
     * @return string|null Canonical form URL, or null if the slug is invalid.
     */
    public static function for_slug(string $slug): ?string
    {
        $slug = trim($slug);

        if ($slug === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $slug)) {
            return null;
        }

        return self::BASE . $slug;
    }
}
