<?php
/**
 * Home Sections Widget Area
 *
 * This shows CT Section widgets on the homepage.
 */
?>

<main id="maranatha-home-main">

	<?php if ( is_active_sidebar( 'ctcom-home-sections' ) ) : ?>

		<?php dynamic_sidebar( 'ctcom-home-sections' ); ?>

	<?php else : ?>

		<section id="maranatha-home-section-1" class="maranatha-home-section maranatha-viewport-height maranatha-color-main-bg maranatha-section-has-title maranatha-section-has-content maranatha-section-no-image">

			<div class="maranatha-home-section-inner">

				<div class="maranatha-home-section-content">

					<h1>
						<?php _e( 'Add a Section', 'maranatha' ); ?>
					</h1>

					<p>
						<?php _e( 'Import sample widgets or go to <b>Appearance</b> > <b>Customize</b> > <b>Widgets</b> to add at least one CT Section widget to your homepage.', 'maranatha' ); ?>
					</p>

				</div>

			</div>

		</section>

	<?php endif; ?>

</main>
