<?php
/**
 * The spine feed: upcoming events + recent announcements, as one ordered
 * Happening[] (events first, then news). Evergreen carousel cards are
 * intentionally NOT here — they are lobby-screen furniture owned by
 * firstchurch-carousel, which composes them in locally.
 *
 * @package FirstChurch\Happenings
 */

use FirstChurch\Happenings\Id;
use FirstChurch\Happenings\Kind;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param array{weeks?:int,days?:int} $args
 * @return array<int,array<string,mixed>>
 */
function happenings_resolve(array $args = []): array
{
    $weeks = max(1, min(52, (int) ($args['weeks'] ?? HAPPENINGS_DEFAULT_WEEKS)));
    $days  = max(1, min(365, (int) ($args['days'] ?? HAPPENINGS_DEFAULT_DAYS)));

    return array_merge(
        happenings_event_items($weeks),
        happenings_news_items($days)
    );
}

/**
 * Resolve ONE named section of the feed for a surface — the shared curation lens
 * behind both the /engage `firstchurch/happenings` block and the e-news. Every
 * surface that shows a "Featured / Upcoming / Recent" section calls this, so they
 * all show the same slice: no surface re-implements the switch, the
 * exclude-featured de-dup, or the count cap. Rendering (web card vs. email card)
 * stays per-surface; this returns the ordered, capped Happening[] only.
 *
 * Sections and kinds (ops/docs/event-kinds.md): 'events' is the date-sorted
 * rail of TIME-BOUND happenings only — the standing weekly rhythms and the
 * monthly groups live in their own sections ('rhythms', 'groups') so their
 * always-imminent next occurrences can't monopolize the rail's slots.
 *
 * @param string $section          'featured' | 'events' | 'rhythms' | 'groups' | 'announcements'
 * @param int    $count            Max items to return.
 * @param int    $weeks            Event look-ahead (featured/events/rhythms/groups).
 * @param int    $days             Announcement look-back.
 * @param bool   $exclude_featured Drop items already promoted into Featured (so a
 *                                 Featured block + an Events block don't double).
 * @return array<int,array<string,mixed>>
 */
function happenings_section_items(
    string $section,
    int $count = 3,
    int $weeks = HAPPENINGS_DEFAULT_WEEKS,
    int $days = HAPPENINGS_DEFAULT_DAYS,
    bool $exclude_featured = false
): array {
    $count = max(1, $count);
    $weeks = max(1, $weeks);
    $days  = max(1, $days);

    switch ($section) {
        case 'events':
            $items = happenings_event_items($weeks, [Kind::EVENT]);
            break;
        case 'rhythms':
            $items = happenings_rhythm_items($weeks);
            break;
        case 'groups':
            $items = happenings_event_items($weeks, [Kind::GROUP]);
            break;
        case 'announcements':
            $items = happenings_news_items($days);
            break;
        case 'featured':
        default:
            $section = 'featured';
            $items   = happenings_featured($count, $weeks);
            break;
    }

    // A Happening's `weight` is non-empty only when > 0 — i.e. it's in the
    // Featured set. Filter before the slice so the list still fills to `count`.
    // Featured is the source of that set, so the toggle is a no-op there.
    if ($exclude_featured && 'featured' !== $section) {
        $items = array_values(array_filter($items, static fn ($it) => empty($it['weight'])));
    }

    return array_slice($items, 0, $count);
}

/**
 * The standing weekly rhythms, ordered by time of day — NOT by date: every
 * rhythm's next occurrence is within the week, so date order is meaningless.
 * Time-of-day order makes the "Every Sunday" strip read as the day actually
 * runs: breakfast → centering prayer → worship → evening study. Items without
 * a clock time (date-only `start`) sort first; ties fall back to title.
 */
function happenings_rhythm_items(int $weeks): array
{
    $items = happenings_event_items($weeks, [Kind::RHYTHM]);
    $tod   = static fn (array $it): string => substr((string) ($it['start'] ?? ''), 11, 8);
    usort(
        $items,
        static fn (array $a, array $b): int =>
            strcmp($tod($a), $tod($b)) ?: strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''))
    );

    return $items;
}

/**
 * Project a single candidate by feed id ("event-7" / "announcement-9"), loading
 * that specific post directly so deck references resolve regardless of any
 * look-ahead window. Returns null if the post is missing, the wrong type,
 * unpublished, or (for news) out of the Announcements category. Card ids are not
 * handled here — the carousel owns the carousel_card source.
 */
function happenings_item_by_id(string $id): ?array
{
    $parsed = Id::parse($id);
    if ($parsed === null) {
        return null;
    }

    $post = get_post($parsed['num']);
    if (!$post || $post->post_status !== 'publish') {
        return null;
    }

    if ($parsed['prefix'] === 'event') {
        // Both event backends share the `event-<postId>` id space (post ids are
        // unique across types). Dispatch by the post's actual type so deck pins
        // and the event single page resolve CTC and lean fce events alike.
        if ($post->post_type === 'ctc_event') {
            return happenings_event_to_item($post);
        }
        if ($post->post_type === 'fce_event' && function_exists('fce_event_item')) {
            return fce_event_item($post->ID);
        }
        return null;
    }
    if ($parsed['prefix'] === 'announcement') {
        return ($post->post_type === 'post' && has_category(happenings_announce_cat_id(), $post))
            ? happenings_news_to_item($post)
            : null;
    }

    return null;
}
