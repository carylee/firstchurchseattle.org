<?php
/**
 * Template Name: Width - Large
 *
 * A page on the full container width. Same template path the old parent
 * template used, so the existing page assignments carry over.
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

?>
<main id="fcs-content" class="fcs-main">
	<?php while ( have_posts() ) : the_post(); ?>
		<div class="fcs-container--lg">
			<div class="fcs-entry">
				<?php the_content(); ?>
			</div>
			<?php do_action( 'fcs_after_content' ); ?>
		</div>
	<?php endwhile; ?>
</main>
<?php

get_footer();
