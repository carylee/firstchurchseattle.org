<?php
/**
 * Temporary code-side patches to DB-held content we can't edit without prod
 * access (MCP / wp-admin). Each patch rewrites content at render time and
 * becomes a silent no-op once the source content is edited properly — at
 * which point delete the patch here.
 *
 * Current patches:
 *
 * 1. Homepage hero "(masks optional)" — pandemic-era copy in the hero CT
 *    Section widget (ctfw-section, content stored in the widget instance).
 *    Strips the parenthetical so "worship with us in-person (masks optional)
 *    or online!" reads "worship with us in-person or online!".
 *    Remove after the widget text is rewritten per
 *    ops/docs/homepage-recommendations-2026-06.md.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'widget_display_callback',
	function ( $instance, $widget ) {
		if ( ! is_array( $instance )
			|| empty( $instance['content'] )
			|| ! ( $widget instanceof WP_Widget )
			|| 'ctfw-section' !== $widget->id_base ) {
			return $instance;
		}

		// Tolerate whitespace/case drift; also eat one leading space so the
		// sentence closes up cleanly.
		$instance['content'] = preg_replace(
			'/\s*\(\s*masks\s+optional\s*\)/i',
			'',
			$instance['content']
		);

		return $instance;
	},
	10,
	2
);
