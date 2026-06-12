<?php
/**
 * Main template file — child override (pinned verbatim copy).
 *
 * All content not using a more specific template comes through this. See
 * content-*.php for different types of content loaded via loop.php.
 *
 * Owned by the child as part of the theme-independence work (extracting the
 * base template skeleton so the maranatha parent can eventually be dropped —
 * see ops/docs/theme-independence.md). Byte-for-byte copy of the parent's
 * index.php; it still pulls the loop-header / loop-author / loop-navigation
 * partials and the CTFW_THEME_PARTIAL_DIR constant from the parent, so
 * behavior is identical for now. Note get_template_part( 'loop' ) now resolves
 * to the child's loop.php (also extracted in this change).
 *
 * Parent source: maranatha/index.php (pinned in this repo) — re-diff if the
 * parent theme is ever updated.
 *
 * More information: http://codex.wordpress.org/Template_Hierarchy
 *
 * @package Maranatha_Child
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

get_header(); // header.php ?>

<main id="maranatha-content">

	<div id="maranatha-content-inner"<?php if ( ! is_singular() ) : ?> class="maranatha-centered-large maranatha-entry-content"<?php endif; ?>>

		<?php
		// loop-header.php shows title, description, etc. for categories, tags, archives, etc. (not used by single posts)
		get_template_part( CTFW_THEME_PARTIAL_DIR . '/loop-header' );
		?>

		<?php
		// loop.php shows single or multiple posts
		get_template_part( 'loop' );
		?>

		<?php
		// loop-author.php shows bio below a blog post
		// (loop-header.php shows the same at top of author archive)
		get_template_part( CTFW_THEME_PARTIAL_DIR . '/loop-author' );
		?>

		<?php
		// loop-navigation.php shows the appropriate navigation at bottom
		get_template_part( CTFW_THEME_PARTIAL_DIR . '/loop-navigation' );
		?>

		<?php
		// comments.php lists comments when enabled (single posts only)
		comments_template();
		?>

	</div>

</main>

<?php get_footer(); // footer.php ?>
