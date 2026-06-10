<?php
/**
 * Media ▸ Stock Photos — a lightweight search/import screen for human editors.
 * Deliberately plain (no block-editor / React integration): a search box, a
 * results grid, and an "Add to Library" button per image, all driven by the
 * firstchurch/v1 REST routes via hand-written JS.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		// Nest under Media; stash the hook suffix so the asset enqueue can match
		// this exact screen without hardcoding WordPress's generated hook name.
		$GLOBALS['fcsp_admin_hook'] = add_media_page(
			'Stock Photos',
			'Stock Photos',
			fcsp_capability(),
			'fcsp-stock-photos',
			'fcsp_render_admin_page'
		);
	}
);

function fcsp_render_admin_page(): void {
	if ( ! current_user_can( fcsp_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'default' ) );
	}
	$providers = fcsp_provider_choices();

	echo '<div class="wrap fcsp-wrap">';
	echo '<h1>Stock Photos</h1>';
	echo '<p class="description">Search free, openly-licensed photos and add them straight to the media library. Attribution and license are recorded automatically.</p>';
	echo '<form id="fcsp-search-form"><p>';
	echo '<input type="search" id="fcsp-query" class="regular-text" placeholder="e.g. autumn leaves, community, candle" autofocus> ';
	echo '<select id="fcsp-orientation"><option value="">Any shape</option><option value="wide">Wide</option><option value="tall">Tall</option><option value="square">Square</option></select> ';
	// Only show the provider picker when there's a real choice to make.
	if ( count( $providers ) > 1 ) {
		$default = fcsp_default_provider();
		echo '<select id="fcsp-provider">';
		foreach ( $providers as $slug => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $slug ),
				selected( $slug, $default, false ),
				esc_html( $label )
			);
		}
		echo '</select> ';
	}
	echo '<button type="submit" class="button button-primary">Search</button>';
	echo '</p></form>';
	echo '<p id="fcsp-status" class="description" aria-live="polite"></p>';
	echo '<div id="fcsp-results" class="fcsp-grid"></div>';
	echo '</div>';
}

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( empty( $GLOBALS['fcsp_admin_hook'] ) || $hook !== $GLOBALS['fcsp_admin_hook'] ) {
			return;
		}
		$base = plugin_dir_url( dirname( __FILE__ ) );
		// Shared, UI-agnostic search/import core (registered + localized once in
		// inc/assets.php) — the standalone page layers its grid/lightbox on top.
		fcsp_register_picker_core();
		wp_enqueue_script( 'firstchurch-stock-core' );
		wp_enqueue_style( 'firstchurch-stock-photos' );
		wp_enqueue_script( 'firstchurch-stock-photos', $base . 'assets/admin.js', array( 'firstchurch-stock-core' ), FCSP_VERSION, true );
	}
);
