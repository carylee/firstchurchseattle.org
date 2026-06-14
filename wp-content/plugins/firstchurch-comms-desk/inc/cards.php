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

/**
 * Render the AI's structured uncertainties as a "check these" checklist, so
 * review is "confirm two things" rather than "re-read everything." Returns ''
 * when there are no gaps. Expects already-normalized entries (see the
 * breeze-forms Gaps helper).
 *
 * @param array<int,array{field?:string,question?:string}> $gaps
 */
function fccd_render_gaps( array $gaps ): string {
	$items = '';
	foreach ( $gaps as $g ) {
		$question = trim( (string) ( $g['question'] ?? '' ) );
		if ( '' === $question ) {
			continue;
		}
		$field = trim( (string) ( $g['field'] ?? '' ) );
		$items .= '<li>'
			. ( '' !== $field ? '<span class="fccd-gap-field">' . esc_html( $field ) . ':</span> ' : '' )
			. esc_html( $question ) . '</li>';
	}
	if ( '' === $items ) {
		return '';
	}
	return '<div class="fccd-gaps"><p class="fccd-gaps-head">Check these before publishing:</p><ul>' . $items . '</ul></div>';
}

/* ============================================================================
 * Speed — triage the worklist so the easy majority clears in one pass
 * ========================================================================== */

/** Confidence at/above which a complete draft is considered publish-ready. */
const FCCD_READY_CONFIDENCE = 0.8;

/**
 * Is this card safe to batch-approve without a closer look? A normal review
 * draft (not a revision) the AI was confident about, that already has a photo
 * and carries no "worth a look" note.
 *
 * @param array<string,mixed> $c A card from fccd_needs_you_now().
 */
function fccd_card_is_ready( array $c ): bool {
	if ( 'review' !== ( $c['type'] ?? 'review' ) ) {
		return false;
	}
	$conf = $c['confidence'] ?? null;
	if ( null === $conf || (float) $conf < FCCD_READY_CONFIDENCE ) {
		return false;
	}
	if ( '' === (string) ( $c['photo'] ?? '' ) ) {
		return false;
	}
	if ( '' !== trim( (string) ( $c['note'] ?? '' ) ) ) {
		return false;
	}
	// Any structured gap the AI flagged means "look first," not "auto-publish."
	return empty( $c['gaps'] );
}

/**
 * Partition the worklist into 'ready' (surest first — blast through them) and
 * 'look' (most-uncertain first — spend attention there). Pure: returns reordered
 * copies, leaves the input untouched.
 *
 * @param array<int,array<string,mixed>> $cards
 * @return array{ready:array<int,array<string,mixed>>,look:array<int,array<string,mixed>>}
 */
function fccd_partition_cards( array $cards ): array {
	$ready = array();
	$look  = array();
	foreach ( $cards as $c ) {
		if ( fccd_card_is_ready( $c ) ) {
			$ready[] = $c;
		} else {
			$look[] = $c;
		}
	}
	$conf = static fn ( $c ) => (float) ( $c['confidence'] ?? 0 );
	usort( $ready, static fn ( $a, $b ) => $conf( $b ) <=> $conf( $a ) ); // surest first
	usort( $look, static fn ( $a, $b ) => $conf( $a ) <=> $conf( $b ) );  // riskiest first
	return array( 'ready' => $ready, 'look' => $look );
}

/* ============================================================================
 * Closure — turn "Needs info" into a real message and stop it nagging
 * ========================================================================== */

/**
 * Build a ready-to-send clarification email to the submitter, in the house
 * voice (warm, an invitation not a demand). Returns a mailto: URL, or '' when
 * there's no usable recipient — the caller falls back to just recording a note.
 */
function fccd_clarification_mailto( string $email, string $title, string $question ): string {
	$email = trim( $email );
	if ( '' === $email || false === strpos( $email, '@' ) || false !== strpos( $email, ' ' ) ) {
		return '';
	}
	$subject = 'Quick question about ' . ( '' !== trim( $title ) ? trim( $title ) : 'your submission' );
	$body    = "Hi,\n\n"
		. "Thanks so much for sending this our way! One quick thing so we can post it:\n\n"
		. trim( $question ) . "\n\n"
		. "No rush — just reply whenever you can and we'll take it from there.\n\n"
		. "Warmly,\nFirst Church Seattle";
	return 'mailto:' . $email . '?subject=' . rawurlencode( $subject ) . '&body=' . rawurlencode( $body );
}

/**
 * Split the worklist into items still needing action vs. those parked awaiting a
 * submitter reply (so "Needs info" stops re-surfacing the same card every visit).
 *
 * @param array<int,array<string,mixed>> $cards
 * @return array{active:array<int,array<string,mixed>>,awaiting:array<int,array<string,mixed>>}
 */
function fccd_split_awaiting( array $cards ): array {
	$active   = array();
	$awaiting = array();
	foreach ( $cards as $c ) {
		if ( ! empty( $c['awaiting'] ) ) {
			$awaiting[] = $c;
		} else {
			$active[] = $c;
		}
	}
	return array( 'active' => $active, 'awaiting' => $awaiting );
}
