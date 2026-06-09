<?php
/**
 * Single event page for the lean events backend (fce_event).
 *
 * fce_event is publicly_queryable (firstchurch-events.php) so each event's
 * permalink resolves — the spine projects that permalink onto /engage,
 * /upcoming-events/, and the calendar, and this is where those links land.
 *
 * Shows the church-phrased "when" (fce_when → EventWhen), the next occurrence,
 * a Register button when there's a registration URL, the featured image, and any
 * description (the CPT supports 'editor'). Reuses the compiled Tailwind utilities
 * + .btn-primary already used by page-worship-live.php, so no new CSS.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$id   = get_the_ID();
	$reg  = (string) get_post_meta( $id, '_fce_registration_url', true ); // FCE_REGURL
	$when = function_exists( 'fce_when' ) ? fce_when( $id ) : '';

	// Next non-cancelled occurrence within a year — concrete date for recurring events.
	$next = '';
	if ( function_exists( 'fce_next_occurrence' ) && function_exists( 'fce_rrule' ) ) {
		$occ = fce_next_occurrence(
			(string) get_post_meta( $id, '_fce_dtstart', true ), // FCE_DTSTART
			fce_rrule( $id ),
			new DateTimeImmutable( current_time( 'Y-m-d' ) ),
			new DateTimeImmutable( current_time( 'Y-m-d' ) . ' +1 year' ),
			function_exists( 'fce_skip_dates' ) ? fce_skip_dates( $id ) : array()
		);
		if ( $occ ) {
			$next = $occ->format( 'l, F j, Y' ); // ->format, not wp_date: no tz drift on a date-only value.
		}
	}
	?>
	<main id="maranatha-content" tabindex="-1" class="bg-white">
		<article class="max-w-3xl mx-auto px-4 sm:px-6 pt-8 pb-12">

			<p class="mb-4">
				<a href="<?php echo esc_url( home_url( '/upcoming-events/' ) ); ?>" class="text-brand hover:text-brand-dark">
					&larr; <?php esc_html_e( 'All events', 'maranatha-child' ); ?>
				</a>
			</p>

			<?php if ( has_post_thumbnail() ) : ?>
				<div class="rounded-xl overflow-hidden mb-6 ring-1 ring-brand-line">
					<?php the_post_thumbnail( 'large', array( 'class' => 'w-full h-auto block' ) ); ?>
				</div>
			<?php endif; ?>

			<h1 class="m-0 text-3xl sm:text-4xl font-display font-light text-brand-ink"><?php the_title(); ?></h1>

			<?php if ( '' !== $when ) : ?>
				<p class="mt-3 text-lg text-brand font-medium"><?php echo esc_html( $when ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $next ) : ?>
				<p class="mt-1 text-sm text-gray-600">
					<?php printf( esc_html__( 'Next: %s', 'maranatha-child' ), esc_html( $next ) ); ?>
				</p>
			<?php endif; ?>

			<?php if ( '' !== $reg ) : ?>
				<p class="mt-5">
					<a href="<?php echo esc_url( $reg ); ?>" class="btn-primary"><?php esc_html_e( 'Register', 'maranatha-child' ); ?></a>
				</p>
			<?php endif; ?>

			<?php if ( '' !== trim( (string) get_the_content() ) ) : ?>
				<div class="entry-content mt-6 leading-relaxed text-gray-800"><?php the_content(); ?></div>
			<?php endif; ?>

		</article>
	</main>
	<?php

endwhile;

get_footer();
