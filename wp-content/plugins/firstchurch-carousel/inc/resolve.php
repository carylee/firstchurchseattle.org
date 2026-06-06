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
 * Phase 2 produces the *auto-assembled default* deck (design doc §5.1):
 * evergreen cards (by menu_order) → upcoming events (by date) → recent
 * announcements (by date). Curation (pick/order/decorate via references +
 * overrides) layers on top in a later phase.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the carousel to an ordered array of feed items.
 *
 * @param array $args { variant?: 'preservice'|'postservice', weeks?: int, days?: int }
 * @return array<int,array<string,mixed>>
 */
function fccar_resolve( array $args = array() ): array {
	$variant = ( isset( $args['variant'] ) && 'postservice' === $args['variant'] ) ? 'postservice' : 'preservice';
	$weeks   = max( 1, min( 52, (int) ( $args['weeks'] ?? FCCAR_DEFAULT_WEEKS ) ) );
	$days    = max( 1, min( 365, (int) ( $args['days'] ?? FCCAR_DEFAULT_DAYS ) ) );

	$items = array_merge(
		fccar_evergreen_items(),
		fccar_event_items( $weeks ),
		fccar_news_items( $days )
	);

	// Postservice drops preservice-only cards (mirrors slides' selectCards()).
	if ( 'postservice' === $variant ) {
		$items = array_values( array_filter( $items, static function ( $it ) {
			return empty( $it['preserviceOnly'] );
		} ) );
	}

	return $items;
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
	$prompt = (string) get_post_meta( $post->ID, FCCAR_META_PROMPT, true );
	$body   = (string) get_post_meta( $post->ID, FCCAR_META_BODY, true );

	return fccar_item( array(
		'id'              => 'card-' . $post->ID,
		'source'          => 'card',
		'layout'          => $layout,
		'title'           => get_the_title( $post ),
		// qr_callout authors its text in `prompt`; the Announcement contract
		// only has `body`, so fold prompt → body for the shape-detect path
		// while also exposing it explicitly.
		'body'            => 'qr_callout' === $layout ? $prompt : $body,
		'prompt'          => $prompt,
		'details'         => (string) get_post_meta( $post->ID, FCCAR_META_DETAILS, true ),
		'ctaUrl'          => (string) get_post_meta( $post->ID, FCCAR_META_QR, true ),
		'backgroundColor' => (string) get_post_meta( $post->ID, FCCAR_META_BGCOLOR, true ),
		'image'           => (string) get_the_post_thumbnail_url( $post, 'full' ),
		'preserviceOnly'  => (bool) get_post_meta( $post->ID, FCCAR_META_PRESVC, true ),
	) );
}

/* ---- Source 2: upcoming events (ctc_event) ---- */

function fccar_event_items( int $weeks ): array {
	$from = current_time( 'Y-m-d' );
	$to   = gmdate( 'Y-m-d', strtotime( "+{$weeks} weeks", strtotime( $from ) ) );

	$q = new WP_Query( array(
		'post_type'      => 'ctc_event',
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'no_found_rows'  => true,
		'meta_query'     => array(
			'start' => array( 'key' => '_ctc_event_start_date' ),
			array( 'key' => '_ctc_event_start_date', 'value' => $from, 'compare' => '>=', 'type' => 'DATE' ),
			array( 'key' => '_ctc_event_start_date', 'value' => $to, 'compare' => '<=', 'type' => 'DATE' ),
		),
		'orderby'        => array( 'start' => 'ASC' ),
	) );
	return array_map( 'fccar_event_to_item', $q->posts );
}

function fccar_event_to_item( WP_Post $post ): array {
	$reg = (string) get_post_meta( $post->ID, '_ctc_event_registration_url', true );
	return fccar_item( array(
		'id'     => 'event-' . $post->ID,
		'source' => 'event',
		'layout' => 'event',
		'title'  => get_the_title( $post ),
		'when'   => fccar_event_when( $post->ID ),
		'ctaUrl' => $reg ?: (string) get_permalink( $post ),
		'image'  => (string) get_the_post_thumbnail_url( $post, 'full' ),
	) );
}

/* ---- Source 3: recent announcements (Announcements-category posts) ---- */

function fccar_news_items( int $days ): array {
	$cat = fccar_announce_cat_id();
	if ( ! $cat ) {
		return array();
	}
	$q = new WP_Query( array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'cat'            => $cat,
		'posts_per_page' => 30,
		'no_found_rows'  => true,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'date_query'     => array( array( 'after' => $days . ' days ago' ) ),
	) );
	return array_map( 'fccar_news_to_item', $q->posts );
}

function fccar_news_to_item( WP_Post $post ): array {
	$body = has_excerpt( $post )
		? get_the_excerpt( $post )
		: wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
	$cta = (string) get_post_meta( $post->ID, 'fcs_cta_url', true );

	return fccar_item( array(
		'id'      => 'announcement-' . $post->ID,
		'source'  => 'announcement',
		'layout'  => fccar_detect_layout( get_the_title( $post ), $body, '', $cta ),
		'title'   => get_the_title( $post ),
		'body'    => $body,
		'ctaUrl'  => $cta,
		'ctaText' => (string) get_post_meta( $post->ID, 'fcs_cta_text', true ),
		'image'   => (string) get_the_post_thumbnail_url( $post, 'full' ),
	) );
}

/* ---- Shared helpers ---- */

function fccar_announce_cat_id(): int {
	$term = get_term_by( 'slug', FCCAR_ANNOUNCE_SLUG, 'category' );
	return $term ? (int) $term->term_id : 0;
}

/**
 * Pick the card layout from an item's shape — kept in lockstep with the slides
 * app's announcementCards() so the explicit `layout` we emit agrees with what
 * shape-detection would choose downstream.
 */
function fccar_detect_layout( string $title, string $body, string $when, string $cta ): string {
	if ( '' !== $when ) {
		return 'event';
	}
	if ( '' !== $title && '' !== $body ) {
		return 'info';
	}
	if ( '' !== $cta && ( '' !== $body || '' !== $title ) ) {
		return 'qr_callout';
	}
	if ( '' !== $title && '' === $body ) {
		return 'divider';
	}
	return 'info';
}

/**
 * Build a feed item, dropping empty values so the JSON stays tight. Booleans:
 * preserviceOnly is emitted only when true (matches the slides side's
 * `preservice_only = a.preserviceOnly || undefined`); `layout`, `id` and
 * `source` are always kept.
 */
function fccar_item( array $fields ): array {
	$always = array( 'id', 'source', 'layout' );
	$out    = array();
	foreach ( $fields as $k => $v ) {
		if ( in_array( $k, $always, true ) ) {
			$out[ $k ] = $v;
			continue;
		}
		if ( is_bool( $v ) ) {
			if ( $v ) {
				$out[ $k ] = true;
			}
			continue;
		}
		if ( null !== $v && '' !== $v ) {
			$out[ $k ] = $v;
		}
	}
	return $out;
}

/**
 * Format an event's date/recurrence/time into the human "when" string the
 * `event` card prints (e.g. "Every 4th Friday at 4:00 pm", "Sundays at 7:00 pm",
 * "April 12 at 7:00 pm"). Venue is appended with a middot when present.
 *
 * Deliberately simple (design doc §10.3 flags this for refinement against real
 * event data); reads CTC recurrence meta directly to stay decoupled from the
 * MCP mu-plugin.
 */
function fccar_event_when( int $post_id ): string {
	$start = (string) get_post_meta( $post_id, '_ctc_event_start_date', true );
	$freq  = (string) get_post_meta( $post_id, '_ctc_event_recurrence', true );
	$venue = (string) get_post_meta( $post_id, '_ctc_event_venue', true );

	$when_ts = $start ? strtotime( $start ) : false;
	$lead    = '';

	if ( $freq && 'none' !== $freq ) {
		$lead = fccar_recurrence_phrase( $post_id, $freq, $when_ts );
	} elseif ( $when_ts ) {
		$lead = date_i18n( 'F j', $when_ts ); // "April 12"
	}

	$time = fccar_event_time( $post_id );
	$out  = trim( $lead . ( $time ? ( $lead ? ' at ' : '' ) . $time : '' ) );

	if ( $venue ) {
		$out = $out ? $out . ' · ' . $venue : $venue;
	}
	return $out;
}

function fccar_event_time( int $post_id ): string {
	// CTC stores a human time string in _ctc_event_time; fall back to the
	// machine start_time (HH:MM) formatted to "g:i a".
	$human = trim( (string) get_post_meta( $post_id, '_ctc_event_time', true ) );
	if ( '' !== $human ) {
		return $human;
	}
	$st = trim( (string) get_post_meta( $post_id, '_ctc_event_start_time', true ) );
	if ( preg_match( '/^\d{1,2}:\d{2}/', $st ) ) {
		return date_i18n( 'g:i a', strtotime( '2000-01-01 ' . $st ) );
	}
	return '';
}

function fccar_recurrence_phrase( int $post_id, string $freq, $when_ts ): string {
	$weekday = $when_ts ? date_i18n( 'l', $when_ts ) : '';

	if ( 'weekly' === $freq ) {
		$interval = max( 1, (int) get_post_meta( $post_id, '_ctc_event_recurrence_weekly_interval', true ) );
		$days_csv = (string) get_post_meta( $post_id, '_ctc_event_recurrence_weekly_day', true );
		$names    = fccar_weekday_names( $days_csv );
		if ( ! $names && $weekday ) {
			$names = array( $weekday );
		}
		$plural = array_map( static function ( $n ) { return $n . 's'; }, $names );
		$joined = fccar_join_list( $plural );
		if ( $interval >= 2 ) {
			return 2 === $interval
				? 'Every other ' . fccar_join_list( $names )
				: 'Every ' . fccar_ordinal( $interval ) . ' week (' . $joined . ')';
		}
		return $joined; // "Sundays", "Tuesdays & Thursdays"
	}

	if ( 'monthly' === $freq ) {
		$type = (string) get_post_meta( $post_id, '_ctc_event_recurrence_monthly_type', true );
		if ( 'week' === $type ) {
			$weeks_csv = (string) get_post_meta( $post_id, '_ctc_event_recurrence_monthly_week', true );
			$weeks     = array_filter( array_map( 'trim', explode( ',', $weeks_csv ) ) );
			$labels    = array_map( static function ( $w ) {
				return 'last' === strtolower( $w ) ? 'last' : fccar_ordinal( (int) $w );
			}, $weeks );
			$wk = $labels ? fccar_join_list( $labels ) : '';
			return trim( 'Every ' . trim( $wk . ' ' . $weekday ) ); // "Every 4th Friday"
		}
		return $weekday ? 'Monthly on ' . $weekday : 'Monthly';
	}

	if ( 'yearly' === $freq ) {
		return $when_ts ? 'Annually on ' . date_i18n( 'F j', $when_ts ) : 'Annually';
	}

	return '';
}

/** Map a CSV of CTC weekday codes (SU,MO,…) to full weekday names, in order. */
function fccar_weekday_names( string $csv ): array {
	$map = array( 'SU' => 'Sunday', 'MO' => 'Monday', 'TU' => 'Tuesday', 'WE' => 'Wednesday', 'TH' => 'Thursday', 'FR' => 'Friday', 'SA' => 'Saturday' );
	$out = array();
	foreach ( array_filter( array_map( 'trim', explode( ',', $csv ) ) ) as $code ) {
		$code = strtoupper( substr( $code, 0, 2 ) );
		if ( isset( $map[ $code ] ) ) {
			$out[] = $map[ $code ];
		}
	}
	return $out;
}

function fccar_ordinal( int $n ): string {
	$suffix = 'th';
	if ( $n % 100 < 11 || $n % 100 > 13 ) {
		$suffix = array( 'th', 'st', 'nd', 'rd' )[ $n % 10 ] ?? 'th';
	}
	return $n . $suffix;
}

/** "A", "A & B", "A, B & C". */
function fccar_join_list( array $items ): string {
	$items = array_values( array_filter( $items ) );
	$n     = count( $items );
	if ( 0 === $n ) {
		return '';
	}
	if ( 1 === $n ) {
		return $items[0];
	}
	$last = array_pop( $items );
	return implode( ', ', $items ) . ' & ' . $last;
}
