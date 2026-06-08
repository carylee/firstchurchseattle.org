<?php
/**
 * Pixabay provider.
 *
 * Pixabay (https://pixabay.com) offers free photos under the Pixabay Content
 * License (commercial use OK, attribution not required but recorded anyway).
 * Requires a free API key, defined in wp-config:
 *
 *   define( 'FCSP_PIXABAY_API_KEY', '…' );  // https://pixabay.com/api/docs/
 *
 * The key is a query parameter (Pixabay's documented method), not a header. We
 * request photos only, safesearch on, and sideload largeImageURL — Pixabay's
 * API terms ask that you not hotlink their image URLs permanently, which our
 * media-library import satisfies.
 */

defined( 'ABSPATH' ) || exit;

const FCSP_PIXABAY_API           = 'https://pixabay.com/api/';
const FCSP_PIXABAY_MIN_PAGE_SIZE = 3;   // Pixabay per_page minimum
const FCSP_PIXABAY_MAX_PAGE_SIZE = 200; // …and maximum
const FCSP_PIXABAY_LICENSE_URL   = 'https://pixabay.com/service/license-summary/';

add_filter(
	'fcsp_providers',
	static function ( array $providers ): array {
		$providers['pixabay'] = array(
			'label'     => 'Pixabay',
			'search'    => 'fcsp_search_pixabay',
			'available' => defined( 'FCSP_PIXABAY_API_KEY' ) && '' !== FCSP_PIXABAY_API_KEY,
		);
		return $providers;
	}
);

/**
 * Search Pixabay. See fcsp_search_openverse() for the return contract.
 *
 * @param array $args { query, count, page, orientation }
 * @return array|WP_Error
 */
function fcsp_search_pixabay( array $args ) {
	if ( ! defined( 'FCSP_PIXABAY_API_KEY' ) || '' === FCSP_PIXABAY_API_KEY ) {
		return new WP_Error( 'fcsp_pixabay_no_key', 'Pixabay is not configured (FCSP_PIXABAY_API_KEY missing).' );
	}

	$query = trim( (string) ( $args['query'] ?? '' ) );
	$count = max( FCSP_PIXABAY_MIN_PAGE_SIZE, min( FCSP_PIXABAY_MAX_PAGE_SIZE, (int) ( $args['count'] ?? 12 ) ) );
	$page  = max( 1, (int) ( $args['page'] ?? 1 ) );

	$params = array(
		'key'        => FCSP_PIXABAY_API_KEY,
		'q'          => $query,
		'per_page'   => $count,
		'page'       => $page,
		'image_type' => 'photo',
		'safesearch' => 'true',
	);

	// Pixabay has no "square"; leave orientation unset (all) in that case.
	$orientation_map = array(
		'wide' => 'horizontal',
		'tall' => 'vertical',
	);
	$orientation = $args['orientation'] ?? '';
	if ( isset( $orientation_map[ $orientation ] ) ) {
		$params['orientation'] = $orientation_map[ $orientation ];
	}

	$url      = add_query_arg( array_map( 'rawurlencode', $params ), FCSP_PIXABAY_API );
	$response = wp_remote_get(
		$url,
		array(
			'timeout'    => 15,
			'headers'    => array( 'Accept' => 'application/json' ),
			'user-agent' => 'FirstChurchSeattle/' . FCSP_VERSION . ' (+https://firstchurchseattle.org)',
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
		return new WP_Error( 'fcsp_pixabay_http', sprintf( 'Pixabay request failed (HTTP %d).', $code ) );
	}

	$results = array();
	foreach ( (array) ( $body['hits'] ?? array() ) as $hit ) {
		$normalized = fcsp_normalize_pixabay( $hit );
		if ( $normalized ) {
			$results[] = $normalized;
		}
	}

	$total = (int) ( $body['totalHits'] ?? count( $results ) );

	return array(
		'results'    => $results,
		'total'      => $total,
		'page'       => $page,
		'page_count' => (int) ceil( $total / $count ),
	);
}

/**
 * Reduce a raw Pixabay hit to our normalized shape. Returns null without a
 * usable image URL. Pixabay has no per-image title, so the tag list stands in.
 */
function fcsp_normalize_pixabay( $hit ): ?array {
	if ( ! is_array( $hit ) || empty( $hit['largeImageURL'] ) ) {
		return null;
	}
	$user    = (string) ( $hit['user'] ?? '' );
	$user_id = (string) ( $hit['user_id'] ?? '' );
	$user_url = ( '' !== $user && '' !== $user_id )
		? sprintf( 'https://pixabay.com/users/%s-%s/', rawurlencode( $user ), $user_id )
		: '';

	return array(
		'id'          => (string) ( $hit['id'] ?? '' ),
		'title'       => (string) ( $hit['tags'] ?? '' ),
		'creator'     => $user,
		'creator_url' => esc_url_raw( $user_url ),
		'url'         => esc_url_raw( (string) $hit['largeImageURL'] ),
		'thumbnail'   => esc_url_raw( (string) ( $hit['webformatURL'] ?? $hit['previewURL'] ?? $hit['largeImageURL'] ) ),
		'foreign_url' => esc_url_raw( (string) ( $hit['pageURL'] ?? '' ) ),
		'license'     => 'Pixabay License',
		'license_url' => FCSP_PIXABAY_LICENSE_URL,
		'attribution' => $user ? sprintf( 'Image by %s from Pixabay', $user ) : 'Image from Pixabay',
		'source'      => 'pixabay',
		'width'       => (int) ( $hit['imageWidth'] ?? 0 ),
		'height'      => (int) ( $hit['imageHeight'] ?? 0 ),
	);
}
