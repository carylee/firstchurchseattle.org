<?php
/**
 * The resolver: assemble the carousel as an ordered list of feed items from the
 * three live sources and project each to the slides pipeline's contract.
 *
 * Each item is a SUPERSET of @church/service-model's Announcement
 * (id/title/body/when/ctaUrl/ctaText/image/preserviceOnly) so the existing
 * composeDeck()/announcementCards() path renders event/info/qr_callout/divider
 * cards with no slide-side change. It additionally carries an explicit `layout`
 * (+ prompt/details/backgroundColor) so a richer consumer can render intro and
 * feature faithfully — those two layouts can't be recovered from shape alone
 * (announcementCards only emits event/info/qr_callout/divider). See the design
 * doc §2 and §10.1.
 *
 * The events + announcements sources (and the pure item/text/layout/when
 * helpers) now live in the firstchurch-happenings plugin (the spine). This file
 * keeps the evergreen carousel_card source and composes cards → events → news,
 * applies the curated deck (deck.php) or the auto-assembled default, and filters
 * preservice/postservice. REQUIRES firstchurch-happenings active.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is the firstchurch-happenings spine available? The carousel composes its feed
 * from the spine's event/announcement sources and shared item/text helpers, so
 * without it we degrade to an empty feed rather than fataling the public
 * /carousel endpoint (the main plugin file warns about this in wp-admin).
 */
function fccar_spine_active(): bool {
	return function_exists( 'happenings_resolve' );
}

/**
 * Resolve the carousel to an ordered array of feed items.
 *
 * @param array $args { variant?: 'preservice'|'postservice', weeks?: int, days?: int }
 * @return array<int,array<string,mixed>>
 */
function fccar_resolve( array $args = array() ): array {
	if ( ! fccar_spine_active() ) {
		return array();
	}
	$variant = ( isset( $args['variant'] ) && 'postservice' === $args['variant'] ) ? 'postservice' : 'preservice';
	$weeks   = max( 1, min( 52, (int) ( $args['weeks'] ?? FCCAR_DEFAULT_WEEKS ) ) );
	$days    = max( 1, min( 365, (int) ( $args['days'] ?? FCCAR_DEFAULT_DAYS ) ) );

	// A saved deck (curation screen) is the source of truth when present;
	// otherwise fall back to the auto-assembled default. `null` means never
	// curated; an empty array means curated-to-empty (honor it).
	$deck = function_exists( 'fccar_get_deck' ) ? fccar_get_deck() : null;
	if ( is_array( $deck ) ) {
		$items = fccar_resolve_from_deck( $deck );
	} else {
		// Auto-assembly skips the weekly rhythms (event-kinds.md): the lobby
		// screen needn't tell people already in the building that Sunday
		// Worship exists. A curated deck can still pin one by id.
		$items = array_merge(
			fccar_evergreen_items(),
			happenings_event_items( $weeks, array( 'event', 'group' ) ),
			happenings_news_items( $days )
		);
	}

	// Postservice drops preservice-only cards (mirrors slides' selectCards()).
	if ( 'postservice' === $variant ) {
		$items = array_values( array_filter( $items, static function ( $it ) {
			return empty( $it['preserviceOnly'] );
		} ) );
	}

	return $items;
}

/** The auto-assembled default deck (evergreen → events+groups → news), default windows. */
function fccar_autodeck_items(): array {
	if ( ! fccar_spine_active() ) {
		return array();
	}
	return array_merge(
		fccar_evergreen_items(),
		happenings_event_items( FCCAR_DEFAULT_WEEKS, array( 'event', 'group' ) ),
		happenings_news_items( FCCAR_DEFAULT_DAYS )
	);
}

/**
 * A generous candidate pool for the curation screen's "available" list.
 * Deliberately every kind — auto-assembly skips rhythms, but staff curating a
 * deck may still want to pin one (e.g. a "join us Sundays" slide).
 */
function fccar_candidate_pool(): array {
	if ( ! fccar_spine_active() ) {
		return array();
	}
	return array_merge(
		fccar_evergreen_items(),
		happenings_event_items( 26 ),
		happenings_news_items( 90 )
	);
}

/**
 * Project a single candidate by its feed id. carousel_card ids resolve here
 * (the carousel owns that source); event-/announcement- delegate to the spine.
 * Returns null if the referenced post is missing, the wrong type, or unpublished.
 */
function fccar_item_by_id( string $id ): ?array {
	if ( ! fccar_spine_active() ) {
		return null;
	}
	if ( 0 === strpos( $id, 'card-' ) ) {
		$p = get_post( (int) substr( $id, 5 ) );
		return ( $p && FCCAR_CPT === $p->post_type && 'publish' === $p->post_status ) ? fccar_card_to_item( $p ) : null;
	}
	// event-/announcement- live in the firstchurch-happenings spine.
	return happenings_item_by_id( $id );
}

/* ---- Source 1: evergreen carousel_card posts ---- */

function fccar_evergreen_items(): array {
	$q = new WP_Query( array(
		'post_type'      => FCCAR_CPT,
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'no_found_rows'  => true,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
	) );
	return array_map( 'fccar_card_to_item', $q->posts );
}

function fccar_card_to_item( WP_Post $post ): array {
	$layout = (string) get_post_meta( $post->ID, FCCAR_META_LAYOUT, true ) ?: 'info';
	$prompt = happenings_text( get_post_meta( $post->ID, FCCAR_META_PROMPT, true ) );
	$body   = happenings_text( get_post_meta( $post->ID, FCCAR_META_BODY, true ) );

	return happenings_item( array(
		'id'              => 'card-' . $post->ID,
		'source'          => 'card',
		'layout'          => $layout,
		'title'           => happenings_text( get_the_title( $post ) ),
		// qr_callout authors its text in `prompt`; the Announcement contract
		// only has `body`, so fold prompt → body for the shape-detect path
		// while also exposing it explicitly.
		'body'            => 'qr_callout' === $layout ? $prompt : $body,
		'prompt'          => $prompt,
		'details'         => happenings_text( get_post_meta( $post->ID, FCCAR_META_DETAILS, true ) ),
		'ctaUrl'          => (string) get_post_meta( $post->ID, FCCAR_META_QR, true ),
		'backgroundColor' => (string) get_post_meta( $post->ID, FCCAR_META_BGCOLOR, true ),
		'image'           => (string) get_the_post_thumbnail_url( $post, 'full' ),
		'preserviceOnly'  => (bool) get_post_meta( $post->ID, FCCAR_META_PRESVC, true ),
	) );
}

/* ---- Curate admin date helpers (carousel-local; the readiness strip in
 * deck.php uses these). The feed's own date/text formatting lives in the
 * firstchurch-happenings spine. ---- */

/** Compact human date label ("Jun 26") for a Y-m-d string; '' if unparseable. */
function fccar_short_date( string $ymd ): string {
	$ts = '' !== $ymd ? strtotime( $ymd ) : false;
	return $ts ? date_i18n( 'M j', $ts ) : '';
}

/** Is this Y-m-d date strictly before today (date-only)? Unparseable → false. */
function fccar_is_past_date( string $ymd, ?string $today = null ): bool {
	$ts = '' !== $ymd ? strtotime( $ymd ) : false;
	if ( ! $ts ) {
		return false;
	}
	$today_ts = strtotime( $today ?: current_time( 'Y-m-d' ) );
	return strtotime( date( 'Y-m-d', $ts ) ) < strtotime( date( 'Y-m-d', $today_ts ) );
}
