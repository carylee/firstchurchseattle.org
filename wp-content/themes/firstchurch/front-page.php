<?php
/**
 * Front page — "The Open Table".
 *
 * Editorial homepage: typographic masthead on the cream canvas, a
 * happening-next ticker fed by the Happenings spine, a photo mosaic of the
 * church's five doors (Worship / Shared Breakfast / Music / Pride /
 * Community — photos ship with the theme in assets/home/), and the candle
 * welcome as a closing creed band.
 *
 * The masthead identity line and the seasonal notice come from the
 * `fcs_front_hero` option (seeded by ops/bin/seed-front-hero.php; editable
 * via wp-cli/MCP) so agents can update them without code.
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hero/notice content: option with a safe fallback.
 *
 * @return array{title:string,content:string,image_id:int,links:array<int,array{text:string,url:string}>}
 */
function fcs_front_hero(): array {
	$defaults = array(
		'title'    => __( 'Serving the Heart of the City', 'firstchurch' ),
		'content'  => '',
		'image_id' => 0,
		'links'    => array(),
	);

	$opt = get_option( 'fcs_front_hero' );

	return is_array( $opt ) ? array_merge( $defaults, $opt ) : $defaults;
}

/**
 * The next few happenings for the ticker: deduped occurrences from the
 * spine, soonest first.
 *
 * @param int $count Items wanted.
 * @return array<int,array{date:string,title:string,url:string}>
 */
function fcs_ticker_items( int $count = 4 ): array {
	if ( ! function_exists( 'happenings_event_occurrences' ) ) {
		return array();
	}

	$today = current_time( 'Y-m-d' );
	$until = gmdate( 'Y-m-d', strtotime( $today . ' +35 days' ) );
	$seen  = array();
	$out   = array();

	foreach ( happenings_event_occurrences( $today, $until ) as $occ ) {
		if ( 'rhythm' === ( $occ['kind'] ?? '' ) ) {
			continue; // weekly fixtures stay out of the news ticker.
		}
		$key = $occ['url'] ?: $occ['title'];
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$out[]        = array(
			'date'  => date_i18n( 'M j', strtotime( $occ['date'] ) ),
			'title' => (string) $occ['title'],
			'url'   => (string) $occ['url'],
		);
		if ( count( $out ) >= $count ) {
			break;
		}
	}

	return $out;
}

get_header();

$fcs_hero   = fcs_front_hero();
$fcs_notice = trim( wp_strip_all_tags( $fcs_hero['content'] ) );
// The option's content carries the standing identity line + any seasonal
// notice; the masthead sets its own identity copy, so surface only a line
// that mentions something dated/seasonal (heuristic: a month name).
$fcs_seasonal = '';
foreach ( preg_split( '/(?<=[.!?])\s+/', $fcs_notice ) ?: array() as $fcs_sentence ) {
	if ( preg_match( '/January|February|March|April|May|June|July|August|September|October|November|December/', $fcs_sentence )
		&& ! preg_match( '/^Worship Sundays/', $fcs_sentence ) ) {
		$fcs_seasonal = trim( $fcs_sentence );
		break;
	}
}

$fcs_theme_uri = get_stylesheet_directory_uri();

$fcs_tiles = array(
	array(
		'class' => 'is-worship',
		'img'   => $fcs_theme_uri . '/assets/home/tile-worship.jpg',
		'title' => __( 'Worship with us', 'firstchurch' ),
		'sub'   => __( 'Sundays 10:30 am — sanctuary & livestream', 'firstchurch' ),
		'url'   => '/worship/live/',
	),
	array(
		'class' => 'is-breakfast',
		'img'   => $fcs_theme_uri . '/assets/home/tile-breakfast.jpg',
		'title' => __( 'Shared Breakfast', 'firstchurch' ),
		'sub'   => __( '15,000 hot meals a year, every Sunday since 1997', 'firstchurch' ),
		'url'   => '/gather/serve/shared-breakfast/',
	),
	array(
		'class' => 'is-music',
		'img'   => $fcs_theme_uri . '/assets/home/tile-music.jpg',
		'title' => __( 'Music', 'firstchurch' ),
		'sub'   => __( 'a sanctuary that sings', 'firstchurch' ),
		'url'   => '/gather/music/',
	),
	array(
		'class' => 'is-pride',
		'img'   => $fcs_theme_uri . '/assets/home/tile-pride.jpg',
		'title' => __( 'Pride + Faith', 'firstchurch' ),
		'sub'   => __( 'Reconciling — all means all', 'firstchurch' ),
		'url'   => '/gather/pride-at-first-church/',
	),
	array(
		'class' => 'is-community',
		'img'   => $fcs_theme_uri . '/assets/home/tile-community.jpg',
		'title' => __( 'Community', 'firstchurch' ),
		'sub'   => __( 'kids, classes, fellowship & serving', 'firstchurch' ),
		'url'   => '/gather/',
	),
);

$fcs_ticker = fcs_ticker_items();

?>
<main id="fcs-home" class="fcs-home">

	<section class="fcs-mast" aria-label="<?php esc_attr_e( 'Welcome', 'firstchurch' ); ?>">
		<p class="fcs-kicker"><?php esc_html_e( 'A progressive, inclusive church in downtown Seattle', 'firstchurch' ); ?></p>
		<h1><?php esc_html_e( 'Whoever you are,', 'firstchurch' ); ?><br><em><?php esc_html_e( 'wherever you find yourself today.', 'firstchurch' ); ?></em></h1>
		<p class="fcs-mast__sub">
			<strong><?php esc_html_e( 'Worship Sundays · 10:30 am · 180 Denny Way', 'firstchurch' ); ?></strong>
			— <?php esc_html_e( 'in person and live on YouTube. Kids always welcome. Doubts always welcome. Come as you are.', 'firstchurch' ); ?>
		</p>
		<?php if ( $fcs_seasonal ) : ?>
			<p class="fcs-mast__notice"><?php echo esc_html( $fcs_seasonal ); ?></p>
		<?php endif; ?>
	</section>

	<?php if ( $fcs_ticker ) : ?>
		<div class="fcs-ticker" role="list" aria-label="<?php esc_attr_e( 'Coming up', 'firstchurch' ); ?>">
			<div class="fcs-ticker__in">
				<span role="listitem"><b><?php esc_html_e( 'This Sunday', 'firstchurch' ); ?></b> <?php esc_html_e( 'Breakfast 7:30 · Worship 10:30', 'firstchurch' ); ?></span>
				<?php foreach ( $fcs_ticker as $t ) : ?>
					<span role="listitem"><b><?php echo esc_html( $t['date'] ); ?></b>
						<?php if ( $t['url'] ) : ?><a href="<?php echo esc_url( $t['url'] ); ?>"><?php echo esc_html( $t['title'] ); ?></a><?php else : ?><?php echo esc_html( $t['title'] ); ?><?php endif; ?>
					</span>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<section class="fcs-mosaic" aria-label="<?php esc_attr_e( 'Life at First Church', 'firstchurch' ); ?>">
		<?php foreach ( $fcs_tiles as $tile ) : ?>
			<a class="fcs-tile <?php echo esc_attr( $tile['class'] ); ?>" href="<?php echo esc_url( home_url( $tile['url'] ) ); ?>" style="background-image:url('<?php echo esc_url( $tile['img'] ); ?>')">
				<span class="fcs-tile__lab">
					<b><?php echo esc_html( $tile['title'] ); ?></b>
					<span><?php echo esc_html( $tile['sub'] ); ?></span>
				</span>
			</a>
		<?php endforeach; ?>
	</section>

	<?php
	// The candle welcome, closing the page as a creed band — adapted for the
	// web from the community-candle liturgy spoken here 2024–2026. The
	// masthead opens the welcome ("Whoever you are, wherever you find
	// yourself today"); this band finishes the sentence.
	?>
	<section class="fcs-creed" aria-label="<?php esc_attr_e( 'Our welcome', 'firstchurch' ); ?>">
		<div class="fcs-creed__in">
			<p class="fcs-creed__big">
				<?php esc_html_e( 'Across the whole spectrum of human existence, with your questions and your doubts, in a world that needs repair — may this community be a place of', 'firstchurch' ); ?>
				<em><?php esc_html_e( 'companionship and healing', 'firstchurch' ); ?></em>
				<?php esc_html_e( 'for you. There is nothing that can separate you from God’s all-embracing love.', 'firstchurch' ); ?>
			</p>
			<ul class="fcs-btn-list">
				<li><a class="is-primary" href="<?php echo esc_url( home_url( '/about/newcomers/' ) ); ?>"><?php esc_html_e( 'Plan a visit', 'firstchurch' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/connection-card/' ) ); ?>"><?php esc_html_e( 'Say hello', 'firstchurch' ); ?></a></li>
			</ul>
		</div>
	</section>

</main>
<?php

get_footer();
