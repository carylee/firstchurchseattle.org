<?php
/**
 * Single event page for the lean events backend (fce_event).
 *
 * In the spirit of the Happenings spine ("author once, project everywhere"), the
 * event's structured identity — title, "when", next occurrence, image, and the
 * registration CTA — is read from the SPINE projection (happenings_item_by_id →
 * happenings_card_view), the same Happening contract /engage, the carousel, and
 * the .ics drink from. This page is just another surface over that feed, not a
 * second place that re-derives event logic from post meta.
 *
 * Even the freeform body comes through the projection: the by-id (detail) item
 * carries the event's full `content`, which this surface renders. Feed items stay
 * lean (a `blurb` summary, no body), so only the detail page pays for full content.
 *
 * Falls back to native title/content if the spine plugin is inactive.
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
	$item = function_exists( 'happenings_item_by_id' ) ? happenings_item_by_id( 'event-' . $id ) : null;
	$view = ( $item && function_exists( 'happenings_card_view' ) ) ? happenings_card_view( $item ) : null;

	$title = $view['title'] ?? get_the_title();
	$when  = $view['meta'] ?? '';
	$image = $item['image'] ?? ( has_post_thumbnail() ? (string) get_the_post_thumbnail_url( $id, 'large' ) : '' );

	// Next occurrence (the projection's `date`); ->format avoids tz drift on a date-only value.
	$next = ( ! empty( $item['date'] ) ) ? ( new DateTimeImmutable( $item['date'] ) )->format( 'l, F j, Y' ) : '';

	// Only a REAL registration CTA (ctaPrimary) — never the permalink-to-self fallback.
	$show_cta = $view && ! empty( $view['ctaPrimary'] ) && ! empty( $view['ctaUrl'] );

	// Body: the projection's full `content` (raw post body), or native content if
	// the spine is inactive. Rendered with the_content filters at the surface.
	$body = $item ? (string) ( $item['content'] ?? '' ) : (string) get_the_content();
	?>
	<main id="maranatha-content" tabindex="-1" class="bg-surface">
		<article class="max-w-3xl mx-auto px-4 sm:px-6 pt-8 pb-12">

			<p class="mb-4">
				<a href="<?php echo esc_url( home_url( '/upcoming-events/' ) ); ?>" class="text-accent hover:text-accent-strong">
					&larr; <?php esc_html_e( 'All events', 'maranatha-child' ); ?>
				</a>
			</p>

			<?php if ( '' !== $image ) : ?>
				<div class="rounded-xl overflow-hidden mb-6 ring-1 ring-line">
					<?php // Above-the-fold hero is the LCP: load it eagerly + high priority, not lazily. ?>
						<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" class="w-full h-auto block" loading="eager" fetchpriority="high">
				</div>
			<?php endif; ?>

			<h1 class="m-0 text-3xl sm:text-4xl font-display font-medium text-ink"><?php echo esc_html( $title ); ?></h1>

			<?php if ( '' !== $when ) : ?>
				<p class="mt-3 text-lg text-accent font-medium"><?php echo esc_html( $when ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $next ) : ?>
				<p class="mt-1 text-sm text-muted">
					<?php printf( esc_html__( 'Next: %s', 'maranatha-child' ), esc_html( $next ) ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $show_cta ) : ?>
				<p class="mt-5">
					<a href="<?php echo esc_url( $view['ctaUrl'] ); ?>" class="btn-primary"><?php echo esc_html( $view['ctaLabel'] ?: __( 'Register', 'maranatha-child' ) ); ?></a>
				</p>
			<?php endif; ?>

			<?php if ( '' !== trim( $body ) ) : ?>
				<div class="entry-content mt-6 leading-relaxed text-soft">
					<?php echo apply_filters( 'the_content', $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content renders/sanitizes post HTML. ?>
				</div>
			<?php endif; ?>

		</article>
	</main>
	<?php

endwhile;

get_footer();
