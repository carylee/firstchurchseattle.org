<?php
/**
 * First Church MCP Abilities — pages.
 *
 * Read + draft-first write abilities for WordPress pages.
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

		/* ---- PAGES ---- */

		wp_register_ability(
			'firstchurch/search-pages',
			array(
				'label'               => 'Search pages',
				'description'         => 'Search pages by title/content, with optional parent filter. Drafts/pending require edit permission. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
						'search' => array( 'type' => 'string' ),
						'parent' => array( 'type' => 'integer', 'description' => 'Only children of this page ID.' ),
						'status' => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'pending', 'any' ), 'default' => 'publish' ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_search_pages',
				'permission_callback' => static function ( $input = array() ) {
					$status = $input['status'] ?? 'publish';
					if ( 'publish' !== $status && ! current_user_can( 'edit_pages' ) ) {
						return new WP_Error( 'forbidden', 'Viewing non-published pages requires edit permission.' );
					}
					return current_user_can( 'read' );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/get-page',
			array(
				'label'               => 'Get page',
				'description'         => 'Get one page by ID, including full content, parent, menu order and template. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					$post = get_post( (int) $input['id'] );
					if ( ! $post || 'page' !== $post->post_type ) {
						return new WP_Error( 'not_found', 'Page not found.' );
					}
					if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) {
						return new WP_Error( 'forbidden', 'Not permitted to view this page.' );
					}
					$data            = fcmcp_page_to_array( $post );
					$data['content'] = apply_filters( 'the_content', $post->post_content );
					return $data;
				},
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/create-page',
			array(
				'label'               => 'Create page',
				'description'         => 'Create a page. Defaults to a DRAFT; set status=pending or status=publish. Supports parent (page ID), menu_order, and template (theme page-template file).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title'      => array( 'type' => 'string' ),
						'content'    => array( 'type' => 'string' ),
						'excerpt'    => array( 'type' => 'string' ),
						'parent'     => array( 'type' => 'integer', 'description' => 'Parent page ID.' ),
						'menu_order' => array( 'type' => 'integer' ),
						'template'   => array( 'type' => 'string', 'description' => 'Page template file, e.g. page-templates/foo.php.' ),
						'image_id'   => array( 'type' => 'integer' ),
						'image_url'  => array( 'type' => 'string' ),
						'date'       => array( 'type' => 'string', 'description' => 'Publication date/time as YYYY-MM-DD or YYYY-MM-DD HH:MM (site local). Past backdates; future schedules it to auto-publish then. Defaults to now.' ),
						'status'     => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ), 'default' => 'draft' ),
					),
					'required'             => array( 'title' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_create_page',
				'permission_callback' => static function () { return current_user_can( 'edit_pages' ); },
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/update-page',
			array(
				'label'               => 'Update page',
				'description'         => 'Update an existing page by ID (title/content/excerpt/parent/menu_order/template/image). Does not change publish status (use set-page-status).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'         => array( 'type' => 'integer' ),
						'title'      => array( 'type' => 'string' ),
						'content'    => array( 'type' => 'string' ),
						'excerpt'    => array( 'type' => 'string' ),
						'parent'     => array( 'type' => 'integer' ),
						'menu_order' => array( 'type' => 'integer' ),
						'template'   => array( 'type' => 'string' ),
						'image_id'   => array( 'type' => 'integer' ),
						'image_url'  => array( 'type' => 'string' ),
						'date'       => array( 'type' => 'string', 'description' => 'Publication date/time as YYYY-MM-DD or YYYY-MM-DD HH:MM (site local). Past backdates; future schedules it to auto-publish then.' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_update_page',
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/set-page-status',
			array(
				'label'               => 'Set page status',
				'description'         => 'Set a page to draft, pending (queue for approval), or publish (go live now).',
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
					return fcmcp_set_status( (int) $input['id'], (string) $input['status'], 'page' );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/trash-page',
			array(
				'label'               => 'Trash page',
				'description'         => 'Move a page to the Trash (recoverable).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return fcmcp_trash( (int) $input['id'], 'page' );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'delete_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => true ) ) ),
			)
		);
	}
);

function fcmcp_page_to_array( WP_Post $post ): array {
	$tpl = (string) get_post_meta( $post->ID, '_wp_page_template', true );
	return array(
		'id'             => $post->ID,
		'title'          => get_the_title( $post ),
		'status'         => $post->post_status,
		'parent'         => (int) $post->post_parent,
		'menu_order'     => (int) $post->menu_order,
		'template'       => $tpl ?: 'default',
		'excerpt'        => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ),
		'featured_image' => (string) get_the_post_thumbnail_url( $post, 'full' ),
		'url'            => (string) get_permalink( $post ),
		'edit_url'       => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
	);
}

/**
 * Build the WP_Query args for a page search (status mapping, free-text, parent
 * filter, menu-order/title ordering). Split out for unit-testability.
 */
function fcmcp_build_page_query_args( array $input ): array {
	$limit  = max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) );
	$status = $input['status'] ?? 'publish';
	$args   = array(
		'post_type'      => 'page',
		'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'pending', 'future' ) : $status,
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
	);
	if ( ! empty( $input['search'] ) ) {
		$args['s'] = sanitize_text_field( $input['search'] );
	}
	if ( isset( $input['parent'] ) ) {
		$args['post_parent'] = (int) $input['parent'];
	}
	return $args;
}

function fcmcp_search_pages( $input = array() ) {
	$q   = new WP_Query( fcmcp_build_page_query_args( (array) $input ) );
	$out = array_map( 'fcmcp_page_to_array', $q->posts );
	return array( 'count' => count( $out ), 'pages' => $out );
}

function fcmcp_create_page( $input ) {
	$arr = array(
		'post_type'    => 'page',
		'post_status'  => fcmcp_new_status( $input ),
		'post_title'   => sanitize_text_field( $input['title'] ),
		'post_content' => wp_kses_post( $input['content'] ?? '' ),
		'post_excerpt' => isset( $input['excerpt'] ) ? sanitize_text_field( $input['excerpt'] ) : '',
	);
	if ( isset( $input['parent'] ) ) {
		$arr['post_parent'] = (int) $input['parent'];
	}
	if ( isset( $input['menu_order'] ) ) {
		$arr['menu_order'] = (int) $input['menu_order'];
	}
	if ( ! empty( $input['template'] ) ) {
		$arr['page_template'] = sanitize_text_field( $input['template'] );
	}
	$arr     = fcmcp_apply_post_date( $arr, $input );
	$post_id = wp_insert_post( $arr, true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	$result = array( 'id' => (int) $post_id, 'status' => get_post_status( $post_id ), 'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
	$img    = fcmcp_set_featured_image( (int) $post_id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['page'] = fcmcp_page_to_array( get_post( $post_id ) );
	return $result;
}

function fcmcp_update_page( $input ) {
	$id   = (int) ( $input['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || 'page' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Page not found.' );
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
	if ( array_key_exists( 'parent', $input ) ) {
		$core['post_parent'] = (int) $input['parent'];
	}
	if ( array_key_exists( 'menu_order', $input ) ) {
		$core['menu_order'] = (int) $input['menu_order'];
	}
	$core = fcmcp_apply_post_date( $core, $input );
	if ( count( $core ) > 1 ) {
		$r = wp_update_post( $core, true );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	if ( array_key_exists( 'template', $input ) ) {
		update_post_meta( $id, '_wp_page_template', sanitize_text_field( $input['template'] ) );
	}
	$result = array( 'id' => $id, 'status' => get_post_status( $id ) );
	$img    = fcmcp_set_featured_image( $id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['page'] = fcmcp_page_to_array( get_post( $id ) );
	return $result;
}
