<?php
/**
 * Excerpt
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Show when have excerpt and no featured image, unless it is search (one column)
// Also exclude from calendar template -- space is limited on hover and mobile
if ( ( ( ! ctfw_has_excerpt() || has_post_thumbnail() ) && ! is_search() ) || is_page_template( CTFW_THEME_PAGE_TPL_DIR . '/events-calendar.php' ) ) {
	return;
}

// Output excerpt shortened if title is long, for more even height
$content = maranatha_short_content( array(
	'return' => true,
) );

// Have content? If not, don't show empty container
if ( ! $content ) {
	return;
}

?>

<div class="maranatha-entry-content maranatha-entry-content-short">

	<?php echo $content; ?>

</div>