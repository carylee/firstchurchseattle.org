<?php
/**
 * Site footer: identity + worship time, contact details, quick links, social
 * icons, and a © bottom bar.
 *
 * Everything here is first-party content. The social links and contact
 * details are part of the site's brand surface and live in code on purpose —
 * they mirror /about/contact-us; if the office address/phone/email changes
 * there, update it here too.
 *
 * Styles: assets/src/footer.css.
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Social links, rendered as inline SVG (24px, stroke/fill = currentColor).
$fcs_social = array(
	array(
		'label' => __( 'Facebook', 'firstchurch' ),
		'url'   => 'https://www.facebook.com/firstchurchseattle',
		'icon'  => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-7h2.4l.4-3h-2.8V9.1c0-.9.3-1.5 1.6-1.5h1.3V4.9c-.3 0-1.1-.1-2-.1-2 0-3.4 1.2-3.4 3.5V11H8.5v3H11v7h2.5z"/></svg>',
	),
	array(
		'label' => __( 'Instagram', 'firstchurch' ),
		'url'   => 'https://www.instagram.com/firstchurchseattle/',
		'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="4.5"/><circle cx="12" cy="12" r="3.8"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg>',
	),
	array(
		'label' => __( 'Email the office', 'firstchurch' ),
		'url'   => 'mailto:office@firstchurchseattle.org',
		'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="m4 7 8 6 8-6"/></svg>',
	),
);

// Only link the privacy policy if the page is actually public.
$privacy_id  = (int) get_option( 'wp_page_for_privacy_policy' );
$privacy_url = ( $privacy_id && 'publish' === get_post_status( $privacy_id ) )
	? get_privacy_policy_url()
	: '';

$quick_links = array(
	__( 'What’s Happening', 'firstchurch' )  => home_url( '/engage/' ),
	__( 'Events Calendar', 'firstchurch' )   => home_url( '/events-calendar/' ),
	__( 'Watch Live', 'firstchurch' )        => home_url( '/worship/live/' ),
	__( 'Give', 'firstchurch' )              => home_url( '/give/' ),
	__( 'Prayer Requests', 'firstchurch' )   => home_url( '/worship/prayer/' ),
	__( 'Contact Us', 'firstchurch' )        => home_url( '/about/contact-us/' ),
	__( 'E-news Sign-up', 'firstchurch' )    => 'https://firstchurchseattle.us2.list-manage.com/subscribe?u=18291af87fbc7224df67d6ab8&id=24fee5f80d',
);

// Small static map under the Contact column — shared builder in
// inc/static-map.php (committed assets/map.webp, no runtime third-party calls).
// The helper emits no loading attr; the footer is always below the fold.
$fcs_footer_map = str_replace( '<img ', '<img loading="lazy" ', fcs_static_map_image() );
?>

<footer class="fcs-footer">

	<div class="fcs-footer__inner">

		<div class="fcs-footer__col fcs-footer__about">
			<p class="fcs-footer__name"><?php esc_html_e( 'First Church Seattle', 'firstchurch' ); ?></p>
			<p class="fcs-footer__denom"><?php esc_html_e( 'First United Methodist Church of Seattle', 'firstchurch' ); ?></p>
			<p class="fcs-footer__worship"><?php esc_html_e( 'Worship with us Sundays at 10:30 am — in person and online.', 'firstchurch' ); ?></p>
			<ul class="fcs-footer__social">
				<?php foreach ( $fcs_social as $s ) : ?>
					<li>
						<a href="<?php echo esc_url( $s['url'] ); ?>"<?php echo str_starts_with( $s['url'], 'http' ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
							<?php echo $s['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG above. ?>
							<span class="screen-reader-text"><?php echo esc_html( $s['label'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="fcs-footer__col">
			<h2 class="fcs-footer__heading"><?php esc_html_e( 'Contact', 'firstchurch' ); ?></h2>
			<address class="fcs-footer__contact">
				<a href="https://maps.google.com/?q=180+Denny+Way,+Seattle,+WA+98109" target="_blank" rel="noopener noreferrer">180 Denny Way<br>Seattle, WA 98109</a>
				<a href="tel:+12066227278">(206) 622-7278</a>
				<a href="mailto:office@firstchurchseattle.org">office@firstchurchseattle.org</a>
				<span class="fcs-footer__mailing"><?php esc_html_e( 'Mail: PO Box 19596, Seattle, WA 98109', 'firstchurch' ); ?></span>
			</address>
			<a class="fcs-footer__map" href="https://www.google.com/maps/dir/?api=1&amp;destination=180+Denny+Way%2C+Seattle%2C+WA+98109" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Get directions to First Church', 'firstchurch' ); ?>">
				<?php echo $fcs_footer_map; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper builds + escapes this markup. ?>
			</a>
		</div>

		<div class="fcs-footer__col">
			<h2 class="fcs-footer__heading"><?php esc_html_e( 'Quick Links', 'firstchurch' ); ?></h2>
			<ul class="fcs-footer__links">
				<?php foreach ( $quick_links as $label => $url ) : ?>
					<li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>

	</div>

	<div class="fcs-footer__bottom">
		<div class="fcs-footer__bottom-inner">
			<p class="fcs-footer__notice">© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php esc_html_e( 'First Church Seattle', 'firstchurch' ); ?></p>
			<?php if ( $privacy_url ) : ?>
				<a class="fcs-footer__privacy" href="<?php echo esc_url( $privacy_url ); ?>"><?php esc_html_e( 'Privacy Policy', 'firstchurch' ); ?></a>
			<?php endif; ?>
		</div>
	</div>

</footer>

<?php wp_footer(); ?>

</body>
</html>
