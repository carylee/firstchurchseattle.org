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
use FirstChurch\Happenings\EventWhen;

if (!defined('ABSPATH')) {
    exit;
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

/**
 * Read a CTC event's date/recurrence/time meta and format the human "when"
 * string (e.g. "Sundays at 10:30 am · Sanctuary"). The formatting itself lives
 * in EventWhen (pure, unit-tested); this only extracts the meta.
 */
function happenings_event_when(int $post_id): string
{
    return EventWhen::format([
        'start'           => (string) get_post_meta($post_id, '_ctc_event_start_date', true),
        'freq'            => (string) get_post_meta($post_id, '_ctc_event_recurrence', true),
        'venue'           => happenings_text(get_post_meta($post_id, '_ctc_event_venue', true)),
        'start_time'      => (string) get_post_meta($post_id, '_ctc_event_start_time', true),
        'time_text'       => happenings_text(get_post_meta($post_id, '_ctc_event_time', true)),
        'weekly_interval' => (int) get_post_meta($post_id, '_ctc_event_recurrence_weekly_interval', true),
        'weekly_days'     => (string) get_post_meta($post_id, '_ctc_event_recurrence_weekly_day', true),
        'monthly_type'    => (string) get_post_meta($post_id, '_ctc_event_recurrence_monthly_type', true),
        'monthly_week'    => (string) get_post_meta($post_id, '_ctc_event_recurrence_monthly_week', true),
    ]);
}
