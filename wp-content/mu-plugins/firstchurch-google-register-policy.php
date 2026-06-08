<?php
/**
 * Plugin Name: First Church — Google Registration Policy
 * Description: Restrict auto account creation via rtCamp "Login with Google" to our
 *              Google Workspace domain (@firstchurchseattle.org). Existing WordPress
 *              users on any domain (e.g. gmail.com) can still log in with Google —
 *              the plugin only consults this policy when no matching user exists yet.
 * Author: First Church Seattle
 * Version: 1.0.0
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Belt-and-suspenders on top of the plugin's "Whitelisted Domains" setting: this
 * filter is the authoritative gate, so the behavior survives even if that setting
 * is cleared in the admin UI. The plugin applies `rtcamp.google_register_user` in
 * Authenticator::authenticate() ONLY for a Google email with no existing WP user;
 * existing users are returned and logged in before this ever runs, so other-domain
 * logins are unaffected.
 *
 * @package firstchurch
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'FIRSTCHURCH_GOOGLE_AUTOCREATE_DOMAINS' ) ) {
	/**
	 * Comma-separated list of domains allowed to auto-create accounts.
	 * Defined as a constant so it can be overridden in wp-config.php if needed.
	 */
	define( 'FIRSTCHURCH_GOOGLE_AUTOCREATE_DOMAINS', 'firstchurchseattle.org' );
}

if ( ! function_exists( 'firstchurch_google_autocreate_domains' ) ) {
	/**
	 * Normalized list of domains permitted to auto-create accounts.
	 *
	 * @return string[] Lower-cased, trimmed domains.
	 */
	function firstchurch_google_autocreate_domains() {
		$domains = array_map(
			static function ( $domain ) {
				return strtolower( trim( $domain ) );
			},
			explode( ',', FIRSTCHURCH_GOOGLE_AUTOCREATE_DOMAINS )
		);

		return array_filter( $domains );
	}
}

if ( ! function_exists( 'firstchurch_google_register_user' ) ) {
	/**
	 * Allow auto-registration only for our workspace domain(s).
	 *
	 * Runs via rtCamp Login with Google's `rtcamp.google_register_user` filter,
	 * which fires only when the Google email has no existing WP user. Returning
	 * false makes the plugin reject the sign-in instead of creating an account;
	 * existing users (any domain) never reach this path.
	 *
	 * @param mixed  $register Whatever the plugin passes through (the prospective
	 *                         user object/username); returned unchanged when allowed.
	 * @param object $user     Google user object exposing ->email.
	 * @return mixed The unchanged $register value to permit creation, or false to deny.
	 */
	function firstchurch_google_register_user( $register, $user ) {
		if ( ! is_object( $user ) || empty( $user->email ) || ! is_string( $user->email ) ) {
			return false;
		}

		$at = strrchr( $user->email, '@' );
		if ( false === $at ) {
			return false;
		}

		$domain = strtolower( substr( $at, 1 ) );

		return in_array( $domain, firstchurch_google_autocreate_domains(), true ) ? $register : false;
	}
}
add_filter( 'rtcamp.google_register_user', 'firstchurch_google_register_user', 10, 2 );
