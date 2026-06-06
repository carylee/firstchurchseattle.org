<?php
/**
 * List Comments and Form
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Show comments only on single posts and pages
if ( ! is_singular() || maranatha_loop_after_content_used() ) {
	return;
}

// If comments are closed and none have been made, hide the whole comment section
if ( ! have_comments() && ! comments_open() ) {
	return;
}

?>

<section id="comments" class="maranatha-centered-small"><?php // #comments is hardcoded in WP core comments_popup_link(), so no maranatha- prefix ?>

	<?php
	// Show message if post is password protected
	if ( post_password_required() ) :
	?>

		<header id="maranatha-comments-header">

			<h2 id="maranatha-comments-title">
				<?php _ex( 'Comments', 'password protected', 'maranatha' ); ?>
			</h2>

		</header>

		<p>
			<?php _e( 'Enter the password above to view any comments.', 'maranatha' ); ?>
		</p>

	<?php
	// Show comments if not password protected
	else :
	?>

		<header id="maranatha-comments-header">

			<h2 id="maranatha-comments-title">

				<?php
				printf(
					_n( 'One Comment', '%1$s Comments', get_comments_number(), 'maranatha' ), // title for 1 comment, title for 2+ comments
					number_format_i18n( get_comments_number() )
				);
				?>

			</h2>

		</header>

		<?php
		// List comments
		if ( have_comments() ) :
		?>

			<ol class="maranatha-comments">
				<?php
				wp_list_comments( array(
					'callback' => 'ctfw_comment' // framework function that loads comment.php for each comment
				) );
				?>
			</ol>

			<?php
			// Comment navigation
			if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) :
			?>
				<div class="maranatha-nav-left-right" id="maranatha-comment-nav">
					<div class="maranatha-nav-left"><?php previous_comments_link( sprintf( __( ' %s Older Comments', 'maranatha' ), '<span class="' . maranatha_get_icon_class( 'nav-left' ) . '"></span>' ) ); ?></div>
					<div class="maranatha-nav-right"><?php next_comments_link( sprintf( __( 'Newer Comments %s', 'maranatha' ), '<span class="' . maranatha_get_icon_class( 'nav-right' ) . '"></span>' ) ); ?></div>
				</div>
			<?php endif; ?>

		<?php endif; ?>

		<?php
		// Comments open
		if ( comments_open() ) : // show if comments not closed
		?>

			<?php
			// Show form
			comment_form( array(
				'title_reply'			=> _x( 'Add a Comment', 'comment form', 'maranatha' ),
				'title_reply_to'		=> __( 'Add a Reply to %s', 'maranatha' ),
				'cancel_reply_link'		=> _x( 'Cancel', 'comment form', 'maranatha' ),
				'label_submit'			=> _x( 'Add Comment', 'comment form', 'maranatha' )
			) );
			?>

		<?php
		// Comments closed
		else :
		?>

		<p id="maranatha-comments-closed">
			<?php _e( 'Commenting has been turned off.', 'maranatha' ); // Show message if comments closed ?>
		</p>

		<?php endif; ?>

	<?php endif; ?>

</section>
