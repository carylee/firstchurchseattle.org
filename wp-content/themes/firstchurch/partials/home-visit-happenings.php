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
 * 2. This Sunday schedule — every spine occurrence landing on the coming
 *    Sunday (rhythms included: worship, Shared Breakfast, Centering Prayer —
 *    a day view is exactly where the weekly rhythms belong). The dated
 *    heading and the list change weekly with zero human effort.
 * 3. Happenings strip — the next few time-bound one-off events from the
 *    Happenings spine ('event' kind only; the weekly rhythms would otherwise
 *    crowd this out every week — see ops/docs/event-kinds.md). Gives the
 *    homepage a heartbeat that updates itself as events are authored.
 *
 * 2 and 3 fail soft to nothing if the spine plugin is inactive.
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** How far ahead the homepage strip looks, and how many cards it shows. */
$fcs_home_weeks = 8;
$fcs_home_count = 3;

// The Sunday this block describes: today if it IS Sunday (people check the
// site Sunday morning), otherwise the coming one. Site timezone throughout.
$fcs_now           = current_datetime();
$fcs_sunday_offset = ( 7 - (int) $fcs_now->format( 'w' ) ) % 7;
$fcs_sunday        = $fcs_now->modify( '+' . $fcs_sunday_offset . ' days' );
$fcs_sunday_ymd    = $fcs_sunday->format( 'Y-m-d' );

// That Sunday's schedule, soonest first; entries without a start time sink
// to the end. Soft dependency on the spine, like the strip below.
$fcs_sunday_items = array();
if ( function_exists( 'happenings_event_occurrences' ) ) {
	$fcs_sunday_items = happenings_event_occurrences( $fcs_sunday_ymd, $fcs_sunday_ymd );
	usort(
		$fcs_sunday_items,
		static function ( $a, $b ) {
			return strcmp( $a['start'] ?? '9999', $b['start'] ?? '9999' );
		}
	);
}
?>

<section class="fcs-visit" aria-label="<?php esc_attr_e( 'Visit First Church', 'firstchurch' ); ?>">
	<div class="fcs-visit__inner">

		<div class="fcs-visit__details">
			<h2 class="fcs-visit__heading">
				<?php esc_html_e( 'This Sunday at First Church', 'firstchurch' ); ?>
				<span class="fcs-visit__date"><?php echo esc_html( wp_date( 'F j', $fcs_sunday->getTimestamp() ) ); ?></span>
			</h2>
			<p class="fcs-visit__time"><?php esc_html_e( 'Worship Sundays at 10:30 am — in person and live on YouTube.', 'firstchurch' ); ?></p>
			<address class="fcs-visit__address">
				<a href="https://maps.google.com/?q=180+Denny+Way,+Seattle,+WA+98109" target="_blank" rel="noopener noreferrer">180 Denny Way, Seattle, WA 98109</a>
			</address>
			<p class="fcs-visit__parking"><?php esc_html_e( 'Free parking in our garage — enter on Warren Ave N, between the church and garage.', 'firstchurch' ); ?></p>
		</div>

		<div class="fcs-visit__actions">
			<a class="fcs-visit__btn fcs-visit__btn--primary" href="<?php echo esc_url( home_url( '/about/newcomers/' ) ); ?>"><?php esc_html_e( 'Plan Your Visit', 'firstchurch' ); ?></a>
			<a class="fcs-visit__btn" href="<?php echo esc_url( home_url( '/worship/live/' ) ); ?>"><?php esc_html_e( 'Watch Live', 'firstchurch' ); ?></a>
			<a class="fcs-visit__btn" href="https://www.google.com/maps/dir/?api=1&amp;destination=180+Denny+Way%2C+Seattle%2C+WA+98109" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get Directions', 'firstchurch' ); ?></a>
		</div>

		<?php if ( ! empty( $fcs_sunday_items ) ) : ?>
		<div class="fcs-visit__schedule">
			<h3 class="fcs-visit__schedule-heading"><?php esc_html_e( 'The day at a glance', 'firstchurch' ); ?></h3>
			<ul class="fcs-visit__schedule-list">
				<?php foreach ( $fcs_sunday_items as $fcs_sunday_item ) : ?>
					<?php
					$fcs_item_title = trim( (string) ( $fcs_sunday_item['title'] ?? '' ) );
					if ( '' === $fcs_item_title ) {
						continue;
					}
					$fcs_item_start = isset( $fcs_sunday_item['start'] ) ? date_create( (string) $fcs_sunday_item['start'] ) : false;
					$fcs_item_url   = (string) ( $fcs_sunday_item['url'] ?? '' );
					$fcs_item_loc   = trim( (string) ( $fcs_sunday_item['location'] ?? '' ) );
					?>
					<li class="fcs-visit__schedule-item">
						<span class="fcs-visit__schedule-time"><?php echo $fcs_item_start ? esc_html( wp_date( 'g:i a', $fcs_item_start->getTimestamp() ) ) : ''; ?></span>
						<span class="fcs-visit__schedule-what">
							<?php if ( '' !== $fcs_item_url ) : ?>
								<a href="<?php echo esc_url( $fcs_item_url ); ?>"><?php echo esc_html( $fcs_item_title ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $fcs_item_title ); ?>
							<?php endif; ?>
							<?php if ( '' !== $fcs_item_loc ) : ?>
								<span class="fcs-visit__schedule-loc">· <?php echo esc_html( $fcs_item_loc ); ?></span>
							<?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>

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

<section class="fcs-home-happenings" aria-label="<?php esc_attr_e( 'Coming up at First Church', 'firstchurch' ); ?>">
	<div class="fcs-home-happenings__inner">

		<h2 class="fcs-home-happenings__heading"><?php esc_html_e( 'Coming up at First Church', 'firstchurch' ); ?></h2>

		<div class="fcs-card-grid fcs-card-grid--three">
			<?php
			foreach ( $fcs_home_items as $fcs_home_item ) {
				echo fcs_render_happening_card( happenings_card_view( $fcs_home_item ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally.
			}
			?>
		</div>

		<p class="fcs-home-happenings__more">
			<a href="<?php echo esc_url( home_url( '/engage/' ) ); ?>"><?php esc_html_e( 'See everything happening →', 'firstchurch' ); ?></a>
		</p>

	</div>
</section>

		<?php
	endif;
endif;
