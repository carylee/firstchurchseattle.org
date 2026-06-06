<?php
/**
 * Full Content Footer
 *
 * Multipage nav, taxonomy terms, admin Edit link, etc. for full display of different post types.
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Collect term lists (categories, tags, etc.) for specific post types
$term_lists = array();

// Blog Terms
if ( is_singular( 'post' ) ) {

	// Blog Tags
	/* translators: used between list items, there is a space after the comma */
	$list = get_the_tag_list( '', __( ', ', 'maranatha' ) );
	if ( $list ) {
		$term_lists[] = '<div><span class="' . maranatha_icon_class( 'entry-tag', 'return' ) . '"></span> ' . sprintf( __( 'Tagged with %s', 'maranatha' ), $list ) . '</div>';
	}

}

// Sermon Tags
elseif ( is_singular( 'ctc_sermon' ) ) {

	// Sermon Tags
	/* translators: used between list items, there is a space after the comma */
	$list = get_the_term_list( $post->ID, 'ctc_sermon_tag', '', __( ', ', 'maranatha' ) );
	if ( $list ) {
		$term_lists[] = '<div><span class="' . maranatha_icon_class( 'entry-tag', 'return' ) . '"></span> ' . sprintf( __( 'Tagged with %s', 'maranatha' ), $list ) . '</div>';
	}

}

?>

<?php
// Have footer content to show?
if ( ( ctfw_is_multipage() && ! post_password_required() ) || ! empty( $term_lists ) ) :
?>

	<footer class="maranatha-entry-full-footer maranatha-centered-medium maranatha-entry-full-footer-<?php echo $term_lists ? 'has' : 'no'; ?>-terms">

		<?php
		// "Pages: 1 2 3" when <!--nextpage--> used
		if ( ctfw_is_multipage() && ! post_password_required() ) :
		?>

			<?php

			$page_links = wp_link_pages( array(
				'before'			=> '<ul class="maranatha-buttons-list maranatha-entry-full-page-nav">',
				'after'				=> '</ul>',
				'next_or_number' 	=> 'next',
				'nextpagelink'     	=> sprintf(
										/* translators: %s is icon */
										__( 'Next Page %s', 'maranatha' ),
										'<span class="' . maranatha_get_icon_class( 'nav-right' ) . '"></span>'
									),
				'previouspagelink'     	=> sprintf(
										/* translators: %s is icon */
										__( '%s Previous Page', 'maranatha' ),
										'<span class="' . maranatha_get_icon_class( 'nav-left' ) . '"></span>'
									),
				'echo'				=> false,
			) );

			$page_links = str_replace( '<a', '<li><a', $page_links );
			$page_links = str_replace( '</a>', '</a></li>', $page_links );

			echo $page_links;

			?>

		<?php endif; ?>

		<?php
		// Term lists (categories, tags, etc.)
		if ( $term_lists ) :
		?>

			<div class="maranatha-entry-full-footer-item">

				<?php foreach ( $term_lists as $term_list ) : ?>
				<div class="maranatha-entry-full-footer-terms">
					<?php echo $term_list; ?>
				</div>
				<?php endforeach; ?>

			</div>

		<?php endif; ?>

	</footer>

<?php endif; ?>