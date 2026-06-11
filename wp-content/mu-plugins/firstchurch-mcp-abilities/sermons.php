<?php
/**
 * First Church MCP Abilities — sermons.
 *
 * Read + draft-first write abilities for ctc_sermon, plus taxonomy resolution.
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

		/* ---- SERMONS: READ ---- */

		wp_register_ability(
			'firstchurch/search-sermons',
			array(
				'label'               => 'Search sermons',
				'description'         => 'Search sermons (ctc_sermon), newest first. Filter by series/speaker/topic/book/tag slug, free text, and status. Drafts/pending require edit permission. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit'   => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
						'search'  => array( 'type' => 'string' ),
						'series'  => array( 'type' => 'string', 'description' => 'ctc_sermon_series slug' ),
						'speaker' => array( 'type' => 'string', 'description' => 'ctc_sermon_speaker slug' ),
						'topic'   => array( 'type' => 'string', 'description' => 'ctc_sermon_topic slug' ),
						'book'    => array( 'type' => 'string', 'description' => 'ctc_sermon_book slug' ),
						'tag'     => array( 'type' => 'string', 'description' => 'ctc_sermon_tag slug' ),
						'status'  => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'pending', 'any' ), 'default' => 'publish' ),
						'order'   => array( 'type' => 'string', 'enum' => array( 'asc', 'desc' ), 'default' => 'desc' ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_search_sermons',
				'permission_callback' => static function ( $input = array() ) {
					$status = $input['status'] ?? 'publish';
					if ( 'publish' !== $status && ! current_user_can( 'edit_posts' ) ) {
						return new WP_Error( 'forbidden', 'Viewing non-published sermons requires edit permission.' );
					}
					return current_user_can( 'read' );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/get-sermon',
			array(
				'label'               => 'Get sermon',
				'description'         => 'Get full detail for one sermon by ID, including video/audio/pdf and taxonomies. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					$post = get_post( (int) $input['id'] );
					if ( ! $post || 'ctc_sermon' !== $post->post_type ) {
						return new WP_Error( 'not_found', 'Sermon not found.' );
					}
					if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) {
						return new WP_Error( 'forbidden', 'Not permitted to view this sermon.' );
					}
					$data            = fcmcp_sermon_to_array( $post );
					$data['content'] = apply_filters( 'the_content', $post->post_content );
					return $data;
				},
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/list-sermon-terms',
			array(
				'label'               => 'List sermon terms',
				'description'         => 'List the terms of a sermon taxonomy (series, speaker, topic, book, or tag) with slug, name, and count. Use to find valid filter/assignment values. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'taxonomy' => array( 'type' => 'string', 'enum' => array( 'series', 'speaker', 'topic', 'book', 'tag' ) ),
					),
					'required'             => array( 'taxonomy' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					$map = array( 'series' => 'ctc_sermon_series', 'speaker' => 'ctc_sermon_speaker', 'topic' => 'ctc_sermon_topic', 'book' => 'ctc_sermon_book', 'tag' => 'ctc_sermon_tag' );
					$tax = $map[ $input['taxonomy'] ] ?? '';
					if ( ! $tax ) {
						return new WP_Error( 'bad_taxonomy', 'Unknown taxonomy.' );
					}
					$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
					$out   = array();
					foreach ( $terms as $t ) {
						$out[] = array( 'slug' => $t->slug, 'name' => $t->name, 'count' => (int) $t->count );
					}
					return array( 'taxonomy' => $input['taxonomy'], 'terms' => $out );
				},
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		/* ---- SERMONS: WRITE ---- */

		wp_register_ability(
			'firstchurch/create-sermon',
			array(
				'label'               => 'Create sermon',
				'description'         => 'Create a sermon. Defaults to a DRAFT; set status=pending to queue for approval or status=publish to go live. The sermon date is the post date. Series/speaker/topic/book/tag accept names or slugs (created if missing).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title'       => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string', 'description' => 'Sermon body/notes (may contain basic HTML).' ),
						'excerpt'     => array( 'type' => 'string' ),
						'date'        => array( 'type' => 'string', 'description' => 'Sermon date / post date as YYYY-MM-DD or YYYY-MM-DD HH:MM (defaults to today). Past backdates; future schedules it to auto-publish then.' ),
						'video'       => array( 'type' => 'string', 'description' => 'Video URL (e.g. YouTube).' ),
						'audio'       => array( 'type' => 'string', 'description' => 'Audio URL.' ),
						'pdf'         => array( 'type' => 'string', 'description' => 'PDF/notes URL.' ),
						'series'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'speakers'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'topics'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'books'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'tags'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'image_id'    => array( 'type' => 'integer', 'description' => 'Existing attachment ID for featured image.' ),
						'image_url'   => array( 'type' => 'string', 'description' => 'Image URL to download and set as featured image.' ),
						'status'      => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ), 'default' => 'draft' ),
					),
					'required'             => array( 'title' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_create_sermon',
				'permission_callback' => $can_edit,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/update-sermon',
			array(
				'label'               => 'Update sermon',
				'description'         => 'Update fields of an existing sermon by ID. Does not change publish status (use set-sermon-status). Provided taxonomies replace existing ones for that taxonomy.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'          => array( 'type' => 'integer' ),
						'title'       => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'excerpt'     => array( 'type' => 'string' ),
						'date'        => array( 'type' => 'string', 'description' => 'Sermon date / post date as YYYY-MM-DD or YYYY-MM-DD HH:MM. Past backdates; future schedules it to auto-publish then.' ),
						'video'       => array( 'type' => 'string' ),
						'audio'       => array( 'type' => 'string' ),
						'pdf'         => array( 'type' => 'string' ),
						'series'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'speakers'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'topics'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'books'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'tags'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'image_id'    => array( 'type' => 'integer' ),
						'image_url'   => array( 'type' => 'string' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_update_sermon',
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/set-sermon-status',
			array(
				'label'               => 'Set sermon status',
				'description'         => 'Set a sermon to draft, pending (queue for approval), or publish (go live now).',
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
					return fcmcp_set_status( (int) $input['id'], (string) $input['status'], 'ctc_sermon' );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'edit_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/trash-sermon',
			array(
				'label'               => 'Trash sermon',
				'description'         => 'Move a sermon to the Trash (recoverable).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return fcmcp_trash( (int) $input['id'], 'ctc_sermon' );
				},
				'permission_callback' => static function ( $input = array() ) {
					return isset( $input['id'] ) && current_user_can( 'delete_post', (int) $input['id'] );
				},
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => true ) ) ),
			)
		);
	}
);

function fcmcp_sermon_to_array( WP_Post $post ): array {
	return array(
		'id'             => $post->ID,
		'title'          => get_the_title( $post ),
		'status'         => $post->post_status,
		'date'           => get_post_time( 'Y-m-d', false, $post ),
		'video'          => (string) get_post_meta( $post->ID, '_ctc_sermon_video', true ),
		'audio'          => (string) get_post_meta( $post->ID, '_ctc_sermon_audio', true ),
		'pdf'            => (string) get_post_meta( $post->ID, '_ctc_sermon_pdf', true ),
		'series'         => wp_get_post_terms( $post->ID, 'ctc_sermon_series', array( 'fields' => 'names' ) ),
		'speakers'       => wp_get_post_terms( $post->ID, 'ctc_sermon_speaker', array( 'fields' => 'names' ) ),
		'topics'         => wp_get_post_terms( $post->ID, 'ctc_sermon_topic', array( 'fields' => 'names' ) ),
		'books'          => wp_get_post_terms( $post->ID, 'ctc_sermon_book', array( 'fields' => 'names' ) ),
		'tags'           => wp_get_post_terms( $post->ID, 'ctc_sermon_tag', array( 'fields' => 'names' ) ),
		'excerpt'        => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ),
		'featured_image' => (string) get_the_post_thumbnail_url( $post, 'full' ),
		'url'            => (string) get_permalink( $post ),
		'edit_url'       => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
	);
}

/** Resolve a list of names/slugs to term IDs in a taxonomy, creating missing terms. */
function fcmcp_resolve_terms( string $taxonomy, $values ): array {
	$ids = array();
	foreach ( (array) $values as $v ) {
		$v = trim( (string) $v );
		if ( '' === $v ) {
			continue;
		}
		$term = get_term_by( 'slug', sanitize_title( $v ), $taxonomy );
		if ( ! $term ) {
			$term = get_term_by( 'name', $v, $taxonomy );
		}
		if ( $term ) {
			$ids[] = (int) $term->term_id;
		} else {
			$new = wp_insert_term( $v, $taxonomy );
			if ( ! is_wp_error( $new ) ) {
				$ids[] = (int) $new['term_id'];
			}
		}
	}
	return $ids;
}

function fcmcp_apply_sermon_fields( int $post_id, array $input ): void {
	$meta = array( 'video' => '_ctc_sermon_video', 'audio' => '_ctc_sermon_audio', 'pdf' => '_ctc_sermon_pdf' );
	foreach ( $meta as $field => $key ) {
		if ( array_key_exists( $field, $input ) ) {
			update_post_meta( $post_id, $key, esc_url_raw( (string) $input[ $field ] ) );
		}
	}
	$tax = array(
		'series'   => 'ctc_sermon_series',
		'speakers' => 'ctc_sermon_speaker',
		'topics'   => 'ctc_sermon_topic',
		'books'    => 'ctc_sermon_book',
		'tags'     => 'ctc_sermon_tag',
	);
	foreach ( $tax as $field => $taxonomy ) {
		if ( array_key_exists( $field, $input ) ) {
			wp_set_object_terms( $post_id, fcmcp_resolve_terms( $taxonomy, $input[ $field ] ), $taxonomy, false );
		}
	}
}

/**
 * Build the WP_Query args for a sermon search (status mapping, free-text, and
 * the AND-combined taxonomy filters). Split out for unit-testability.
 */
function fcmcp_build_sermon_query_args( array $input ): array {
	$limit  = max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) );
	$status = $input['status'] ?? 'publish';
	$order  = ( isset( $input['order'] ) && 'asc' === strtolower( $input['order'] ) ) ? 'ASC' : 'DESC';

	$args = array(
		'post_type'      => 'ctc_sermon',
		'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'pending', 'future' ) : $status,
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		'orderby'        => 'date',
		'order'          => $order,
	);
	if ( ! empty( $input['search'] ) ) {
		$args['s'] = sanitize_text_field( $input['search'] );
	}
	$tax_map   = array( 'series' => 'ctc_sermon_series', 'speaker' => 'ctc_sermon_speaker', 'topic' => 'ctc_sermon_topic', 'book' => 'ctc_sermon_book', 'tag' => 'ctc_sermon_tag' );
	$tax_query = array();
	foreach ( $tax_map as $field => $taxonomy ) {
		if ( ! empty( $input[ $field ] ) ) {
			$tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => sanitize_title( $input[ $field ] ) );
		}
	}
	if ( $tax_query ) {
		$tax_query['relation'] = 'AND';
		$args['tax_query']     = $tax_query;
	}
	return $args;
}

function fcmcp_search_sermons( $input = array() ) {
	$q   = new WP_Query( fcmcp_build_sermon_query_args( (array) $input ) );
	$out = array_map( 'fcmcp_sermon_to_array', $q->posts );
	return array( 'count' => count( $out ), 'sermons' => $out );
}

function fcmcp_create_sermon( $input ) {
	$postarr = array(
		'post_type'    => 'ctc_sermon',
		'post_status'  => fcmcp_new_status( $input ),
		'post_title'   => sanitize_text_field( $input['title'] ),
		'post_content' => wp_kses_post( $input['description'] ?? '' ),
		'post_excerpt' => isset( $input['excerpt'] ) ? sanitize_text_field( $input['excerpt'] ) : '',
	);
	$postarr = fcmcp_apply_post_date( $postarr, $input );
	$post_id = wp_insert_post( $postarr, true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	fcmcp_apply_sermon_fields( (int) $post_id, $input );

	$result = array( 'id' => (int) $post_id, 'status' => get_post_status( $post_id ), 'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
	$img    = fcmcp_set_featured_image( (int) $post_id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['sermon'] = fcmcp_sermon_to_array( get_post( $post_id ) );
	return $result;
}

function fcmcp_update_sermon( $input ) {
	$id   = (int) ( $input['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || 'ctc_sermon' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Sermon not found.' );
	}
	$core = array( 'ID' => $id );
	if ( array_key_exists( 'title', $input ) ) {
		$core['post_title'] = sanitize_text_field( $input['title'] );
	}
	if ( array_key_exists( 'description', $input ) ) {
		$core['post_content'] = wp_kses_post( $input['description'] );
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
	fcmcp_apply_sermon_fields( $id, $input );

	$result = array( 'id' => $id, 'status' => get_post_status( $id ) );
	$img    = fcmcp_set_featured_image( $id, $input );
	if ( is_wp_error( $img ) ) {
		$result['image_warning'] = $img->get_error_message();
	}
	$result['sermon'] = fcmcp_sermon_to_array( get_post( $id ) );
	return $result;
}
