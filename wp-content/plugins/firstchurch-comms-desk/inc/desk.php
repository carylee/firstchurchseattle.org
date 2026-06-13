<?php
/**
 * The Comms Desk — the coordinator's home base. A single page that answers
 * "what needs me this week?" by assembling EXISTING signals into a worklist:
 *   - Needs you now : AI drafts from intake awaiting review (with provenance)
 *   - Loose ends    : fcmcp_content_health() findings
 *   - This week     : the latest e-news issue + the carousel deck
 *   - Recently published : reassurance / audit
 *   - Quick actions : new event/announcement/e-news/carousel + "Add a thing"
 *
 * Server-rendered; a little JS (desk.js) wires the review actions. No new data
 * logic — it reuses fcmcp_review_queue, fcmcp_content_health, and the fc_intake
 * meta from the engine plugins.
 *
 * @package FirstChurch\CommsDesk
 */

defined( 'ABSPATH' ) || exit;

/** Register the Comms Desk as the FIRST top-level menu item (the front door). */
add_action( 'admin_menu', static function () {
	$hook = add_menu_page(
		'Comms Desk',
		'Comms Desk',
		'edit_posts',
		FCCD_SLUG,
		'fccd_render_desk',
		'dashicons-megaphone',
		2
	);
	add_action( 'admin_print_styles-' . $hook, 'fccd_enqueue_desk_assets' );
} );

function fccd_enqueue_desk_assets(): void {
	$base = plugin_dir_url( dirname( __FILE__ ) );
	$dir  = plugin_dir_path( dirname( __FILE__ ) );
	wp_enqueue_style( 'fccd-desk', $base . 'assets/desk.css', array(), (string) @filemtime( $dir . 'assets/desk.css' ) );
	wp_enqueue_script( 'fccd-desk', $base . 'assets/desk.js', array( 'wp-api-fetch' ), (string) @filemtime( $dir . 'assets/desk.js' ), true );
}

/* ---- Data: what needs the coordinator now ---- */

/**
 * AI drafts from intake awaiting review: fc_intake items marked 'drafted' whose
 * linked post is still a draft/pending (once published, they drop off). Each
 * carries provenance the coordinator can trust at a glance.
 *
 * @return array<int,array<string,mixed>>
 */
function fccd_needs_you_now(): array {
	if ( ! defined( 'FCBF_INTAKE_CPT' ) ) {
		return array();
	}
	$q = new WP_Query( array(
		'post_type'      => FCBF_INTAKE_CPT,
		'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
		'posts_per_page' => 30,
		'orderby'        => 'modified',
		'order'          => 'DESC',
		'meta_query'     => array( array( 'key' => FCBF_INTAKE_STATUS, 'value' => 'drafted' ) ),
	) );

	$cards = array();
	foreach ( $q->posts as $item ) {
		$draft_id = (int) get_post_meta( $item->ID, FCBF_INTAKE_LINKED, true );
		$draft    = $draft_id ? get_post( $draft_id ) : null;
		if ( ! $draft || ! in_array( $draft->post_status, array( 'draft', 'pending' ), true ) ) {
			continue; // already published / gone — not awaiting review
		}
		$contact = json_decode( (string) get_post_meta( $item->ID, FCBF_INTAKE_CONTACT, true ), true );
		$conf    = get_post_meta( $item->ID, FCBF_INTAKE_CONFIDENCE, true );
		$cards[] = array(
			'item_id'    => $item->ID,
			'draft_id'   => $draft_id,
			'kind'       => ( 'fce_event' === $draft->post_type ) ? 'event' : 'announcement',
			'title'      => get_the_title( $draft ),
			'excerpt'    => wp_trim_words( wp_strip_all_tags( $draft->post_content ), 40 ),
			'source'     => (string) get_post_meta( $item->ID, FCBF_INTAKE_SOURCE, true ),
			'from'       => is_array( $contact ) && ! empty( $contact['email'] ) ? $contact['email'] : '',
			'confidence' => ( '' !== (string) $conf ) ? (float) $conf : null,
			'note'       => (string) get_post_meta( $item->ID, FCBF_INTAKE_NOTE, true ),
			'edit_url'   => (string) get_edit_post_link( $draft_id, 'raw' ),
			'start_date' => ( 'fce_event' === $draft->post_type ) ? (string) get_post_meta( $draft_id, '_fce_dtstart', true ) : '',
		);
	}
	return $cards;
}

/* ---- Render ---- */

function fccd_render_desk(): void {
	$cards   = fccd_needs_you_now();
	$health  = function_exists( 'fcmcp_content_health' )
		? fcmcp_content_health( array( 'checks' => array( 'events_missing_image', 'announcements_expiring', 'announcements_expired', 'stale_drafts' ) ) )
		: array( 'counts' => array(), 'findings' => array() );
	$other   = function_exists( 'fcmcp_review_queue' ) ? fcmcp_review_queue( array( 'limit' => 50 ) ) : array( 'items' => array() );
	$user    = wp_get_current_user();

	echo '<div class="wrap fccd-wrap">';
	printf( '<h1 class="fccd-h1">Comms Desk</h1>' );
	printf( '<p class="fccd-greeting">Hi %s — here\'s what needs you.</p>', esc_html( $user->display_name ?: $user->user_login ) );

	/* Quick actions */
	echo '<div class="fccd-quick">';
	fccd_quick_link( admin_url( 'post-new.php?post_type=fce_event' ), 'New event' );
	fccd_quick_link( admin_url( 'post-new.php?post_type=post' ), 'New announcement' );
	fccd_quick_link( admin_url( 'edit.php?post_type=enews_issue' ), 'Compose e-news' );
	if ( defined( 'FCCAR_CURATE_SLUG' ) ) {
		fccd_quick_link( admin_url( 'admin.php?page=' . FCCAR_CURATE_SLUG ), 'Curate carousel' );
	}
	echo '<button type="button" class="button fccd-addthing-btn" data-fccd-addthing>+ Add a thing</button>';
	echo '</div>';

	/* Add-a-thing inline composer (hidden until clicked) */
	echo '<div class="fccd-addthing" hidden>';
	echo '<p>Paste anything — an email, a flyer\'s text, a few notes. It lands in the intake queue and the next run drafts it in our voice.</p>';
	echo '<input type="text" class="fccd-addthing-subject" placeholder="Subject (e.g. Choir concert June 22)" />';
	echo '<textarea class="fccd-addthing-body" rows="5" placeholder="Paste the details here…"></textarea>';
	echo '<button type="button" class="button button-primary fccd-addthing-submit">Add to intake</button> <span class="fccd-addthing-status"></span>';
	echo '</div>';

	/* Needs you now */
	echo '<h2 class="fccd-sec">Needs you now <span class="fccd-count">' . count( $cards ) . '</span></h2>';
	if ( ! $cards ) {
		echo '<p class="fccd-empty">Nothing waiting — the queue is clear. 🎉</p>';
	}
	foreach ( $cards as $c ) {
		fccd_render_card( $c );
	}

	/* Loose ends */
	echo '<h2 class="fccd-sec">Loose ends</h2>';
	fccd_render_loose_ends( $health );

	/* This week */
	echo '<h2 class="fccd-sec">This week\'s rhythm</h2>';
	fccd_render_this_week();

	/* Recently published */
	echo '<h2 class="fccd-sec">Recently published</h2>';
	fccd_render_recent();

	echo '</div>';
}

function fccd_quick_link( string $url, string $label ): void {
	printf( '<a href="%s" class="button">%s</a> ', esc_url( $url ), esc_html( $label ) );
}

function fccd_render_card( array $c ): void {
	echo '<div class="fccd-card" data-item="' . (int) $c['item_id'] . '" data-draft="' . (int) $c['draft_id'] . '" data-kind="' . esc_attr( $c['kind'] ) . '">';
	echo '<div class="fccd-card-main">';
	echo '<span class="fccd-pill fccd-pill--' . esc_attr( $c['kind'] ) . '">' . esc_html( ucfirst( $c['kind'] ) ) . '</span> ';
	echo '<strong class="fccd-card-title">' . esc_html( $c['title'] ) . '</strong>';
	echo '<p class="fccd-card-excerpt">' . esc_html( $c['excerpt'] ) . '</p>';
	echo '</div>';

	/* provenance — trust at a glance */
	echo '<div class="fccd-prov">';
	$bits = array();
	if ( '' !== $c['source'] ) {
		$bits[] = 'from ' . esc_html( $c['source'] ) . ( '' !== $c['from'] ? ' · ' . esc_html( $c['from'] ) : '' );
	}
	if ( null !== $c['confidence'] ) {
		$bits[] = 'AI confidence ' . esc_html( round( $c['confidence'] * 100 ) . '%' );
	}
	echo '<span class="fccd-prov-meta">' . implode( ' · ', $bits ) . '</span>';
	if ( '' !== $c['note'] ) {
		echo '<span class="fccd-prov-note">' . esc_html( $c['note'] ) . '</span>';
	}
	echo '</div>';

	/* actions */
	echo '<div class="fccd-actions">';
	echo '<button type="button" class="button button-primary fccd-approve">Approve &amp; publish</button> ';
	echo '<a href="' . esc_url( $c['edit_url'] ) . '" class="button">Tweak in editor</a> ';
	echo '<button type="button" class="button fccd-needsinfo">Needs info</button>';
	echo '<span class="fccd-card-status"></span>';
	echo '</div>';
	echo '</div>';
}

function fccd_render_loose_ends( array $health ): void {
	$labels = array(
		'events_missing_image'   => 'Upcoming events missing an image',
		'announcements_expiring' => 'Announcements expiring soon',
		'announcements_expired'  => 'Announcements already expired',
		'stale_drafts'           => 'Stale drafts',
	);
	$findings = $health['findings'] ?? array();
	$any      = false;
	echo '<ul class="fccd-loose">';
	foreach ( $labels as $key => $label ) {
		$items = $findings[ $key ] ?? array();
		if ( ! $items ) {
			continue;
		}
		$any = true;
		echo '<li><strong>' . esc_html( $label ) . ' (' . count( $items ) . ')</strong><ul>';
		foreach ( array_slice( $items, 0, 6 ) as $it ) {
			printf(
				'<li><a href="%s">%s</a></li>',
				esc_url( (string) ( $it['edit_url'] ?? '#' ) ),
				esc_html( (string) ( $it['title'] ?? '(untitled)' ) )
			);
		}
		echo '</ul></li>';
	}
	echo '</ul>';
	if ( ! $any ) {
		echo '<p class="fccd-empty">All tidy — no loose ends. ✨</p>';
	}
}

function fccd_render_this_week(): void {
	echo '<ul class="fccd-week">';
	// Latest e-news issue + its status.
	$enews = get_posts( array( 'post_type' => 'enews_issue', 'post_status' => array( 'draft', 'pending', 'publish' ), 'posts_per_page' => 1, 'orderby' => 'modified', 'order' => 'DESC' ) );
	if ( $enews ) {
		$e = $enews[0];
		printf(
			'<li><strong>E-news:</strong> latest issue &#8220;%s&#8221; is <em>%s</em>. <a href="%s">Open</a></li>',
			esc_html( get_the_title( $e ) ?: 'Untitled' ),
			esc_html( $e->post_status ),
			esc_url( (string) get_edit_post_link( $e->ID, 'raw' ) )
		);
	} else {
		printf( '<li><strong>E-news:</strong> no issue yet. <a href="%s">Start one</a></li>', esc_url( admin_url( 'post-new.php?post_type=enews_issue' ) ) );
	}
	if ( defined( 'FCCAR_CURATE_SLUG' ) ) {
		printf( '<li><strong>Carousel:</strong> <a href="%s">review this week\'s deck</a></li>', esc_url( admin_url( 'admin.php?page=' . FCCAR_CURATE_SLUG ) ) );
	}
	echo '</ul>';
}

function fccd_render_recent(): void {
	$q = new WP_Query( array(
		'post_type'      => array( 'fce_event', 'post' ),
		'post_status'    => 'publish',
		'posts_per_page' => 6,
		'orderby'        => 'modified',
		'order'          => 'DESC',
	) );
	if ( ! $q->posts ) {
		echo '<p class="fccd-empty">Nothing published recently.</p>';
		return;
	}
	echo '<ul class="fccd-recent">';
	foreach ( $q->posts as $p ) {
		printf(
			'<li><a href="%s">%s</a> <span class="fccd-muted">· %s</span></li>',
			esc_url( get_permalink( $p ) ),
			esc_html( get_the_title( $p ) ),
			esc_html( get_post_modified_time( 'M j', false, $p ) )
		);
	}
	echo '</ul>';
}
