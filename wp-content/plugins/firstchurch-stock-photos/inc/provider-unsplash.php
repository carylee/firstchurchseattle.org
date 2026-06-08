<?php
/**
 * Unsplash provider.
 *
 * Unsplash (https://unsplash.com) offers high-quality photos under the Unsplash
 * License (free, commercial use OK, no per-photo license). Requires a free API
 * access key, defined in wp-config:
 *
 *   define( 'FCSP_UNSPLASH_ACCESS_KEY', '…' );  // https://unsplash.com/developers
 *
 * ToS note: the Unsplash API guidelines REQUIRE that when a photo is "used"
 * (downloaded) the app pings the photo's download endpoint. We do that on import
 * via the provider's `on_import` hook (fcsp_unsplash_after_import) — see
 * https://help.unsplash.com/en/articles/2511258-guideline-triggering-a-download.
 */

defined( 'ABSPATH' ) || exit;

const FCSP_UNSPLASH_API           = 'https://api.unsplash.com/search/photos';
const FCSP_UNSPLASH_MAX_PAGE_SIZE = 30; // Unsplash per_page maximum
const FCSP_UNSPLASH_LICENSE_URL   = 'https://unsplash.com/license';

add_filter(
	'fcsp_providers',
	static function ( array $providers ): array {
		$providers['unsplash'] = array(
			'label'     => 'Unsplash',
			'search'    => 'fcsp_search_unsplash',
			'available' => defined( 'FCSP_UNSPLASH_ACCESS_KEY' ) && '' !== FCSP_UNSPLASH_ACCESS_KEY,
			// Fire the ToS-required download ping after a successful import.
			'on_import' => 'fcsp_unsplash_after_import',
		);
		return $providers;
	}
);

/**
 * Search Unsplash. See fcsp_search_openverse() for the return contract.
 *
 * @param array $args { query, count, page, orientation }
 * @return array|WP_Error
 */
function fcsp_search_unsplash( array $args ) {
	if ( ! defined( 'FCSP_UNSPLASH_ACCESS_KEY' ) || '' === FCSP_UNSPLASH_ACCESS_KEY ) {
		return new WP_Error( 'fcsp_unsplash_no_key', 'Unsplash is not configured (FCSP_UNSPLASH_ACCESS_KEY missing).' );
	}

	$query = trim( (string) ( $args['query'] ?? '' ) );
	$count = max( 1, min( FCSP_UNSPLASH_MAX_PAGE_SIZE, (int) ( $args['count'] ?? 12 ) ) );
	$page  = max( 1, (int) ( $args['page'] ?? 1 ) );

	$params = array(
		'query'    => $query,
		'per_page' => $count,
		'page'     => $page,
	);

	$orientation_map = array(
		'wide'   => 'landscape',
		'tall'   => 'portrait',
		'square' => 'squarish',
	);
	$orientation = $args['orientation'] ?? '';
	if ( isset( $orientation_map[ $orientation ] ) ) {
		$params['orientation'] = $orientation_map[ $orientation ];
	}

	$url      = add_query_arg( array_map( 'rawurlencode', $params ), FCSP_UNSPLASH_API );
	$response = wp_remote_get(
		$url,
		array(
			'timeout'    => 15,
			'headers'    => array(
				'Authorization'       => 'Client-ID ' . FCSP_UNSPLASH_ACCESS_KEY,
				'Accept-Version'      => 'v1',
				'Accept'              => 'application/json',
			),
			'user-agent' => 'FirstChurchSeattle/' . FCSP_VERSION . ' (+https://firstchurchseattle.org)',
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
		$detail = is_array( $body ) && isset( $body['errors'][0] ) ? (string) $body['errors'][0] : 'Unexpected response.';
		return new WP_Error( 'fcsp_unsplash_http', sprintf( 'Unsplash request failed (HTTP %d): %s', $code, $detail ) );
	}

	$results = array();
	foreach ( (array) ( $body['results'] ?? array() ) as $photo ) {
		$normalized = fcsp_normalize_unsplash( $photo );
		if ( $normalized ) {
			$results[] = $normalized;
		}
	}

	return array(
		'results'    => $results,
		'total'      => (int) ( $body['total'] ?? count( $results ) ),
		'page'       => $page,
		'page_count' => (int) ( $body['total_pages'] ?? 1 ),
	);
}

/**
 * Reduce a raw Unsplash photo to our normalized shape. Returns null without a
 * usable image URL. Carries `download_location` so the import can fire the
 * ToS-required download ping.
 */
function fcsp_normalize_unsplash( $photo ): ?array {
	if ( ! is_array( $photo ) || empty( $photo['urls']['full'] ) ) {
		return null;
	}
	$urls = $photo['urls'];
	$name = (string) ( $photo['user']['name'] ?? '' );

	return array(
		'id'                => (string) ( $photo['id'] ?? '' ),
		'title'             => (string) ( $photo['alt_description'] ?? $photo['description'] ?? '' ),
		'creator'           => $name,
		'creator_url'       => esc_url_raw( (string) ( $photo['user']['links']['html'] ?? '' ) ),
		'url'               => esc_url_raw( (string) $urls['full'] ),
		'thumbnail'         => esc_url_raw( (string) ( $urls['small'] ?? $urls['thumb'] ?? $urls['full'] ) ),
		'foreign_url'       => esc_url_raw( (string) ( $photo['links']['html'] ?? '' ) ),
		'license'           => 'Unsplash License',
		'license_url'       => FCSP_UNSPLASH_LICENSE_URL,
		'attribution'       => $name ? sprintf( 'Photo by %s on Unsplash', $name ) : 'Photo on Unsplash',
		'source'            => 'unsplash',
		'width'             => (int) ( $photo['width'] ?? 0 ),
		'height'            => (int) ( $photo['height'] ?? 0 ),
		'download_location' => esc_url_raw( (string) ( $photo['links']['download_location'] ?? '' ) ),
	);
}

/**
 * Unsplash ToS: ping the photo's download endpoint when it's used. Fire-and-
 * forget — never let a tracking hiccup fail the actual import.
 *
 * @param array $data The import payload (includes download_location, provider).
 */
function fcsp_unsplash_after_import( array $data ): void {
	$location = isset( $data['download_location'] ) ? esc_url_raw( (string) $data['download_location'] ) : '';
	if ( '' === $location || ! defined( 'FCSP_UNSPLASH_ACCESS_KEY' ) || '' === FCSP_UNSPLASH_ACCESS_KEY ) {
		return;
	}
	wp_remote_get(
		$location,
		array(
			'timeout'    => 10,
			'headers'    => array( 'Authorization' => 'Client-ID ' . FCSP_UNSPLASH_ACCESS_KEY ),
			'user-agent' => 'FirstChurchSeattle/' . FCSP_VERSION . ' (+https://firstchurchseattle.org)',
		)
	);
}
