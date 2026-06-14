<?php
/**
 * Pure card logic for the Comms Desk — the testable seams behind the worklist.
 *
 * These functions take plain arrays/scalars and return values or escaped HTML;
 * they touch only a tiny set of WP primitives (the esc_* family). The
 * WordPress-coupled glue (WP_Query, get_post_meta, REST, echo) lives in
 * desk.php / the REST handlers and calls into here. Keeping the logic pure is
 * what lets the suite exercise it without standing up WordPress.
 *
 * @package FirstChurch\CommsDesk
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================================
 * Trust — let the coordinator check a draft against its source before publishing
 * ========================================================================== */

/**
 * The "What was sent" disclosure: the submitter's original Q&A, collapsed by
 * default, so a reviewer can diff the AI's extracted facts (date, price, name)
 * against the verbatim source. Returns '' when there's nothing to show.
 *
 * @param array<int,array{label?:string,value?:string}> $responses Original label/value pairs.
 * @param array{name?:string,email?:string,phone?:string} $contact  Submitter (may be empty).
 */
function fccd_render_original( array $responses, array $contact = array() ): string {
	$rows = array();
	foreach ( $responses as $r ) {
		$label = trim( (string) ( $r['label'] ?? '' ) );
		$value = trim( (string) ( $r['value'] ?? '' ) );
		if ( '' === $label && '' === $value ) {
			continue;
		}
		$rows[] = array( $label, $value );
	}

	$from = array_filter(
		array(
			(string) ( $contact['name'] ?? '' ),
			(string) ( $contact['email'] ?? '' ),
			(string) ( $contact['phone'] ?? '' ),
		),
		static fn ( $s ) => '' !== trim( (string) $s )
	);

	if ( ! $rows && ! $from ) {
		return '';
	}

	$html = '<details class="fccd-original"><summary>What was sent</summary>';
	if ( $from ) {
		$html .= '<p class="fccd-original-from">' . esc_html( implode( ' · ', $from ) ) . '</p>';
	}
	if ( $rows ) {
		$html .= '<dl class="fccd-original-qa">';
		foreach ( $rows as [$label, $value] ) {
			$html .= '<dt>' . esc_html( $label ) . '</dt><dd>' . nl2br( esc_html( $value ) ) . '</dd>';
		}
		$html .= '</dl>';
	}
	return $html . '</details>';
}

/**
 * Elevate the AI's free-text note ("guesses, gaps, a flyer to attach") from a
 * buried footer line into a "worth a look" callout above the controls. Returns
 * '' for a blank note so callers can append unconditionally.
 */
function fccd_render_note_callout( string $note ): string {
	$note = trim( $note );
	if ( '' === $note ) {
		return '';
	}
	return '<p class="fccd-note-callout"><span class="fccd-note-icon" aria-hidden="true">⚠</span> '
		. '<span class="fccd-note-label">Worth a look:</span> ' . esc_html( $note ) . '</p>';
}
