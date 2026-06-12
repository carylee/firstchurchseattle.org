<?php
/**
 * Theme Footer — child override of the parent's footer.php.
 *
 * The parent footer is just a maroon strip with social icons + a copyright
 * line (its widgets sidebar is empty and the footer map is suppressed by
 * inc/footer-map.php). Replace it with a standard modern site footer:
 * identity + worship time, contact details, quick links, social icons, and
 * the Customizer copyright notice in a bottom bar.
 *
 * Contact details mirror /about/contact-us — if the office address/phone/email
 * changes there, update it here too.
 *
 * Styles: the `.fcs-footer` section of assets/mobile.css.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Social icon list from the Customizer (same source as the parent footer);
// markup is the parent's <ul class="maranatha-list-icons"> with el-* classes,
// which mobile.css re-skins with modern SVG masks.
$footer_icons = function_exists( 'maranatha_social_icons' )
	? maranatha_social_icons( ctfw_customization( 'footer_icon_urls' ), 'return' )
	: '';

// Copyright line; the child's inc/footer.php filter strips the theme credit.
$footer_notice = ctfw_customization( 'footer_notice' );

// Only link the privacy policy if the page is actually public.
$privacy_id  = (int) get_option( 'wp_page_for_privacy_policy' );
$privacy_url = ( $privacy_id && 'publish' === get_post_status( $privacy_id ) )
	? get_privacy_policy_url()
	: '';

$quick_links = array(
	__( 'What’s Happening', 'maranatha-child' )  => home_url( '/engage/' ),
	__( 'Events Calendar', 'maranatha-child' )   => home_url( '/events-calendar/' ),
	__( 'Watch Live', 'maranatha-child' )        => home_url( '/worship/live/' ),
	__( 'Give', 'maranatha-child' )              => home_url( '/give/' ),
	__( 'Prayer Requests', 'maranatha-child' )   => home_url( '/worship/prayer/' ),
	__( 'Contact Us', 'maranatha-child' )        => home_url( '/about/contact-us/' ),
	__( 'E-news Sign-up', 'maranatha-child' )    => 'https://firstchurchseattle.us2.list-manage.com/subscribe?u=18291af87fbc7224df67d6ab8&id=24fee5f80d',
);

// Small static map under the Contact column — shared builder in
// inc/static-map.php (committed assets/map.webp, no runtime third-party calls).
// The helper emits no loading attr; the footer is always below the fold.
$fcs_footer_map = str_replace( '<img ', '<img loading="lazy" ', fcs_static_map_image() );
?>

<footer id="maranatha-footer" class="fcs-footer">

	<div class="fcs-footer__inner">

		<div class="fcs-footer__col fcs-footer__about">
			<p class="fcs-footer__name"><?php esc_html_e( 'First Church Seattle', 'maranatha-child' ); ?></p>
			<p class="fcs-footer__denom"><?php esc_html_e( 'First United Methodist Church of Seattle', 'maranatha-child' ); ?></p>
			<p class="fcs-footer__worship"><?php esc_html_e( 'Worship with us Sundays at 10:30 am — in person and online.', 'maranatha-child' ); ?></p>
			<?php if ( $footer_icons ) : ?>
				<div class="fcs-footer__social">
					<?php echo $footer_icons; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- parent builds + escapes this markup. ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="fcs-footer__col">
			<h2 class="fcs-footer__heading"><?php esc_html_e( 'Contact', 'maranatha-child' ); ?></h2>
			<address class="fcs-footer__contact">
				<a href="https://maps.google.com/?q=180+Denny+Way,+Seattle,+WA+98109" target="_blank" rel="noopener noreferrer">180 Denny Way<br>Seattle, WA 98109</a>
				<a href="tel:+12066227278">(206) 622-7278</a>
				<a href="mailto:office@firstchurchseattle.org">office@firstchurchseattle.org</a>
				<span class="fcs-footer__mailing"><?php esc_html_e( 'Mail: PO Box 19596, Seattle, WA 98109', 'maranatha-child' ); ?></span>
			</address>
			<a class="fcs-footer__map" href="https://www.google.com/maps/dir/?api=1&amp;destination=180+Denny+Way%2C+Seattle%2C+WA+98109" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Get directions to First Church', 'maranatha-child' ); ?>">
				<?php echo $fcs_footer_map; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper builds + escapes this markup. ?>
			</a>
		</div>

		<div class="fcs-footer__col">
			<h2 class="fcs-footer__heading"><?php esc_html_e( 'Quick Links', 'maranatha-child' ); ?></h2>
			<ul class="fcs-footer__links">
				<?php foreach ( $quick_links as $label => $url ) : ?>
					<li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>

	</div>

	<?php if ( $footer_notice || $privacy_url ) : ?>
		<div class="fcs-footer__bottom">
			<div class="fcs-footer__bottom-inner">
				<?php if ( $footer_notice ) : ?>
					<p class="fcs-footer__notice"><?php echo nl2br( wptexturize( do_shortcode( $footer_notice ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Customizer value, same treatment as parent footer. ?></p>
				<?php endif; ?>
				<?php if ( $privacy_url ) : ?>
					<a class="fcs-footer__privacy" href="<?php echo esc_url( $privacy_url ); ?>"><?php esc_html_e( 'Privacy Policy', 'maranatha-child' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

</footer>

<?php
// Parent parity: the "stickies" partial (latest events / comments pinned to the
// bottom of large screens) self-gates on its Customizer options.
get_template_part( 'partials/footer-stickies' );

wp_footer();
?>

</body>
</html>
