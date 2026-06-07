<?php
/**
 * Tools ▸ Stock Photos — a lightweight search/import screen for human editors.
 * Deliberately plain (no block-editor / React integration): a search box, a
 * results grid, and an "Add to Library" button per image, all driven by the
 * firstchurch/v1 REST routes via hand-written JS.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		add_management_page(
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
		echo '<select id="fcsp-provider">';
		foreach ( $providers as $slug => $label ) {
			printf( '<option value="%s">%s</option>', esc_attr( $slug ), esc_html( $label ) );
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
		if ( 'tools_page_fcsp-stock-photos' !== $hook ) {
			return;
		}
		$base = plugin_dir_url( dirname( __FILE__ ) );
		wp_enqueue_style( 'firstchurch-stock-photos', $base . 'assets/admin.css', array(), FCSP_VERSION );
		wp_enqueue_script( 'firstchurch-stock-photos', $base . 'assets/admin.js', array(), FCSP_VERSION, true );
		wp_localize_script(
			'firstchurch-stock-photos',
			'fcspData',
			array(
				'searchUrl' => esc_url_raw( rest_url( 'firstchurch/v1/stock-photos/search' ) ),
				'importUrl' => esc_url_raw( rest_url( 'firstchurch/v1/stock-photos/import' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'mediaUrl'  => esc_url_raw( admin_url( 'upload.php' ) ),
			)
		);
	}
);
