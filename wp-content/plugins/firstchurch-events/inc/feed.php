<?php
/**
 * A public `/events.ics` subscription feed — the win a lean, RRULE-native model
 * unlocks that CTC doesn't give us well. Each event emits a VEVENT carrying its
 * RRULE + EXDATE, so a subscriber's Google/Apple calendar expands the recurrence
 * and drops cancelled occurrences itself.
 *
 * @package FirstChurch\Events
 */

use FirstChurch\Events\Ics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', static function () {
	add_rewrite_rule( '^events\.ics/?$', 'index.php?fce_ics=1', 'top' );
} );

add_filter( 'query_vars', static function ( $vars ) {
	$vars[] = 'fce_ics';
	return $vars;
} );

// Don't let WP's canonical redirect bounce /events.ics → /events.ics/.
add_filter( 'redirect_canonical', static fn ( $url ) => get_query_var( 'fce_ics' ) ? false : $url );

add_action( 'template_redirect', static function () {
	if ( ! get_query_var( 'fce_ics' ) ) {
		return;
	}

	$q      = new WP_Query( array( 'post_type' => FCE_CPT, 'post_status' => 'publish', 'posts_per_page' => 500, 'no_found_rows' => true ) );
	$events = array();
	foreach ( $q->posts as $p ) {
		$dtstart = (string) get_post_meta( $p->ID, FCE_DTSTART, true );
		if ( '' === $dtstart ) {
			continue;
		}
		$events[] = array(
			'uid'        => 'fce-' . $p->ID . '@firstchurchseattle.org',
			'title'      => html_entity_decode( get_the_title( $p ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'dtstart'    => $dtstart,
			'time'       => (string) get_post_meta( $p->ID, FCE_TIME, true ),
			'venue'      => (string) get_post_meta( $p->ID, FCE_VENUE, true ),
			'url'        => (string) get_permalink( $p ),
			'rrule'      => fce_rrule( $p->ID ),
			'skip_dates' => fce_skip_dates( $p->ID ),
		);
	}

	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: inline; filename="firstchurch-events.ics"' );
	echo Ics::calendar( $events, gmdate( 'Ymd\THis\Z' ) );
	exit;
} );
