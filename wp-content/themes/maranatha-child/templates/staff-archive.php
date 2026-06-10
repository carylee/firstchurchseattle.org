<?php
/**
 * Staff directory (/staff/) — child-theme display for the firstchurch-people
 * plugin. Renders people grouped by ctc_person_group, each group in manual
 * (menu_order) order, via fcs_people_by_group(). Self-contained: no loop.php,
 * no parent content-person-* partials.
 *
 * Swapped in by inc/people-display.php only when the plugin owns the person type
 * (post-CTC cutover).
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$groups = function_exists( 'fcs_people_by_group' ) ? fcs_people_by_group() : array();
?>
<main id="maranatha-content" tabindex="-1" class="bg-white">
	<div class="max-w-5xl mx-auto px-4 sm:px-6 pt-8 pb-12">

		<h1 class="m-0 text-3xl sm:text-4xl font-display font-medium text-brand-ink">
			<?php echo esc_html( post_type_archive_title( '', false ) ?: __( 'Staff', 'maranatha-child' ) ); ?>
		</h1>

		<?php foreach ( $groups as $section ) : ?>
			<section class="mt-10">
				<?php if ( $section['group'] instanceof WP_Term ) : ?>
					<h2 class="text-xl font-display font-medium text-brand border-b border-brand-line pb-2">
						<?php echo esc_html( $section['group']->name ); ?>
					</h2>
				<?php endif; ?>

				<ul class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-8 list-none p-0">
					<?php foreach ( $section['people'] as $person ) : ?>
						<?php
						$pid     = $person->ID;
						$d       = fcs_person_data( $pid );
						$img     = has_post_thumbnail( $pid ) ? (string) get_the_post_thumbnail_url( $pid, 'medium' ) : '';
						$link    = (string) get_permalink( $pid );
						$has_bio = '' !== trim( (string) $person->post_content );
						?>
						<li class="text-center">
							<?php if ( '' !== $img ) : ?>
								<?php echo $has_bio ? '<a href="' . esc_url( $link ) . '" class="block">' : ''; ?>
									<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( get_the_title( $pid ) ); ?>"
									     class="w-32 h-32 mx-auto rounded-full object-cover ring-1 ring-brand-line" loading="lazy">
								<?php echo $has_bio ? '</a>' : ''; ?>
							<?php endif; ?>

							<h3 class="mt-3 mb-0 text-lg font-medium text-brand-ink">
								<?php if ( $has_bio ) : ?>
									<a href="<?php echo esc_url( $link ); ?>" class="text-brand-ink hover:text-brand"><?php echo esc_html( get_the_title( $pid ) ); ?></a>
								<?php else : ?>
									<?php echo esc_html( get_the_title( $pid ) ); ?>
								<?php endif; ?>
								<?php if ( ! empty( $d['pronouns'] ) ) : ?>
									<span class="block text-sm font-normal text-gray-500"><?php echo esc_html( $d['pronouns'] ); ?></span>
								<?php endif; ?>
							</h3>

							<?php if ( ! empty( $d['position'] ) ) : ?>
								<p class="mt-1 mb-0 text-sm text-brand"><?php echo esc_html( $d['position'] ); ?></p>
							<?php endif; ?>

							<?php if ( ! empty( $d['email'] ) ) : ?>
								<p class="mt-1 mb-0 text-sm">
									<a href="<?php echo esc_attr( 'mailto:' . antispambot( $d['email'], true ) ); ?>" class="text-brand hover:text-brand-dark break-words"><?php echo esc_html( antispambot( $d['email'] ) ); ?></a>
								</p>
							<?php endif; ?>

							<?php if ( ! empty( $d['phone'] ) ) : ?>
								<p class="mt-1 mb-0 text-sm text-gray-600"><?php echo fcs_person_phone_html( $d['phone'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></p>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endforeach; ?>

		<?php if ( empty( $groups ) ) : ?>
			<p class="mt-8 text-gray-600"><?php esc_html_e( 'No staff to show yet.', 'maranatha-child' ); ?></p>
		<?php endif; ?>

	</div>
</main>
<?php

get_footer();
