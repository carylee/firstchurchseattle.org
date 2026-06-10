<?php
/**
 * Short Content Header — child override.
 *
 * Copy of the parent partial with one addition: blog posts without a
 * featured image get a branded gradient banner (.fcs-card-placeholder) in
 * the News grid instead of nothing, so the two-column listing doesn't
 * checkerboard. Scoped to the `post` type — sermons, events, and people
 * keep the parent's behavior exactly.
 *
 * Parent source: maranatha/partials/content-header-short.php (pinned in
 * this repo) — re-diff if the parent theme is ever updated.
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Post type
$post_type = get_post_type();
$post_type_friendly = ctfw_make_friendly( $post_type );

// Thumbnail image
$image_size = 'post-thumbnail';
if ( 'ctc_person' == $post_type ) {
	$image_size = 'maranatha-thumb-small';
}

// Has content?
$has_content = ctfw_has_content();

?>

<?php if ( has_post_thumbnail() ) : ?>

	<div class="maranatha-entry-short-image maranatha-<?php echo esc_attr( $post_type_friendly ); ?>-short-image maranatha-hover-image">

		<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
			<?php the_post_thumbnail( $image_size ); ?>
		</a>

	</div>

<?php elseif ( 'post' === $post_type && ! is_singular() ) : ?>

	<div class="maranatha-entry-short-image maranatha-<?php echo esc_attr( $post_type_friendly ); ?>-short-image">

		<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
			<span class="fcs-card-placeholder" aria-hidden="true"></span>
		</a>

	</div>

<?php endif; ?>

<?php if ( ctfw_has_title() ) : ?>

	<h2 class="maranatha-entry-short-title">

		<?php if ( 'ctc_person' == $post_type && ! $has_content ) : // not linked ?>

			<?php the_title(); ?>

		<?php else : // not linked ?>

			<a href="<?php echo esc_url( get_permalink() ); ?>" title="<?php the_title_attribute( array( 'echo' => false ) ); ?>"><?php the_title(); ?></a>

		<?php endif; ?>

	</h2>

<?php endif; ?>
