<?php
/**
 * The curated deck: an ordered list of references + per-entry overrides, stored
 * as the `fccar_deck` option (design doc §5.3). Each entry references a live
 * candidate by feed id and patches a few fields; content stays live (editing
 * the underlying event/post/card updates the card). Absence of the option means
 * "never curated" — the resolver auto-assembles the default instead.
 *
 * Entry shape (all overrides optional; empty string = use the source value):
 *   { id, title, when, image, preserviceOnly }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FCCAR_DECK_OPTION = 'fccar_deck';

/** The saved deck, or null if never curated. */
function fccar_get_deck(): ?array {
	$deck = get_option( FCCAR_DECK_OPTION, null );
	return is_array( $deck ) ? $deck : null;
}

/** Persist a (already-sanitized) deck. */
function fccar_save_deck( array $deck ): void {
	update_option( FCCAR_DECK_OPTION, array_values( $deck ), false );
}

/** Forget the curated deck — the feed reverts to the auto-assembled default. */
function fccar_reset_deck(): void {
	delete_option( FCCAR_DECK_OPTION );
}

/** Sanitize one raw entry from the editor into a storable entry. */
function fccar_sanitize_deck_entry( $e ): ?array {
	if ( ! is_array( $e ) ) {
		return null;
	}
	$id = isset( $e['id'] ) ? sanitize_text_field( (string) $e['id'] ) : '';
	if ( ! preg_match( '/^(card|event|announcement)-\d+$/', $id ) ) {
		return null;
	}
	return array(
		'id'             => $id,
		'title'          => isset( $e['title'] ) ? sanitize_text_field( (string) $e['title'] ) : '',
		'when'           => isset( $e['when'] ) ? sanitize_text_field( (string) $e['when'] ) : '',
		'image'          => isset( $e['image'] ) ? esc_url_raw( (string) $e['image'] ) : '',
		'preserviceOnly' => ! empty( $e['preserviceOnly'] ),
	);
}

/**
 * Resolve the deck to ordered feed items: look each entry's id up against the
 * live content, apply its overrides, and drop entries whose source is gone.
 */
function fccar_resolve_from_deck( array $deck ): array {
	$out = array();
	foreach ( $deck as $e ) {
		$id = isset( $e['id'] ) ? (string) $e['id'] : '';
		if ( '' === $id ) {
			continue;
		}
		$item = fccar_item_by_id( $id );
		if ( null === $item ) {
			continue; // referenced content unpublished/deleted — skip
		}
		if ( ! empty( $e['title'] ) ) {
			$item['title'] = (string) $e['title'];
		}
		if ( ! empty( $e['when'] ) ) {
			$item['when'] = (string) $e['when'];
		}
		if ( ! empty( $e['image'] ) ) {
			$item['image'] = (string) $e['image'];
		}
		if ( ! empty( $e['preserviceOnly'] ) ) {
			$item['preserviceOnly'] = true;
		} else {
			unset( $item['preserviceOnly'] );
		}
		$out[] = $item;
	}
	return $out;
}

/**
 * A row for the curation UI: the source's current values plus any overrides on
 * the entry. Used for both the deck list (entry given) and the available pool
 * (entry null → defaults from the source).
 */
function fccar_deck_view_row( array $item, ?array $entry = null ): array {
	return array(
		'id'             => $item['id'],
		'source'         => $item['source'],
		'layout'         => $item['layout'],
		'srcTitle'       => $item['title'] ?? '',
		'srcWhen'        => $item['when'] ?? '',
		'srcImage'       => $item['image'] ?? '',
		'title'          => $entry['title'] ?? '',
		'when'           => $entry['when'] ?? '',
		'image'          => $entry['image'] ?? '',
		'preserviceOnly' => $entry ? ! empty( $entry['preserviceOnly'] ) : ! empty( $item['preserviceOnly'] ),
		// Non-overridable content fields, carried so the curation screen can
		// render a faithful thumbnail of the card (same renderer as the live feed).
		'body'            => $item['body'] ?? '',
		'prompt'          => $item['prompt'] ?? '',
		'details'         => $item['details'] ?? '',
		'ctaUrl'          => $item['ctaUrl'] ?? '',
		'backgroundColor' => $item['backgroundColor'] ?? '',
	);
}

/**
 * Build the editor's view model: the deck rows (saved deck, or the
 * auto-assembled default on first visit) and the available candidates not yet
 * in the deck.
 *
 * @return array{ deck: array, available: array }
 */
function fccar_curate_view(): array {
	$pool = array();
	foreach ( fccar_candidate_pool() as $it ) {
		$pool[ $it['id'] ] = $it;
	}

	$deck = fccar_get_deck();
	if ( null === $deck ) {
		// First visit: seed the editor from the auto-assembled default so there
		// is something to curate rather than a blank slate.
		$deck = array_map(
			static function ( $it ) {
				return array( 'id' => $it['id'], 'preserviceOnly' => ! empty( $it['preserviceOnly'] ) );
			},
			fccar_autodeck_items()
		);
	}

	$deck_rows = array();
	$used      = array();
	foreach ( $deck as $entry ) {
		$id   = isset( $entry['id'] ) ? (string) $entry['id'] : '';
		$item = $pool[ $id ] ?? fccar_item_by_id( $id );
		if ( null === $item ) {
			continue;
		}
		$used[ $id ] = true;
		$deck_rows[] = fccar_deck_view_row( $item, $entry );
	}

	$available = array();
	foreach ( $pool as $id => $item ) {
		if ( empty( $used[ $id ] ) ) {
			$available[] = fccar_deck_view_row( $item, null );
		}
	}

	return array( 'deck' => $deck_rows, 'available' => $available );
}
