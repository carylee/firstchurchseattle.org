<?php
/**
 * Author Box
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Do not show again at bottom if already shown at top
// This relates to author archives
if ( ! empty( $GLOBALS['maranatha_author_box_shown'] ) ) {
	return;
} else {
	$GLOBALS['maranatha_author_box_shown'] = true;
}

// Show only on single blog posts and list of author's posts
if ( ! is_singular( 'post' ) && ! is_author() ) return;

// Show only if have profile bio
if ( ! get_the_author_meta( 'description' ) ) return;

?>

<aside class="maranatha-author-box maranatha-centered-medium maranatha-entry-content">

	<div class="maranatha-author-avatar">
		<?php echo get_avatar( get_the_author_meta( 'user_email' ), 230 ); // 115x115 so 230 for hiDPI/Retina ?>
	</div>

	<div class="maranatha-author-content">

		<header>

			<h2 class="maranatha-h3"><?php echo esc_html( wptexturize( get_the_author() ) ); ?></h2>

			<?php if ( is_singular() && get_the_author_posts() > 1 ) : // not on author archive and has more than this post ?>

				<div class="maranatha-author-box-archive">
					<a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>" class="maranatha-button"><?php printf( __( 'More Posts', 'maranatha' ), get_the_author() ); ?></a>
				</div>

			<?php endif; ?>

		</header>

		<div class="maranatha-author-bio">

			<?php echo wpautop( wptexturize( get_the_author_meta( 'description' ) ) ); ?>

		</div>

	</div>

</aside>
