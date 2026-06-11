<?php
/**
 * First Church MCP Abilities — navigation menus.
 *
 * wp_nav_menu management gated by the narrow fcmcp_manage_menus cap.
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
		$can_menus     = static function () { return current_user_can( 'fcmcp_manage_menus' ); };

		/* ---- NAVIGATION MENUS (wp_nav_menu) ---- */

		wp_register_ability(
			'firstchurch/list-menus',
			array(
				'label'               => 'List navigation menus',
				'description'         => 'List the site navigation menus (id, name, slug, item count, and which theme locations each is assigned to), plus the theme\'s registered menu locations. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_list_menus',
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/get-menu',
			array(
				'label'               => 'Get navigation menu',
				'description'         => 'Get one navigation menu by id, slug, or name, with its items in order. Each item carries id, title, url, type (post_type/taxonomy/custom), the linked object + id, parent item id, order, and target. Read-only.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'menu' => array( 'type' => 'string', 'description' => 'Menu id, slug, or name. Use list-menus to find it.' ),
					),
					'required'             => array( 'menu' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_get_menu',
				'permission_callback' => $can_read,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/create-menu',
			array(
				'label'               => 'Create navigation menu',
				'description'         => 'Create a new (empty) navigation menu by name. Assigning it to a theme location is a separate theme decision and is not done here.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'name' => array( 'type' => 'string' ),
					),
					'required'             => array( 'name' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_create_menu',
				'permission_callback' => $can_menus,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/add-menu-item',
			array(
				'label'               => 'Add navigation menu item',
				'description'         => 'Add an item to a navigation menu. Link target is exactly ONE of: page_id, post_id, category_id, or url (custom link — title required). Optional parent (an existing item id, for a submenu), position (1-based), target (_blank to open in a new tab), and description.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'menu'        => array( 'type' => 'string', 'description' => 'Menu id, slug, or name.' ),
						'title'       => array( 'type' => 'string', 'description' => 'Item label. Required for a custom-link (url) item; optional for object links (defaults to the linked item\'s title).' ),
						'page_id'     => array( 'type' => 'integer', 'description' => 'Link to a page by ID.' ),
						'post_id'     => array( 'type' => 'integer', 'description' => 'Link to a post by ID.' ),
						'category_id' => array( 'type' => 'integer', 'description' => 'Link to a category archive by term ID.' ),
						'url'         => array( 'type' => 'string', 'description' => 'Custom link URL (requires title).' ),
						'parent'      => array( 'type' => 'integer', 'description' => 'Parent menu item id (makes this a submenu entry).' ),
						'position'    => array( 'type' => 'integer', 'description' => '1-based position within the menu/level.' ),
						'target'      => array( 'type' => 'string', 'enum' => array( '', '_blank' ), 'description' => '_blank opens in a new tab.' ),
						'description' => array( 'type' => 'string' ),
						'attr_title'  => array( 'type' => 'string', 'description' => 'HTML title attribute (tooltip).' ),
					),
					'required'             => array( 'menu' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_add_menu_item',
				'permission_callback' => $can_menus,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/update-menu-item',
			array(
				'label'               => 'Update navigation menu item',
				'description'         => 'Update an existing menu item by id. Changeable: title, parent, position, target, description, attr_title — and url for a custom-link item. The link target type of an object link (page/post/category) is preserved; to relink, remove and re-add.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'          => array( 'type' => 'integer', 'description' => 'Menu item id (from get-menu).' ),
						'title'       => array( 'type' => 'string' ),
						'url'         => array( 'type' => 'string', 'description' => 'Custom-link items only.' ),
						'parent'      => array( 'type' => 'integer' ),
						'position'    => array( 'type' => 'integer' ),
						'target'      => array( 'type' => 'string', 'enum' => array( '', '_blank' ) ),
						'description' => array( 'type' => 'string' ),
						'attr_title'  => array( 'type' => 'string' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_update_menu_item',
				'permission_callback' => $can_menus,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/remove-menu-item',
			array(
				'label'               => 'Remove navigation menu item',
				'description'         => 'Delete a menu item by id. Removes the item from the menu (does not delete the page/post it links to). Child items are re-parented by WordPress.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_remove_menu_item',
				'permission_callback' => $can_menus,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => true ) ) ),
			)
		);

		wp_register_ability(
			'firstchurch/reorder-menu',
			array(
				'label'               => 'Reorder navigation menu',
				'description'         => 'Set the top-to-bottom order of a menu by passing item ids in the desired order. Items not listed keep their relative order after the listed ones. Use update-menu-item to change an item\'s parent.',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'menu'     => array( 'type' => 'string', 'description' => 'Menu id, slug, or name.' ),
						'item_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => 'Menu item ids in the desired order.' ),
					),
					'required'             => array( 'menu', 'item_ids' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcmcp_reorder_menu',
				'permission_callback' => $can_menus,
				'meta'                => array_merge( $mcp_public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
			)
		);
	}
);

/** Flatten a (set-up) nav menu item object into a compact, stable shape. */
function fcmcp_menu_item_to_array( $item ): array {
	return array(
		'id'        => (int) $item->ID,
		'title'     => (string) $item->title,
		'url'       => (string) $item->url,
		'type'      => (string) $item->type,        // post_type | taxonomy | custom | post_type_archive
		'object'    => (string) $item->object,      // page | post | category | …
		'object_id' => (int) $item->object_id,
		'parent'    => (int) $item->menu_item_parent,
		'order'     => (int) $item->menu_order,
		'target'    => (string) $item->target,
	);
}

/** Map the type-agnostic fields shared by add/update onto a menu-item-* data array. */
function fcmcp_apply_menu_item_fields( array $data, array $input ): array {
	if ( array_key_exists( 'title', $input ) ) {
		$data['menu-item-title'] = sanitize_text_field( (string) $input['title'] );
	}
	if ( array_key_exists( 'parent', $input ) ) {
		$data['menu-item-parent-id'] = absint( $input['parent'] );
	}
	if ( array_key_exists( 'position', $input ) ) {
		$data['menu-item-position'] = absint( $input['position'] );
	}
	if ( array_key_exists( 'target', $input ) ) {
		$data['menu-item-target'] = ( '_blank' === $input['target'] ) ? '_blank' : '';
	}
	if ( array_key_exists( 'description', $input ) ) {
		$data['menu-item-description'] = sanitize_text_field( (string) $input['description'] );
	}
	if ( array_key_exists( 'attr_title', $input ) ) {
		$data['menu-item-attr-title'] = sanitize_text_field( (string) $input['attr_title'] );
	}
	return $data;
}

/**
 * Build the wp_update_nav_menu_item data array for a NEW item from MCP input.
 * Validates that exactly one link target is given (page_id|post_id|category_id|url)
 * and that a custom-link item carries a title. Returns the data array or WP_Error.
 */
function fcmcp_build_menu_item_args( array $input ) {
	$provided = array();
	foreach ( array( 'page_id', 'post_id', 'category_id', 'url' ) as $k ) {
		if ( isset( $input[ $k ] ) && '' !== $input[ $k ] && null !== $input[ $k ] ) {
			$provided[] = $k;
		}
	}
	if ( 1 !== count( $provided ) ) {
		return new WP_Error( 'bad_link_target', 'Provide exactly one of page_id, post_id, category_id, or url.' );
	}

	$data = array( 'menu-item-status' => 'publish' );
	switch ( $provided[0] ) {
		case 'page_id':
			$data['menu-item-type']      = 'post_type';
			$data['menu-item-object']    = 'page';
			$data['menu-item-object-id'] = absint( $input['page_id'] );
			break;
		case 'post_id':
			$data['menu-item-type']      = 'post_type';
			$data['menu-item-object']    = 'post';
			$data['menu-item-object-id'] = absint( $input['post_id'] );
			break;
		case 'category_id':
			$data['menu-item-type']      = 'taxonomy';
			$data['menu-item-object']    = 'category';
			$data['menu-item-object-id'] = absint( $input['category_id'] );
			break;
		case 'url':
			$url = esc_url_raw( (string) $input['url'] );
			if ( '' === $url ) {
				return new WP_Error( 'bad_url', 'url must be a valid URL.' );
			}
			if ( '' === trim( (string) ( $input['title'] ?? '' ) ) ) {
				return new WP_Error( 'missing_title', 'title is required for a custom-link menu item.' );
			}
			$data['menu-item-type'] = 'custom';
			$data['menu-item-url']  = $url;
			break;
	}
	return fcmcp_apply_menu_item_fields( $data, $input );
}

/** Resolve a menu reference (numeric id, slug, or name) to a menu term, or WP_Error. */
function fcmcp_resolve_menu( $ref ) {
	if ( is_numeric( $ref ) ) {
		$ref = (int) $ref;
	}
	$menu = $ref ? wp_get_nav_menu_object( $ref ) : false;
	if ( ! $menu ) {
		return new WP_Error( 'not_found', 'Menu not found.' );
	}
	return $menu;
}

/** The nav_menu term id a given menu item belongs to (0 if none). */
function fcmcp_menu_id_for_item( int $item_id ): int {
	$terms = wp_get_object_terms( $item_id, 'nav_menu' );
	return ( ! is_wp_error( $terms ) && $terms ) ? (int) $terms[0]->term_id : 0;
}

function fcmcp_list_menus( $input = array() ) {
	$menus     = wp_get_nav_menus();
	$locations = get_nav_menu_locations();
	$by_menu   = array();
	foreach ( (array) $locations as $loc => $mid ) {
		$by_menu[ (int) $mid ][] = $loc;
	}
	$out = array();
	foreach ( (array) $menus as $m ) {
		$out[] = array(
			'id'        => (int) $m->term_id,
			'name'      => $m->name,
			'slug'      => $m->slug,
			'count'     => (int) $m->count,
			'locations' => $by_menu[ (int) $m->term_id ] ?? array(),
		);
	}
	$registered = function_exists( 'get_registered_nav_menus' ) ? get_registered_nav_menus() : array();
	return array( 'menus' => $out, 'registered_locations' => $registered );
}

function fcmcp_get_menu( $input ) {
	$menu = fcmcp_resolve_menu( $input['menu'] ?? null );
	if ( is_wp_error( $menu ) ) {
		return $menu;
	}
	$items = wp_get_nav_menu_items( $menu->term_id );
	$out   = array_map( 'fcmcp_menu_item_to_array', $items ? $items : array() );
	return array(
		'id'    => (int) $menu->term_id,
		'name'  => $menu->name,
		'slug'  => $menu->slug,
		'items' => $out,
	);
}

function fcmcp_create_menu( $input ) {
	$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
	if ( '' === $name ) {
		return new WP_Error( 'missing_name', 'name is required.' );
	}
	if ( wp_get_nav_menu_object( $name ) ) {
		return new WP_Error( 'menu_exists', 'A menu with that name already exists.' );
	}
	$id = wp_create_nav_menu( $name );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	return array( 'id' => (int) $id, 'name' => $name );
}

function fcmcp_add_menu_item( $input ) {
	$menu = fcmcp_resolve_menu( $input['menu'] ?? null );
	if ( is_wp_error( $menu ) ) {
		return $menu;
	}
	$data = fcmcp_build_menu_item_args( (array) $input );
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	$item_id = wp_update_nav_menu_item( $menu->term_id, 0, $data );
	if ( is_wp_error( $item_id ) ) {
		return $item_id;
	}
	return array(
		'menu_id' => (int) $menu->term_id,
		'item_id' => (int) $item_id,
		'title'   => $data['menu-item-title'] ?? '',
	);
}

function fcmcp_update_menu_item( $input ) {
	$id   = absint( $input['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Menu item not found.' );
	}
	$menu_id  = fcmcp_menu_id_for_item( $id );
	$existing = wp_setup_nav_menu_item( $post );

	// Start from the item's current values so an unspecified field is preserved
	// (wp_update_nav_menu_item blanks anything missing from the data array).
	$data = array(
		'menu-item-type'      => $existing->type,
		'menu-item-object'    => $existing->object,
		'menu-item-object-id' => (int) $existing->object_id,
		'menu-item-url'       => $existing->url,
		'menu-item-title'     => $existing->title,
		'menu-item-parent-id' => (int) $existing->menu_item_parent,
		'menu-item-position'  => (int) $existing->menu_order,
		'menu-item-target'    => $existing->target,
		'menu-item-status'    => 'publish',
	);
	if ( 'custom' === $existing->type && array_key_exists( 'url', $input ) ) {
		$data['menu-item-url'] = esc_url_raw( (string) $input['url'] );
	}
	$data = fcmcp_apply_menu_item_fields( $data, $input );

	$r = wp_update_nav_menu_item( $menu_id, $id, $data );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	return array( 'id' => $id, 'menu_id' => (int) $menu_id );
}

function fcmcp_remove_menu_item( $input ) {
	$id   = absint( $input['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Menu item not found.' );
	}
	if ( ! wp_delete_post( $id, true ) ) {
		return new WP_Error( 'delete_failed', 'Could not delete the menu item.' );
	}
	return array( 'id' => $id, 'deleted' => true );
}

function fcmcp_reorder_menu( $input ) {
	$menu = fcmcp_resolve_menu( $input['menu'] ?? null );
	if ( is_wp_error( $menu ) ) {
		return $menu;
	}
	$order = $input['item_ids'] ?? array();
	if ( ! is_array( $order ) || ! $order ) {
		return new WP_Error( 'bad_order', 'item_ids must be a non-empty array of menu item ids.' );
	}
	// Setting menu_order directly reorders without disturbing the items' other
	// nav-menu meta (unlike re-running wp_update_nav_menu_item per item).
	$pos     = 0;
	$ordered = array();
	foreach ( $order as $iid ) {
		$iid  = absint( $iid );
		$post = get_post( $iid );
		if ( ! $post || 'nav_menu_item' !== $post->post_type || (int) fcmcp_menu_id_for_item( $iid ) !== (int) $menu->term_id ) {
			continue;
		}
		$pos++;
		wp_update_post( array( 'ID' => $iid, 'menu_order' => $pos ) );
		$ordered[] = $iid;
	}
	return array( 'menu_id' => (int) $menu->term_id, 'ordered' => $ordered );
}
