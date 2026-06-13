<?php
/**
 * MCP authoring path for the weekly e-news. Mirrors the draft-first CRUD the
 * mu-plugin exposes for events/announcements/etc., but for `enews_issue`: an
 * agent can draft a pre-filled issue (the spine-composed body + the Bucket-C
 * envelope — subject, preview tagline, send date, Pastoral Message), curate the
 * envelope, preview the rendered email, and push a *draft* campaign to Mailchimp.
 * The irreversible send stays a human action in Mailchimp's UI (enews-spine.md §5).
 *
 * The composing body is built by fcen_compose_issue_body() (inc/compose.php),
 * the API-side twin of the editor's block template — so an MCP-drafted issue
 * previews and pushes identically to one opened in Gutenberg.
 *
 * @package FirstChurch\ENews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_abilities_api_init', static function () {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	$public    = array( 'mcp' => array( 'public' => true ) );
	$can_read  = static fn () => current_user_can( 'read' );
	$can_edit  = static fn () => current_user_can( 'edit_posts' );
	$can_one   = static fn ( $i = array() ) => isset( $i['id'] ) && current_user_can( 'edit_post', (int) $i['id'] );

	$envelope = array(
		'subject'      => array( 'type' => 'string', 'description' => 'Mailchimp subject line. Defaults to the issue title.' ),
		'preview_text' => array( 'type' => 'string', 'description' => 'Preview / tagline text shown in the inbox.' ),
		'issue_date'   => array( 'type' => 'string', 'description' => 'Send date / week anchor, YYYY-MM-DD.' ),
	);

	wp_register_ability( 'firstchurch/list-enews', array(
		'label'               => 'List e-news issues',
		'description'         => 'List weekly e-news issues, newest first. Read-only.',
		'category'            => 'firstchurch',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array(
				'status' => array( 'type' => 'string', 'enum' => array( 'any', 'draft', 'pending', 'publish' ), 'default' => 'any' ),
				'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10 ),
			),
			'additionalProperties' => false,
		),
		'execute_callback'    => 'fcen_mcp_list',
		'permission_callback' => $can_read,
		'meta'                => array_merge( $public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
	) );

	wp_register_ability( 'firstchurch/get-enews', array(
		'label'               => 'Get e-news issue',
		'description'         => 'Fetch one e-news issue by id: envelope, status, body block markup, and Mailchimp link if pushed. Read-only.',
		'category'            => 'firstchurch',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array( 'id' => array( 'type' => 'integer' ) ),
			'required'   => array( 'id' ),
			'additionalProperties' => false,
		),
		'execute_callback'    => 'fcen_mcp_get',
		'permission_callback' => $can_read,
		'meta'                => array_merge( $public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
	) );

	wp_register_ability( 'firstchurch/create-enews', array(
		'label'               => 'Create e-news issue',
		'description'         => 'Draft a new weekly e-news issue, pre-filled from the Happenings spine (featured highlight, this week\'s events, recent announcements) — no "duplicate last week". You supply the editorial Bucket C: subject, preview tagline, send date, and the Pastoral Message prose. Draft-first.',
		'category'            => 'firstchurch',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'title'            => array( 'type' => 'string', 'description' => 'Issue title. Defaults to "First Church Weekly News — <issue_date>".' ),
					'pastoral_message' => array( 'type' => 'string', 'description' => 'Optional fallback prose for the "From the Pastor" block. The block auto-fills from the latest pastoral-letters post (published within ~5 days); this prose is shown only when there is no recent letter.' ),
					'status'           => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ), 'default' => 'draft' ),
				),
				$envelope
			),
			'additionalProperties' => false,
		),
		'execute_callback'    => 'fcen_mcp_create',
		'permission_callback' => $can_edit,
		'meta'                => array_merge( $public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ) ),
	) );

	wp_register_ability( 'firstchurch/update-enews', array(
		'label'               => 'Update e-news issue',
		'description'         => 'Update an issue\'s envelope by id (partial): title, subject, preview_text, issue_date, status. The composed body (Happenings blocks + Pastoral Message) is curated in the editor and is NOT touched here.',
		'category'            => 'firstchurch',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'id'     => array( 'type' => 'integer' ),
					'title'  => array( 'type' => 'string' ),
					'status' => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ) ),
				),
				$envelope
			),
			'required'   => array( 'id' ),
			'additionalProperties' => false,
		),
		'execute_callback'    => 'fcen_mcp_update',
		'permission_callback' => $can_one,
		'meta'                => array_merge( $public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
	) );

	wp_register_ability( 'firstchurch/set-enews-status', array(
		'label'               => 'Set e-news status',
		'description'         => 'Move an issue between draft, pending (queued for review), and publish (web archive). Publishing does NOT email anyone — the send happens in Mailchimp.',
		'category'            => 'firstchurch',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array(
				'id'     => array( 'type' => 'integer' ),
				'status' => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish' ) ),
			),
			'required'   => array( 'id', 'status' ),
			'additionalProperties' => false,
		),
		'execute_callback'    => 'fcen_mcp_set_status',
		'permission_callback' => $can_one,
		'meta'                => array_merge( $public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ) ),
	) );

	wp_register_ability( 'firstchurch/preview-enews', array(
		'label'               => 'Preview e-news email',
		'description'         => 'Render an issue to its email-safe HTML (the same artifact Mailchimp receives) plus a staff browser-preview URL. Read-only.',
		'category'            => 'firstchurch',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array( 'id' => array( 'type' => 'integer' ) ),
			'required'   => array( 'id' ),
			'additionalProperties' => false,
		),
		'execute_callback'    => 'fcen_mcp_preview',
		'permission_callback' => $can_one,
		'meta'                => array_merge( $public, array( 'annotations' => array( 'readonly' => true, 'idempotent' => true ) ) ),
	) );

	wp_register_ability( 'firstchurch/push-enews-to-mailchimp', array(
		'label'               => 'Push e-news to Mailchimp (draft)',
		'description'         => 'Create or update the issue\'s DRAFT campaign in Mailchimp (subject, preview, rendered HTML) via the Marketing API. Never sends — staff review and send from Mailchimp. Requires Mailchimp credentials on the server.',
		'category'            => 'firstchurch',
		'input_schema'        => array(
			'type'       => 'object',
			'properties' => array( 'id' => array( 'type' => 'integer' ) ),
			'required'   => array( 'id' ),
			'additionalProperties' => false,
		),
		'execute_callback'    => 'fcen_mcp_push',
		'permission_callback' => $can_one,
		'meta'                => array_merge( $public, array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ) ),
	) );
} );

/* ---- Callbacks ---------------------------------------------------------- */

/** Compact list shape for one issue. */
function fcen_mcp_issue_summary( int $id ): array {
	return array(
		'id'           => $id,
		'title'        => get_the_title( $id ),
		'status'       => get_post_status( $id ),
		'issue_date'   => (string) get_post_meta( $id, FCEN_DATE_KEY, true ),
		'subject'      => (string) get_post_meta( $id, FCEN_SUBJECT_KEY, true ),
		'preview_text' => (string) get_post_meta( $id, FCEN_PREVIEW_KEY, true ),
		'edit_url'     => (string) get_edit_post_link( $id, 'raw' ),
	);
}

/** Full shape for one issue (summary + body + Mailchimp link). */
function fcen_mcp_issue_full( int $id ): array {
	$campaign = (string) get_post_meta( $id, FCEN_MC_CAMPAIGN_KEY, true );
	return array_merge(
		fcen_mcp_issue_summary( $id ),
		array(
			'body'             => (string) get_post( $id )->post_content,
			'mailchimp_pushed' => '' !== $campaign,
		)
	);
}

function fcen_mcp_list( $in = array() ) {
	$status = $in['status'] ?? 'any';
	$args   = array(
		'post_type'      => FCEN_CPT,
		'posts_per_page' => max( 1, min( 50, (int) ( $in['limit'] ?? 10 ) ) ),
		'orderby'        => 'date',
		'order'          => 'DESC',
		'post_status'    => ( 'any' === $status ) ? array( 'draft', 'pending', 'publish', 'future' ) : $status,
	);
	$items = array();
	foreach ( get_posts( $args ) as $post ) {
		$items[] = fcen_mcp_issue_summary( $post->ID );
	}
	return array( 'count' => count( $items ), 'items' => $items );
}

/** Resolve + validate an issue id, returning the post or a WP_Error. */
function fcen_mcp_require_issue( $in ) {
	$id   = (int) ( $in['id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || FCEN_CPT !== $post->post_type ) {
		return new WP_Error( 'not_found', 'E-news issue not found.' );
	}
	return $post;
}

function fcen_mcp_get( $in ) {
	$post = fcen_mcp_require_issue( $in );
	return is_wp_error( $post ) ? $post : fcen_mcp_issue_full( $post->ID );
}

/** Validate + normalize a requested status (draft default). */
function fcen_mcp_status( $value ): string {
	$s = strtolower( (string) $value );
	return in_array( $s, array( 'draft', 'pending', 'publish' ), true ) ? $s : 'draft';
}

/** Write the three envelope meta fields from input (only those present). */
function fcen_mcp_apply_envelope( int $id, array $in ): void {
	if ( array_key_exists( 'subject', $in ) ) {
		update_post_meta( $id, FCEN_SUBJECT_KEY, sanitize_text_field( (string) $in['subject'] ) );
	}
	if ( array_key_exists( 'preview_text', $in ) ) {
		update_post_meta( $id, FCEN_PREVIEW_KEY, sanitize_text_field( (string) $in['preview_text'] ) );
	}
	if ( array_key_exists( 'issue_date', $in ) ) {
		update_post_meta( $id, FCEN_DATE_KEY, fcen_sanitize_date( $in['issue_date'] ) );
	}
}

function fcen_mcp_create( $in ) {
	$date  = fcen_sanitize_date( $in['issue_date'] ?? '' );
	$title = trim( (string) ( $in['title'] ?? '' ) );
	if ( '' === $title ) {
		$title = 'First Church Weekly News' . ( '' !== $date ? " \u{2014} {$date}" : '' );
	}
	$id = wp_insert_post(
		array(
			'post_type'    => FCEN_CPT,
			'post_status'  => fcen_mcp_status( $in['status'] ?? 'draft' ),
			'post_title'   => sanitize_text_field( $title ),
			'post_content' => fcen_compose_issue_body( (string) ( $in['pastoral_message'] ?? '' ) ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	fcen_mcp_apply_envelope( (int) $id, $in );
	return fcen_mcp_issue_full( (int) $id );
}

function fcen_mcp_update( $in ) {
	$post = fcen_mcp_require_issue( $in );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$update = array( 'ID' => $post->ID );
	if ( array_key_exists( 'title', $in ) ) {
		$update['post_title'] = sanitize_text_field( (string) $in['title'] );
	}
	if ( array_key_exists( 'status', $in ) ) {
		$update['post_status'] = fcen_mcp_status( $in['status'] );
	}
	if ( count( $update ) > 1 ) {
		$r = wp_update_post( $update, true );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	fcen_mcp_apply_envelope( $post->ID, $in );
	return fcen_mcp_issue_full( $post->ID );
}

function fcen_mcp_set_status( $in ) {
	$post = fcen_mcp_require_issue( $in );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$r = wp_update_post( array( 'ID' => $post->ID, 'post_status' => fcen_mcp_status( $in['status'] ?? 'draft' ) ), true );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	return array( 'id' => $post->ID, 'status' => get_post_status( $post->ID ) );
}

function fcen_mcp_preview( $in ) {
	$post = fcen_mcp_require_issue( $in );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	return array(
		'id'          => $post->ID,
		'preview_url' => function_exists( 'fcen_email_preview_url' ) ? fcen_email_preview_url( $post->ID ) : '',
		'email_html'  => function_exists( 'fcen_render_email' ) ? fcen_render_email( $post->ID ) : '',
	);
}

function fcen_mcp_push( $in ) {
	$post = fcen_mcp_require_issue( $in );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	if ( ! function_exists( 'fcen_push_to_mailchimp' ) ) {
		return new WP_Error( 'unavailable', 'Mailchimp push is unavailable.' );
	}
	$res = fcen_push_to_mailchimp( $post->ID );
	if ( empty( $res['ok'] ) ) {
		return new WP_Error( 'mailchimp_failed', (string) ( $res['message'] ?? 'Mailchimp push failed.' ) );
	}
	return array(
		'id'       => $post->ID,
		'message'  => (string) ( $res['message'] ?? '' ),
		'edit_url' => $res['edit_url'] ?? null,
	);
}
