<?php
/**
 * Plugin Name: First Church People
 * Description: First-party staff/people directory — the replacement for Church Theme Content's person type. ADOPTS the ctc_person type in place (same post type, ctc_person_group taxonomy, and _ctc_person_* meta keys) so the existing staff posts, groups, headshots, and /staff/ URLs keep working with zero data migration. Actively displaces CTC's person registration at init:5 (removes ctc_register_post_type_person + ctc-people theme support) — no dependency on full CTC decommission. See ops/docs/theme-independence.md.
 * Version:     0.1.0
 * Author:      First Church Seattle
 *
 * @package FirstChurch\People
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/src/Person.php';

// We adopt CTC's type/taxonomy/meta names verbatim — that is the whole point of
// "adopt in place": no content moves, no URLs change, the cutover is invisible.
const FCP_CPT      = 'ctc_person';
const FCP_TAX      = 'ctc_person_group';
const FCP_POSITION = '_ctc_person_position';
const FCP_PHONE    = '_ctc_person_phone';
const FCP_EMAIL    = '_ctc_person_email';
const FCP_URLS     = '_ctc_person_urls';      // newline-separated social/web URLs (CTC shape)
const FCP_PRONOUNS = '_ctc_person_pronouns';  // ours — CTC has no pronouns field

/**
 * Displace CTC's person registration before it fires.
 *
 * Church Theme Content registers each post type (ctc_sermon, ctc_event,
 * ctc_person, ctc_location) as a SEPARATE function hooked to `init` at
 * priority 10. We unhook only ctc_register_post_type_person so the other
 * three types are untouched — ctc_event (rollback insurance), ctc_sermon
 * (150 published-but-redirected posts), and ctc_location (pending).
 *
 * We also remove ctc-people theme support so CTC's person metaboxes,
 * single-template filters, and prev/next nav disable themselves — our
 * plugin provides all of these. The parent's ctfw_person_data() reads
 * _ctc_person_* meta keys (no theme-support gate), so legacy callers
 * that still read person data remain safe.
 *
 * Runs at init:5 so it beats CTC's init:10 registration.
 */
add_action(
	'init',
	static function () {
		remove_action( 'init', 'ctc_register_post_type_person' );
		remove_theme_support( 'ctc-people' );
	},
	5
);

/**
 * Register the person type + group taxonomy.
 *
 * CTC's registration was unhooked above at priority 5, so
 * post_type_exists('ctc_person') is false and we own the type. Same name +
 * slug + meta as CTC, so /staff/ URLs, single profile URLs, and existing
 * posts are unaffected — zero data migration.
 *
 * FCP_OWNS_PEOPLE is the single signal the behaviour-changing pieces (admin
 * metabox, child display templates) gate on via fcs_people_active(). It is
 * defined once we register the type. MCP authoring deliberately does NOT
 * gate on it — it writes the same meta on the same posts whether CTC or we
 * register the type.
 */
add_action(
	'init',
	static function () {
		if ( post_type_exists( FCP_CPT ) ) {
			return;
		}

		register_post_type(
			FCP_CPT,
			array(
				'label'           => __( 'People', 'firstchurch-people' ),
				'labels'          => array(
					'name'          => __( 'People', 'firstchurch-people' ),
					'singular_name' => __( 'Person', 'firstchurch-people' ),
					'add_new_item'  => __( 'Add Person', 'firstchurch-people' ),
					'edit_item'     => __( 'Edit Person', 'firstchurch-people' ),
				),
				'public'          => true,
				'has_archive'     => true,
				'menu_icon'       => 'dashicons-groups',
				'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
				'show_in_rest'    => true,
				// Live singles are /staff/<slug>/. with_front=false keeps them at
				// the site root. NOTE: whether /staff/ itself is this archive or a
				// Page must be reconciled at cutover — see ops/docs/theme-independence.md.
				'rewrite'         => array(
					'slug'       => 'staff',
					'with_front' => false,
				),
			)
		);

		register_taxonomy(
			FCP_TAX,
			FCP_CPT,
			array(
				'label'             => __( 'Groups', 'firstchurch-people' ),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'staff-group' ),
			)
		);

		if ( ! defined( 'FCP_OWNS_PEOPLE' ) ) {
			define( 'FCP_OWNS_PEOPLE', true );
		}
	},
	20
);

/**
 * True once we own the person type (displaced CTC at init:5, registered at
 * init:20). The behaviour-changing surfaces — the admin metabox and the child
 * display templates — gate on this. Callers must run after init:20
 * (admin metaboxes, template filters, frontend render all do).
 */
function fcs_people_active(): bool {
	return defined( 'FCP_OWNS_PEOPLE' ) && FCP_OWNS_PEOPLE;
}

require_once __DIR__ . '/inc/person.php';
require_once __DIR__ . '/inc/admin.php';
require_once __DIR__ . '/inc/mcp.php';

// The /staff/ rewrite rule only exists once we register the type; flush when we
// activate (and on deactivation, so CTC's rules are rebuilt cleanly).
register_activation_hook( __FILE__, 'flush_rewrite_rules' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
