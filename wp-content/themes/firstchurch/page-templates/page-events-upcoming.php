<?php
/**
 * Template Name: Events - Upcoming (Spine)
 *
 * Renders the Happenings spine's upcoming events after the page's own intro
 * copy — the "author once, project everywhere" surface described in
 * ops/docs/happenings.md (§5). The cards reuse the /engage `.fcs-card`
 * language via fcs_render_happening_card() (inc/happenings-block.php).
 *
 * Assign via wp-admin → Pages → Upcoming Events → Page Attributes → Template.
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Forward look-ahead for the list, in weeks (~6 months). */
if ( ! defined( 'FCS_EVENTS_LIST_WEEKS' ) ) {
	define( 'FCS_EVENTS_LIST_WEEKS', 26 );
}

/**
 * The grouped event sections (one-offs lead, then weekly rhythms, then
 * ongoing groups — see ops/docs/event-kinds.md for why they're separated).
 */
function fcs_events_upcoming_sections() {
	// Fail soft if the spine is inactive — show nothing rather than error.
	if ( ! function_exists( 'happenings_event_items' ) || ! function_exists( 'fcs_render_happening_card' ) ) {
		return;
	}

	$events  = happenings_event_items( FCS_EVENTS_LIST_WEEKS, array( 'event' ) );
	$rhythms = function_exists( 'happenings_rhythm_items' ) ? happenings_rhythm_items( FCS_EVENTS_LIST_WEEKS ) : array();
	$groups  = happenings_event_items( FCS_EVENTS_LIST_WEEKS, array( 'group' ) );

	echo '<section class="fcs-events fcs-events--list" aria-label="' . esc_attr__( 'Upcoming events', 'firstchurch' ) . '">';

	if ( empty( $events ) ) {
		echo '<p class="fcs-events__empty">' . esc_html__( 'No upcoming events are scheduled right now — please check back soon.', 'firstchurch' ) . '</p>';
	} else {
		echo '<div class="fcs-card-grid">';
		foreach ( $events as $item ) {
			echo fcs_render_happening_card( happenings_card_view( $item ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally.
		}
		echo '</div>';
	}

	if ( ! empty( $rhythms ) ) {
		echo '<h2 class="fcs-happenings__heading">' . esc_html__( 'Every week', 'firstchurch' ) . '</h2>';
		echo fcs_render_rhythm_strip( $rhythms ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally.
	}

	if ( ! empty( $groups ) ) {
		echo '<h2 class="fcs-happenings__heading">' . esc_html__( 'Groups & gatherings', 'firstchurch' ) . '</h2>';
		echo '<div class="fcs-card-grid">';
		foreach ( $groups as $item ) {
			echo fcs_render_happening_card( happenings_card_view( $item ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally.
		}
		echo '</div>';
	}

	echo '</section>';
}

get_header();

?>
<main id="fcs-content" class="fcs-main">
	<?php while ( have_posts() ) : the_post(); ?>
		<div class="fcs-container--med">
			<div class="fcs-measure fcs-entry">
				<?php the_content(); ?>
			</div>
			<?php fcs_events_upcoming_sections(); ?>
		</div>
	<?php endwhile; ?>
</main>
<?php

get_footer();
