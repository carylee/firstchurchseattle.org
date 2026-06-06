<?php
/**
 * This loops to show one or multiple posts using content-*.php templates.
 *
 * It is used by index.php, maranatha_loop_after_content() and can be used elsewhere.
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