<?php

declare(strict_types=1);

namespace FirstChurch\Happenings;

/**
 * Picks a card layout from an item's shape — kept in lockstep with the slides
 * app's announcementCards() so the explicit `layout` we emit agrees with what
 * downstream shape-detection would choose.
 */
final class Layout
{
    public static function detect(string $title, string $body, string $when, string $cta): string
    {
        if ($when !== '') {
            return 'event';
        }
        if ($title !== '' && $body !== '') {
            return 'info';
        }
        if ($cta !== '' && ($body !== '' || $title !== '')) {
            return 'qr_callout';
        }
        if ($title !== '' && $body === '') {
            return 'divider';
        }

        return 'info';
    }
}
