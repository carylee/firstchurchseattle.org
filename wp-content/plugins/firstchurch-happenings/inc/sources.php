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

/* ---- Source 1: upcoming events (ctc_event) ---- */

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

    return array_map('happenings_event_to_item', $q->posts);
}

function happenings_event_to_item(WP_Post $post): array
{
    $reg = (string) get_post_meta($post->ID, '_ctc_event_registration_url', true);

    return happenings_item([
        'id'     => 'event-' . $post->ID,
        'source' => 'event',
        'layout' => 'event',
        'title'  => happenings_text(get_the_title($post)),
        'when'   => happenings_event_when($post->ID),
        'ctaUrl' => $reg ?: (string) get_permalink($post),
        'image'  => (string) get_the_post_thumbnail_url($post, 'full'),
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

    // Lifecycle (ops/docs/happenings.md §4): drop expired posts (fcs_expires set
    // and in the past). Posts without the key — older announcements — never expire.
    $today = current_time('Y-m-d');

    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'cat'            => $cat,
        'posts_per_page' => 30,
        'no_found_rows'  => true,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'date_query'     => [['after' => $days . ' days ago']],
        'meta_query'     => [
            'relation' => 'OR',
            ['key' => 'fcs_expires', 'compare' => 'NOT EXISTS'],
            ['key' => 'fcs_expires', 'value' => '', 'compare' => '='],
            ['key' => 'fcs_expires', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'],
        ],
    ]);

    // Weight floats important items up; equal weights keep the query's date-desc
    // order (PHP 8 sort is stable). Sorted in PHP so posts lacking the meta key
    // (weight 0) aren't dropped by an INNER JOIN order-by.
    $posts = $q->posts;
    usort($posts, static function ($a, $b) {
        return (int) get_post_meta($b->ID, 'fcs_weight', true) <=> (int) get_post_meta($a->ID, 'fcs_weight', true);
    });

    return array_map('happenings_news_to_item', $posts);
}

/**
 * Posts promoted to the Featured row: any published post with fcs_weight > 0,
 * honoring expiry, sorted by weight (desc) then date (desc), capped. Featured is
 * curated by weight — it is NOT recency-bound and not limited to the
 * Announcements category (the weight meta is post-wide). A promoted post stays
 * featured until its weight is cleared or it expires. Projected like news.
 */
function happenings_featured_news(int $count): array
{
    $today = current_time('Y-m-d');

    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'no_found_rows'  => true,
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => 'fcs_weight', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'],
            [
                'relation' => 'OR',
                ['key' => 'fcs_expires', 'compare' => 'NOT EXISTS'],
                ['key' => 'fcs_expires', 'value' => '', 'compare' => '='],
                ['key' => 'fcs_expires', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'],
            ],
        ],
    ]);

    $posts = $q->posts;
    usort($posts, static function ($a, $b) {
        $wa = (int) get_post_meta($a->ID, 'fcs_weight', true);
        $wb = (int) get_post_meta($b->ID, 'fcs_weight', true);
        return ($wb <=> $wa) ?: strcmp($b->post_date, $a->post_date);
    });

    return array_slice(array_map('happenings_news_to_item', $posts), 0, max(0, $count));
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
