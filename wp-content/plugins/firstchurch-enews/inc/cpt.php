<?php
/**
 * The `enews_issue` custom post type — one weekly e-news issue, authored in the
 * block editor. It is a thin curation layer, NOT a content store: the timely
 * content (a featured event, this week's events, recent news) is projected from
 * the firstchurch-happenings spine via the theme's `firstchurch/happenings`
 * block at render time. Only the editorial bits (Pastoral Message, headings) and
 * the curation (order, which to lead) live in the post itself.
 *
 * The block `template` is the "opens pre-filled, not blank" win (enews-spine.md
 * §4): a new issue already contains the composing blocks in order, so there is
 * no "duplicate last week" ritual and stale items are already gone (the spine
 * self-expires). The template is unlocked so staff can reorder, add, and remove.
 *
 * @package FirstChurch\ENews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'fcen_register_cpt' );

function fcen_register_cpt(): void {
	register_post_type(
		FCEN_CPT,
		array(
			'labels'              => array(
				'name'          => 'E-News',
				'singular_name' => 'E-News Issue',
				'add_new_item'  => 'Add E-News Issue',
				'edit_item'     => 'Edit E-News Issue',
				'new_item'      => 'New E-News Issue',
				'menu_name'     => 'E-News',
				'all_items'     => 'All Issues',
			),
			// Not part of the public site (no archive, out of search/nav menus), but
			// a single is publicly queryable so an issue can be PREVIEWED in the
			// browser and, once published, serve as a lightweight web archive
			// (enews-spine.md §8). show_in_rest is REQUIRED for the block editor.
			'public'              => false,
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'show_in_nav_menus'   => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => true,
			'menu_icon'           => 'dashicons-email-alt',
			'capability_type'     => 'post',
			'supports'            => array( 'title', 'editor', 'revisions', 'thumbnail', 'custom-fields' ),
			'rewrite'             => array( 'slug' => 'enews', 'with_front' => false ),
			'template'            => fcen_issue_block_template(),
			// Unlocked: curation (reorder/add/remove) is the staff's job.
			'template_lock'       => false,
		)
	);
}

/**
 * The default block composition a new issue opens with. Each `firstchurch/
 * happenings` block renders server-side from the spine, so the timely sections
 * auto-fill; the headings and the Pastoral Message paragraph are editorial
 * placeholders the staff replace.
 *
 * Windows match the weekly cadence (enews-spine.md §3): featured leads with a
 * single highlight (now able to be a dated event with a real when-line —
 * Phase 4a), then this week's events and the last 7 days of announcements, each
 * excluding whatever was already promoted into Featured so nothing doubles.
 *
 * @return array<int,array{0:string,1?:array<string,mixed>,2?:array<int,mixed>}>
 */
function fcen_issue_block_template(): array {
	return array(
		// --- Bucket C: the one hand-written block (the Pastoral Message). ---
		array( 'core/heading', array( 'level' => 2, 'content' => 'From the Pastor' ) ),
		array( 'core/paragraph', array( 'placeholder' => "This week's message from our pastor…" ) ),

		// --- The week's lead highlight (a featured event or announcement). ---
		array( 'core/heading', array( 'level' => 2, 'content' => 'This Week’s Highlight' ) ),
		array( 'firstchurch/happenings', array( 'section' => 'featured', 'count' => 1 ) ),

		// --- Everything happening this week (events), minus the highlight. ---
		array( 'core/heading', array( 'level' => 2, 'content' => 'This Week at First Church' ) ),
		array( 'firstchurch/happenings', array( 'section' => 'events', 'weeks' => 1, 'excludeFeatured' => true ) ),

		// --- Recent announcements / news & notes. ---
		array( 'core/heading', array( 'level' => 2, 'content' => 'News & Notes' ) ),
		array( 'firstchurch/happenings', array( 'section' => 'announcements', 'days' => 7, 'excludeFeatured' => true ) ),

		// --- Bucket B: evergreen recurring ministries. Editable furniture for now
		//     (projecting these from a real evergreen source is a follow-up —
		//     enews-spine.md §2/§8). Seeded so the structure is present. ---
		array( 'core/heading', array( 'level' => 2, 'content' => 'Recurring at First Church' ) ),
		array(
			'core/list',
			array( 'placeholder' => 'Centering Prayer · Shared Breakfast · Men’s Breakfast · Nursery · …' ),
			array(),
		),

		// --- Fixed footer furniture (Bucket C). ---
		array( 'core/separator' ),
		array(
			'core/paragraph',
			array(
				'content' => 'E-news deadline: Tuesdays at noon · comms@firstchurchseattle.org',
				'align'   => 'center',
				'fontSize' => 'small',
			),
		),
	);
}
