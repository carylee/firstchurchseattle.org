<?php
/**
 * Post loop — child override (pinned verbatim copy).
 *
 * Loops to show one or multiple posts using content-*.php templates. Used by
 * index.php, maranatha_loop_after_content() and can be used elsewhere.
 *
 * Owned by the child as part of the theme-independence work (extracting the
 * base template skeleton so the maranatha parent can eventually be dropped —
 * see ops/docs/theme-independence.md). Byte-for-byte copy of the parent's
 * loop.php; it still calls ctfw_current_content_type() and
 * ctfw_get_content_template() from the parent framework, so behavior is
 * identical for now. ctfw_get_content_template() dispatches to the parent's
 * content-*-short / content-*-full wrappers, which in turn pull the child's
 * content-header-short.php / content-footer-short.php overrides.
 *
 * Parent source: maranatha/loop.php (pinned in this repo) — re-diff if the
 * parent theme is ever updated.
 *
 * @package Maranatha_Child
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

?>

<?php if ( have_posts() && ! is_404() ) : ?>

	<?php if ( ! is_singular() ) : ?>

		<div id="maranatha-loop-multiple" class="maranatha-clearfix <?php

			// Three Columns
			if ( ctfw_current_content_type() == 'people' ) :

				echo 'maranatha-loop-three-columns';

			// One Column
			elseif ( is_search() ) :

				echo 'maranatha-loop-one-column';

			// Two Columns - Default
			else :

				echo 'maranatha-loop-two-columns';

			endif;

		?>">

	<?php endif; ?>

		<?php while ( have_posts() ) : the_post(); ?>

			<?php ctfw_get_content_template(); // load content-*.php according to post type and post format ?>

		<?php endwhile; ?>

	<?php if ( ! is_singular() ) : ?>
		</div>
	<?php endif; ?>

<?php endif; ?>
