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
        return $post->post_type === 'ctc_event' ? happenings_event_to_item($post) : null;
    }
    if ($parsed['prefix'] === 'announcement') {
        return ($post->post_type === 'post' && has_category(happenings_announce_cat_id(), $post))
            ? happenings_news_to_item($post)
            : null;
    }

    return null;
}
