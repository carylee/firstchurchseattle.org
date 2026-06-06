<?php
/**
 * The Carousel curation screen — a "Curate" submenu under the Carousel menu
 * where staff build this week's deck over live content: drag to reorder,
 * add/remove candidates, flag preservice-only, and override title/when/
 * background per card. Saves the ordered references + overrides via REST
 * (fccar_save_deck); the feed then resolves through it.
 *
 * The heavy lifting (view model, storage, resolution) is in deck.php; this file
 * is the page, the asset wiring, and the save/reset endpoint.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FCCAR_CURATE_SLUG = 'fccar-curate';

add_action( 'admin_menu', static function () {
	add_submenu_page(
		'edit.php?post_type=' . FCCAR_CPT,
		'Curate Carousel',
		'Curate',
		'edit_posts',
		FCCAR_CURATE_SLUG,
		'fccar_render_curate_page'
	);
} );

add_action( 'admin_enqueue_scripts', static function ( $hook ) {
	if ( ! is_string( $hook ) || false === strpos( $hook, FCCAR_CURATE_SLUG ) ) {
		return;
	}
	$base = plugin_dir_url( dirname( __FILE__ ) ) . 'assets/';
	wp_enqueue_media(); // background-image picker
	wp_enqueue_style( 'fccar-curate', $base . 'curate.css', array(), FCCAR_VERSION );
	wp_enqueue_script( 'fccar-curate', $base . 'curate.js', array( 'jquery', 'jquery-ui-sortable' ), FCCAR_VERSION, true );

	$view = fccar_curate_view();
	wp_localize_script( 'fccar-curate', 'FCCAR', array(
		'deck'      => $view['deck'],
		'available' => $view['available'],
		'restUrl'   => esc_url_raw( rest_url( 'firstchurch/v1/carousel/deck' ) ),
		'feedUrl'   => esc_url_raw( rest_url( 'firstchurch/v1/carousel' ) ),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
	) );
} );

function fccar_render_curate_page(): void {
	$is_curated = null !== fccar_get_deck();
	?>
	<div class="wrap fccar-curate">
		<h1>Curate the announcement carousel</h1>
		<p class="fccar-intro">
			This is the ordered deck the slides carousel (and the feed at
			<code>/wp-json/firstchurch/v1/carousel</code>) plays. Drag to reorder, add or
			remove cards, and override a title, time, or background per card. Content stays
			live — editing the underlying event, announcement, or card updates its card here.
			<?php if ( ! $is_curated ) : ?>
				<br><em>Showing the auto-assembled default — save to start curating.</em>
			<?php endif; ?>
		</p>

		<div class="fccar-actions">
			<button type="button" class="button button-primary" id="fccar-save">Save deck</button>
			<button type="button" class="button" id="fccar-reset" title="Discard the curated deck and revert to the auto-assembled default">Reset to default</button>
			<a class="button" href="<?php echo esc_url( rest_url( 'firstchurch/v1/carousel' ) ); ?>" target="_blank" rel="noopener">Preview feed ↗</a>
			<span class="fccar-status" id="fccar-status" role="status"></span>
		</div>

		<div class="fccar-cols">
			<section class="fccar-col">
				<h2>Deck <span class="fccar-count" id="fccar-deck-count"></span></h2>
				<ul class="fccar-list" id="fccar-deck"></ul>
			</section>
			<section class="fccar-col">
				<h2>Available</h2>
				<p class="description">Published events, announcements, and cards not in the deck.</p>
				<ul class="fccar-list fccar-list--avail" id="fccar-available"></ul>
			</section>
		</div>
	</div>
	<?php
}

/* ---- Save / reset endpoint ---- */

add_action( 'rest_api_init', static function () {
	register_rest_route( 'firstchurch/v1', '/carousel/deck', array(
		'methods'             => 'POST',
		'callback'            => 'fccar_rest_save_deck',
		'permission_callback' => static function () {
			return current_user_can( 'edit_posts' );
		},
	) );
} );

function fccar_rest_save_deck( WP_REST_Request $req ) {
	if ( $req->get_param( 'reset' ) ) {
		fccar_reset_deck();
		return new WP_REST_Response( array( 'ok' => true, 'reset' => true ) );
	}

	$raw   = $req->get_param( 'deck' );
	$clean = array();
	if ( is_array( $raw ) ) {
		foreach ( $raw as $e ) {
			$entry = fccar_sanitize_deck_entry( $e );
			if ( null !== $entry ) {
				$clean[] = $entry;
			}
		}
	}
	fccar_save_deck( $clean );
	return new WP_REST_Response( array( 'ok' => true, 'count' => count( $clean ) ) );
}
