<?php
/**
 * Openverse API client.
 *
 * Openverse (https://openverse.org) aggregates openly-licensed images from
 * Flickr, Wikimedia, museums, etc. Anonymous requests work out of the box;
 * supplying a registered client id/secret (constants FCSP_OPENVERSE_CLIENT_ID
 * and FCSP_OPENVERSE_CLIENT_SECRET, e.g. via wp-config) raises the rate limit.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Search Openverse for images.
 *
 * @param array $args {
 *     @type string $query        Required search text.
 *     @type int    $count        Results to return (1-50). Default 12.
 *     @type int    $page         1-based page. Default 1.
 *     @type string $orientation  '', 'square', 'tall', or 'wide'.
 *     @type string $license_type Comma list: commercial,modification. Defaults to FCSP_DEFAULT_LICENSE_TYPE.
 * }
 * @return array|WP_Error { results: array<normalized image>, total: int, page: int, page_count: int }
 */
function fcsp_search( array $args ) {
	$query = isset( $args['query'] ) ? trim( (string) $args['query'] ) : '';
	if ( '' === $query ) {
		return new WP_Error( 'fcsp_empty_query', 'A search query is required.' );
	}

	$count        = (int) ( $args['count'] ?? 12 );
	$count        = max( 1, min( 50, $count ) );
	$page         = max( 1, (int) ( $args['page'] ?? 1 ) );
	$license_type = isset( $args['license_type'] ) && '' !== $args['license_type']
		? (string) $args['license_type']
		: FCSP_DEFAULT_LICENSE_TYPE;

	$params = array(
		'q'            => $query,
		'page_size'    => $count,
		'page'         => $page,
		'license_type' => $license_type,
		'mature'       => 'false',
	);

	$orientation = $args['orientation'] ?? '';
	$ar_map      = array(
		'square' => 'square',
		'tall'   => 'tall',
		'wide'   => 'wide',
	);
	if ( isset( $ar_map[ $orientation ] ) ) {
		$params['aspect_ratio'] = $ar_map[ $orientation ];
	}

	$url      = add_query_arg( array_map( 'rawurlencode', $params ), FCSP_OPENVERSE_API );
	$headers  = array( 'Accept' => 'application/json' );
	$token    = fcsp_openverse_token();
	if ( $token ) {
		$headers['Authorization'] = 'Bearer ' . $token;
	}

	$response = wp_remote_get(
		$url,
		array(
			'timeout'    => 15,
			'headers'    => $headers,
			'user-agent' => 'FirstChurchSeattle/' . FCSP_VERSION . ' (+https://firstchurchseattle.org)',
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
		$detail = is_array( $body ) && isset( $body['detail'] ) ? (string) $body['detail'] : 'Unexpected response.';
		return new WP_Error( 'fcsp_openverse_http', sprintf( 'Openverse request failed (HTTP %d): %s', $code, $detail ) );
	}

	$results = array();
	foreach ( (array) ( $body['results'] ?? array() ) as $item ) {
		$normalized = fcsp_normalize_result( $item );
		if ( $normalized ) {
			$results[] = $normalized;
		}
	}

	return array(
		'results'    => $results,
		'total'      => (int) ( $body['result_count'] ?? count( $results ) ),
		'page'       => $page,
		'page_count' => (int) ( $body['page_count'] ?? 1 ),
	);
}

/**
 * Reduce a raw Openverse result to the fields we surface and store. Returns
 * null if the item lacks a usable full-size URL.
 */
function fcsp_normalize_result( $item ): ?array {
	if ( ! is_array( $item ) || empty( $item['url'] ) ) {
		return null;
	}
	return array(
		'id'           => (string) ( $item['id'] ?? '' ),
		'title'        => (string) ( $item['title'] ?? '' ),
		'creator'      => (string) ( $item['creator'] ?? '' ),
		'creator_url'  => (string) ( $item['creator_url'] ?? '' ),
		'url'          => esc_url_raw( (string) $item['url'] ),
		'thumbnail'    => esc_url_raw( (string) ( $item['thumbnail'] ?? $item['url'] ) ),
		'foreign_url'  => esc_url_raw( (string) ( $item['foreign_landing_url'] ?? '' ) ),
		'license'      => (string) ( $item['license'] ?? '' ),
		'license_url'  => esc_url_raw( (string) ( $item['license_url'] ?? '' ) ),
		'attribution'  => (string) ( $item['attribution'] ?? '' ),
		'source'       => (string) ( $item['source'] ?? '' ),
		'width'        => (int) ( $item['width'] ?? 0 ),
		'height'       => (int) ( $item['height'] ?? 0 ),
	);
}

/**
 * Fetch (and briefly cache) an OAuth2 client-credentials token, if client
 * credentials are configured. Returns '' when running anonymously.
 */
function fcsp_openverse_token(): string {
	if ( ! defined( 'FCSP_OPENVERSE_CLIENT_ID' ) || ! defined( 'FCSP_OPENVERSE_CLIENT_SECRET' ) ) {
		return '';
	}
	$cached = get_transient( 'fcsp_openverse_token' );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}

	$response = wp_remote_post(
		'https://api.openverse.org/v1/auth_tokens/token/',
		array(
			'timeout' => 15,
			'body'    => array(
				'grant_type'    => 'client_credentials',
				'client_id'     => FCSP_OPENVERSE_CLIENT_ID,
				'client_secret' => FCSP_OPENVERSE_CLIENT_SECRET,
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		return '';
	}
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $body ) || empty( $body['access_token'] ) ) {
		return '';
	}
	$token   = (string) $body['access_token'];
	$expires = max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 60 );
	set_transient( 'fcsp_openverse_token', $token, $expires );
	return $token;
}
