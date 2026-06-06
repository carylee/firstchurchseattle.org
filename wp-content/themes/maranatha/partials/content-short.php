<?php
/**
 * Short Page Content (Archive)
 *
 * This is also the default content template. Any posts without a specific template will use this.
 * It outputs minimal content (title and content) in generic way compatible with any post type.
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

?>

<article id="post-<?php the_ID(); ?>" <?php maranatha_short_post_classes( 'maranatha-entry-short' ); ?>>

	<header class="maranatha-entry-short-header">

		<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-header-short' ); // show title and image ?>

	</header>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-excerpt' ); // show excerpt if no image ?>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-footer-short' ); // show appropriate button(s) ?>

</article>
