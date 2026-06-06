<?php
/**
 * Title and description or other content shown above loop on categories, archives, etc.
 *
 * Hierarchy information: http://codex.wordpress.org/Template_Hierarchy
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Show only on loop multiple
if ( is_singular() ) return;

/**************************************
 * BLOG POSTS
 **************************************/

// Show page title and content before loop when static front page used and "Posts page" is specified
// Possibility: Simplify by making this condition cause Blog tempate to load with "Posts page" $post global?
if ( ctfw_is_posts_page() ) : ?>

	<h1 id="maranatha-main-title"><?php maranatha_title_paged(); ?></h1>

	<?php
	$content = apply_filters( 'the_content', get_post_field( 'post_content', get_queried_object_id() ) );
	if ( $content ) :
	?>

		<div class="maranatha-entry-content maranatha-centered-medium">
			<?php echo $content; ?>
		</div>

	<?php endif; ?>

<?php

/**************************************
 * BLOG TAXONOMIES
 **************************************/

elseif ( is_category() ) : ?>

	<h1 id="maranatha-main-title"><?php maranatha_title_paged(); ?></h1>

	<?php
	$description = category_description();
	if ( $description ) :
	?>

		<div class="maranatha-entry-content maranatha-centered-medium">
			<?php echo $description; ?>
		</div>

	<?php endif; ?>

<?php elseif ( is_tag() ) : ?>

	<h1 id="maranatha-main-title"><?php maranatha_title_paged(); ?></h1>

<?php

/**************************************
 * CUSTOM TAXONOMY
 **************************************/

elseif ( is_tax() ) : ?>

	<h1 id="maranatha-main-title"><?php maranatha_title_paged(); ?></h1>

	<?php

	// Get taxonomy
	$taxonomy = get_query_var( 'taxonomy' );
	$taxonomy_obj = get_taxonomy( $taxonomy );
	$post_type = isset( $taxonomy_obj->object_type[0] ) ? $taxonomy_obj->object_type[0] : '';
	$post_type_obj = get_post_type_object( $post_type );
	$taxonomy_label_lc = isset( $post_type_obj->labels->name ) ? strtolower( $post_type_obj->labels->name ) : '';

	// Description
	$description = term_description( '', get_query_var( 'taxonomy' ) );

	?>

	<?php if ( $description || ! have_posts() ) : ?>

		<div class="maranatha-entry-content maranatha-centered-medium">

			<?php echo $description; ?>

			<?php if ( ! have_posts() ) : ?>

				<p class="maranatha-no-posts-message">
					<?php printf( __( 'There are no %s to show.', 'maranatha' ), $taxonomy_label_lc ); ?>
				</p>

			<?php endif; ?>

		</div>

	<?php endif; ?>

<?php

/**************************************
 * AUTHOR ARCHIVE
 **************************************/

elseif ( is_author() ) : ?>

	<h1 id="maranatha-main-title"><?php echo maranatha_title_paged(); ?></h1>

	<?php
	// loop-author.php shows bio below blog posts and at top of author archives
	get_template_part( CTFW_THEME_PARTIAL_DIR . '/loop-author' );
	?>

<?php

/**************************************
 * SEARCH RESULTS
 **************************************/

?>

<?php elseif ( is_search() ) : ?>

	<h1 id="maranatha-main-title"><?php maranatha_title_paged(); ?></h1>

	<div class="maranatha-entry-content maranatha-centered-medium">

		<p>

			<?php
			/* translators: %d is number of matches, %s is search term */
			printf( _n( 'Your search returned %d match for %s.', 'Your search returned %d matches for %s.', $wp_query->found_posts, 'maranatha' ), $wp_query->found_posts, '<i>' . get_search_query() . '</i>' );
			?>

		</p>

	</div>

<?php

/**************************************
 * DATE ARCHIVE
 **************************************/

elseif ( is_day() || is_month() || is_year() ) : ?>

	<h1 id="maranatha-main-title"><?php maranatha_title_paged(); ?></h1>

<?php

/**************************************
 * POST TYPE ARCHIVE
 **************************************/

// When page template not used to output post type items, post type name shown at top

elseif ( is_post_type_archive() ) : ?>

	<h1 id="maranatha-main-title"><?php maranatha_title_paged(); ?></h1>

<?php

/**************************************
 * GENERIC ARCHIVE
 **************************************/

// This is used when nothing higher up is suitable

elseif ( is_archive() ) : ?>

	<h1 id="maranatha-main-title"><?php maranatha_title_paged(); ?></h1>

<?php

elseif ( is_404() ) : ?>

	<h1 id="maranatha-main-title"><?php maranatha_title_paged(); // outputs Not Found ?></h1>

	<div class="maranatha-entry-content maranatha-centered-medium">
		<?php _e( 'Sorry, the page or file you tried to access was not found.', 'maranatha' ); ?>
	</div>

<?php endif; ?>
