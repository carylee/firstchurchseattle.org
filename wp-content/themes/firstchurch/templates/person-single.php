<?php
/**
 * Single person profile (ctc_person) — child-theme display for the
 * firstchurch-people plugin. Reads the structured fields via fcs_person_data()
 * (no parent ctfw_* dependency) and the bio from the post body.
 *
 * Swapped in by inc/people-display.php only when the plugin owns the person type
 * (post-CTC cutover); never the live template while the parent renders people.
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$id   = get_the_ID();
	$d    = function_exists( 'fcs_person_data' ) ? fcs_person_data( $id ) : array();
	$img  = has_post_thumbnail() ? (string) get_the_post_thumbnail_url( $id, 'medium' ) : '';
	$name = get_the_title();
	?>
	<main id="fcs-content" tabindex="-1" class="fcs-main bg-surface">
		<article class="max-w-3xl mx-auto px-4 sm:px-6 pt-8 pb-12">

			<p class="mb-6">
				<a href="<?php echo esc_url( get_post_type_archive_link( 'ctc_person' ) ?: home_url( '/staff/' ) ); ?>" class="text-brand hover:text-brand-dark">
					&larr; <?php esc_html_e( 'All staff', 'firstchurch' ); ?>
				</a>
			</p>

			<header class="flex flex-col sm:flex-row sm:items-start gap-6">
				<?php if ( '' !== $img ) : ?>
					<div class="shrink-0">
						<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $name ); ?>"
						     class="w-32 h-32 sm:w-40 sm:h-40 rounded-xl object-cover ring-1 ring-brand-line"
						     loading="eager" fetchpriority="high">
					</div>
				<?php endif; ?>

				<div class="min-w-0">
					<h1 class="m-0 text-3xl sm:text-4xl font-display font-medium text-brand-ink">
						<?php echo esc_html( $name ); ?>
						<?php if ( ! empty( $d['pronouns'] ) ) : ?>
							<span class="text-base font-normal text-gray-500">(<?php echo esc_html( $d['pronouns'] ); ?>)</span>
						<?php endif; ?>
					</h1>

					<?php if ( ! empty( $d['position'] ) ) : ?>
						<p class="mt-1 text-lg text-brand font-medium"><?php echo esc_html( $d['position'] ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $d['phone'] ) || ! empty( $d['email'] ) ) : ?>
						<ul class="mt-3 space-y-1 text-gray-700 list-none p-0">
							<?php if ( ! empty( $d['phone'] ) ) : ?>
								<li><?php echo fcs_person_phone_html( $d['phone'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></li>
							<?php endif; ?>
							<?php if ( ! empty( $d['email'] ) ) : ?>
								<li><a href="<?php echo esc_attr( 'mailto:' . antispambot( $d['email'], true ) ); ?>" class="text-brand hover:text-brand-dark"><?php echo esc_html( antispambot( $d['email'] ) ); ?></a></li>
							<?php endif; ?>
						</ul>
					<?php endif; ?>

					<?php if ( ! empty( $d['social'] ) ) : ?>
						<ul class="mt-3 flex flex-wrap gap-2 list-none p-0">
							<?php foreach ( $d['social'] as $link ) : ?>
								<li>
									<a href="<?php echo esc_url( $link['href'] ); ?>"
									   class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-brand-soft text-brand hover:bg-brand hover:text-white ring-1 ring-brand-line"
									   rel="noopener noreferrer"><?php echo esc_html( $link['label'] ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</header>

			<?php if ( '' !== trim( get_the_content() ) ) : ?>
				<div class="entry-content mt-8 leading-relaxed text-gray-800">
					<?php the_content(); ?>
				</div>
			<?php endif; ?>

		</article>
	</main>
	<?php

endwhile;

get_footer();
