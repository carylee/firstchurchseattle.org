<?php
/**
 * Short Person Content (Archive)
 */

// No direct access
if (! defined( 'ABSPATH' )) exit;

// Get data
// $position, $phone, $email, $urls
extract( ctfw_person_data() );

?>

<article id="post-<?php the_ID(); ?>" <?php maranatha_short_post_classes( 'maranatha-person-short' ); ?>>

	<header class="maranatha-entry-short-header">

		<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-header-short' ); // show title and image ?>

		<ul class="maranatha-entry-meta maranatha-entry-short-meta">

			<?php if ($position) : ?>

				<li class="maranatha-person-short-position maranatha-dark">
					<?php echo esc_html( wptexturize( $position ) ); ?>
				</li>

			<?php endif; ?>

			<?php if ($phone) : ?>

				<li class="maranatha-person-short-phone maranatha-dark">
					<?php echo ctfw_format_phone( $phone ); // escaped, possibly linked ?>
				</li>

			<?php endif; ?>

			<?php if ($email) : ?>

				<li class="maranatha-person-short-email maranatha-dark">

					<a href="mailto:<?php echo antispambot( $email, true ); ?>">
						<?php echo antispambot( $email ); // this on own line or validation can fail ?>
					</a>

				</li>

			<?php endif; ?>

			<?php if ($urls) : ?>

				<li class="maranatha-person-short-icons">
					<?php maranatha_social_icons( $urls ); ?>
				</li>

			<?php endif; ?>

		</ul>

	</header>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-excerpt' ); // show excerpt if no image ?>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-footer-short' ); // show appropriate button(s) ?>

</article>
