<?php
/**
 * First Church MCP Abilities — posts.
 *
 * Read + draft-first write abilities for general blog posts (any category).
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

		/* ---- POSTS (general blog posts, any category) ---- */

		wp_register_ability(
			'firstchurch/search-posts',
			array(
				'label'               => 'Search posts',
				'description'         => 'Search blog posts (any category), newest first. Filter by category slug, free text, and status. Drafts/pending require edit permission. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit'      => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
						'search'     => array( 'type' => 'string' ),
						'category'   => array( 'type' => 'string', 'description' => 'category slug' ),
						'since_date' => array( 'type' => 'string', 'description' => 'YYYY-MM-DD lower bound' ),
						'status'     => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'pending', 'any' ), 'default' => 'publish' ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_search_posts',
				'permission_callback' => static function ( $input = array() ) {
					$status = $input['status'] ?? 'publish';
					if ( 'publish' !== $status && ! current_user_can( 'edit_posts' ) ) {
						return new WP_Error( 'forbidden', 'Viewing non-published posts requires edit permission.' );
					}
					return current_user_can( 'read' );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/get-post',
			array(
				'label'               => 'Get post',
				'description'         => 'Get one blog post by ID, including full content. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					$post = get_post( (int) $input['id'] );
					if ( ! $post || 'post' !== $post->post_type ) {
						return new WP_Error( 'not_found', 'Post not found.' );
					}
					if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) {
						return new WP_Error( 'forbidden', 'Not permitted to view this post.' );
					}
					$data            = fcmcp_post_to_array( $post );
					$data['content'] = apply_filters( 'the_content', $post->post_content );
					$data['tags']    = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'slugs' ) );
					return $data;
				},
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/list-post-categories',
			array(
				'label'               => 'List post categories',
				'description'         => 'List blog post categories (slug, name, count). Read-only.',
				'category'            => 'firstchurch',
				'execute_callback'    => static function () {
					$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
					$out   = array();
					foreach ( $terms as $t ) {
						$out[] = array( 'slug' => $t->slug, 'name' => $t->name, 'count' => (int) $t->count );
					}
					return array( 'categories' => $out );
				},
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/create-post',
			array(
				'label'               => 'Create post',
				'description'         => 'Create a blog post. Defaults to a DRAFT; set status=pending (queue for approval) or status=publish (go live). Categories/tags accept names or slugs (created if missing).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title'      => array( 'type' => 'string' ),
						'content'    => array( 'type' => 'string', 'description' => 'Body (may contain basic HTML).' ),
						'excerpt'    => array( 'type' => 'string' ),
						'categories' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'tags'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'image_id'   => array( 'type' => 'integer' ),
						'image_url'  => array( 'type' => 'string' ),
						'date'       => array( 'type' => 'string', 'description' => 'Publication date/time as YYYY-MM-DD or YYYY-MM-DD HH:MM (site local). Past backdates; future schedules it to auto-publish then. Defaults to now.' ),
						'status'     => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ), 'default' => 'draft' ),
					),
					'required'             => array( 'title', 'content' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_create_post',
				'permission_callback' => $can_edit,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/update-post',
			array(
				'label'               => 'Update post',
				'description'         => 'Update an existing blog post by ID (title/content/excerpt/categories/tags/image). Does not change publish status (use set-post-status). Provided categories/tags replace existing.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'         => array( 'type' => 'integer' ),
						'title'      => array( 'type' => 'string' ),
						'content'    => array( 'type' => 'string' ),
						'excerpt'    => array( 'type' => 'string' ),
						'categories' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'tags'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'image_id'   => array( 'type' => 'integer' ),
						'image_url'  => array( 'type' => 'string' ),
						'date'       => array( 'type' => 'string', 'description' => 'Publication date/time as YYYY-MM-DD or YYYY-MM-DD HH:MM (site local). Past backdates; future schedules it to auto-publish then.' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_update_post',
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/set-post-status',
			array(
				'label'               => 'Set post status',
				'description'         => 'Set a blog post to draft, pending (queue for approval), or publish (go live now).',
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
			'firstchurch/trash-post',
			array(
				'label'               => 'Trash post',
				'description'         => 'Move a blog post to the Trash (recoverable).',
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
 * Build the WP_Query args for a blog-post search (status mapping, free-text,
 * category slug, since-date lower bound). Split out for unit-testability.
 */
function fcmcp_build_post_query_args( array $input ): array {
	$limit  = max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) );
	$status = $input['status'] ?? 'publish';
	$args   = array(
		'post_type'      => 'post',
		'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'pending', 'future' ) : $status,
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
	);
	if ( ! empty( $input['search'] ) ) {
		$args['s'] = sanitize_text_field( $input['search'] );
	}
	if ( ! empty( $input['category'] ) ) {
		$args['category_name'] = sanitize_title( $input['category'] );
	}
	if ( ! empty( $input['since_date'] ) && fcmcp_sanitize_date( $input['since_date'] ) ) {
		$args['date_query'] = array( array( 'after' => $input['since_date'], 'inclusive' => true ) );
	}
	return $args;
}

function fcmcp_search_posts( $input = array() ) {
	$q   = new WP_Query( fcmcp_build_post_query_args( (array) $input ) );
	$out = array_map( 'fcmcp_post_to_array', $q->posts );
	return array( 'count' => count( $out ), 'posts' => $out );
}

function fcmcp_create_post( $input ) {
	$post_id = wp_insert_post( fcmcp_apply_post_date( array(
		'post_type'    => 'post',
		'post_status'  => fcmcp_new_status( $input ),
		'post_title'   => sanitize_text_field( $input['title'] ),
		'post_content' => wp_kses_post( $input['content'] ?? '' ),
		'post_excerpt' => isset( $input['excerpt'] ) ? sanitize_text_field( $input['excerpt'] ) : '',
	), $input ), true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	if ( array_key_exists( 'categories', $input ) ) {
		wp_set_object_terms( $post_id, fcmcp_resolve_terms( 'category', $input['categories'] ), 'category', false );
	}
	if ( array_key_exists( 'tags', $input ) ) {
		wp_set_object_terms( $post_id, fcmcp_resolve_terms( 'post_tag', $input['tags'] ), 'post_tag', false );
	}
	$result = array( 'id' => (int) $post_id, 'status' => get_post_status( $post_id ), 'edit_url' => get_edit_post_link( $post_id, 'raw' ) );
	$img    = fcmcp_set_featured_image( (int) $post_id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['post'] = fcmcp_post_to_array( get_post( $post_id ) );
	return $result;
}

function fcmcp_update_post( $input ) {
	$id   = (int) ( $input['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || 'post' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Post not found.' );
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
	if ( array_key_exists( 'categories', $input ) ) {
		wp_set_object_terms( $id, fcmcp_resolve_terms( 'category', $input['categories'] ), 'category', false );
	}
	if ( array_key_exists( 'tags', $input ) ) {
		wp_set_object_terms( $id, fcmcp_resolve_terms( 'post_tag', $input['tags'] ), 'post_tag', false );
	}
	$result = array( 'id' => $id, 'status' => get_post_status( $id ) );
	$img    = fcmcp_set_featured_image( $id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['post'] = fcmcp_post_to_array( get_post( $id ) );
	return $result;
}

