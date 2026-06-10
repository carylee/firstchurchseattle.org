<?php
/**
 * Homepage: Shared Breakfast story section.
 *
 * "Lead with the story, not the org chart"
 * (ops/docs/homepage-recommendations-2026-06.md): one section that says who
 * this church is — the headline, the photo, a real guest's words, one button.
 * Headline and quote are the Shared Breakfast page's own copy
 * (/gather/serve/shared-breakfast/); the photo is whatever that page's
 * featured image is, so editors update both surfaces in one place. Fails
 * soft to no image if the page or its thumbnail goes away.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fcs_breakfast_page = get_page_by_path( 'gather/serve/shared-breakfast' );
$fcs_breakfast_url  = $fcs_breakfast_page ? get_permalink( $fcs_breakfast_page ) : home_url( '/gather/serve/shared-breakfast/' );
$fcs_breakfast_img  = $fcs_breakfast_page ? get_the_post_thumbnail( $fcs_breakfast_page, 'large', array( 'class' => 'fcs-breakfast__img', 'loading' => 'lazy' ) ) : '';
?>

<section class="maranatha-home-section fcs-breakfast" aria-label="<?php esc_attr_e( 'Shared Breakfast', 'maranatha-child' ); ?>">
	<div class="fcs-breakfast__inner">

		<?php if ( $fcs_breakfast_img ) : ?>
		<div class="fcs-breakfast__media">
			<?php echo $fcs_breakfast_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-generated img tag. ?>
		</div>
		<?php endif; ?>

		<div class="fcs-breakfast__story">
			<p class="fcs-breakfast__kicker"><?php esc_html_e( 'Shared Breakfast · Sundays 7:30–9:00 am', 'maranatha-child' ); ?></p>
			<h2 class="fcs-breakfast__heading"><?php esc_html_e( 'Together, we can feed 15,000 hungry people every year.', 'maranatha-child' ); ?></h2>
			<p class="fcs-breakfast__copy"><?php esc_html_e( 'Every Sunday since 1997, volunteers have served a hot breakfast in our Fellowship Hall to anyone who comes hungry.', 'maranatha-child' ); ?></p>
			<blockquote class="fcs-breakfast__quote">
				<p>&#8220;<?php esc_html_e( 'Thanks for the great breakfast. You made me smile when I thought there was not anything to smile about.', 'maranatha-child' ); ?>&#8221;</p>
				<cite><?php esc_html_e( 'a Shared Breakfast guest', 'maranatha-child' ); ?></cite>
			</blockquote>
			<a class="fcs-visit__btn fcs-visit__btn--primary fcs-breakfast__btn" href="<?php echo esc_url( $fcs_breakfast_url ); ?>"><?php esc_html_e( 'Support Shared Breakfast', 'maranatha-child' ); ?></a>
		</div>

	</div>
</section>
