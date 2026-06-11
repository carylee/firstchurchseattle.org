<?php
/**
 * First Church MCP Abilities — media + review queue.
 *
 * Media search/labeling and the editorial review-queue dashboard.
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

		/* ---- MEDIA: SEARCH + LABEL ---- */

		wp_register_ability(
			'firstchurch/search-media',
			array(
				'label'               => 'Search media',
				'description'         => 'Browse/search the media library (images by default). Returns id, title, alt-text label, caption, dimensions and URLs so an image can be reused by id. Use only_unlabeled=true to find images missing an alt-text label.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search'         => array( 'type' => 'string', 'description' => 'Match filename, title, caption, description, and alt-text label.' ),
						'limit'          => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
						'mime_type'      => array( 'type' => 'string', 'description' => 'MIME filter, e.g. "image" (default) or "image/png".', 'default' => 'image' ),
						'only_unlabeled' => array( 'type' => 'boolean', 'description' => 'Only return images with no alt-text label (candidates for labeling).', 'default' => false ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_search_media',
				'permission_callback' => static function () { return current_user_can( 'upload_files' ); },
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/label-image',
			array(
				'label'               => 'Label image',
				'description'         => 'Describe an image to improve future selection. Writes a concise description to the image\'s alt text (used for accessibility and search), and optionally sets caption/title. Intended for agents to label unlabeled images using their vision — get each image URL from search-media.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'      => array( 'type' => 'integer' ),
						'label'   => array( 'type' => 'string', 'description' => 'Concise description of what the image shows (becomes the alt text).' ),
						'caption' => array( 'type' => 'string', 'description' => 'Optional caption shown under the image.' ),
						'title'   => array( 'type' => 'string', 'description' => 'Optional human-friendly title.' ),
					),
					'required'             => array( 'id', 'label' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_label_image',
				'permission_callback' => static function () { return current_user_can( 'upload_files' ); },
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		/* ---- DASHBOARD: REVIEW QUEUE ---- */

		wp_register_ability(
			'firstchurch/review-queue',
			array(
				'label'               => 'Review queue',
				'description'         => 'List all draft/pending events and announcements awaiting human review and publishing — the publish queue for the draft-first workflow. Each item includes an edit URL. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'status' => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'both' ), 'default' => 'both' ),
						'types'  => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'enum' => array( 'events', 'announcements' ) ), 'description' => 'Which content types to include (default: both).' ),
						'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25, 'description' => 'Max items per type.' ),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_review_queue',
				'permission_callback' => static function () { return current_user_can( 'edit_posts' ); },
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);
	}
);

function fcmcp_media_to_array( WP_Post $post ): array {
	$alt  = (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
	$meta = wp_get_attachment_metadata( $post->ID );
	return array(
		'id'            => $post->ID,
		'title'         => get_the_title( $post ),
		'alt'           => $alt,
		'caption'       => $post->post_excerpt,
		'description'   => $post->post_content,
		'mime_type'     => $post->post_mime_type,
		'labeled'       => ( '' !== $alt ),
		'width'         => isset( $meta['width'] ) ? (int) $meta['width'] : null,
		'height'        => isset( $meta['height'] ) ? (int) $meta['height'] : null,
		'url'           => (string) wp_get_attachment_url( $post->ID ),
		'thumbnail_url' => (string) wp_get_attachment_image_url( $post->ID, 'thumbnail' ),
	);
}

function fcmcp_search_media( $input = array() ) {
	$limit     = max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) );
	$term      = isset( $input['search'] ) ? trim( (string) $input['search'] ) : '';
	$mime      = ! empty( $input['mime_type'] ) ? sanitize_text_field( $input['mime_type'] ) : 'image';
	$unlabeled = ! empty( $input['only_unlabeled'] );

	$base = array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		'post_mime_type' => $mime,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	if ( $unlabeled ) {
		$base['meta_query'] = array(
			'relation' => 'OR',
			array( 'key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '=' ),
		);
	}

	$found = array();
	if ( '' !== $term && ! $unlabeled ) {
		// Match title/caption/description/filename, then also alt-text label; merge unique.
		$qa = new WP_Query( array_merge( $base, array( 's' => $term ) ) );
		foreach ( $qa->posts as $p ) {
			$found[ $p->ID ] = $p;
		}
		$qb = new WP_Query( array_merge( $base, array( 'meta_query' => array( array( 'key' => '_wp_attachment_image_alt', 'value' => $term, 'compare' => 'LIKE' ) ) ) ) );
		foreach ( $qb->posts as $p ) {
			$found[ $p->ID ] = $p;
		}
	} else {
		if ( '' !== $term ) {
			$base['s'] = $term;
		}
		$q = new WP_Query( $base );
		foreach ( $q->posts as $p ) {
			$found[ $p->ID ] = $p;
		}
	}

	$found = array_slice( $found, 0, $limit, true );
	$out   = array_map( 'fcmcp_media_to_array', array_values( $found ) );
	return array( 'count' => count( $out ), 'media' => $out );
}

function fcmcp_label_image( $input ) {
	$id  = (int) ( $input['id'] ?? 0 );
	$att = get_post( $id );
	if ( ! $att || 'attachment' !== $att->post_type ) {
		return new WP_Error( 'not_found', 'Attachment not found.' );
	}
	if ( 0 !== strpos( (string) $att->post_mime_type, 'image/' ) ) {
		return new WP_Error( 'not_image', 'Only image attachments can be labeled.' );
	}
	if ( isset( $input['label'] ) ) {
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $input['label'] ) );
	}
	$core = array( 'ID' => $id );
	if ( array_key_exists( 'title', $input ) ) {
		$core['post_title'] = sanitize_text_field( $input['title'] );
	}
	if ( array_key_exists( 'caption', $input ) ) {
		$core['post_excerpt'] = sanitize_text_field( $input['caption'] );
	}
	if ( count( $core ) > 1 ) {
		$r = wp_update_post( $core, true );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	return fcmcp_media_to_array( get_post( $id ) );
}

function fcmcp_review_queue( $input = array() ) {
	$status_in = $input['status'] ?? 'both';
	$statuses  = 'both' === $status_in ? array( 'draft', 'pending' ) : array_values( array_intersect( array( $status_in ), array( 'draft', 'pending' ) ) );
	if ( ! $statuses ) {
		$statuses = array( 'draft', 'pending' );
	}
	$limit = max( 1, min( 100, (int) ( $input['limit'] ?? 25 ) ) );
	$types = ( ! empty( $input['types'] ) && is_array( $input['types'] ) ) ? $input['types'] : array( 'events', 'announcements' );

	$sources = array(
		'events'        => array( 'post_type' => 'ctc_event', 'label' => 'event' ),
		'announcements' => array( 'post_type' => 'post', 'label' => 'announcement', 'cat' => fcmcp_announce_cat_id() ),
	);

	$items  = array();
	$counts = array();
	foreach ( $sources as $key => $src ) {
		if ( ! in_array( $key, $types, true ) ) {
			continue;
		}
		$args = array(
			'post_type'      => $src['post_type'],
			'post_status'    => $statuses,
			'posts_per_page' => $limit,
			'no_found_rows'  => true,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);
		if ( isset( $src['cat'] ) ) {
			$args['cat'] = $src['cat'];
		}
		$q = new WP_Query( $args );
		foreach ( $q->posts as $p ) {
			$item = array(
				'type'     => $src['label'],
				'id'       => $p->ID,
				'title'    => get_the_title( $p ),
				'status'   => $p->post_status,
				'author'   => get_the_author_meta( 'display_name', $p->post_author ),
				'modified' => get_post_modified_time( 'Y-m-d H:i', false, $p ),
				'edit_url' => admin_url( 'post.php?post=' . $p->ID . '&action=edit' ),
			);
			if ( 'ctc_event' === $src['post_type'] ) {
				$item['start_date'] = (string) get_post_meta( $p->ID, '_ctc_event_start_date', true );
			}
			$items[] = $item;
		}
		$counts[ $key ] = count( $q->posts );
	}
	$counts['total'] = count( $items );

	return array( 'counts' => $counts, 'items' => $items );
}
