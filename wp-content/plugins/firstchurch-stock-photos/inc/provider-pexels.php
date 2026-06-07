<?php
/**
 * Pexels provider.
 *
 * Pexels (https://pexels.com) offers high-quality modern stock photos under the
 * permissive Pexels License (free for commercial use, no per-photo license,
 * attribution appreciated). Requires a free API key, defined in wp-config:
 *
 *   define( 'FCSP_PEXELS_API_KEY', '…' );  // https://www.pexels.com/api/
 *
 * Unlike Openverse there is no per-item CC license to reason about, so we record
 * a uniform "Pexels License" + a generated attribution string for provenance.
 */

defined( 'ABSPATH' ) || exit;

const FCSP_PEXELS_API           = 'https://api.pexels.com/v1/search';
const FCSP_PEXELS_MAX_PAGE_SIZE = 80; // Pexels per_page maximum
const FCSP_PEXELS_LICENSE_URL   = 'https://www.pexels.com/license/';

add_filter(
	'fcsp_providers',
	static function ( array $providers ): array {
		$providers['pexels'] = array(
			'label'     => 'Pexels',
			'search'    => 'fcsp_search_pexels',
			'available' => defined( 'FCSP_PEXELS_API_KEY' ) && '' !== FCSP_PEXELS_API_KEY,
		);
		return $providers;
	}
);

/**
 * Search Pexels. See fcsp_search_openverse() for the return contract.
 *
 * @param array $args { query, count, page, orientation }
 * @return array|WP_Error
 */
function fcsp_search_pexels( array $args ) {
	if ( ! defined( 'FCSP_PEXELS_API_KEY' ) || '' === FCSP_PEXELS_API_KEY ) {
		return new WP_Error( 'fcsp_pexels_no_key', 'Pexels is not configured (FCSP_PEXELS_API_KEY missing).' );
	}

	$query = trim( (string) ( $args['query'] ?? '' ) );
	$count = max( 1, min( FCSP_PEXELS_MAX_PAGE_SIZE, (int) ( $args['count'] ?? 12 ) ) );
	$page  = max( 1, (int) ( $args['page'] ?? 1 ) );

	$params = array(
		'query'    => $query,
		'per_page' => $count,
		'page'     => $page,
	);

	// Map our shape vocabulary to Pexels' orientation values.
	$orientation_map = array(
		'wide'   => 'landscape',
		'tall'   => 'portrait',
		'square' => 'square',
	);
	$orientation = $args['orientation'] ?? '';
	if ( isset( $orientation_map[ $orientation ] ) ) {
		$params['orientation'] = $orientation_map[ $orientation ];
	}

	$url      = add_query_arg( array_map( 'rawurlencode', $params ), FCSP_PEXELS_API );
	$response = wp_remote_get(
		$url,
		array(
			'timeout'    => 15,
			'headers'    => array(
				'Authorization' => FCSP_PEXELS_API_KEY,
				'Accept'        => 'application/json',
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
		$detail = is_array( $body ) && isset( $body['error'] ) ? (string) $body['error'] : 'Unexpected response.';
		return new WP_Error( 'fcsp_pexels_http', sprintf( 'Pexels request failed (HTTP %d): %s', $code, $detail ) );
	}

	$results = array();
	foreach ( (array) ( $body['photos'] ?? array() ) as $photo ) {
		$normalized = fcsp_normalize_pexels( $photo );
		if ( $normalized ) {
			$results[] = $normalized;
		}
	}

	$total    = (int) ( $body['total_results'] ?? count( $results ) );
	$per_page = max( 1, (int) ( $body['per_page'] ?? $count ) );

	return array(
		'results'    => $results,
		'total'      => $total,
		'page'       => $page,
		'page_count' => (int) ceil( $total / $per_page ),
	);
}

/**
 * Reduce a raw Pexels photo to our normalized shape. Returns null without a
 * usable original URL.
 */
function fcsp_normalize_pexels( $photo ): ?array {
	if ( ! is_array( $photo ) || empty( $photo['src']['original'] ) ) {
		return null;
	}
	$src         = $photo['src'];
	$photographer = (string) ( $photo['photographer'] ?? '' );

	return array(
		'id'          => (string) ( $photo['id'] ?? '' ),
		'title'       => (string) ( $photo['alt'] ?? '' ),
		'creator'     => $photographer,
		'creator_url' => esc_url_raw( (string) ( $photo['photographer_url'] ?? '' ) ),
		'url'         => esc_url_raw( (string) $src['original'] ),
		'thumbnail'   => esc_url_raw( (string) ( $src['medium'] ?? $src['original'] ) ),
		'foreign_url' => esc_url_raw( (string) ( $photo['url'] ?? '' ) ),
		'license'     => 'Pexels License',
		'license_url' => FCSP_PEXELS_LICENSE_URL,
		'attribution' => $photographer ? sprintf( 'Photo by %s on Pexels', $photographer ) : 'Photo on Pexels',
		'source'      => 'pexels',
		'width'       => (int) ( $photo['width'] ?? 0 ),
		'height'      => (int) ( $photo['height'] ?? 0 ),
	);
}
