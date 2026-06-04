<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms;

/**
 * Resolves which form list to use: last-good synced data, else the baked seed.
 *
 * The synced list (cached in a WP option, refreshed from Breeze on a schedule)
 * is only written on a *successful* fetch, so a transient API failure can never
 * blank it. When it's empty/absent — first boot, or before the first sync —
 * we fall back to the committed data/forms.json seed so the picker and id=
 * resolution work with zero configuration.
 *
 * This is the pure coalesce; the WP option read/write is the thin edge.
 */
final class Store
{
    /**
     * @param array<int,array<string,string>>|null $synced
     * @param array<int,array<string,string>>      $baked
     * @return array<int,array<string,string>>
     */
    public static function resolve(?array $synced, array $baked): array
    {
        return !empty($synced) ? $synced : $baked;
    }
}
