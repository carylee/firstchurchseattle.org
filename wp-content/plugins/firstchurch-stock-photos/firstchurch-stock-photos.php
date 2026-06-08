<?php
/**
 * Plugin Name: First Church Stock Photos
 * Description: Find attribution-safe, openly-licensed photos from Openverse and pull them into the media library — from the WP admin (Tools ▸ Stock Photos) or programmatically via the MCP server. Captures provider/creator/license provenance on every import.
 * Version:     0.5.0
 * Author:      First Church Seattle
 */

defined( 'ABSPATH' ) || exit;

const FCSP_VERSION = '0.5.0';

// Openverse REST API. No key required for our volume; an optional client
// credential can be supplied to raise rate limits (see inc/openverse.php).
const FCSP_OPENVERSE_API = 'https://api.openverse.org/v1/images/';

// We only ever surface results that allow commercial use AND modification, so
// editors never have to reason about license edge cases. Attribution is still
// captured and stored regardless. Overridable per-request.
const FCSP_DEFAULT_LICENSE_TYPE = 'commercial,modification';

// Attachment meta keys recording where an imported image came from.
const FCSP_META_PROVIDER    = '_fcsp_provider';      // top-level provider (openverse, pexels, …)
const FCSP_META_SOURCE      = '_fcsp_source';        // provider's sub-source (e.g. flickr, wikimedia)
const FCSP_META_OV_ID       = '_fcsp_openverse_id';
const FCSP_META_CREATOR     = '_fcsp_creator';
const FCSP_META_CREATOR_URL = '_fcsp_creator_url';
const FCSP_META_LICENSE     = '_fcsp_license';        // e.g. "by-sa"
const FCSP_META_LICENSE_URL = '_fcsp_license_url';
const FCSP_META_ATTRIBUTION = '_fcsp_attribution';    // ready-made credit string
const FCSP_META_FOREIGN_URL = '_fcsp_foreign_url';    // landing page on the source site

require_once __DIR__ . '/inc/providers.php';
require_once __DIR__ . '/inc/provider-openverse.php';
require_once __DIR__ . '/inc/provider-pexels.php';
require_once __DIR__ . '/inc/provider-unsplash.php';
require_once __DIR__ . '/inc/provider-pixabay.php';
require_once __DIR__ . '/inc/import.php';
require_once __DIR__ . '/inc/credits.php';
require_once __DIR__ . '/inc/rest.php';
require_once __DIR__ . '/inc/mcp.php';
require_once __DIR__ . '/inc/policy.php';                // locks Instant Images config in code
require_once __DIR__ . '/inc/instant-images-bridge.php'; // mirrors II uploads into our provenance
if ( is_admin() ) {
	require_once __DIR__ . '/inc/admin.php';
}

/**
 * The capability required to search/import stock photos. Mirrors core's media
 * upload gate; filterable for tighter control.
 */
function fcsp_capability(): string {
	return (string) apply_filters( 'fcsp_capability', 'upload_files' );
}

/**
 * Build a human-readable credit line for an imported attachment, or '' if the
 * attachment wasn't sourced through this plugin.
 */
function fcsp_attachment_credit( int $attachment_id ): string {
	$attribution = (string) get_post_meta( $attachment_id, FCSP_META_ATTRIBUTION, true );
	if ( '' !== $attribution ) {
		return $attribution;
	}
	$creator = (string) get_post_meta( $attachment_id, FCSP_META_CREATOR, true );
	$license = (string) get_post_meta( $attachment_id, FCSP_META_LICENSE, true );
	if ( '' === $creator && '' === $license ) {
		return '';
	}
	$bits = array_filter( array( $creator, $license ? strtoupper( $license ) : '' ) );
	return implode( ' · ', $bits );
}

/* ----------------------------------------------------------------------------
 * Media library: a "Source" column so anyone can see, at a glance, which
 * library images carry an attribution obligation and where they came from.
 * ------------------------------------------------------------------------- */
add_filter(
	'manage_media_columns',
	static function ( array $columns ): array {
		$columns['fcsp_source'] = 'Source';
		return $columns;
	}
);

add_action(
	'manage_media_custom_column',
	static function ( string $column, int $attachment_id ): void {
		if ( 'fcsp_source' !== $column ) {
			return;
		}
		$source  = (string) get_post_meta( $attachment_id, FCSP_META_SOURCE, true );
		$creator = (string) get_post_meta( $attachment_id, FCSP_META_CREATOR, true );
		$license = (string) get_post_meta( $attachment_id, FCSP_META_LICENSE, true );
		$foreign = (string) get_post_meta( $attachment_id, FCSP_META_FOREIGN_URL, true );
		if ( '' === $source && '' === $creator ) {
			echo '&mdash;';
			return;
		}
		$label = $creator ? $creator : $source;
		if ( $foreign ) {
			printf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $foreign ),
				esc_html( $label )
			);
		} else {
			echo esc_html( $label );
		}
		if ( $license ) {
			printf( '<br><span class="description">%s</span>', esc_html( strtoupper( $license ) ) );
		}
	},
	10,
	2
);
