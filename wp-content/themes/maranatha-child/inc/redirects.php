<?php
/**
 * Front-end redirects handled in code.
 *
 * These live in the child theme (not .htaccess, not a redirect plugin) so they
 * deploy through the normal merge-to-main pipeline and need no prod DB access.
 *
 * 1. Sermons retirement — the sermon archive is retired in favor of a YouTube
 *    service history. Until that page exists, every sermon surface 301s to the
 *    live-worship page, which already links the YouTube live stream and the
 *    past-services playlist. When the YouTube history page is ready, change
 *    fcs_sermons_redirect_target() and the whole retirement follows.
 *
 * 2. /worship/ canonicalization — /worship/ and /worship/live/ are two WP
 *    pages rendering identical content. The published vanity URL (/live) and
 *    the footer "Watch Live" link both point at /worship/live/, so it wins.
 *
 * 3. Location singles retirement — /locations/firstchurch/ was near-orphaned
 *    (in no menu, linked from no theme code), so single ctc_location URLs 301
 *    to the Contact page. The post itself stays published: footer.php reads
 *    its authored coordinates for the footer static map. The /locations/
 *    archive already 301s to /campus-locations/ elsewhere and is left alone.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where retired sermon URLs land.
 *
 * @return string Absolute URL.
 */
function fcs_sermons_redirect_target() {
	return home_url( '/worship/live/' );
}

/**
 * Is this URL path part of the retired sermon archive?
 *
 * Covers single sermons and the CPT archive (/sermons/...), the sermon
 * taxonomies (/sermon-topic/, /sermon-series/, /sermon-book/, /sermon-speaker/,
 * /sermon-tag/), and the hand-made index pages under /worship/sermons-2/.
 *
 * @param string $path URL path, e.g. "/sermons/bearing-fruit/".
 * @return bool
 */
function fcs_is_retired_sermon_path( $path ) {
	$path = strtolower( untrailingslashit( (string) $path ) );

	return (bool) preg_match(
		'~^/(sermons|sermon-(topic|series|book|speaker|tag)|worship/sermons-2)(/|$)~',
		trailingslashit( $path )
	);
}

add_action(
	'template_redirect',
	function () {
		$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );

		// Sermons retirement. Path prefixes catch everything with the standard
		// permalink structure; the conditionals are a safety net in case a
		// sermon URL form exists that the prefixes miss (e.g. ?p= permalinks).
		$is_sermon_request = fcs_is_retired_sermon_path( $path )
			|| is_singular( 'ctc_sermon' )
			|| is_post_type_archive( 'ctc_sermon' )
			|| is_tax( array( 'ctc_sermon_topic', 'ctc_sermon_series', 'ctc_sermon_book', 'ctc_sermon_speaker', 'ctc_sermon_tag' ) );

		if ( $is_sermon_request ) {
			wp_safe_redirect( fcs_sermons_redirect_target(), 301 );
			exit;
		}

		// /worship/ duplicates /worship/live/ — canonicalize to the latter.
		// Children of /worship/ (prayer, live, …) are exact-match exempt.
		if ( '/worship' === untrailingslashit( strtolower( $path ) ) ) {
			wp_safe_redirect( home_url( '/worship/live/' ), 301 );
			exit;
		}

		// Location singles retirement. The path prefix requires something
		// after /locations/ so the bare archive keeps its existing 301 to
		// /campus-locations/; the conditional is the ?p= safety net.
		if ( is_singular( 'ctc_location' ) || preg_match( '~^/locations/.~', strtolower( $path ) ) ) {
			wp_safe_redirect( home_url( '/about/contact-us/' ), 301 );
			exit;
		}
	}
);
