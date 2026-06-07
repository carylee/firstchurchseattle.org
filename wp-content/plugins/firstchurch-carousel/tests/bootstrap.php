<?php
/**
 * PHPUnit bootstrap for the carousel plugin.
 *
 * The plugin is procedural WordPress code (inc/*.php), but a useful slice of it
 * is pure logic: deck-entry sanitization, layout shape-detection, text
 * normalization, and the recurrence → human "when" string formatter. We test
 * that slice outside WordPress by defining behavior-faithful shims for the
 * handful of WP primitives those functions touch — so the assertions exercise
 * real behavior (a bad id is genuinely rejected, "Men&#8217;s" genuinely
 * decodes), not a no-op. Each shim is guarded with function_exists() so running
 * inside a real WP test install stays harmless.
 *
 * Loading the plugin's main file pulls in inc/*.php; their only load-time work is
 * defining constants and registering hooks, which the no-op hook shims absorb.
 *
 * @package FirstChurch\Carousel\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' ); // satisfy the inc/*.php guards
}

/* ---- hook + plugin lifecycle: no-ops (we test functions, not wiring) ---- */
foreach ( array( 'add_action', 'add_filter', 'register_activation_hook', 'register_deactivation_hook' ) as $fn ) {
	if ( ! function_exists( $fn ) ) {
		eval( "function {$fn}() {}" );
	}
}

/* ---- escaping / sanitizing: faithful-enough ports ---- */

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $v ) {
		return is_string( $v ) ? stripslashes( $v ) : $v;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ): string {
		$str = (string) $str;
		$str = wp_strip_all_tags( $str );
		$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );
		return trim( $str );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ): string {
		$str = (string) $str;
		$str = wp_strip_all_tags( $str );
		// Preserve newlines, collapse runs of spaces/tabs.
		$str = preg_replace( '/[ \t]+/', ' ', $str );
		return trim( $str );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $str ): string {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/** DB/redirect context: allow only http/https/relative, strip dangerous chars. */
	function esc_url_raw( $url ): string {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( preg_match( '#^\s*javascript:#i', $url ) ) {
			return '';
		}
		if ( ! preg_match( '#^(https?:)?//#i', $url ) && ! str_starts_with( $url, '/' ) ) {
			return '';
		}
		return str_replace( array( ' ', '"', "'", '<', '>' ), array( '%20', '%22', '%27', '%3C', '%3E' ), $url );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ): string {
		return str_replace( '&', '&#038;', esc_url_raw( $url ) );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $t ): string {
		return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $t ): string {
		return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $t ): string {
		return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $n ): int {
		return abs( (int) $n );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $flags = 0 ) {
		return json_encode( $data, $flags );
	}
}

/* ---- dates: lean on PHP's date(); strtotime is native ---- */

if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $format, $ts = null ): string {
		return date( $format, null === $ts ? time() : (int) $ts );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		return 'timestamp' === $type ? time() : date( 'Y-m-d' === $type ? 'Y-m-d' : 'c' );
	}
}

/* ---- in-memory post-meta store (drives the recurrence/when tests) ---- */

$GLOBALS['__fccar_meta'] = array(); // [ post_id => [ key => value ] ]

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$all = $GLOBALS['__fccar_meta'][ $post_id ] ?? array();
		if ( '' === $key ) {
			return $all;
		}
		$v = $all[ $key ] ?? '';
		return $single ? $v : array( $v );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ): bool {
		$GLOBALS['__fccar_meta'][ $post_id ][ $key ] = $value;
		return true;
	}
}

/** Test helper: seed a post's meta map in one shot. */
function fccar_test_set_meta( int $post_id, array $meta ): void {
	$GLOBALS['__fccar_meta'][ $post_id ] = $meta;
}

/* ---- load the plugin (constants + function defs; hooks are no-ops) ---- */

require __DIR__ . '/../firstchurch-carousel.php';
