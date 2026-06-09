<?php
/**
 * Plugin Name: First Church Security Headers
 * Description: Sends a conservative set of security response headers on front-end
 *              requests (X-Content-Type-Options, X-Frame-Options, Referrer-Policy,
 *              Permissions-Policy). HSTS and a full Content-Security-Policy are
 *              intentionally NOT set here — see the note below.
 * Version:     0.1.0
 * Author:      First Church Seattle
 *
 * @package FirstChurch\SecurityHeaders
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add baseline security headers to front-end responses.
 *
 * Scope notes:
 * - Admin requests are skipped: WordPress core already sends X-Frame-Options on
 *   wp-admin, and we don't want to second-guess core there.
 * - HSTS (Strict-Transport-Security) is deliberately omitted. The site sits
 *   behind Cloudflare; HSTS belongs at the edge so it can't be half-applied by
 *   an origin that some requests bypass. Set it in the Cloudflare dashboard.
 * - A full Content-Security-Policy is also omitted: the Maranatha parent theme
 *   emits inline <style>/<script> in <head>, so a strict CSP needs a
 *   report-only rollout first (tracked as Phase 6 in
 *   ops/docs/website-improvements.md).
 *
 * These four are safe to send unconditionally on the public site.
 */
add_action(
	'send_headers',
	static function () {
		if ( is_admin() ) {
			return;
		}

		// Don't override headers an upstream layer (Cloudflare) may already set.
		if ( ! headers_sent() ) {
			header( 'X-Content-Type-Options: nosniff' );
			header( 'X-Frame-Options: SAMEORIGIN' );
			header( 'Referrer-Policy: strict-origin-when-cross-origin' );
			header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
		}
	}
);
