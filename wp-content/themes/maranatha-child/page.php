<?php
/**
 * Default page template: banner (from header.php) + the page's content on the
 * text measure.
 *
 * Fires `fcs_after_content` after the entry — inc/announcements-cta.php uses
 * it for the single-announcement CTA button, and page templates that extend
 * this layout (events list/calendar) render their sections in the same slot.
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
		<div class="fcs-container--text">
			<div class="fcs-entry">
				<?php the_content(); ?>
			</div>
			<?php do_action( 'fcs_after_content' ); ?>
		</div>
	<?php endwhile; ?>
</main>
<?php

get_footer();
