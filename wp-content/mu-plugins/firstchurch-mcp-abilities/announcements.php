<?php
/**
 * First Church MCP Abilities — announcements.
 *
 * Read + draft-first write abilities for Announcements-category posts.
 *
 * Loaded by ../firstchurch-mcp-abilities.php (WordPress does not auto-load
 * mu-plugin subdirectories). Procedural, global namespace, no autoloader —
 * matches the rest of the mu-plugin.
 *
 * @package FirstChurch\Mcp
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$mcp_public    = array( 'mcp' => array( 'public' => true ) );
		$can_read      = static function () { return current_user_can( 'read' ); };
		$can_edit      = static function () { return current_user_can( 'edit_posts' ); };

		/* ---- ANNOUNCEMENTS: READ ---- */

		wp_register_ability(
			'firstchurch/list-announcements',
			array(
				'label'               => 'List announcements',
				'description'         => 'List posts in the Announcements category, newest first. Each item carries a truncated excerpt only — call get-announcement with the id for the full body. Drafts/pending require edit permission. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit'      => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
						'since_date' => array( 'type' => 'string', 'description' => 'YYYY-MM-DD lower bound' ),
						'search'     => array( 'type' => 'string' ),
						'status'     => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'pending', 'any' ), 'default' => 'publish' ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_list_announcements',
				'permission_callback' => static function ( $input = array() ) {
					$status = $input['status'] ?? 'publish';
					if ( 'publish' !== $status && ! current_user_can( 'edit_posts' ) ) {
						return new WP_Error( 'forbidden', 'Viewing non-published announcements requires edit permission.' );
					}
					return current_user_can( 'read' );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/get-announcement',
			array(
				'label'               => 'Get announcement',
				'description'         => 'Get one announcement post by ID, including full content. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					$post = get_post( (int) $input['id'] );
					if ( ! $post || 'post' !== $post->post_type || ! has_category( fcmcp_announce_cat_id(), $post ) ) {
						return new WP_Error( 'not_found', 'Announcement not found.' );
					}
					if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) {
						return new WP_Error( 'forbidden', 'Not permitted to view this announcement.' );
					}
					$data            = fcmcp_post_to_array( $post );
					$data            = array_merge( $data, fcmcp_announcement_extra( $post ) );
					$data['content'] = apply_filters( 'the_content', $post->post_content );
					return $data;
				},
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		/* ---- ANNOUNCEMENTS: WRITE (draft-first) ---- */

		wp_register_ability(
			'firstchurch/create-announcement',
			array(
				'label'               => 'Create announcement (draft)',
				'description'         => 'Create a new announcement post as a DRAFT in the Announcements category (a human publishes it).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title'   => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string', 'description' => 'Body (may contain basic HTML).' ),
						'excerpt' => array( 'type' => 'string' ),
						'cta_text' => array( 'type' => 'string', 'description' => 'Call-to-action button label (e.g. "RSVP", "Learn more"). Optional; defaults to "Learn more" when a cta_url is set.' ),
						'cta_url'  => array( 'type' => 'string', 'description' => 'Call-to-action button URL. The button only renders when this is set. Use mailto: for "contact X" asks.' ),
						'weight'   => array( 'type' => 'integer', 'description' => 'Prominence on /engage and the carousel. 0 = normal; 10 floats it to the Featured row; 20 pins it to the top.' ),
						'expires'  => array( 'type' => 'string', 'description' => 'Stop showing on /engage and the carousel after this date (YYYY-MM-DD). The post stays published in the news archive.' ),
						'image_id'  => array( 'type' => 'integer', 'description' => 'Existing media library attachment ID to use as the featured image.' ),
						'image_url' => array( 'type' => 'string', 'description' => 'URL of an image to download and set as the featured image.' ),
						'date'      => array( 'type' => 'string', 'description' => 'Publication date/time as YYYY-MM-DD or YYYY-MM-DD HH:MM (site local). Past backdates; future schedules it to auto-publish then. Defaults to now.' ),
						'status'    => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ), 'default' => 'draft', 'description' => 'draft (default), pending (queue for approval), or publish (go live now).' ),
					),
					'required'             => array( 'title', 'content' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_create_announcement',
				'permission_callback' => $can_edit,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/update-announcement',
			array(
				'label'               => 'Update announcement',
				'description'         => 'Update an existing announcement (title/content/excerpt) by ID. Does not change publish status.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'      => array( 'type' => 'integer' ),
						'title'   => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string' ),
						'excerpt' => array( 'type' => 'string' ),
						'cta_text' => array( 'type' => 'string', 'description' => 'Call-to-action button label.' ),
						'cta_url'  => array( 'type' => 'string', 'description' => 'Call-to-action button URL (button renders only when set).' ),
						'weight'   => array( 'type' => 'integer', 'description' => 'Prominence on /engage and the carousel. 0 = normal; 10 floats it to the Featured row; 20 pins it to the top.' ),
						'expires'  => array( 'type' => 'string', 'description' => 'Stop showing on /engage and the carousel after this date (YYYY-MM-DD). The post stays published in the news archive.' ),
						'image_id'  => array( 'type' => 'integer', 'description' => 'Existing media library attachment ID to use as the featured image.' ),
						'image_url' => array( 'type' => 'string', 'description' => 'URL of an image to download and set as the featured image.' ),
						'date'      => array( 'type' => 'string', 'description' => 'Publication date/time as YYYY-MM-DD or YYYY-MM-DD HH:MM (site local). Past backdates; future schedules it to auto-publish then.' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_update_announcement',
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/set-announcement-status',
			array(
				'label'               => 'Set announcement status',
				'description'         => 'Set an announcement to draft, pending (queue for approval), or publish (go live now).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'     => array( 'type' => 'integer' ),
						'status' => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ) ),
					),
					'required'             => array( 'id', 'status' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return fcmcp_set_status( (int) $input['id'], (string) $input['status'], 'post' );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/trash-announcement',
			array(
				'label'               => 'Trash announcement',
				'description'         => 'Move an announcement to the Trash (recoverable).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return fcmcp_trash( (int) $input['id'], 'post' );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'delete_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => true ) ) ),
			)
		);
	}
);

/**
 * Build the WP_Query args for an announcements listing (Announcements category,
 * status mapping, free-text, since-date lower bound). Split out for testability.
 */
function fcmcp_build_announcement_query_args( array $input ): array {
	$limit  = max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) );
	$status = $input['status'] ?? 'publish';
	$args   = array(
		'post_type'      => 'post',
		'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'pending', 'future' ) : $status,
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		'cat'            => fcmcp_announce_cat_id(),
	);
	if ( ! empty( $input['search'] ) ) {
		$args['s'] = sanitize_text_field( $input['search'] );
	}
	if ( ! empty( $input['since_date'] ) && fcmcp_sanitize_date( $input['since_date'] ) ) {
		$args['date_query'] = array( array( 'after' => $input['since_date'], 'inclusive' => true ) );
	}
	return $args;
}

function fcmcp_list_announcements( $input = array() ) {
	$q   = new WP_Query( fcmcp_build_announcement_query_args( (array) $input ) );
	$out = array_map( static function ( $p ) { return array_merge( fcmcp_post_to_array( $p ), fcmcp_announcement_extra( $p ) ); }, $q->posts );
	return array( 'count' => count( $out ), 'announcements' => $out );
}

/** Write the announcement call-to-action button meta (fcs_cta_text/fcs_cta_url). */
function fcmcp_apply_cta( int $post_id, array $input ): void {
	if ( array_key_exists( 'cta_text', $input ) ) {
		update_post_meta( $post_id, 'fcs_cta_text', sanitize_text_field( (string) $input['cta_text'] ) );
	}
	if ( array_key_exists( 'cta_url', $input ) ) {
		update_post_meta( $post_id, 'fcs_cta_url', esc_url_raw( (string) $input['cta_url'] ) );
	}
}

/** Write announcement lifecycle meta: fcs_weight (prominence) + fcs_expires (date). See ops/docs/happenings.md. */
function fcmcp_apply_announcement_lifecycle( int $post_id, array $input ): void {
	if ( array_key_exists( 'weight', $input ) ) {
		update_post_meta( $post_id, 'fcs_weight', absint( $input['weight'] ) );
	}
	if ( array_key_exists( 'expires', $input ) ) {
		update_post_meta( $post_id, 'fcs_expires', fcmcp_sanitize_date( (string) $input['expires'] ) );
	}
}

/** Lifecycle fields echoed back on announcement reads (kept off the shared post serializer so posts/pages don't carry them). */
function fcmcp_announcement_extra( WP_Post $post ): array {
	return array(
		'weight'  => (int) get_post_meta( $post->ID, 'fcs_weight', true ),
		'expires' => (string) get_post_meta( $post->ID, 'fcs_expires', true ),
	);
}

function fcmcp_create_announcement( $input ) {
	$cat = fcmcp_announce_cat_id();
	if ( ! $cat ) {
		return new WP_Error( 'no_category', 'Announcements category not found.' );
	}
	$post_id = wp_insert_post(
		fcmcp_apply_post_date(
			array(
				'post_type'     => 'post',
				'post_status'   => fcmcp_new_status( $input ),
				'post_title'    => sanitize_text_field( $input['title'] ),
				'post_content'  => wp_kses_post( $input['content'] ),
				'post_excerpt'  => isset( $input['excerpt'] ) ? sanitize_text_field( $input['excerpt'] ) : '',
				'post_category' => array( $cat ),
			),
			$input
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	fcmcp_apply_cta( (int) $post_id, $input );
	fcmcp_apply_announcement_lifecycle( (int) $post_id, $input );
	$result = array( 'id' => (int) $post_id, 'status' => get_post_status( $post_id ), 'edit_url' => get_edit_post_link( $post_id, 'raw' ) );
	$img    = fcmcp_set_featured_image( (int) $post_id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['announcement'] = array_merge( fcmcp_post_to_array( get_post( $post_id ) ), fcmcp_announcement_extra( get_post( $post_id ) ) );
	return $result;
}

function fcmcp_update_announcement( $input ) {
	$id   = (int) ( $input['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || 'post' !== $post->post_type || ! has_category( fcmcp_announce_cat_id(), $post ) ) {
		return new WP_Error( 'not_found', 'Announcement not found.' );
	}
	$core = array( 'ID' => $id );
	if ( array_key_exists( 'title', $input ) ) {
		$core['post_title'] = sanitize_text_field( $input['title'] );
	}
	if ( array_key_exists( 'content', $input ) ) {
		$core['post_content'] = wp_kses_post( $input['content'] );
	}
	if ( array_key_exists( 'excerpt', $input ) ) {
		$core['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
	}
	$core = fcmcp_apply_post_date( $core, $input );
	if ( count( $core ) > 1 ) {
		$r = wp_update_post( $core, true );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	fcmcp_apply_cta( $id, $input );
	fcmcp_apply_announcement_lifecycle( $id, $input );
	$result = array( 'id' => $id, 'status' => get_post_status( $id ) );
	$img    = fcmcp_set_featured_image( $id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['announcement'] = array_merge( fcmcp_post_to_array( get_post( $id ) ), fcmcp_announcement_extra( get_post( $id ) ) );
	return $result;
}
