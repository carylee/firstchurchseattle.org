<?php
/**
 * Provider registry + search dispatcher.
 *
 * Stock photos can come from several sources. Importing is already
 * provider-agnostic (it just sideloads a URL + stores provenance); only search
 * differs per provider. Each provider registers a search adapter that returns
 * the SAME normalized shape — { id, title, creator, creator_url, url,
 * thumbnail, foreign_url, license, license_url, attribution, source, width,
 * height } — and this dispatcher routes to it and stamps the provider slug onto
 * every result.
 *
 * Providers are equal peers: callers pass a `provider` arg per query; when
 * omitted, FCSP_DEFAULT_PROVIDER is used (Openverse, since it needs no key).
 */

defined( 'ABSPATH' ) || exit;

const FCSP_DEFAULT_PROVIDER = 'pexels';

/**
 * The available providers, keyed by slug. Each entry:
 *   - label:     human name for the picker
 *   - search:    callable( array $args ): array|WP_Error
 *   - available: whether it's usable right now (e.g. has its API key)
 *
 * Filter `fcsp_providers` to add or gate providers.
 *
 * @return array<string, array{label:string, search:callable, available:bool}>
 */
function fcsp_providers(): array {
	$providers = array(
		'openverse' => array(
			'label'     => 'Openverse',
			'search'    => 'fcsp_search_openverse',
			'available' => true, // works anonymously
		),
	);

	/**
	 * @param array $providers Registry keyed by slug.
	 */
	return (array) apply_filters( 'fcsp_providers', $providers );
}

/**
 * Providers that are usable right now, as slug => label (for the admin picker).
 *
 * @return array<string, string>
 */
function fcsp_provider_choices(): array {
	$choices = array();
	foreach ( fcsp_providers() as $slug => $p ) {
		if ( ! empty( $p['available'] ) ) {
			$choices[ $slug ] = (string) ( $p['label'] ?? $slug );
		}
	}
	return $choices;
}

/**
 * The provider used when a caller doesn't specify one: FCSP_DEFAULT_PROVIDER if
 * it's available, otherwise the first available provider. (Pexels is key-gated,
 * so this keeps default searches working even if its key is ever missing.)
 */
function fcsp_default_provider(): string {
	$providers = fcsp_providers();
	if ( ! empty( $providers[ FCSP_DEFAULT_PROVIDER ]['available'] ) ) {
		return FCSP_DEFAULT_PROVIDER;
	}
	foreach ( $providers as $slug => $p ) {
		if ( ! empty( $p['available'] ) ) {
			return $slug;
		}
	}
	return FCSP_DEFAULT_PROVIDER; // nothing available; the dispatcher will surface the error
}

/**
 * Search a provider and return normalized results.
 *
 * @param array $args { query (required), count, page, orientation, provider, ... }
 * @return array|WP_Error { results, total, page, page_count, provider }
 */
function fcsp_search( array $args ) {
	$query = trim( (string) ( $args['query'] ?? '' ) );
	if ( '' === $query ) {
		return new WP_Error( 'fcsp_empty_query', 'A search query is required.' );
	}

	$providers = fcsp_providers();
	$slug      = isset( $args['provider'] ) && '' !== $args['provider']
		? sanitize_key( (string) $args['provider'] )
		: fcsp_default_provider();

	if ( ! isset( $providers[ $slug ] ) ) {
		return new WP_Error(
			'fcsp_bad_provider',
			sprintf( 'Unknown provider "%s". Available: %s.', $slug, implode( ', ', array_keys( $providers ) ) )
		);
	}
	if ( empty( $providers[ $slug ]['available'] ) ) {
		return new WP_Error( 'fcsp_provider_unavailable', sprintf( 'Provider "%s" is not configured (missing API key?).', $slug ) );
	}

	$result = call_user_func( $providers[ $slug ]['search'], $args );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	// Stamp the provider slug onto every result so downstream (import,
	// provenance, the UI) can record and display where it came from.
	$result['provider'] = $slug;
	if ( isset( $result['results'] ) && is_array( $result['results'] ) ) {
		foreach ( $result['results'] as &$item ) {
			if ( is_array( $item ) ) {
				$item['provider'] = $slug;
			}
		}
		unset( $item );
	}

	return $result;
}
