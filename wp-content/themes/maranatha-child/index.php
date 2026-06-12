<?php
/**
 * Main template file — child override (pinned verbatim copy).
 *
 * All content not using a more specific template comes through this. See
 * content-*.php for different types of content loaded via loop.php.
 *
 * Owned by the child as part of the theme-independence work (extracting the
 * base template skeleton so the maranatha parent can eventually be dropped —
 * see ops/docs/theme-independence.md). Started as a verbatim copy of the
 * parent's index.php; the parent's CTFW_THEME_PARTIAL_DIR constant is now
 * literalized to 'partials' so this file no longer fatals once the parent is
 * gone. The loop-header / loop-author / loop-navigation sub-partials it pulls
 * still resolve to the parent (the child has no copy yet) — tracked as a
 * remaining dependency. Note get_template_part( 'loop' ) resolves to the
 * child's loop.php (also extracted in this change).
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
		get_template_part( 'partials/loop-header' );
		?>

		<?php
		// loop.php shows single or multiple posts
		get_template_part( 'loop' );
		?>

		<?php
		// loop-author.php shows bio below a blog post
		// (loop-header.php shows the same at top of author archive)
		get_template_part( 'partials/loop-author' );
		?>

		<?php
		// loop-navigation.php shows the appropriate navigation at bottom
		get_template_part( 'partials/loop-navigation' );
		?>

		<?php
		// comments.php lists comments when enabled (single posts only)
		comments_template();
		?>

	</div>

</main>

<?php get_footer(); // footer.php ?>
