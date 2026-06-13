<?php
/**
 * The "From the Pastor" block — `firstchurch/pastoral-letter`.
 *
 * The one editorial slot that now self-fills like the spine sections: if a post
 * in the `pastoral-letters` category was published within the look-back window
 * (default 5 days), the issue shows that letter automatically — Pastor's prose is
 * authored once as a blog post and projected into the e-news, never re-keyed.
 * When there is no recent letter, the block falls back to its `fallback` prose,
 * written directly in the issue (some weeks the message is composed straight into
 * the e-news — enews-spine.md §6/§8).
 *
 * Dynamic block, mirroring `firstchurch/happenings`: the editor JS
 * (assets/pastoral-letter-block.js) only collects attributes + the fallback
 * prose; this renders server-side. The email projection lives in inc/render.php
 * (Email::letter), sharing the SAME resolver so web and email never disagree.
 *
 * @package FirstChurch\ENews
 */

use FirstChurch\ENews\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The blog category whose latest post is the auto "From the Pastor" letter. */
const FCEN_PASTORAL_CATEGORY = 'pastoral-letters';
/** Default look-back: a letter newer than this (days) auto-fills the slot. */
const FCEN_PASTORAL_DAYS     = 5;

add_action( 'init', 'fcen_pastoral_letter_block_register' );

function fcen_pastoral_letter_block_register(): void {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	wp_register_script(
		'fcen-pastoral-letter-block',
		FCEN_URL . 'assets/pastoral-letter-block.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
		fcen_asset_version( 'assets/pastoral-letter-block.js' ),
		true
	);

	register_block_type(
		'firstchurch/pastoral-letter',
		array(
			'api_version'     => 3,
			'editor_script'   => 'fcen-pastoral-letter-block',
			'attributes'      => array(
				'days'     => array( 'type' => 'number', 'default' => FCEN_PASTORAL_DAYS ),
				'fallback' => array( 'type' => 'string', 'default' => '' ),
			),
			'render_callback' => 'fcen_pastoral_letter_block_render',
		)
	);
}

/** filemtime cache-bust for a plugin asset (mirrors the theme's fcs_asset_version). */
function fcen_asset_version( string $rel ): string {
	$path = FCEN_DIR . ltrim( $rel, '/' );
	return file_exists( $path ) ? (string) filemtime( $path ) : '0.3.0';
}

/**
 * The latest `pastoral-letters` post within the look-back window, as a view model
 * shared by the web render and the email projection — or null if none is recent.
 * The body is the full post content (the staff choice: show the letter inline),
 * resolved through the_content so blocks/shortcodes/wpautop apply.
 *
 * @param int $days Look-back window in days.
 * @return array{id:int,title:string,url:string,body:string,date:string}|null
 */
function fcen_pastoral_letter_resolve( int $days = FCEN_PASTORAL_DAYS ): ?array {
	$days = max( 1, $days );

	$q = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'category_name'       => FCEN_PASTORAL_CATEGORY,
			'posts_per_page'      => 1,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'date_query'          => array(
				array(
					'after'     => $days . ' days ago',
					'inclusive' => true,
					'column'    => 'post_date',
				),
			),
		)
	);

	if ( empty( $q->posts ) ) {
		return null;
	}

	$post = $q->posts[0];

	// Resolve the_content against this post, then restore the prior global so the
	// surrounding block walk (inc/render.php) is left untouched.
	$prev            = $GLOBALS['post'] ?? null;
	$GLOBALS['post'] = $post;
	setup_postdata( $post );
	$body = apply_filters( 'the_content', $post->post_content );
	wp_reset_postdata();
	$GLOBALS['post'] = $prev;

	return array(
		'id'    => (int) $post->ID,
		'title' => get_the_title( $post ),
		'url'   => (string) get_permalink( $post ),
		'body'  => (string) $body,
		'date'  => get_the_date( '', $post ),
	);
}

/**
 * Web render (the issue's `/enews/<slug>` archive page): the auto-pulled letter
 * (title + full body), else the hand-authored fallback prose. Returns '' when
 * there is neither a recent letter nor fallback text.
 *
 * @param array<string,mixed> $attrs Block attributes.
 */
function fcen_pastoral_letter_block_render( $attrs ): string {
	$days   = max( 1, (int) ( $attrs['days'] ?? FCEN_PASTORAL_DAYS ) );
	$letter = fcen_pastoral_letter_resolve( $days );

	if ( $letter ) {
		$title = '<a href="' . esc_url( $letter['url'] ) . '">' . esc_html( $letter['title'] ) . '</a>';
		return '<div class="fcs-pastoral-letter">'
			. '<h3 class="fcs-pastoral-letter__title">' . $title . '</h3>'
			// the_content output — already filtered/sanitized by WordPress.
			. '<div class="fcs-pastoral-letter__body">' . $letter['body'] . '</div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '</div>';
	}

	// Email::prose is the single plain-text→paragraphs converter (escapes its input),
	// shared with the email projection so the fallback reads identically on both.
	return Email::prose( (string) ( $attrs['fallback'] ?? '' ) );
}
