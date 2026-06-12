<?php
/**
 * One-time seed: copy the homepage hero out of the legacy parent-theme
 * "CT Section" widget (widget_ctfw-section instance 1) into the first-party
 * `fcs_front_hero` option that front-page.php renders.
 *
 * Run:  wp eval-file ops/bin/seed-front-hero.php          (local: ddev wp …)
 * Safe to re-run — it overwrites the option from the widget each time, and
 * does nothing (exit 1) if the widget instance is missing.
 *
 * After the theme cutover is verified, the widget option becomes dead data;
 * hero edits go straight to the option:
 *   wp option patch update fcs_front_hero title "New title"
 */

$widgets = get_option( 'widget_ctfw-section' );
if ( ! is_array( $widgets ) || empty( $widgets[1] ) ) {
	WP_CLI::error( 'widget_ctfw-section[1] not found — nothing to seed.' );
}

$w = $widgets[1];

$links = array();
foreach ( array( 1, 2, 3, 4 ) as $i ) {
	if ( ! empty( $w[ "link{$i}_url" ] ) && ! empty( $w[ "link{$i}_text" ] ) ) {
		$links[] = array(
			'text' => (string) $w[ "link{$i}_text" ],
			'url'  => (string) $w[ "link{$i}_url" ],
		);
	}
}

$hero = array(
	'title'    => (string) ( $w['title'] ?? '' ),
	// The widget stored loose text; wpautop it once here so the option holds
	// the HTML the template prints (front-page.php runs it through wp_kses_post).
	'content'  => wpautop( (string) ( $w['content'] ?? '' ) ),
	'image_id' => (int) ( $w['image_id'] ?? 0 ),
	'links'    => $links,
);

update_option( 'fcs_front_hero', $hero, false );

WP_CLI::success( 'fcs_front_hero seeded: "' . $hero['title'] . '", ' . count( $links ) . ' links, image ' . $hero['image_id'] . '.' );
