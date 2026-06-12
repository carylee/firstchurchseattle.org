<?php
/**
 * Staff directory (/staff/) — display for the firstchurch-people plugin's
 * ctc_person archive. The banner (header.php) carries the page title; the
 * grid itself is partials/staff-directory.php.
 *
 * Swapped in by inc/people-display.php while the plugin owns the person type.
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

?>
<main id="fcs-content" class="fcs-main bg-surface">
	<div class="fcs-container--med">
		<?php get_template_part( 'partials/staff-directory' ); ?>
	</div>
</main>
<?php

get_footer();
