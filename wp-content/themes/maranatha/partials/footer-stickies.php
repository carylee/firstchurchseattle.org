<?php
/**
 * Footer Stickies
 *
 * This outputs HTML at bottom of footer.php for rendering stickies to show latest events, comments, etc.
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

global $post;

// What to show at top right
$bottom_left_sticky = ctfw_customization( 'bottom_left_sticky' );

// Showing sermons, events or posts at top-right?
$bottom_left_sticky_posts = array();
$show_bottom_left_sticky_posts = in_array( $bottom_left_sticky, array( 'sermons', 'events', 'posts' ) );
if ( $show_bottom_left_sticky_posts ) {

	// Get and sanitize limit
	$max_limit = 2;
	$limit = absint( ctfw_customization( 'bottom_left_sticky_items_limit' ) );
	if ( $limit > $max_limit ) {
		$limit = $max_limit;
	}

	// Get date format
	$date_format = get_option( 'date_format' );

	// Today, tomorrow and yesterday in local time
	$today = date_i18n( 'Y-m-d' );
	$today_ts = strtotime( $today );
	$tomorrow =  date_i18n( 'Y-m-d', $today_ts + DAY_IN_SECONDS ); // add one day in seconds to today
	$yesterday =  date_i18n( 'Y-m-d', $today_ts - DAY_IN_SECONDS ); // subtract one day in seconds from today

	// Get sermons, events or posts
	if ( 'sermons' == $bottom_left_sticky ) {
		$bottom_left_sticky_posts = get_posts( array(
			'post_type'       	=> 'ctc_sermon',
			'orderby'         	=> 'publish_date',
			'order'           	=> 'desc',
			'numberposts'     	=> $limit,
			'suppress_filters'	=> false // keep WPML from showing posts from all languages: http://bit.ly/I1JIlV + http://bit.ly/1f9GZ7D
		) );
	} elseif ( 'events' == $bottom_left_sticky ) {
		$bottom_left_sticky_posts = ctfw_get_events( array(
			'timeframe'	=> 'upcoming',
			'limit'	=> $limit,
		) );
	} elseif ( 'posts' == $bottom_left_sticky ) {
		$bottom_left_sticky_posts = get_posts( array(
			'post_type'       	=> 'post',
			'orderby'         	=> 'publish_date',
			'order'           	=> 'desc',
			'numberposts'     	=> $limit,
			'suppress_filters'	=> false // keep WPML from showing posts from all languages: http://bit.ly/I1JIlV + http://bit.ly/1f9GZ7D
		) );
	}

}

// Custom content for bottom-left
$custom_content = ctfw_customization( 'bottom_left_sticky_content' );

// Show comments link if comments are open or closed by with comments
$show_comments = false;
if ( is_singular() && ( comments_open() || have_comments() ) ) {
	$show_comments = true;
}

// Can edit post?
$can_edit = false;
if ( is_singular() && ctfw_can_edit_post() && ! is_front_page() ) {
	$can_edit = true;
}

// Show bottom left
$show_bottom_left = true;
if ( 'none' == $bottom_left_sticky || ( 'content' == $bottom_left_sticky && ! $custom_content ) || ( $show_bottom_left_sticky_posts && ! $bottom_left_sticky_posts ) ) {
	$show_bottom_left = false;
}

// Show bottom right
$show_bottom_right = true;
if ( ! $show_comments && ! $can_edit ) {
	$show_bottom_right = false;
}

// Do not output container if no contents
if ( ! $show_bottom_left && ! $show_bottom_right ) {
	return;
}

?>

<div id="maranatha-stickies">

	<div id="maranatha-stickies-inner">

		<?php
		// Bottom Left Sticky
		if ( $show_bottom_left ) :
		?>

			<aside id="maranatha-stickies-left" class="maranatha-stickies-left-type-<?php echo esc_attr( $bottom_left_sticky ); ?>">

				<?php

				// Sermons, events or posts
				if ( $bottom_left_sticky_posts ) :

				?>

					<?php

					// Loop posts
					$old_post = $post;
					foreach ( $bottom_left_sticky_posts as $post ) :

						// Make the_title() , the_permalink() and so on work
						setup_postdata( $post );

						// Prepare date
						$show_date = '';
						$publish_date = date_i18n( 'Y-m-d', strtotime( $post->post_date ) );
						if ( in_array( $bottom_left_sticky, array( 'sermons', 'posts' ) ) ) { // sermon or post

							// Today, yesterday or date
							if ( $today == $publish_date ) {
								$show_date = _x( 'Today', 'top right items', 'maranatha' );
							} elseif ( $yesterday == $publish_date ) {
								$show_date = _x( 'Yesterday', 'top right items', 'maranatha' );
							} else {
								/* translators: see date formatting documentation: http://codex.wordpress.org/Formatting_Date_and_Time */
								$show_date = get_the_date( _x( 'F j', 'top right items', 'maranatha' ) );
							}

						} elseif ( 'events' == $bottom_left_sticky ) { // event

							// Get date range
							$start_date = get_post_meta( $post->ID , '_ctc_event_start_date' , true );
							$end_date = get_post_meta( $post->ID , '_ctc_event_end_date' , true );

							// Have a start date
							if ( $start_date ) {

								// Start and end dates as local timestamps
								$start_date_ts = strtotime( date_i18n( 'Y-m-d', strtotime( $start_date ) ) );
								$end_date_ts = strtotime( date_i18n( 'Y-m-d', strtotime( $end_date ) ) );

								// Today, tomorrow or date
								if ( $today_ts >= $start_date_ts && $today_ts <= $end_date_ts ) { // start date or any date in range is today
									$show_date = _x( 'Today', 'top right items', 'maranatha' );
								} elseif ( $start_date == $tomorrow ) {
									$show_date = _x( 'Tomorrow', 'top right items', 'maranatha' );
								} else {
									/* translators: see date formatting documentation: http://codex.wordpress.org/Formatting_Date_and_Time */
									$show_date = date_i18n( _x( 'F j', 'top right items', 'maranatha' ), strtotime( $start_date ) );
								}

							}

						}

					?>

						<div class="maranatha-stickies-left-item maranatha-stickies-left-content">

							<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">

								<?php if ( $show_date ) : ?>
									<span class="maranatha-stickies-left-item-date">
										<?php echo esc_html( $show_date ); ?>
									</span>
								<?php endif; ?>

								<span class="maranatha-stickies-left-item-title"><?php echo ctfw_shorten( get_the_title(), 28 ); // shorten title without truncating words ?></span>

							</a>

						</div>

					<?php

					// End Loop
					endforeach;

					// Restore $post global
					wp_reset_postdata();
					$post = $old_post; // wp_reset_postdata() isn't enough with this code

					?>

				<?php

				// Custom Content
				elseif ( 'content' == $bottom_left_sticky && $custom_content ) :

				?>

					<div id="maranatha-stickies-left-custom-content" class="maranatha-stickies-left-content">
						<?php echo wptexturize( do_shortcode( $custom_content ) ); ?>
					</div>

				<?php endif; ?>

			</aside>

		<?php endif; ?>

		<?php if ( $show_bottom_right ) : ?>

			<aside id="maranatha-stickies-right">

				<ul>

					<?php if ( $show_comments ) : ?>

						<li>

							<a href="#comments" id="maranatha-stickies-comments-link" class="maranatha-scroll-to-comments">

								<span class="el el-comment"></span>

								<?php

								printf(
									_n( '1 Comment', '%1$s Comments', get_comments_number(), 'maranatha' ), // title for 1 comment, title for 2+ comments
									number_format_i18n( get_comments_number() )
								);

								?>

							</a>

						</li>

					<?php endif; ?>

					<?php
					// "Edit" link for privileged user
					// especially since Admin Bar disabled for this theme (interferes with our sticky menu bar)
					if ( $can_edit ) :
					?>

						<li>

							<?php

							$post_type_obj = get_post_type_object( $post->post_type );

							edit_post_link(
								sprintf(
									/* translators: %1$s is icon, %1$s is post type singular name */
									__( '%1$s Edit %2$s', 'maranatha' ), // Link text format
									'<span class="' . maranatha_get_icon_class( 'edit-post' ) . '"></span>', // Icon
									$post_type_obj->labels->singular_name // Post type name
								)
							);

							?>

						</li>

					<?php endif; ?>

				</ul>

			</aside>

		<?php endif; ?>

	</div>

</div>
