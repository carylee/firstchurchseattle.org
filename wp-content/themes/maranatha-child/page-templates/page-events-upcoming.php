<?php
/**
 * Template Name: Events - Upcoming (Spine)
 *
 * Spine-backed replacement for the parent theme's "Events - Upcoming" template
 * (page-templates/events-upcoming.php), which queried only `ctc_event` and went
 * empty once events migrated to the lean `fce_event` backend. This renders the
 * Happenings spine's upcoming events — the "author once, project everywhere"
 * surface described in ops/docs/happenings.md (§5).
 *
 * Mirrors the parent's pattern: hook `maranatha_after_content` to inject the list
 * after the page's own intro copy, then load index.php for the standard chrome
 * (banner, breadcrumb, title, content). The cards reuse the /engage `.fcs-card`
 * language via fcs_render_happening_card() (inc/happenings-block.php).
 *
 * Assign via wp-admin → Pages → Upcoming Events → Page Attributes → Template.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Forward look-ahead for the list, in weeks (~6 months — the migration window). */
if ( ! defined( 'FCS_EVENTS_LIST_WEEKS' ) ) {
	define( 'FCS_EVENTS_LIST_WEEKS', 26 );
}

add_action( 'maranatha_after_content', 'fcs_events_upcoming_after_content' );
function fcs_events_upcoming_after_content() {
	// Fail soft if the spine is inactive — show nothing rather than error.
	if ( ! function_exists( 'happenings_event_items' ) || ! function_exists( 'fcs_render_happening_card' ) ) {
		return;
	}

	// Three groups by kind (ops/docs/event-kinds.md): the time-bound one-offs
	// lead — before kinds, the permanent weekly fixtures sat pinned above them
	// every single week. The standing rhythms and ongoing groups follow as
	// context, not competition.
	$events  = happenings_event_items( FCS_EVENTS_LIST_WEEKS, array( 'event' ) );
	$rhythms = function_exists( 'happenings_rhythm_items' ) ? happenings_rhythm_items( FCS_EVENTS_LIST_WEEKS ) : array();
	$groups  = happenings_event_items( FCS_EVENTS_LIST_WEEKS, array( 'group' ) );

	echo '<section class="fcs-events fcs-events--list" aria-label="' . esc_attr__( 'Upcoming events', 'maranatha-child' ) . '">';

	if ( empty( $events ) ) {
		echo '<p class="fcs-events__empty">' . esc_html__( 'No upcoming events are scheduled right now — please check back soon.', 'maranatha-child' ) . '</p>';
	} else {
		echo '<div class="fcs-card-grid">';
		foreach ( $events as $item ) {
			echo fcs_render_happening_card( happenings_card_view( $item ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally.
		}
		echo '</div>';
	}

	if ( ! empty( $rhythms ) ) {
		echo '<h2 class="fcs-happenings__heading">' . esc_html__( 'Every week', 'maranatha-child' ) . '</h2>';
		echo fcs_render_rhythm_strip( $rhythms ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally.
	}

	if ( ! empty( $groups ) ) {
		echo '<h2 class="fcs-happenings__heading">' . esc_html__( 'Groups & gatherings', 'maranatha-child' ) . '</h2>';
		echo '<div class="fcs-card-grid">';
		foreach ( $groups as $item ) {
			echo fcs_render_happening_card( happenings_card_view( $item ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally.
		}
		echo '</div>';
	}

	echo '</section>';
}

// Load main template to show the page (standard chrome + the page's own content).
locate_template( 'index.php', true );
