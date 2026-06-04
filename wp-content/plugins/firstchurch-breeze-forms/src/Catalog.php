<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms;

/**
 * Optional id→slug convenience.
 *
 * Authors usually know a form by its public slug, but sometimes only have the
 * numeric form id from Breeze. This resolves an id to a slug using a baked map
 * (committed at data/forms.php, regenerated offline from the Breeze catalog).
 * The map is injected so this stays a pure, file-IO-free function under test;
 * the plugin edge loads data/forms.php and passes it in.
 *
 * Slugs are NOT trusted here — the caller still runs them through Url::for_slug.
 */
final class Catalog
{
    /**
     * @param array<string,string> $map id => slug
     */
    public static function slug_for_id(string $id, array $map): ?string
    {
        return $map[$id] ?? null;
    }
}
