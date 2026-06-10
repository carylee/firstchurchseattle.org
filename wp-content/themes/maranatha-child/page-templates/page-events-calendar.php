<?php
/**
 * Template Name: Events - Calendar (Spine)
 *
 * Spine-backed replacement for the parent theme's "Events - Calendar" template
 * (page-templates/events-calendar.php), which queried only `ctc_event` (via
 * ctfw_event_calendar_data) and went empty once events migrated to `fce_event`.
 *
 * Renders a static month grid from happenings_event_occurrences() — the spine's
 * occurrence-expanded event feed (recurring events land on each of their dates).
 * Reuses the parent theme's `maranatha-calendar-table*` styling for the grid (no
 * JS/AJAX — month nav is plain ?month= links), with a card list below for mobile.
 * See ops/docs/happenings.md (§5).
 *
 * Assign via wp-admin → Pages → Events Calendar → Page Attributes → Template.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The parent maps its own events-calendar.php template to the full 1170px
// container (maranatha_content_width() in includes/content.php special-cases it
// by filename). This replacement template isn't on that list, so it would fall
// back to the 700px reading width — far too narrow for a 7-column month grid.
// Claim the full width here; this file only loads when the template is active.
add_filter(
	'maranatha_content_width',
	function () {
		return 1170;
	}
);

add_action( 'maranatha_after_content', 'fcs_events_calendar_after_content' );
function fcs_events_calendar_after_content() {
	if ( ! function_exists( 'happenings_event_occurrences' ) || ! function_exists( 'fcs_render_happening_card' ) ) {
		return; // spine inactive — fail soft.
	}

	$today      = current_time( 'Y-m-d' );          // site-local
	$this_month = substr( $today, 0, 7 );           // Y-m

	// Selected month from ?month=YYYY-MM, clamped to [this month, +12 months].
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only navigation, no state change.
	$req   = isset( $_GET['month'] ) ? preg_replace( '/[^0-9-]/', '', sanitize_text_field( wp_unslash( $_GET['month'] ) ) ) : '';
	$month = preg_match( '/^\d{4}-\d{2}$/', $req ) ? $req : $this_month;
	if ( $month < $this_month ) {
		$month = $this_month;
	}

	// First/last of month as UTC-midnight immutables; format() never applies the
	// WP tz offset, so month/day labels stay correct (no boundary drift).
	$first = DateTimeImmutable::createFromFormat( '!Y-m-d', $month . '-01' );
	if ( ! $first ) {
		$first = DateTimeImmutable::createFromFormat( '!Y-m-d', $this_month . '-01' );
		$month = $this_month;
	}
	$last = $first->modify( 'last day of this month' );

	// Grid spans whole weeks. start_of_week: 0 = Sunday … 6 = Saturday.
	$sow        = (int) get_option( 'start_of_week' );
	$back       = ( (int) $first->format( 'w' ) - $sow + 7 ) % 7;
	$grid_start = $first->modify( "-{$back} days" );
	$fwd        = 6 - ( ( (int) $last->format( 'w' ) - $sow + 7 ) % 7 );
	$grid_end   = $last->modify( "+{$fwd} days" );

	// Occurrences across the visible grid, grouped by Y-m-d.
	$by_date = array();
	foreach ( happenings_event_occurrences( $grid_start->format( 'Y-m-d' ), $grid_end->format( 'Y-m-d' ) ) as $it ) {
		$by_date[ $it['date'] ][] = $it;
	}

	// Weekday header labels, localized, starting at start_of_week.
	global $wp_locale;
	$weekdays = array();
	for ( $i = 0; $i < 7; $i++ ) {
		$weekdays[] = $wp_locale->get_weekday( ( $sow + $i ) % 7 );
	}

	$prev      = $first->modify( '-1 month' );
	$next      = $first->modify( '+1 month' );
	$max_month = DateTimeImmutable::createFromFormat( '!Y-m-d', $this_month . '-01' )->modify( '+12 months' )->format( 'Y-m' );
	$show_prev = $month > $this_month;
	$show_next = $next->format( 'Y-m' ) <= $max_month;

	// NB: deliberately NOT id="maranatha-calendar" — that id makes the parent
	// theme's main.js (maranatha_attach_calendar_dropdowns + pjax) auto-fire and
	// call a jQuery .dropdown() plugin we don't load (console error). We reuse the
	// table's `maranatha-calendar-table*` styling without the parent's JS.
	echo '<section class="fcs-events-calendar" aria-label="' . esc_attr__( 'Events calendar', 'maranatha-child' ) . '">';

	// ---- Header: prev · month · next ----
	echo '<div class="fcs-events-calendar__header">';
	if ( $show_prev ) {
		echo '<a class="fcs-events-calendar__nav" href="' . esc_url( add_query_arg( 'month', $prev->format( 'Y-m' ) ) ) . '">&larr; ' . esc_html( $prev->format( 'F' ) ) . '</a>';
	} else {
		echo '<span class="fcs-events-calendar__nav is-disabled" aria-hidden="true"></span>';
	}
	echo '<h2 class="fcs-events-calendar__title">' . esc_html( $first->format( 'F Y' ) ) . '</h2>';
	if ( $show_next ) {
		echo '<a class="fcs-events-calendar__nav" href="' . esc_url( add_query_arg( 'month', $next->format( 'Y-m' ) ) ) . '">' . esc_html( $next->format( 'F' ) ) . ' &rarr;</a>';
	} else {
		echo '<span class="fcs-events-calendar__nav is-disabled" aria-hidden="true"></span>';
	}
	echo '</div>';

	// ---- Month grid (reuses parent maranatha-calendar-table* styling) ----
	echo '<table id="maranatha-calendar-table"><tbody>';
	echo '<tr class="maranatha-calendar-table-header-row">';
	foreach ( $weekdays as $wd ) {
		echo '<th class="maranatha-calendar-table-header"><div class="maranatha-calendar-table-header-content"><span class="maranatha-calendar-table-header-full">' . esc_html( $wd ) . '</span></div></th>';
	}
	echo '</tr>';

	$cur = $grid_start;
	while ( $cur <= $grid_end ) {
		echo '<tr class="maranatha-calendar-table-week">';
		for ( $i = 0; $i < 7; $i++ ) {
			$ymd        = $cur->format( 'Y-m-d' );
			$dow_friend = ( ( (int) $cur->format( 'w' ) - $sow + 7 ) % 7 ) + 1; // 1..7
			$has_events = ! empty( $by_date[ $ymd ] );

			$classes = array( 'maranatha-calendar-table-day', 'maranatha-calendar-table-day-' . $dow_friend );
			if ( $cur->format( 'Y-m' ) !== $month ) {
				$classes[] = 'maranatha-calendar-table-day-other-month';
			}
			if ( $ymd === $today ) {
				$classes[] = 'maranatha-calendar-table-day-today';
			}
			if ( $ymd < $today ) {
				$classes[] = 'maranatha-calendar-table-day-past';
			}
			if ( $has_events ) {
				$classes[] = 'maranatha-calendar-table-day-has-events';
			}

			echo '<td class="' . esc_attr( implode( ' ', $classes ) ) . '" data-date="' . esc_attr( $ymd ) . '">';
			echo '<div class="maranatha-calendar-table-day-content-container"><div class="maranatha-calendar-table-day-content">';
			echo '<div class="maranatha-calendar-table-day-heading">';
			if ( $ymd === $today ) {
				echo '<span class="maranatha-calendar-table-day-label">' . esc_html_x( 'Today', 'event calendar', 'maranatha-child' ) . '</span>';
			}
			echo '<span class="maranatha-calendar-table-day-number">' . esc_html( $cur->format( 'j' ) ) . '</span>';
			echo '</div>';

			// Only surface events today or later (past days stay quiet, like the old calendar).
			if ( $has_events && $ymd >= $today ) {
				echo '<ul class="maranatha-calendar-table-day-events">';
				foreach ( $by_date[ $ymd ] as $ev ) {
					// Weekly rhythms land on every week of the grid by design; mute
					// them so the special Sundays read as the signal (event-kinds.md).
					$li_class = 'rhythm' === ( $ev['kind'] ?? '' ) ? ' class="fcs-cal-event--rhythm"' : '';
					echo '<li' . $li_class . '><a href="' . esc_url( $ev['url'] ) . '">' . esc_html( $ev['title'] ) . '</a></li>';
				}
				echo '</ul>';
			}

			echo '</div></div></td>';
			$cur = $cur->modify( '+1 day' );
		}
		echo '</tr>';
	}
	echo '</tbody></table>';

	// ---- Card list below: one card per event this month (deduped), for mobile
	// and as an accessible linear view of the grid. ----
	$seen  = array();
	$cards = array();
	foreach ( happenings_event_occurrences( $first->format( 'Y-m-d' ), $last->format( 'Y-m-d' ) ) as $it ) {
		$key = $it['url'] ?: $it['id'];
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$cards[]      = $it;
	}

	if ( ! empty( $cards ) ) {
		echo '<div class="fcs-events-calendar__list">';
		echo '<h3 class="fcs-events-calendar__list-heading">' . esc_html( sprintf( /* translators: %s: month and year */ __( 'Events in %s', 'maranatha-child' ), $first->format( 'F Y' ) ) ) . '</h3>';
		echo '<div class="fcs-card-grid">';
		foreach ( $cards as $item ) {
			echo fcs_render_happening_card( happenings_card_view( $item ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally.
		}
		echo '</div>';
		echo '</div>';
	}

	echo '</section>';
}

// Load main template to show the page (standard chrome + the page's own content).
locate_template( 'index.php', true );
