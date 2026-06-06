<?php
/**
 * Full Post Content (Single)
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Taxonomy Terms
$categories = get_the_category_list(
	/* translators: used between list items, there is a space after the comma */
	__( ', ', 'maranatha' )
);

?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'maranatha-entry-full maranatha-blog-full' ); ?>>

	<header class="maranatha-entry-full-header maranatha-centered-large">

		<?php
		// This is visible only to screenreaders.
		// Page title is shown in banner. This is for proper HTML5 and Outline
		if ( ctfw_has_title() ) :
		?>

			<h1 id="maranatha-main-title">
				<?php maranatha_title_paged(); ?>
			</h1>

		<?php endif; ?>

		<ul class="maranatha-entry-meta maranatha-entry-full-meta">

			<li class="maranatha-entry-full-date">
				<div class="maranatha-entry-full-meta-label"><?php _ex( 'Date', 'post meta label', 'maranatha' ); ?></div>
				<time datetime="<?php echo esc_attr( the_time( 'c' ) ); ?>" class="maranatha-dark"><?php ctfw_post_date(); ?></time>
			</li>

			<li class="maranatha-entry-full-author">
				<div class="maranatha-entry-full-meta-label"><?php _ex( 'Author', 'post meta label', 'maranatha' ); ?></div>
				<a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>"><?php the_author(); ?></a>
			</li>

			<?php if ( $categories ) : ?>

				<li class="maranatha-entry-full-category">
					<div class="maranatha-entry-full-meta-label"><?php _ex( 'Category', 'post meta label', 'maranatha' ); ?></div>
					<?php echo $categories; ?>
				</li>

			<?php endif; ?>

		</ul>

	</header>

	<div class="maranatha-entry-content maranatha-entry-full-content maranatha-centered-small">

		<?php the_content(); ?>

		<?php do_action( 'maranatha_after_content' ); ?>

	</div>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-footer-full' ); // multipage nav, term lists, "Edit" button, etc. ?>

</article>
