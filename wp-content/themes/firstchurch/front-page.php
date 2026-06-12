<?php
/**
 * Front page.
 *
 * Section order: hero (full-colour photo under a maroon scrim) → "New here?"
 * pathway → visit card + This Sunday + happenings strip → Shared Breakfast
 * story → one featured story band (from the Happenings spine's Featured row;
 * skipped when nothing is featured).
 *
 * The hero's copy is content, not code — it changes (seasonal notices like
 * Pride Sunday), so it lives in the `fcs_front_hero` option (seeded from the
 * old widget by ops/bin/seed-front-hero.php; editable via wp-cli/MCP). The
 * pathway tiles are stable wayfinding and live here in the template.
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hero data: option with a safe hardcoded fallback so the front page can
 * never render an empty hero.
 *
 * @return array{title:string,content:string,image_id:int,links:array<int,array{text:string,url:string}>}
 */
function fcs_front_hero(): array {
	$defaults = array(
		'title'    => __( 'Serving the Heart of the City', 'firstchurch' ),
		'content'  => '<p>' . __( 'A progressive, inclusive United Methodist community in downtown Seattle — all are welcome, no exceptions.', 'firstchurch' ) . '</p>'
			. '<p><strong>' . __( 'Worship Sundays · 10:30 am · 180 Denny Way · in person &amp; live on YouTube', 'firstchurch' ) . '</strong></p>',
		'image_id' => 0,
		'links'    => array(
			array( 'text' => __( 'Plan Your Visit', 'firstchurch' ), 'url' => '/about/newcomers/' ),
			array( 'text' => __( 'Watch Live', 'firstchurch' ), 'url' => '/worship/live/' ),
		),
	);

	$opt = get_option( 'fcs_front_hero' );

	return is_array( $opt ) ? array_merge( $defaults, $opt ) : $defaults;
}

get_header();

$fcs_hero       = fcs_front_hero();
$fcs_hero_image = $fcs_hero['image_id'] ? wp_get_attachment_image_url( (int) $fcs_hero['image_id'], 'full' ) : '';

// "New here?" pathway — three first steps for a newcomer.
$fcs_path_tiles = array(
	array(
		'title' => __( 'Plan your visit', 'firstchurch' ),
		'copy'  => __( 'Where to park, what Sunday looks like, and what to expect when you arrive.', 'firstchurch' ),
		'url'   => '/about/newcomers/',
	),
	array(
		'title' => __( 'Watch a service', 'firstchurch' ),
		'copy'  => __( 'Join live on Sunday at 10:30 am, or see what worship is like first.', 'firstchurch' ),
		'url'   => '/worship/live/',
	),
	array(
		'title' => __( 'Say hello', 'firstchurch' ),
		'copy'  => __( 'Introduce yourself with the connection card — we’d love to meet you.', 'firstchurch' ),
		'url'   => '/connection-card/',
	),
);

// One featured story from the Happenings spine (fcs_weight-promoted).
$fcs_featured = null;
if ( function_exists( 'happenings_section_items' ) && function_exists( 'happenings_card_view' ) ) {
	$fcs_featured_items = happenings_section_items( 'featured', 1 );
	if ( $fcs_featured_items ) {
		$fcs_featured = happenings_card_view( $fcs_featured_items[0] );
	}
}

?>
<main id="fcs-home" class="fcs-home">

	<section class="fcs-hero" aria-label="<?php esc_attr_e( 'Welcome', 'firstchurch' ); ?>">
		<?php if ( $fcs_hero_image ) : ?>
			<div class="fcs-hero__image" style="background-image: url('<?php echo esc_url( $fcs_hero_image ); ?>')" aria-hidden="true"></div>
		<?php endif; ?>
		<div class="fcs-hero__scrim" aria-hidden="true"></div>
		<div class="fcs-hero__inner">
			<div class="fcs-hero__content">
				<h1><?php echo esc_html( $fcs_hero['title'] ); ?></h1>
				<div class="fcs-hero__copy"><?php echo wp_kses_post( $fcs_hero['content'] ); ?></div>
				<?php if ( ! empty( $fcs_hero['links'] ) ) : ?>
					<ul class="fcs-btn-list">
						<?php foreach ( $fcs_hero['links'] as $i => $link ) : ?>
							<li><a class="<?php echo 0 === $i ? 'is-primary' : ''; ?>" href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['text'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="fcs-path" aria-label="<?php esc_attr_e( 'New here?', 'firstchurch' ); ?>">
		<div class="fcs-container--med">
			<p class="fcs-kicker"><?php esc_html_e( 'New here?', 'firstchurch' ); ?></p>
			<h2 class="fcs-path__heading"><?php esc_html_e( 'Three easy first steps', 'firstchurch' ); ?></h2>
			<div class="fcs-path__tiles">
				<?php foreach ( $fcs_path_tiles as $i => $tile ) : ?>
					<a class="fcs-path__tile" href="<?php echo esc_url( $tile['url'] ); ?>">
						<span class="fcs-path__num" aria-hidden="true"><?php echo esc_html( $i + 1 ); ?></span>
						<span class="fcs-path__body">
							<span class="fcs-path__title"><?php echo esc_html( $tile['title'] ); ?></span>
							<span class="fcs-path__copy"><?php echo esc_html( $tile['copy'] ); ?></span>
						</span>
						<span class="fcs-path__arrow" aria-hidden="true">→</span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<?php get_template_part( 'partials/home-visit-happenings' ); ?>

	<?php get_template_part( 'partials/home-breakfast-story' ); ?>

	<?php if ( $fcs_featured ) : ?>
		<section class="fcs-feature" aria-label="<?php esc_attr_e( 'Featured at First Church', 'firstchurch' ); ?>">
			<div class="fcs-feature__content">
				<p class="fcs-kicker fcs-kicker--on-dark"><?php esc_html_e( 'Featured at First Church', 'firstchurch' ); ?></p>
				<h2>
					<?php if ( '' !== $fcs_featured['url'] ) : ?>
						<a href="<?php echo esc_url( $fcs_featured['url'] ); ?>"><?php echo esc_html( $fcs_featured['title'] ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $fcs_featured['title'] ); ?>
					<?php endif; ?>
				</h2>
				<?php if ( '' !== $fcs_featured['blurb'] ) : ?>
					<p class="fcs-feature__blurb"><?php echo esc_html( wp_trim_words( $fcs_featured['blurb'], 40 ) ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $fcs_featured['ctaUrl'] ) : ?>
					<ul class="fcs-btn-list">
						<li><a class="is-primary" href="<?php echo esc_url( $fcs_featured['ctaUrl'] ); ?>"><?php echo esc_html( $fcs_featured['ctaLabel'] ?: __( 'Learn more', 'firstchurch' ) ); ?></a></li>
					</ul>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

</main>
<?php

get_footer();
