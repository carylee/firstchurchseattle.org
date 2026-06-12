<?php
/**
 * Template Name: People
 *
 * The Staff page (/about/staff-2/): the page's own intro content, then the
 * staff directory grid (partials/staff-directory.php, powered by the
 * firstchurch-people plugin). Same template path the old parent template
 * used, so the existing page assignment carries over.
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
		<div class="fcs-container--med">
			<?php if ( fcs_has_content() ) : ?>
				<div class="fcs-measure fcs-entry">
					<?php the_content(); ?>
				</div>
			<?php endif; ?>
			<?php get_template_part( 'partials/staff-directory' ); ?>
		</div>
	<?php endwhile; ?>
</main>
<?php

get_footer();
