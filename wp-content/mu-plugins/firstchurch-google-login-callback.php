<?php
/**
 * Plugin Name: First Church — Google Login Callback
 * Description: Move the rtCamp "Login with Google" OAuth callback off wp-login.php to
 *              /google-auth/ so host WAF rules targeting wp-login.php can't break
 *              Google sign-in. The Google Cloud Console OAuth client must list
 *              https://firstchurchseattle.org/google-auth/ under Authorized redirect URIs.
 * Author: First Church Seattle
 * Version: 1.0.0
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * The plugin reads its redirect URI through the `rtcamp.google_redirect_url` filter in
 * GoogleClient::gt_redirect_url(), which is applied to BOTH the authorize URL and the
 * token exchange, so overriding it here keeps the two consistent. What the filter can't
 * change is *processing*: the plugin exchanges the code inside WordPress's `authenticate`
 * filter, which only fires when something calls wp_signon() — on wp-login.php that
 * happens on every page load, but on an arbitrary front-end URL nothing triggers it.
 * The init handler below is that trigger. Everything else (state nonce check, code
 * exchange, user matching, the registration policy in
 * firstchurch-google-register-policy.php, the post-login redirect via the plugin's
 * wp_login hook) is the plugin's own wp-login.php code path, unchanged.
 *
 * @package firstchurch
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'FIRSTCHURCH_GOOGLE_CALLBACK_PATH' ) ) {
	/**
	 * URL path that receives the OAuth callback from Google. Must match the
	 * Authorized redirect URI registered in Google Cloud Console (with host
	 * prepended via home_url()). Overridable from wp-config.php.
	 */
	define( 'FIRSTCHURCH_GOOGLE_CALLBACK_PATH', '/google-auth/' );
}

if ( ! function_exists( 'firstchurch_google_callback_url' ) ) {
	/**
	 * Point the Login with Google OAuth redirect URI at our callback path.
	 *
	 * Priority 20 so this runs after the plugin's own callback on this filter
	 * (Login::redirect_url, priority 10), which expects to operate on the
	 * wp-login.php URL; we discard its result entirely.
	 *
	 * @param string $url Redirect URI proposed by the plugin (wp-login.php).
	 * @return string Our callback URL.
	 */
	function firstchurch_google_callback_url( $url ) {
		return home_url( FIRSTCHURCH_GOOGLE_CALLBACK_PATH );
	}
}
add_filter( 'rtcamp.google_redirect_url', 'firstchurch_google_callback_url', 20 );

if ( ! function_exists( 'firstchurch_google_handle_callback' ) ) {
	/**
	 * Complete the sign-in when Google redirects back to the callback path.
	 *
	 * wp_signon() runs the `authenticate` filter chain, where Login with Google
	 * (priority 20) verifies the state nonce, exchanges the code, and returns the
	 * WP_User. On success wp_signon() sets the auth cookies and fires `wp_login`,
	 * where the plugin's Login::login_redirect() redirects to the `redirect_to`
	 * carried in state and exits — so the fallback redirect below is rarely reached.
	 *
	 * Runs on init: late enough that the plugin's hooks are registered, early
	 * enough to skip query/template work. Without code+state params we return and
	 * let the request 404 naturally.
	 *
	 * @return void
	 */
	function firstchurch_google_handle_callback() {
		$path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );

		if ( untrailingslashit( $path ) !== untrailingslashit( FIRSTCHURCH_GOOGLE_CALLBACK_PATH ) ) {
			return;
		}

		if ( empty( $_GET['code'] ) || empty( $_GET['state'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- state nonce is verified by Login with Google in the authenticate filter.
			return;
		}

		$user = wp_signon();

		if ( is_wp_error( $user ) ) {
			wp_die(
				esc_html( wp_strip_all_tags( $user->get_error_message() ) ),
				esc_html__( 'Google sign-in failed' ),
				[
					'response'  => 403,
					'link_url'  => wp_login_url(),
					'link_text' => __( 'Back to sign-in' ),
				]
			);
		}

		wp_safe_redirect( admin_url() );
		exit;
	}
}
add_action( 'init', 'firstchurch_google_handle_callback' );
