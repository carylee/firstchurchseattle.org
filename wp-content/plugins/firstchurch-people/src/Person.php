<?php
/**
 * Pure, WordPress-free helpers for the person directory — the bits worth unit
 * testing without a WP runtime (mirrors firstchurch-events' src/). Display +
 * persistence wrappers live in inc/; this is just string/data shaping.
 *
 * @package FirstChurch\People
 */

declare(strict_types=1);

namespace FirstChurch\People;

final class Person {

	/**
	 * Social/web link → icon. Order matters: the first match wins, so put the
	 * specific hosts before the catch-all. Keys are the dashicon-ish slug the
	 * template renders; values are [ needle(s), human label ].
	 *
	 * @var array<string,array{0:string|array<int,string>,1:string}>
	 */
	private const ICON_MAP = array(
		'facebook'  => array( 'facebook.com', 'Facebook' ),
		'instagram' => array( 'instagram.com', 'Instagram' ),
		'twitter'   => array( array( 'twitter.com', 'x.com' ), 'X' ),
		'youtube'   => array( array( 'youtube.com', 'youtu.be' ), 'YouTube' ),
		'linkedin'  => array( 'linkedin.com', 'LinkedIn' ),
		'email'     => array( 'mailto:', 'Email' ),
	);

	/**
	 * Split a CTC-shaped urls blob (one URL per line) into a clean list,
	 * dropping blanks and obvious junk. Preserves author order.
	 *
	 * @return array<int,string>
	 */
	public static function urls( string $blob ): array {
		$out = array();
		foreach ( preg_split( '/\R/', $blob ) ?: array() as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '#^(https?://|mailto:)#i', $line ) ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/**
	 * Map URLs to render-ready social links: [ 'href', 'icon', 'label' ].
	 * Unrecognised URLs fall back to a generic 'link' icon with the host as label.
	 *
	 * @param array<int,string>|string $urls List, or a newline blob.
	 * @return array<int,array{href:string,icon:string,label:string}>
	 */
	public static function socialLinks( $urls ): array {
		$list = is_array( $urls ) ? $urls : self::urls( (string) $urls );
		$out  = array();
		foreach ( $list as $url ) {
			$out[] = array(
				'href'  => $url,
				'icon'  => self::iconFor( $url ),
				'label' => self::labelFor( $url ),
			);
		}
		return $out;
	}

	/** The icon slug for a URL, or 'link' when nothing matches. */
	public static function iconFor( string $url ): string {
		foreach ( self::ICON_MAP as $icon => $data ) {
			foreach ( (array) $data[0] as $needle ) {
				if ( false !== stripos( $url, $needle ) ) {
					return $icon;
				}
			}
		}
		return 'link';
	}

	/** A human label for a URL — the mapped name, else the bare host. */
	public static function labelFor( string $url ): string {
		foreach ( self::ICON_MAP as $data ) {
			foreach ( (array) $data[0] as $needle ) {
				if ( false !== stripos( $url, $needle ) ) {
					return $data[1];
				}
			}
		}
		$host = (string) parse_url( $url, PHP_URL_HOST );
		return '' !== $host ? preg_replace( '/^www\./', '', $host ) : $url;
	}

	/**
	 * Digits (and a leading +) only — the href for a tel: link. Returns '' when
	 * the value has no diallable digits (e.g. "ext. 4" alone), so callers can
	 * decide whether to link.
	 */
	public static function telHref( string $phone ): string {
		$plain = preg_replace( '/[^0-9+]/', '', $phone );
		// A lone '+' or empty string isn't diallable.
		return preg_match( '/[0-9]/', (string) $plain ) ? (string) $plain : '';
	}

	/**
	 * Pronouns, normalised: trimmed, surrounding brackets/parens stripped (the
	 * live page wrote "[she/her]"), collapsed whitespace. '' when none.
	 */
	public static function pronouns( string $raw ): string {
		$p = trim( $raw );
		$p = trim( $p, "[](){}" );
		$p = preg_replace( '/\s+/', ' ', $p );
		return trim( (string) $p );
	}
}
