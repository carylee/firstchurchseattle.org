<?php
/**
 * The two spine sources, projected to the Happening contract:
 *   1. upcoming events     (ctc_event)
 *   2. recent announcements (posts in the Announcements category)
 *
 * Field order in each item is deliberate — it is the JSON key order the carousel
 * and other consumers have always seen. Lifted verbatim from the carousel's
 * resolver (firstchurch-carousel/inc/resolve.php) so the feed is unchanged.
 *
 * @package FirstChurch\Happenings
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Meta-query OR-clause for the announcement lifecycle: keep posts whose
 * fcs_expires is unset, empty, or today-or-later. The single source of truth for
 * the expiry rule (ops/docs/happenings.md §4).
 *
 * @return array<string,mixed>
 */
function happenings_unexpired_clause(): array
{
    $today = current_time('Y-m-d');
    return [
        'relation' => 'OR',
        ['key' => 'fcs_expires', 'compare' => 'NOT EXISTS'],
        ['key' => 'fcs_expires', 'value' => '', 'compare' => '='],
        ['key' => 'fcs_expires', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'],
    ];
}

/* ---- Source 1: upcoming events ---- */

/**
 * Upcoming events, merged from Church Theme Content (ctc_event) and the lean
 * firstchurch-events backend (fce_event), date-sorted. This is the read-both
 * transition: until events are migrated off CTC, both surface. fce_event_items()
 * is guarded — when that plugin is inactive the spine reads CTC only. No dedup
 * needed: migration unpublishes the CTC original when it moves an event.
 */
function happenings_event_items(int $weeks): array
{
    $from = current_time('Y-m-d');
    $to   = gmdate('Y-m-d', strtotime("+{$weeks} weeks", strtotime($from)));

    $q = new WP_Query([
        'post_type'      => 'ctc_event',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'no_found_rows'  => true,
        'meta_query'     => [
            'start' => ['key' => '_ctc_event_start_date'],
            ['key' => '_ctc_event_start_date', 'value' => $from, 'compare' => '>=', 'type' => 'DATE'],
            ['key' => '_ctc_event_start_date', 'value' => $to, 'compare' => '<=', 'type' => 'DATE'],
        ],
        'orderby'        => ['start' => 'ASC'],
    ]);

    $items = array_map('happenings_event_to_item', $q->posts);

    if (function_exists('fce_event_items')) {
        $items = array_merge($items, fce_event_items($weeks));
        usort($items, static fn ($a, $b) => strcmp($a['date'] ?? '', $b['date'] ?? ''));
    }

    return $items;
}

/**
 * Every event OCCURRENCE in [$from, $to] (Y-m-d, inclusive) — the calendar grid's
 * source, where a weekly event must land on each of its dates. This is the
 * occurrence-expanded counterpart to happenings_event_items() (which collapses
 * each event to its next date for a list).
 *
 * fce_event occurrences are fully recurrence-expanded (firstchurch-events owns the
 * RRULE). CTC events are placed on their single start_date only — CTC recurrence
 * is NOT expanded here: the live recurring set was migrated to fce_event and CTC
 * is being decommissioned (ops/docs/events-migration.md). When that plugin is
 * inactive the spine returns the CTC-on-start-date set alone.
 *
 * @return array<int,array<string,mixed>>
 */
function happenings_event_occurrences(string $from, string $to): array
{
    $q = new WP_Query([
        'post_type'      => 'ctc_event',
        'post_status'    => 'publish',
        'posts_per_page' => 100,
        'no_found_rows'  => true,
        'meta_query'     => [
            'start' => ['key' => '_ctc_event_start_date'],
            ['key' => '_ctc_event_start_date', 'value' => $from, 'compare' => '>=', 'type' => 'DATE'],
            ['key' => '_ctc_event_start_date', 'value' => $to, 'compare' => '<=', 'type' => 'DATE'],
        ],
        'orderby'        => ['start' => 'ASC'],
    ]);

    $items = array_map('happenings_event_to_item', $q->posts);

    if (function_exists('fce_event_occurrences')) {
        $items = array_merge($items, fce_event_occurrences(new DateTimeImmutable($from), new DateTimeImmutable($to)));
        usort($items, static fn ($a, $b) => strcmp($a['date'] ?? '', $b['date'] ?? ''));
    }

    return $items;
}

function happenings_event_to_item(WP_Post $post): array
{
    $reg    = (string) get_post_meta($post->ID, '_ctc_event_registration_url', true);
    $weight = (int) get_post_meta($post->ID, 'fcs_weight', true);

    return happenings_item([
        'id'     => 'event-' . $post->ID,
        'source' => 'event',
        'layout' => 'event',
        'title'  => happenings_text(get_the_title($post)),
        'date'   => (string) get_post_meta($post->ID, '_ctc_event_start_date', true),
        'when'   => happenings_event_when($post->ID),
        'ctaUrl' => $reg ?: (string) get_permalink($post),
        'image'  => (string) get_the_post_thumbnail_url($post, 'full'),
        // Prominence, so a weighted event can join the Featured row (Phase 4).
        // Present only when > 0 (Item drops 0), matching the announcement source.
        'weight' => $weight > 0 ? $weight : '',
        'url'    => (string) get_permalink($post),
    ]);
}

/* ---- Source 2: recent announcements (Announcements-category posts) ---- */

function happenings_announce_cat_id(): int
{
    $term = get_term_by('slug', HAPPENINGS_ANNOUNCE_SLUG, 'category');
    return $term ? (int) $term->term_id : 0;
}

function happenings_news_items(int $days): array
{
    $cat = happenings_announce_cat_id();
    if (!$cat) {
        return [];
    }

    // Lifecycle (ops/docs/happenings.md §4): drop expired posts; posts without
    // the key — older announcements — never expire.
    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'cat'            => $cat,
        'posts_per_page' => 30,
        'no_found_rows'  => true,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'date_query'     => [['after' => $days . ' days ago']],
        'meta_query'     => happenings_unexpired_clause(),
    ]);

    // Weight floats important items up; equal weights keep the query's date-desc
    // order (PHP 8 sort is stable). Sorted in PHP so posts lacking the meta key
    // (weight 0) aren't dropped by an INNER JOIN order-by.
    $posts   = $q->posts;
    $weights = happenings_weight_map($posts);
    usort($posts, static fn ($a, $b) => $weights[$b->ID] <=> $weights[$a->ID]);

    return array_map('happenings_news_to_item', $posts);
}

/**
 * The Featured row: the most prominent Happenings, curated by weight — spanning
 * BOTH announcements and upcoming events (Phase 4). Either source can be promoted
 * (set fcs_weight > 0); a weighted event joins the row carrying its real when-line
 * and Register CTA, so a dated happening no longer has to masquerade as a
 * date-suppressed announcement.
 *
 * Pool:
 *   - announcements/posts — any published post with fcs_weight > 0, honoring
 *     expiry. NOT recency-bound and not limited to the Announcements category
 *     (the weight meta is post-wide); a promoted post stays featured until its
 *     weight is cleared or it expires.
 *   - events — weighted upcoming events (CTC + fce) within the look-ahead window;
 *     past events drop out by virtue of the window.
 *
 * Ranking (weight desc, then date) is the pure Featured::rank(); this function
 * only assembles the candidate pool.
 */
function happenings_featured(int $count, int $weeks = HAPPENINGS_DEFAULT_WEEKS): array
{
    return \FirstChurch\Happenings\Featured::rank(
        array_merge(happenings_featured_events($weeks), happenings_featured_posts()),
        $count
    );
}

/** Weighted upcoming events (both backends), projected — the event side of Featured. */
function happenings_featured_events(int $weeks): array
{
    return array_values(array_filter(
        happenings_event_items($weeks),
        static fn (array $it): bool => (int) ($it['weight'] ?? 0) > 0
    ));
}

/** Weighted, unexpired posts, projected — the announcement side of Featured. */
function happenings_featured_posts(): array
{
    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'no_found_rows'  => true,
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => 'fcs_weight', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'],
            happenings_unexpired_clause(),
        ],
    ]);

    return array_map('happenings_news_to_item', $q->posts);
}

/** Read fcs_weight once per post into an [id => weight] map for sort comparators. */
function happenings_weight_map(array $posts): array
{
    $map = [];
    foreach ($posts as $p) {
        $map[$p->ID] = (int) get_post_meta($p->ID, 'fcs_weight', true);
    }
    return $map;
}

function happenings_news_to_item(WP_Post $post): array
{
    $body = happenings_text(wp_strip_all_tags(
        has_excerpt($post) ? get_the_excerpt($post) : wp_trim_words($post->post_content, 40)
    ));
    $title  = happenings_text(get_the_title($post));
    $cta    = (string) get_post_meta($post->ID, 'fcs_cta_url', true);
    $weight = (int) get_post_meta($post->ID, 'fcs_weight', true);

    return happenings_item([
        'id'      => 'announcement-' . $post->ID,
        'source'  => 'announcement',
        'layout'  => happenings_detect_layout($title, $body, '', $cta),
        'title'   => $title,
        'body'    => $body,
        'ctaUrl'  => $cta,
        'ctaText' => happenings_text(get_post_meta($post->ID, 'fcs_cta_text', true)),
        'image'   => (string) get_the_post_thumbnail_url($post, 'full'),
        'weight'  => $weight > 0 ? $weight : '',
        'url'     => (string) get_permalink($post),
        'date'    => (string) get_post_time('Y-m-d', false, $post),
    ]);
}
