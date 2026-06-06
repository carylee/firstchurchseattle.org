<?php
/**
 * Full Person Content (Single)
 */

// No direct access
if (! defined( 'ABSPATH' )) exit;

// Get data
// $position, $phone, $email, $urls
extract( ctfw_person_data() );

// Has meta to show?
$has_meta = ( $position || $phone || $email || $urls ) ? true : false;

// Show header
$show_header = ( $has_meta || has_post_thumbnail() ) ? true : false;

?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'maranatha-entry-full maranatha-person-full' ); ?>>

	<?php if ($show_header) : ?>
		<header class="maranatha-entry-full-header">
	<?php endif; ?>

		<?php
		// This is visible only to screenreaders.
		// Page title is shown in banner. This is for proper HTML5 and Outline
		if (ctfw_has_title()) :
		?>

			<h1 id="maranatha-main-title">
				<?php maranatha_title_paged(); ?>
			</h1>

		<?php endif; ?>

		<?php if ($show_header) : ?>

			<div id="maranatha-person-header-content"<?php if (has_post_thumbnail()) : ?> class="maranatha-person-has-image"<?php endif; ?>>

				<?php if (has_post_thumbnail()) : ?>

					<div id="maranatha-person-image-column">

						<div id="maranatha-person-image" class="maranatha-person-image maranatha-hover-image">
							<?php the_post_thumbnail(  'maranatha-thumb-small' ); ?>
						</div>

					</div>

				<?php endif; ?>

				<?php if ($has_meta) : ?>

					<div id="maranatha-person-meta">

						<ul class="maranatha-entry-meta maranatha-entry-full-meta">

							<?php if ($position) : ?>

								<li id="maranatha-person-position" class="maranatha-dark">
									<div class="maranatha-entry-full-meta-label"><?php _ex( 'Position', 'person', 'maranatha' ); ?></div>
									<?php echo esc_html( wptexturize( $position ) ); ?>
								</li>

							<?php endif; ?>

							<?php if ($phone) : ?>

								<li id="maranatha-person-phone" class="maranatha-dark">
									<div class="maranatha-entry-full-meta-label"><?php _e( 'Phone', 'maranatha' ); ?></div>
									<?php echo ctfw_format_phone( $phone ); // escaped, possibly linked ?>
								</li>

							<?php endif; ?>

							<?php if ($email) : ?>

								<li id="maranatha-person-email">

									<div class="maranatha-entry-full-meta-label"><?php _e( 'Email', 'maranatha' ); ?></div>

									<a href="mailto:<?php echo antispambot( $email, true ); ?>">
										<?php echo antispambot( $email ); // this on own line or validation can fail ?>
									</a>

								</li>

							<?php endif; ?>

							<?php if ($urls) : ?>

								<li id="maranatha-person-icons" class="maranatha-entry-full-icons">
									<?php maranatha_social_icons( $urls ); ?>
								</li>

							<?php endif; ?>

						</ul>

					</div>

				<?php endif; ?>

			</div>

		<?php endif; ?>

	<?php if ($show_header) : ?>
		</header>
	<?php endif; ?>

	<?php if (ctfw_has_content()) : // might not be any content, so let header sit flush with bottom ?>

		<div id="maranatha-person-content" class="maranatha-entry-content maranatha-entry-full-content maranatha-centered-small">

			<?php the_content(); ?>

			<?php do_action( 'maranatha_after_content' ); ?>

		</div>

	<?php endif; ?>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-footer-full' ); // multipage nav, term lists, "Edit" button, etc. ?>

</article>
