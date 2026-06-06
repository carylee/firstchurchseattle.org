<?php
/**
 * Attachment content for images (gallery) and other files (audio, video, PDF, etc.).
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'maranatha-entry-full maranatha-attachment-full' ); ?>>

	<?php
	// This is visible only to screenreaders.
	// Page title is shown in banner. This is for proper HTML5 and Outline
	if ( ctfw_has_title() ) :
	?>

		<h1 id="maranatha-main-title">
			<?php maranatha_title_paged(); ?>
		</h1>

	<?php endif; ?>

	<?php
	// Show image first, if it is an image
	if ( wp_attachment_is_image() ) :
	?>

		<div class="maranatha-attachment-image">

			<div class="wp-caption aligncenter maranatha-centered-large">

				<?php echo wp_get_attachment_image( $post->ID, 'large' ); ?>

				<?php if ( ctfw_has_manual_excerpt() ) : ?>

					<p class="wp-caption-text">
						<?php echo wptexturize( get_the_excerpt() ); ?>
					</p>

				<?php endif; ?>

			</div>

		</div>

	<?php endif; ?>

	<div class="maranatha-entry-header maranatha-centered-large">

		<ul class="maranatha-entry-full-meta">

			<li class="maranatha-attachment-date">
				<div class="maranatha-entry-full-meta-label"><?php _ex( 'Uploaded', 'attachment', 'maranatha' ); ?></div>
				<time datetime="<?php echo esc_attr( the_time( 'c' ) ); ?>"><?php ctfw_post_date(); ?></time>
			</li>

			<?php if ( $post->post_parent ) : ?>

				<li class="maranatha-entry-full-parent">
					<div class="maranatha-entry-full-meta-label"><?php _e( 'Attached To', 'maranatha' ); ?></div>
					<a href="<?php echo esc_url( get_permalink( $post->post_parent ) ); ?>" title="<?php echo esc_attr( get_the_title( $post->post_parent ) ); ?>"><?php echo get_the_title( $post->post_parent ); ?></a>
				</li>

			<?php endif; ?>

		</ul>

	</div>

	<?php
	// Non-image files are represented by download link
	// Typically non-image file attachment pages are never linked to
	// except from Media library in admin
	if ( ! wp_attachment_is_image() ) :
	?>

		<div class="maranatha-attachment-button">

			<ul class="maranatha-circle-buttons-list">

				<li>

					<a href="<?php echo esc_url( ctfw_download_url( wp_get_attachment_url( $post->ID ) ) ); ?>" download="download">

						<span class="<?php maranatha_icon_class( 'download' ); ?>"></span>

						<?php
						$filetype = wp_check_filetype( wp_get_attachment_url( $post->ID ) );
						if ( $filetype['ext'] ) {
							/* translators: %s is file extension */
							printf( __( 'Save %s', 'maranatha' ), strtoupper( $filetype['ext'] ) );
						}
						?>

					</a>

				</li>

			</ul>

		</div>

	<?php endif; ?>

	<?php if ( ctfw_has_content() ) : ?>

		<div class="maranatha-entry-content maranatha-centered-small">

			<?php the_content(); ?>

			<?php do_action( 'maranatha_after_content' ); ?>

		</div>

	<?php endif; ?>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-footer-full' ); // multipage nav, term lists, "Edit" button, etc. ?>

</article>