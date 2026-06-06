<?php
/**
 * Footer Widget Area
 *
 * This shows three widgets at the bottom of every page.
 */

global $maranatha_home_section_num, $maranatha_home_section_last_has_image;

if ( is_active_sidebar( 'ctcom-footer' ) ) :

?>

<div id="maranatha-footer-widgets-row"<?php if ( ! empty( $maranatha_home_section_last_has_image ) && isset( $maranatha_home_section_num ) && $maranatha_home_section_num % 2 == 1 ) : // odd is light; if odd and has image show footer as white to constrast ?> class="maranatha-footer-widgets-row-light"<?php endif; ?>>

	<div id="maranatha-footer-widgets-container" class="maranatha-centered-large">

		<div id="maranatha-footer-widgets">

			<?php dynamic_sidebar( 'ctcom-footer' ); ?>

		</div>

	</div>

</div>

<?php

endif;
