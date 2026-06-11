<?php
/**
 * Procedural bridges from WordPress into the pure src/ projection classes, so
 * the WP glue here (and the carousel, which consumes them) calls plain function
 * names rather than reaching for the namespaced classes directly.
 *
 * @package FirstChurch\Happenings
 */

use FirstChurch\Happenings\Item;
use FirstChurch\Happenings\Layout;
use FirstChurch\Happenings\Text;
use FirstChurch\Happenings\CardView;

if (!defined('ABSPATH')) {
    exit;
}

/** Flatten a Happening item into the /engage card view-model. */
function happenings_card_view(array $item): array
{
    return CardView::fromHappening($item);
}

/** Build one feed item, dropping empty values. @param array<string,mixed> $fields */
function happenings_item(array $fields): array
{
    return Item::build($fields);
}

/** Decode WP entity-encoded text to plain UTF-8 and trim. */
function happenings_text($value): string
{
    return Text::clean($value);
}

/** Pick a card layout from an item's shape. */
function happenings_detect_layout(string $title, string $body, string $when, string $cta): string
{
    return Layout::detect($title, $body, $when, $cta);
}
