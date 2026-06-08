<?php
/**
 * The spine-shaped source reader: same Happening contract the carousel /
 * happenings plugin expect, so the spine merges this alongside CTC events
 * (see happenings_event_items). Recurrence/when derivation lives in inc/event.php.
 *
 * @package FirstChurch\Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Upcoming events as Happening items — one per event (its next non-cancelled
 * occurrence in the window). Each carries `date` so the spine can date-sort the
 * merged CTC + fce list.
 *
 * @return array<int,array<string,mixed>>
 */
function fce_event_items( int $weeks ): array {
	$from = new DateTimeImmutable( current_time( 'Y-m-d' ) );
	$to   = $from->modify( "+{$weeks} weeks" );

	$q = new WP_Query( array(
		'post_type'      => FCE_CPT,
		'post_status'    => 'publish',
		'posts_per_page' => 200,
		'no_found_rows'  => true,
	) );

	$items = array();
	foreach ( $q->posts as $p ) {
		$dtstart = (string) get_post_meta( $p->ID, FCE_DTSTART, true );
		$next    = fce_next_occurrence( $dtstart, fce_rrule( $p->ID ), $from, $to, fce_skip_dates( $p->ID ) );
		if ( null === $next ) {
			continue;
		}
		$reg     = (string) get_post_meta( $p->ID, FCE_REGURL, true );
		$items[] = array(
			'id'     => 'event-' . $p->ID,
			'source' => 'event',
			'layout' => 'event',
			'title'  => html_entity_decode( get_the_title( $p ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'date'   => $next->format( 'Y-m-d' ),
			'when'   => fce_when( $p->ID ),
			'ctaUrl' => $reg ?: (string) get_permalink( $p ),
			'image'  => (string) get_the_post_thumbnail_url( $p, 'full' ),
			'url'    => (string) get_permalink( $p ),
		);
	}

	usort( $items, static fn ( $a, $b ) => strcmp( $a['date'], $b['date'] ) );

	return $items;
}
