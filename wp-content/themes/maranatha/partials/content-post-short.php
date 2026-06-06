<?php
/**
 * Short Post Content (Archive)
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Taxonomy Terms
$categories = get_the_category_list(
	/* translators: used between list items, there is a space after the comma */
	__( ', ', 'maranatha' )
);

?>

<article id="post-<?php the_ID(); ?>" <?php maranatha_short_post_classes(); ?>>

	<header class="maranatha-entry-short-header">

		<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-header-short' ); // show title and image ?>

		<ul class="maranatha-entry-meta maranatha-entry-short-meta">

			<li class="maranatha-entry-short-date maranatha-dark">
				<time datetime="<?php echo esc_attr( the_time( 'c' ) ); ?>"><?php ctfw_post_date(); ?></time>
			</li>

			<li class="maranatha-entry-short-author">
				<a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>"><?php the_author(); ?></a>
			</li>

			<?php if ( $categories ) : ?>

				<li class="maranatha-entry-short-category">
					<?php echo $categories; ?>
				</li>

			<?php endif; ?>

		</ul>

	</header>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-excerpt' ); // show excerpt if no image ?>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-footer-short' ); // show appropriate button(s) ?>

</article>
