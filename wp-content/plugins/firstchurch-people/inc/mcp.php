<?php
/**
 * MCP authoring path: create/update a staff person from a friendly structured
 * object — the agent-first surface for the one content type agents previously
 * couldn't touch. Writes through the shared fcs_write_person() (same as the
 * editor metabox), so the two stay consistent.
 *
 * NOT gated on fcs_people_active(): these operate on ctc_person posts + their
 * _ctc_person_* meta, which exist whether Church Theme Content or we register
 * the type. So agents can maintain the roster immediately, before the cutover.
 *
 * @package FirstChurch\People
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_abilities_api_init',
	static function () {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$fields = array(
			'name'     => array( 'type' => 'string', 'description' => "Person's display name (post title)." ),
			'position' => array( 'type' => 'string', 'description' => 'Role/title, e.g. "Director of Music".' ),
			'pronouns' => array( 'type' => 'string', 'description' => 'e.g. "she/her". Stored without brackets.' ),
			'phone'    => array( 'type' => 'string' ),
			'email'    => array( 'type' => 'string' ),
			'urls'     => array(
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'description' => 'Social/web links (https://… or mailto:…). Facebook/Instagram/X/YouTube/LinkedIn/email get matching icons.',
			),
			'bio'      => array( 'type' => 'string', 'description' => 'Profile body (post content). Plain text or simple HTML.' ),
			'group'    => array( 'type' => 'string', 'description' => 'ctc_person_group term name (e.g. "Pastors"). Created if missing.' ),
			'order'    => array( 'type' => 'integer', 'description' => 'Manual sort within the group (menu_order); lower is first.' ),
			'status'   => array( 'type' => 'string', 'enum' => array( 'draft', 'publish' ), 'default' => 'publish' ),
		);

		wp_register_ability(
			'firstchurch/create-person',
			array(
				'label'               => 'Create person',
				'description'         => 'Add a staff/leadership person to the directory (ctc_person).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => $fields,
					'required'             => array( 'name' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcp_mcp_create_person',
				'permission_callback' => static fn () => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'readonly' => false, 'destructive' => false ),
				),
			)
		);

		wp_register_ability(
			'firstchurch/update-person',
			array(
				'label'               => 'Update person',
				'description'         => 'Update a person by id (partial — only the fields you pass change).',
				'category'            => 'firstchurch',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => array( 'type' => 'integer' ) ) + $fields,
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => 'fcp_mcp_update_person',
				'permission_callback' => static fn ( $i = array() ) => isset( $i['id'] ) && current_user_can( 'edit_post', (int) $i['id'] ),
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);
	}
);

function fcp_mcp_create_person( $in ) {
	if ( empty( $in['name'] ) ) {
		return new WP_Error( 'bad_input', 'name is required.' );
	}
	$status = ( ( $in['status'] ?? 'publish' ) === 'draft' ) ? 'draft' : 'publish';
	$id     = wp_insert_post(
		array(
			'post_type'    => FCP_CPT,
			'post_status'  => $status,
			'post_title'   => sanitize_text_field( (string) $in['name'] ),
			'post_content' => isset( $in['bio'] ) ? wp_kses_post( (string) $in['bio'] ) : '',
			'menu_order'   => isset( $in['order'] ) ? (int) $in['order'] : 0,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	fcp_mcp_apply( (int) $id, $in );
	return fcp_mcp_result( (int) $id );
}

function fcp_mcp_update_person( $in ) {
	$id   = (int) ( $in['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || FCP_CPT !== $post->post_type ) {
		return new WP_Error( 'not_found', 'person not found.' );
	}

	$update = array( 'ID' => $id );
	if ( array_key_exists( 'name', $in ) ) {
		$update['post_title'] = sanitize_text_field( (string) $in['name'] );
	}
	if ( array_key_exists( 'bio', $in ) ) {
		$update['post_content'] = wp_kses_post( (string) $in['bio'] );
	}
	if ( array_key_exists( 'order', $in ) ) {
		$update['menu_order'] = (int) $in['order'];
	}
	if ( count( $update ) > 1 ) {
		wp_update_post( $update );
	}
	fcp_mcp_apply( $id, $in );
	return fcp_mcp_result( $id );
}

/** Shared tail: meta (via the one writer) + the group term. */
function fcp_mcp_apply( int $id, array $in ): void {
	fcs_write_person( $id, $in );
	if ( array_key_exists( 'group', $in ) && '' !== trim( (string) $in['group'] ) ) {
		wp_set_object_terms( $id, sanitize_text_field( (string) $in['group'] ), FCP_TAX, false );
	}
}

/** Echo back the saved person so the agent can confirm. */
function fcp_mcp_result( int $id ): array {
	$d     = fcs_person_data( $id );
	$terms = wp_get_object_terms( $id, FCP_TAX, array( 'fields' => 'names' ) );
	return array(
		'id'       => $id,
		'status'   => get_post_status( $id ),
		'name'     => get_the_title( $id ),
		'position' => $d['position'],
		'pronouns' => $d['pronouns'],
		'phone'    => $d['phone'],
		'email'    => $d['email'],
		'urls'     => $d['urls'],
		'group'    => is_wp_error( $terms ) ? array() : $terms,
		'order'    => (int) get_post_field( 'menu_order', $id ),
		'edit_url' => get_edit_post_link( $id, 'raw' ),
	);
}
