<?php
/**
 * Phase 5 (design doc): render the carousel as a live, self-playing web page —
 * the website itself is the renderer, not just the feed. Point a kiosk / smart-TV
 * browser at /carousel/?variant=preservice and it loops the resolved deck,
 * crossfading each card and silently re-pulling the feed so a newly published
 * event appears without anyone touching the screen.
 *
 * This is deliberately the *browser* doing the rendering (the same thing the
 * slides app already relies on), so none of the GIF/PPTX bake machinery
 * (fontkit/gifenc/pptxgenjs/headless Chromium) is needed here. The .pptx path
 * stays in apps/slides for now; this is the additional, live surface.
 *
 * PHP's job is tiny: resolve the feed in-process and emit a bare full-screen
 * document with the items inlined as JSON. The six card layouts, the QR codes,
 * the scaling and the loop all live in assets/carousel.js.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Pretty route: /carousel/ → the fccar_carousel query var.
add_action( 'init', 'fccar_render_rewrite', 11 );
function fccar_render_rewrite() {
	add_rewrite_rule( '^carousel/?$', 'index.php?fccar_carousel=1', 'top' );
	// Self-heal: flush once per version bump so a deploy doesn't need a manual
	// plugin re-activation for the new rule to take effect.
	if ( get_option( 'fccar_rewrite_v' ) !== FCCAR_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'fccar_rewrite_v', FCCAR_VERSION );
	}
}

add_filter( 'query_vars', static function ( $vars ) {
	$vars[] = 'fccar_carousel';
	return $vars;
} );

add_action( 'template_redirect', 'fccar_maybe_render_carousel' );
function fccar_maybe_render_carousel() {
	if ( ! get_query_var( 'fccar_carousel' ) ) {
		return;
	}

	$variant = ( isset( $_GET['variant'] ) && 'postservice' === $_GET['variant'] ) ? 'postservice' : 'preservice'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$seconds = isset( $_GET['seconds'] ) ? max( 3, min( 60, (int) $_GET['seconds'] ) ) : 7; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$args = array( 'variant' => $variant );
	if ( isset( $_GET['weeks'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args['weeks'] = (int) $_GET['weeks']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	if ( isset( $_GET['days'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args['days'] = (int) $_GET['days']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	$items = fccar_resolve( $args );

	fccar_render_page( $items, $variant, $seconds );
	exit;
}

/**
 * Emit the standalone carousel page. No theme chrome — this is a full-bleed
 * kiosk surface. The feed is inlined so the first loop plays without a second
 * round-trip; carousel.js re-fetches FCCAR.restUrl periodically for freshness.
 */
function fccar_render_page( array $items, string $variant, int $seconds ): void {
	// Plugin-root URL (this file lives in inc/, so go up one level).
	$base    = plugins_url( '', dirname( __DIR__ ) . '/firstchurch-carousel.php' );
	$ver     = FCCAR_VERSION;
	$rest    = esc_url_raw( rest_url( 'firstchurch/v1/carousel' ) );
	$campaign = gmdate( 'Y-m-d' );

	$boot = array(
		'variant'   => $variant,
		'seconds'   => $seconds,
		'refreshMs' => 5 * 60 * 1000, // re-pull the feed every 5 min.
		'restUrl'   => $rest,
		'campaign'  => $campaign,
		'items'     => array_values( $items ),
	);

	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );

	// Raleway to match the slides cards; falls back to system sans if offline.
	$font = 'https://fonts.googleapis.com/css2?family=Raleway:ital,wght@0,400;0,600;0,700;1,400&display=swap';

	?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="noindex, nofollow">
	<title>First Church Seattle — Announcements</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="<?php echo esc_url( $font ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( $base . '/assets/carousel.css?v=' . $ver ); ?>">
</head>
<body>
	<div class="fccar-viewport" id="fccar-viewport">
		<div class="fccar-deck" id="fccar-deck"></div>
		<div class="fccar-empty" id="fccar-empty" hidden>No announcements right now.</div>
	</div>
	<script>window.FCCAR = <?php echo wp_json_encode( $boot ); ?>;</script>
	<script src="<?php echo esc_url( $base . '/assets/vendor/qrcode-generator.js?v=' . $ver ); ?>"></script>
	<script src="<?php echo esc_url( $base . '/assets/carousel.js?v=' . $ver ); ?>"></script>
</body>
</html>
	<?php
}
