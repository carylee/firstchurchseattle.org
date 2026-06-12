<?php
/**
 * Staff directory grid — people grouped by ctc_person_group, each group in
 * manual (menu_order) order, via the firstchurch-people plugin's
 * fcs_people_by_group(). Shared by templates/staff-archive.php (the /staff/
 * CPT archive) and page-templates/people.php (the Staff page).
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fcs_groups = function_exists( 'fcs_people_by_group' ) ? fcs_people_by_group() : array();
?>

<?php foreach ( $fcs_groups as $section ) : ?>
	<section class="mt-10">
		<?php if ( $section['group'] instanceof WP_Term ) : ?>
			<h2 class="text-xl font-display font-medium text-accent border-b border-line pb-2">
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
							     class="w-32 h-32 mx-auto rounded-full object-cover ring-1 ring-line" loading="lazy">
						<?php echo $has_bio ? '</a>' : ''; ?>
					<?php endif; ?>

					<h3 class="mt-3 mb-0 text-lg font-medium text-ink">
						<?php if ( $has_bio ) : ?>
							<a href="<?php echo esc_url( $link ); ?>" class="text-ink hover:text-accent"><?php echo esc_html( get_the_title( $pid ) ); ?></a>
						<?php else : ?>
							<?php echo esc_html( get_the_title( $pid ) ); ?>
						<?php endif; ?>
						<?php if ( ! empty( $d['pronouns'] ) ) : ?>
							<span class="block text-sm font-normal text-muted"><?php echo esc_html( $d['pronouns'] ); ?></span>
						<?php endif; ?>
					</h3>

					<?php if ( ! empty( $d['position'] ) ) : ?>
						<p class="mt-1 mb-0 text-sm text-accent"><?php echo esc_html( $d['position'] ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $d['email'] ) ) : ?>
						<p class="mt-1 mb-0 text-sm">
							<a href="<?php echo esc_attr( 'mailto:' . antispambot( $d['email'], true ) ); ?>" class="text-accent hover:text-accent-strong break-words"><?php echo esc_html( antispambot( $d['email'] ) ); ?></a>
						</p>
					<?php endif; ?>

					<?php if ( ! empty( $d['phone'] ) ) : ?>
						<p class="mt-1 mb-0 text-sm text-soft"><?php echo fcs_person_phone_html( $d['phone'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endforeach; ?>

<?php if ( empty( $fcs_groups ) ) : ?>
	<p class="mt-8 text-soft"><?php esc_html_e( 'No staff to show yet.', 'firstchurch' ); ?></p>
<?php endif; ?>
