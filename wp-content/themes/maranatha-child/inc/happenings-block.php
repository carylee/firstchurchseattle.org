<?php
/**
 * The `firstchurch/happenings` block — renders a section of the Happenings feed
 * (Featured / Upcoming events / Recent announcements) as .fcs-card cards on the
 * What's Happening (/engage) page.
 *
 * Dynamic block: the editor JS (assets/happenings-block.js) only collects
 * attributes; this renders server-side from the firstchurch-happenings spine,
 * mapping each item through the shared CardView (happenings_card_view). Reuses
 * the existing .fcs-card visual language (assets/mobile.css). Renders nothing if
 * the spine plugin is inactive or the section is empty.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'fcs_happenings_block_register' );
function fcs_happenings_block_register() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	wp_register_script(
		'fcs-happenings-block',
		get_stylesheet_directory_uri() . '/assets/happenings-block.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
		FCS_CHILD_VERSION,
		true
	);

	register_block_type(
		'firstchurch/happenings',
		array(
			'api_version'     => 3,
			'editor_script'   => 'fcs-happenings-block',
			'attributes'      => array(
				'section' => array( 'type' => 'string', 'default' => 'featured' ),
				'count'   => array( 'type' => 'number', 'default' => 3 ),
				'weeks'   => array( 'type' => 'number', 'default' => 8 ),
				'days'    => array( 'type' => 'number', 'default' => 30 ),
				'heading'         => array( 'type' => 'string', 'default' => '' ),
				'excludeFeatured' => array( 'type' => 'boolean', 'default' => false ),
			),
			'render_callback' => 'fcs_happenings_block_render',
		)
	);
}

/**
 * Resolve the requested section from the spine and echo a .fcs-card-grid.
 *
 * @param array $attrs Block attributes.
 * @return string HTML.
 */
function fcs_happenings_block_render( $attrs ) {
	if ( ! function_exists( 'happenings_card_view' ) ) {
		return ''; // firstchurch-happenings inactive — fail soft.
	}

	$section = isset( $attrs['section'] ) ? (string) $attrs['section'] : 'featured';
	$count   = max( 1, (int) ( $attrs['count'] ?? 3 ) );
	$weeks   = max( 1, (int) ( $attrs['weeks'] ?? 8 ) );
	$days    = max( 1, (int) ( $attrs['days'] ?? 30 ) );

	switch ( $section ) {
		case 'events':
			$items = happenings_event_items( $weeks );
			break;
		case 'announcements':
			$items = happenings_news_items( $days );
			break;
		case 'featured':
		default:
			$items = happenings_featured( $count, $weeks );
			break;
	}

	// Drop items already promoted into a Featured block on the same page so they
	// don't appear twice (a Happening's `weight` is non-empty only when > 0 —
	// i.e. it's in the Featured set). Filter before the count slice so the list
	// still fills to `count`. Featured itself is the source of truth, so the
	// toggle is a no-op there.
	if ( ! empty( $attrs['excludeFeatured'] ) && 'featured' !== $section ) {
		$items = array_values( array_filter( $items, static function ( $it ) {
			return empty( $it['weight'] );
		} ) );
	}

	$items = array_slice( $items, 0, $count );

	if ( empty( $items ) ) {
		return '';
	}

	$heading = isset( $attrs['heading'] ) ? trim( (string) $attrs['heading'] ) : '';

	ob_start();

	if ( '' !== $heading ) {
		echo '<h2 class="fcs-happenings__heading">' . esc_html( $heading ) . '</h2>';
	}

	echo '<div class="fcs-card-grid">';

	foreach ( $items as $item ) {
		// The Featured row is curated, not chronological, so it suppresses an
		// ANNOUNCEMENT's published-on date (it reads as the item's "when" and
		// misleads when the real date lives in the title/body). An EVENT's meta is
		// its genuine when-line ("June 17 at 7:00 pm"), which is exactly what a
		// featured event should show (Phase 4), so it is never suppressed. Other
		// sections keep every item's meta line.
		$show_meta = ( 'featured' !== $section ) || ( 'event' === ( $item['source'] ?? '' ) );
		echo fcs_render_happening_card( happenings_card_view( $item ), $show_meta ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally.
	}

	echo '</div>';

	return (string) ob_get_clean();
}

/**
 * Render one Happening as a `.fcs-card` article — the shared card markup used by
 * the /engage block and the spine-backed events templates
 * (page-templates/page-events-*.php), so the card visual language lives in one
 * place. Escapes all output; returns HTML.
 *
 * @param array  $v         View-model from happenings_card_view().
 * @param bool   $show_meta Show the date/when line (Featured suppresses it).
 * @return string
 */
function fcs_render_happening_card( array $v, bool $show_meta = true ): string {
	ob_start();
	?>
	<article class="fcs-card">
		<div class="fcs-card__body">
			<h3 class="fcs-card__title">
				<?php if ( '' !== $v['url'] ) : ?>
					<a href="<?php echo esc_url( $v['url'] ); ?>"><?php echo esc_html( $v['title'] ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $v['title'] ); ?>
				<?php endif; ?>
			</h3>
			<?php if ( $show_meta && '' !== $v['meta'] ) : ?>
				<p class="fcs-card__meta"><?php echo esc_html( $v['meta'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $v['blurb'] ) : ?>
				<p class="fcs-card__excerpt"><?php echo esc_html( wp_trim_words( $v['blurb'], 28 ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $v['ctaUrl'] ) : ?>
			<?php // Fallback CTAs (Read more / Event details) render quieter than a real sign-up link. ?>
			<div class="fcs-card__cta">
				<a href="<?php echo esc_url( $v['ctaUrl'] ); ?>" class="fcs-cta-button<?php echo empty( $v['ctaPrimary'] ) ? ' is-fallback' : ''; ?>"><?php echo esc_html( $v['ctaLabel'] ); ?></a>
			</div>
		<?php endif; ?>
	</article>
	<?php
	return (string) ob_get_clean();
}
