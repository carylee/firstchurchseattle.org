<?php
/**
 * Front page.
 *
 * Section order preserves the old homepage: hero → visit card + This Sunday +
 * happenings strip → Shared Breakfast story → three navigation bands
 * (Worship / News + Events / Gatherings).
 *
 * The hero's copy is content, not code — it changes (seasonal notices like
 * Pride Sunday), so it lives in the `fcs_front_hero` option (seeded from the
 * old widget by ops/bin/seed-front-hero.php; editable via wp-cli/MCP). The
 * three bands are stable navigation copy and live here in the template.
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

// The three stable navigation bands. Each carries the same washed background
// photo the old widgets used (attachment IDs from the retired ctfw-section
// instances; a missing attachment just renders the flat band).
$fcs_bands = array(
	array(
		'variant'  => 'dark',
		'title'    => __( 'Worship at First Church', 'firstchurch' ),
		'copy'     => __( 'Join us for an uplifting service, choral music, and a thought-provoking sermon every Sunday. Nursery and Children’s activities available.', 'firstchurch' ),
		'image_id' => 1669,
		'links'    => array(
			array( 'text' => __( 'Worship Livestream', 'firstchurch' ), 'url' => '/worship/live/' ),
			array( 'text' => __( 'Prayer', 'firstchurch' ), 'url' => '/worship/prayer/' ),
		),
	),
	array(
		'variant'  => 'light',
		'title'    => __( 'News + Events', 'firstchurch' ),
		'copy'     => __( 'There’s always a lot going on at First Church and in our community. Find more information about upcoming events and read our latest updates.', 'firstchurch' ),
		'image_id' => 7641,
		'links'    => array(
			array( 'text' => __( 'Monthly Calendar', 'firstchurch' ), 'url' => '/events-calendar/' ),
			array( 'text' => __( 'Upcoming Events', 'firstchurch' ), 'url' => '/upcoming-events/' ),
			array( 'text' => __( 'News', 'firstchurch' ), 'url' => '/news/' ),
		),
	),
	array(
		'variant'  => 'dark',
		'title'    => __( 'Gatherings at First Church', 'firstchurch' ),
		'copy'     => __( 'Gather to learn, to fellowship, to serve, or to make music! We have something for everyone.', 'firstchurch' ),
		'image_id' => 2087,
		'links'    => array(
			array( 'text' => __( 'Learn + Grow', 'firstchurch' ), 'url' => '/gather/grow-learn/' ),
			array( 'text' => __( 'Fellowship', 'firstchurch' ), 'url' => '/gather/fellowship/' ),
			array( 'text' => __( 'Serve', 'firstchurch' ), 'url' => '/gather/serve/' ),
		),
	),
);

?>
<main id="fcs-home" class="fcs-home">

	<section class="fcs-hero" aria-label="<?php esc_attr_e( 'Welcome', 'firstchurch' ); ?>">
		<?php if ( $fcs_hero_image ) : ?>
			<div class="fcs-hero__image" style="background-image: url('<?php echo esc_url( $fcs_hero_image ); ?>')" aria-hidden="true"></div>
		<?php endif; ?>
		<div class="fcs-hero__content">
			<h1><?php echo esc_html( $fcs_hero['title'] ); ?></h1>
			<div class="fcs-hero__copy"><?php echo wp_kses_post( $fcs_hero['content'] ); ?></div>
			<?php if ( ! empty( $fcs_hero['links'] ) ) : ?>
				<ul class="fcs-pill-list">
					<?php foreach ( $fcs_hero['links'] as $i => $link ) : ?>
						<li><a class="<?php echo 0 === $i ? 'is-primary' : ''; ?>" href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['text'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</section>

	<?php get_template_part( 'partials/home-visit-happenings' ); ?>

	<?php get_template_part( 'partials/home-breakfast-story' ); ?>

	<?php foreach ( $fcs_bands as $band ) : ?>
		<?php $fcs_band_img = ! empty( $band['image_id'] ) ? wp_get_attachment_image_url( (int) $band['image_id'], 'full' ) : ''; ?>
		<section class="fcs-band fcs-band--<?php echo esc_attr( $band['variant'] ); ?>">
			<?php if ( $fcs_band_img ) : ?>
				<div class="fcs-band__image" style="background-image: url('<?php echo esc_url( $fcs_band_img ); ?>')" aria-hidden="true"></div>
			<?php endif; ?>
			<div class="fcs-band__content">
				<h2><?php echo esc_html( $band['title'] ); ?></h2>
				<p><?php echo esc_html( $band['copy'] ); ?></p>
				<ul class="fcs-pill-list">
					<?php foreach ( $band['links'] as $link ) : ?>
						<li><a href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['text'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
	<?php endforeach; ?>

</main>
<?php

get_footer();
