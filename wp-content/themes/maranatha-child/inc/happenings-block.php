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
				'heading' => array( 'type' => 'string', 'default' => '' ),
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
			$items = array_slice( happenings_event_items( $weeks ), 0, $count );
			break;
		case 'announcements':
			$items = array_slice( happenings_news_items( $days ), 0, $count );
			break;
		case 'featured':
		default:
			$items = happenings_featured_news( $count );
			break;
	}

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
		$v = happenings_card_view( $item );
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
				<?php if ( '' !== $v['meta'] ) : ?>
					<p class="fcs-card__meta"><?php echo esc_html( $v['meta'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $v['blurb'] ) : ?>
					<p class="fcs-card__excerpt"><?php echo esc_html( wp_trim_words( $v['blurb'], 28 ) ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $v['ctaUrl'] ) : ?>
				<div class="fcs-card__cta">
					<a href="<?php echo esc_url( $v['ctaUrl'] ); ?>" class="fcs-cta-button"><?php echo esc_html( $v['ctaLabel'] ); ?></a>
				</div>
			<?php endif; ?>
		</article>
		<?php
	}

	echo '</div>';

	return (string) ob_get_clean();
}
