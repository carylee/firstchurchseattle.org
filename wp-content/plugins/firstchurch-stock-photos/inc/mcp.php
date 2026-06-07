<?php
/**
 * MCP abilities — let an AI agent find and pull in a free stock photo.
 *
 * Two abilities under the existing `firstchurch` category (registered by the
 * firstchurch-mcp-abilities mu-plugin). Typical flow: the agent calls
 * search-stock-photo, picks a candidate, then either calls import-stock-photo
 * (which records provenance and can set the featured image in one step) or
 * feeds the chosen attachment_id into the existing create/update abilities'
 * image_id parameter.
 *
 * Registration is guarded so the plugin is harmless if the Abilities API /
 * MCP adapter aren't present.
 */

defined( 'ABSPATH' ) || exit;

/* Promote both abilities to first-class tools on the MCP adapter's default
 * server (same mechanism the mu-plugin uses for its curated verbs). */
add_filter(
	'mcp_adapter_default_server_config',
	static function ( $config ) {
		if ( ! is_array( $config ) ) {
			return $config;
		}
		$existing        = isset( $config['tools'] ) && is_array( $config['tools'] ) ? $config['tools'] : array();
		$config['tools'] = array_values(
			array_unique(
				array_merge(
					$existing,
					array( 'firstchurch/search-stock-photo', 'firstchurch/import-stock-photo' )
				)
			)
		);
		return $config;
	}
);

add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$mcp_public = array( 'mcp' => array( 'public' => true ) );
		$can_use    = static function () {
			return current_user_can( fcsp_capability() );
		};

		wp_register_ability(
			'firstchurch/search-stock-photo',
			array(
				'label'               => 'Search stock photos',
				'description'         => 'Search free stock photos across providers (' . implode( ', ', array_keys( fcsp_provider_choices() ) ) . '). Defaults to ' . fcsp_default_provider() . '. Returns candidates with thumbnail, full-size URL, creator, license, and a ready-made attribution string. Read-only — pass a chosen candidate to import-stock-photo to add it to the media library.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'query'       => array( 'type' => 'string', 'description' => 'What to search for, e.g. "autumn trees" or "diverse community".' ),
						'count'       => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 8 ),
						'orientation' => array( 'type' => 'string', 'enum' => array( '', 'square', 'tall', 'wide' ), 'default' => '' ),
						'provider'    => array( 'type' => 'string', 'enum' => array_merge( array( '' ), array_keys( fcsp_provider_choices() ) ), 'default' => '', 'description' => 'Which provider to search; defaults to Openverse.' ),
					),
					'required'             => array( 'query' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return fcsp_search(
						array(
							'query'       => $input['query'] ?? '',
							'count'       => $input['count'] ?? 8,
							'orientation' => $input['orientation'] ?? '',
							'provider'    => $input['provider'] ?? '',
						)
					);
				},
				'permission_callback' => $can_use,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/import-stock-photo',
			array(
				'label'               => 'Import stock photo',
				'description'         => 'Download a stock photo into the media library and record its provenance (provider, creator, license, attribution, source). Pass the fields returned by search-stock-photo. Optionally set it as a post\'s featured image via post_id. Returns the new attachment_id.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'image_url'    => array( 'type' => 'string', 'description' => 'The candidate "url" (full-size image) from search-stock-photo.' ),
						'title'        => array( 'type' => 'string' ),
						'alt'          => array( 'type' => 'string', 'description' => 'Alt text. Defaults to title.' ),
						'post_id'      => array( 'type' => 'integer', 'description' => 'If set, also use this image as the post\'s featured image.' ),
						'provider'     => array( 'type' => 'string' ),
						'openverse_id' => array( 'type' => 'string' ),
						'creator'      => array( 'type' => 'string' ),
						'creator_url'  => array( 'type' => 'string' ),
						'license'      => array( 'type' => 'string' ),
						'license_url'  => array( 'type' => 'string' ),
						'attribution'  => array( 'type' => 'string' ),
						'source'       => array( 'type' => 'string' ),
						'foreign_url'  => array( 'type' => 'string' ),
						'download_location' => array( 'type' => 'string', 'description' => 'Provider download-tracking URL to pass back from search (Unsplash).' ),
					),
					'required'             => array( 'image_url' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return fcsp_import( (array) $input );
				},
				'permission_callback' => $can_use,
				'meta'                => $mcp_public,
			)
		);
	}
);
