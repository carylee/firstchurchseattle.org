<?php
/**
 * Homepage: visit card + "coming up" strip.
 *
 * Rendered in the slot right after the hero section (see the child's
 * partials/map-section.php override). Two jobs, per
 * ops/docs/homepage-recommendations-2026-06.md:
 *
 * 1. Visit card — the logistics a first-time visitor needs (time, address,
 *    free parking) with the two homepage CTAs: Plan Your Visit + Get
 *    Directions. Static markup, no Maps JS.
 * 2. Happenings strip — the next few time-bound one-off events from the
 *    Happenings spine ('event' kind only; the weekly rhythms would otherwise
 *    crowd this out every week — see ops/docs/event-kinds.md). Gives the
 *    homepage a heartbeat that updates itself as events are authored.
 *    Fails soft to nothing if the spine plugin is inactive.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** How far ahead the homepage strip looks, and how many cards it shows. */
$fcs_home_weeks = 8;
$fcs_home_count = 3;
?>

<section class="maranatha-home-section fcs-visit" aria-label="<?php esc_attr_e( 'Visit First Church', 'maranatha-child' ); ?>">
	<div class="fcs-visit__inner">

		<div class="fcs-visit__details">
			<h2 class="fcs-visit__heading"><?php esc_html_e( 'Join us this Sunday', 'maranatha-child' ); ?></h2>
			<p class="fcs-visit__time"><?php esc_html_e( 'Worship Sundays at 10:30 am — in person and live on YouTube.', 'maranatha-child' ); ?></p>
			<address class="fcs-visit__address">
				<a href="https://maps.google.com/?q=180+Denny+Way,+Seattle,+WA+98109" target="_blank" rel="noopener noreferrer">180 Denny Way, Seattle, WA 98109</a>
			</address>
			<p class="fcs-visit__parking"><?php esc_html_e( 'Free parking in our garage — enter on Warren Ave N, between the church and garage.', 'maranatha-child' ); ?></p>
		</div>

		<div class="fcs-visit__actions">
			<a class="fcs-visit__btn fcs-visit__btn--primary" href="<?php echo esc_url( home_url( '/about/newcomers/' ) ); ?>"><?php esc_html_e( 'Plan Your Visit', 'maranatha-child' ); ?></a>
			<a class="fcs-visit__btn" href="<?php echo esc_url( home_url( '/worship/live/' ) ); ?>"><?php esc_html_e( 'Watch Live', 'maranatha-child' ); ?></a>
			<a class="fcs-visit__btn" href="https://www.google.com/maps/dir/?api=1&amp;destination=180+Denny+Way%2C+Seattle%2C+WA+98109" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get Directions', 'maranatha-child' ); ?></a>
		</div>

	</div>
</section>

<?php
// Happenings strip — one-off events only, soonest first.
if ( function_exists( 'happenings_event_items' )
	&& function_exists( 'happenings_card_view' )
	&& function_exists( 'fcs_render_happening_card' ) ) :

	$fcs_home_items = array_slice( happenings_event_items( $fcs_home_weeks, array( 'event' ) ), 0, $fcs_home_count );

	if ( ! empty( $fcs_home_items ) ) :
		?>

<section class="maranatha-home-section fcs-home-happenings" aria-label="<?php esc_attr_e( 'Coming up at First Church', 'maranatha-child' ); ?>">
	<div class="fcs-home-happenings__inner">

		<h2 class="fcs-home-happenings__heading"><?php esc_html_e( 'Coming up at First Church', 'maranatha-child' ); ?></h2>

		<div class="fcs-card-grid fcs-card-grid--three">
			<?php
			foreach ( $fcs_home_items as $fcs_home_item ) {
				echo fcs_render_happening_card( happenings_card_view( $fcs_home_item ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally.
			}
			?>
		</div>

		<p class="fcs-home-happenings__more">
			<a href="<?php echo esc_url( home_url( '/engage/' ) ); ?>"><?php esc_html_e( 'See everything happening →', 'maranatha-child' ); ?></a>
		</p>

	</div>
</section>

		<?php
	endif;
endif;
