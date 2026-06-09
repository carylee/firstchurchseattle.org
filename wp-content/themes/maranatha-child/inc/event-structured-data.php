<?php
/**
 * Event JSON-LD for single event pages — itself a spine surface.
 *
 * Reads the SAME projection the single template renders
 * (happenings_item_by_id('event-<id>')), never re-deriving event logic from post
 * meta: "the destination a projection points at is also a projection"
 * (ops/docs/happenings.md §5). The machine `start` + `location` fields it relies
 * on are part of the Happening contract (§2), supplied by the events source.
 *
 * Complements Yoast (which emits Article/WebPage for the CPT but not Event) and
 * unlocks event rich results. Conservative: emits only when the projection has a
 * title and a start date, so we never publish invalid schema.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_head',
	static function () {
		if ( ! is_singular( array( 'fce_event', 'ctc_event' ) ) || ! function_exists( 'happenings_item_by_id' ) ) {
			return;
		}

		$id   = get_queried_object_id();
		$item = happenings_item_by_id( 'event-' . $id );
		if ( ! $item ) {
			return;
		}

		$title = isset( $item['title'] ) ? trim( (string) $item['title'] ) : '';
		// Prefer the machine `start` (ISO datetime); fall back to the date-only
		// `date` the projection always carries for an upcoming occurrence.
		$start = '';
		if ( ! empty( $item['start'] ) ) {
			$start = (string) $item['start'];
		} elseif ( ! empty( $item['date'] ) ) {
			$start = (string) $item['date'];
		}
		if ( '' === $title || '' === $start ) {
			return;
		}

		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Event',
			'name'        => $title,
			'startDate'   => $start,
			'eventStatus' => 'https://schema.org/EventScheduled',
			'url'         => ! empty( $item['url'] ) ? (string) $item['url'] : get_permalink( $id ),
			'organizer'   => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
		);

		if ( ! empty( $item['image'] ) ) {
			$schema['image'] = (string) $item['image'];
		}

		// A real venue → an offline Place. Without it we leave location/attendance
		// mode off rather than guess.
		if ( ! empty( $item['location'] ) ) {
			$schema['location']            = array(
				'@type' => 'Place',
				'name'  => (string) $item['location'],
			);
			$schema['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';
		}

		// Description: the lean blurb if present, else the body stripped to text,
		// collapsed and capped so the JSON-LD stays compact.
		$desc = '';
		if ( ! empty( $item['blurb'] ) ) {
			$desc = (string) $item['blurb'];
		} elseif ( ! empty( $item['content'] ) ) {
			$desc = wp_strip_all_tags( (string) $item['content'] );
		}
		$desc = trim( (string) preg_replace( '/\s+/', ' ', $desc ) );
		if ( '' !== $desc ) {
			if ( mb_strlen( $desc ) > 300 ) {
				$desc = rtrim( mb_substr( $desc, 0, 299 ) ) . '…';
			}
			$schema['description'] = $desc;
		}

		echo "\n" . '<script type="application/ld+json">'
			. wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. '</script>' . "\n";
	},
	20
);
