<?php
/**
 * 404 template.
 *
 * The parent theme has no 404.php, so missing pages fall through to
 * index.php, which renders the banner plus a single apology sentence — a
 * dead end. This override keeps the parent's banner/breadcrumb (rendered by
 * get_header()) and adds a search form and quick links so visitors have
 * somewhere to go.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fcs_404_links = array(
	__( 'Home', 'maranatha-child' )             => home_url( '/' ),
	__( "What's Happening", 'maranatha-child' ) => home_url( '/engage/' ),
	__( 'Events Calendar', 'maranatha-child' )  => home_url( '/events-calendar/' ),
	__( 'Watch Live', 'maranatha-child' )       => home_url( '/worship/live/' ),
	__( 'News', 'maranatha-child' )             => home_url( '/news/' ),
	__( 'Give', 'maranatha-child' )             => home_url( '/give/' ),
	__( 'Contact Us', 'maranatha-child' )       => home_url( '/about/contact-us/' ),
);

get_header(); ?>

<main id="maranatha-content" tabindex="-1">

	<div id="maranatha-content-inner" class="maranatha-centered-large maranatha-entry-content">

		<div class="fcs-404">

			<p class="fcs-404__lead">
				<?php esc_html_e( 'Sorry — we couldn\'t find that page. It may have moved, or the link may be out of date.', 'maranatha-child' ); ?>
			</p>

			<?php get_search_form(); ?>

			<h2 class="fcs-404__heading"><?php esc_html_e( 'Or try one of these', 'maranatha-child' ); ?></h2>

			<ul class="fcs-404__links">
				<?php foreach ( $fcs_404_links as $fcs_404_label => $fcs_404_url ) : ?>
					<li><a href="<?php echo esc_url( $fcs_404_url ); ?>"><?php echo esc_html( $fcs_404_label ); ?></a></li>
				<?php endforeach; ?>
			</ul>

		</div>

	</div>

</main>

<?php get_footer();
