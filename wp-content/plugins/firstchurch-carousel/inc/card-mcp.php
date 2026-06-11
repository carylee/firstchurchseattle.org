<?php
/**
 * MCP authoring for standing (evergreen) carousel cards — the write side of the
 * carousel "source of truth" (inc/mcp.php exposes only the resolved read feed).
 * Lets an agent curate the reusable cards (intro/dividers/QR callouts/
 * housekeeping) that project to the lobby slides, /engage, and the e-news.
 *
 * Every write runs through the SAME fccar_sanitize_card_input() +
 * fccar_apply_card_meta() the classic metabox and the Curate drawer use, so the
 * three authoring paths can't drift. Draft-first: new cards default to draft so
 * nothing hits the live lobby loop without a human (or an explicit publish).
 *
 * Cards are edited by the mcp_editor credential, so the mu-plugin lists
 * carousel_card in fcmcp_is_managed_post (its map_meta_cap gate); see
 * firstchurch-mcp-abilities/helpers.php.
 *
 * @package FirstChurch\Carousel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The card field names the agent may set (form-shape, no `fccar_` prefix). */
const FCCAR_CARD_FIELDS = array( 'title', 'layout', 'body', 'prompt', 'details', 'qr_url', 'bg_color', 'preservice' );

/**
 * Pick only the card fields actually present in $input — the partial-update
 * overlay. Pure: provided keys win, everything else is left to the current
 * value (or sanitize defaults on create).
 *
 * @param array<string,mixed> $input
 * @return array<string,mixed>
 */
function fccar_pick_card_fields( array $input ): array {
	$out = array();
	foreach ( FCCAR_CARD_FIELDS as $k ) {
		if ( array_key_exists( $k, $input ) ) {
			$out[ $k ] = $input[ $k ];
		}
	}
	return $out;
}

/** The current card's fields, in raw (pre-sanitize) form, for a partial update. */
function fccar_card_current_raw( int $id ): array {
	return array(
		'title'      => get_the_title( $id ),
		'layout'     => (string) get_post_meta( $id, FCCAR_META_LAYOUT, true ),
		'body'       => (string) get_post_meta( $id, FCCAR_META_BODY, true ),
		'prompt'     => (string) get_post_meta( $id, FCCAR_META_PROMPT, true ),
		'details'    => (string) get_post_meta( $id, FCCAR_META_DETAILS, true ),
		'qr_url'     => (string) get_post_meta( $id, FCCAR_META_QR, true ),
		'bg_color'   => (string) get_post_meta( $id, FCCAR_META_BGCOLOR, true ),
		'preservice' => (bool) get_post_meta( $id, FCCAR_META_PRESVC, true ),
	);
}

/** Compact MCP shape for one standing card. */
function fccar_card_mcp_shape( WP_Post $p ): array {
	return array(
		'id'         => $p->ID,
		'feed_id'    => 'card-' . $p->ID,
		'title'      => get_the_title( $p ),
		'status'     => $p->post_status,
		'order'      => (int) $p->menu_order,
		'layout'     => (string) get_post_meta( $p->ID, FCCAR_META_LAYOUT, true ) ?: 'info',
		'body'       => (string) get_post_meta( $p->ID, FCCAR_META_BODY, true ),
		'prompt'     => (string) get_post_meta( $p->ID, FCCAR_META_PROMPT, true ),
		'details'    => (string) get_post_meta( $p->ID, FCCAR_META_DETAILS, true ),
		'qr_url'     => (string) get_post_meta( $p->ID, FCCAR_META_QR, true ),
		'bg_color'   => (string) get_post_meta( $p->ID, FCCAR_META_BGCOLOR, true ),
		'preservice' => (bool) get_post_meta( $p->ID, FCCAR_META_PRESVC, true ),
		'image_id'   => (int) get_post_thumbnail_id( $p ),
		'image'      => (string) get_the_post_thumbnail_url( $p, 'full' ),
		'edit_url'   => (string) get_edit_post_link( $p->ID, 'raw' ),
	);
}

/** Resolve a card id to its post or a WP_Error. */
function fccar_mcp_require_card( $input ) {
	$id   = (int) ( $input['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || FCCAR_CPT !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Standing card not found.' );
	}
	return $post;
}

/** Normalize a requested status to draft|publish (draft default). */
function fccar_mcp_status( $value ): string {
	return 'publish' === strtolower( (string) $value ) ? 'publish' : 'draft';
}

/* ---- Callbacks ---------------------------------------------------------- */

function fccar_mcp_list_cards( $input = array() ) {
	$status = $input['status'] ?? 'any';
	$posts  = get_posts( array(
		'post_type'      => FCCAR_CPT,
		'post_status'    => ( 'any' === $status ) ? array( 'publish', 'draft', 'pending' ) : $status,
		'posts_per_page' => max( 1, min( 100, (int) ( $input['limit'] ?? 50 ) ) ),
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
	) );
	return array(
		'count' => count( $posts ),
		'items' => array_map( 'fccar_card_mcp_shape', $posts ),
	);
}

function fccar_mcp_create_card( $input ) {
	$clean = fccar_sanitize_card_input( fccar_pick_card_fields( $input ) );
	if ( '' === $clean['title'] && '' === $clean['body'] && '' === $clean['prompt'] && '' === $clean['details'] ) {
		return new WP_Error( 'empty_card', 'Add a title, body, prompt, or details before saving.' );
	}
	$id = wp_insert_post( array(
		'post_type'   => FCCAR_CPT,
		'post_status' => fccar_mcp_status( $input['status'] ?? 'draft' ),
		'post_title'  => $clean['title'],
		'menu_order'  => isset( $input['order'] ) ? (int) $input['order'] : 0,
	), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	fccar_apply_card_meta( (int) $id, $clean );
	if ( array_key_exists( 'image_id', $input ) && (int) $input['image_id'] > 0 ) {
		set_post_thumbnail( (int) $id, (int) $input['image_id'] );
	}
	return fccar_card_mcp_shape( get_post( (int) $id ) );
}

function fccar_mcp_update_card( $input ) {
	$post = fccar_mcp_require_card( $input );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$merged = array_merge( fccar_card_current_raw( $post->ID ), fccar_pick_card_fields( $input ) );
	$clean  = fccar_sanitize_card_input( $merged );

	$update = array( 'ID' => $post->ID );
	if ( array_key_exists( 'title', $input ) ) {
		$update['post_title'] = $clean['title'];
	}
	if ( array_key_exists( 'status', $input ) ) {
		$update['post_status'] = fccar_mcp_status( $input['status'] );
	}
	if ( array_key_exists( 'order', $input ) ) {
		$update['menu_order'] = (int) $input['order'];
	}
	if ( count( $update ) > 1 ) {
		$r = wp_update_post( $update, true );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	fccar_apply_card_meta( $post->ID, $clean );
	if ( array_key_exists( 'image_id', $input ) ) {
		if ( (int) $input['image_id'] > 0 ) {
			set_post_thumbnail( $post->ID, (int) $input['image_id'] );
		} else {
			delete_post_thumbnail( $post->ID );
		}
	}
	return fccar_card_mcp_shape( get_post( $post->ID ) );
}

function fccar_mcp_set_card_status( $input ) {
	$post = fccar_mcp_require_card( $input );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$r = wp_update_post( array( 'ID' => $post->ID, 'post_status' => fccar_mcp_status( $input['status'] ?? 'draft' ) ), true );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	return array( 'id' => $post->ID, 'status' => get_post_status( $post->ID ) );
}

function fccar_mcp_reorder_cards( $input ) {
	$ids = array();
	foreach ( (array) ( $input['ids'] ?? array() ) as $i ) {
		$id   = (int) $i;
		$post = get_post( $id );
		if ( ! $post || FCCAR_CPT !== $post->post_type ) {
			return new WP_Error( 'not_found', "Card {$id} not found." );
		}
		$ids[] = $id;
	}
	if ( ! $ids ) {
		return new WP_Error( 'bad_input', 'ids must be a non-empty list of card ids in the desired order.' );
	}
	$order = 0;
	foreach ( $ids as $id ) {
		wp_update_post( array( 'ID' => $id, 'menu_order' => $order++ ) );
	}
	return array( 'ordered' => $ids );
}

function fccar_mcp_trash_card( $input ) {
	$post = fccar_mcp_require_card( $input );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	if ( ! wp_trash_post( $post->ID ) ) {
		return new WP_Error( 'trash_failed', 'Could not trash the card.' );
	}
	return array( 'id' => $post->ID, 'status' => 'trash' );
}

/* ---- Abilities ---------------------------------------------------------- */

add_action( 'wp_abilities_api_init', static function () {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	$public   = array( 'mcp' => array( 'public' => true ) );
	$can_edit = static fn () => current_user_can( 'edit_posts' );
	$can_one  = static fn ( $i = array() ) => isset( $i['id'] ) && current_user_can( 'edit_post', (int) $i['id'] );

	$card_fields = array(
		'title'      => array( 'type' => 'string', 'description' => 'Card title (the post title).' ),
		'layout'     => array( 'type' => 'string', 'enum' => FCCAR_LAYOUTS, 'description' => 'Slide layout.' ),
		'body'       => array( 'type' => 'string', 'description' => 'Body text. For info cards, one "- " per bulleted line.' ),
		'prompt'     => array( 'type' => 'string', 'description' => 'qr_callout prompt text.' ),
		'details'    => array( 'type' => 'string', 'description' => 'feature layout — the italic detail line.' ),
		'qr_url'     => array( 'type' => 'string', 'description' => 'The card\'s QR-code target URL.' ),
		'bg_color'   => array( 'type' => 'string', 'description' => 'Solid background fallback hex, e.g. #7FA888.' ),
		'preservice' => array( 'type' => 'boolean', 'description' => 'Preservice-only (dropped from the post-service loop).' ),
		'image_id'   => array( 'type' => 'integer', 'description' => 'Featured-image (background) attachment id; 0 clears it.' ),
		'order'      => array( 'type' => 'integer', 'description' => 'Deck position (menu_order); lower shows first.' ),
	);

	wp_register_ability( 'firstchurch/list-carousel-cards', array(
		'label'               => 'List standing carousel cards',
		'description'         => 'List the evergreen standing cards (intro, dividers, QR callouts, housekeeping) in deck order, with all fields. The reusable source the lobby carousel, /engage, and e-news draw from. Read-only. (Use get-carousel for the full resolved feed incl. events + news.)',
		'category'            => 'firstchurch',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array(
				'status' => array( 'type' => 'string', 'enum' => array( 'any', 'draft', 'publish', 'pending' ), 'default' => 'any' ),
				'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback'    => 'fccar_mcp_list_cards',
		'permission_callback' => $can_edit,
		'meta'                => array_merge( $public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
	) );

	wp_register_ability( 'firstchurch/create-carousel-card', array(
		'label'               => 'Create standing carousel card',
		'description'         => 'Create an evergreen standing card. Draft-first: defaults to draft so it stays off the live lobby loop until published. Needs at least a title, body, prompt, or details.',
		'category'            => 'firstchurch',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array_merge( $card_fields, array(
				'status' => array( 'type' => 'string', 'enum' => array( 'draft', 'publish' ), 'default' => 'draft' ),
			) ),
			'additionalProperties' => false,
		),
		'execute_callback'    => 'fccar_mcp_create_card',
		'permission_callback' => $can_edit,
		'meta'                => array_merge( $public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ) ),
	) );

	wp_register_ability( 'firstchurch/update-carousel-card', array(
		'label'               => 'Update standing carousel card',
		'description'         => 'Update a standing card by id (partial — only the fields you pass change). Validation matches the metabox + Curate drawer.',
		'category'            => 'firstchurch',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array_merge(
				array( 'id' => array( 'type' => 'integer' ) ),
				$card_fields,
				array( 'status' => array( 'type' => 'string', 'enum' => array( 'draft', 'publish' ) ) )
			),
			'required'   => array( 'id' ),
			'additionalProperties' => false,
		),
		'execute_callback'    => 'fccar_mcp_update_card',
		'permission_callback' => $can_one,
		'meta'                => array_merge( $public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
	) );

	wp_register_ability( 'firstchurch/set-carousel-card-status', array(
		'label'               => 'Set carousel card status',
		'description'         => 'Publish a standing card to the live deck, or move it back to draft. Publishing makes it appear in the lobby carousel.',
		'category'            => 'firstchurch',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array(
				'id'     => array( 'type' => 'integer' ),
				'status' => array( 'type' => 'string', 'enum' => array( 'draft', 'publish' ) ),
			),
			'required'   => array( 'id', 'status' ),
			'additionalProperties' => false,
		),
		'execute_callback'    => 'fccar_mcp_set_card_status',
		'permission_callback' => $can_one,
		'meta'                => array_merge( $public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
	) );

	wp_register_ability( 'firstchurch/reorder-carousel-cards', array(
		'label'               => 'Reorder standing carousel cards',
		'description'         => 'Set the deck order of standing cards. Pass the card ids in the desired order; positions are renumbered to match.',
		'category'            => 'firstchurch',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array(
				'ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => 'Card ids in the desired deck order.' ),
			),
			'required'   => array( 'ids' ),
			'additionalProperties' => false,
		),
		'execute_callback'    => 'fccar_mcp_reorder_cards',
		'permission_callback' => $can_edit,
		'meta'                => array_merge( $public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
	) );

	wp_register_ability( 'firstchurch/trash-carousel-card', array(
		'label'               => 'Trash standing carousel card',
		'description'         => 'Move a standing card to the Trash (recoverable via restore). Removes it from the deck.',
		'category'            => 'firstchurch',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array( 'id' => array( 'type' => 'integer' ) ),
			'required'   => array( 'id' ),
			'additionalProperties' => false,
		),
		'execute_callback'    => 'fccar_mcp_trash_card',
		'permission_callback' => $can_one,
		'meta'                => array_merge( $public, array( 'annotations' => array( 'readonly' => false, 'destructive' => true ) ) ),
	) );
} );
