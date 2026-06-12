<?php
/**
 * First-party replacements for the parent theme's leaf helper functions.
 *
 * Part of the theme-independence work (step 3 in ops/docs/theme-independence.md):
 * de-coupling the child from the maranatha parent's `ctfw_*` / `maranatha_*`
 * functions so the parent can eventually be dropped. Each function here is a
 * faithful reimplementation of its parent counterpart, verified against the
 * pinned parent source — and, where the original applied a filter, that filter
 * is dropped only because nothing in our code hooks it (checked).
 *
 * Only the pure-logic leaf helpers live here. The customizer-bound functions
 * (ctfw_customization, maranatha_social_icons, maranatha_title_paged) and the
 * inherited header/loop sub-partials are intentionally NOT reimplemented yet —
 * they depend on the parent's customizer + framework data model and need the
 * site running to verify. They stay coupled and tracked in the doc.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'fcs_make_friendly' ) ) {
	/**
	 * Strip a CTC post-type/taxonomy prefix and make the slug template-friendly.
	 *
	 * Replica of ctfw_make_friendly(): 'ctc_person' => 'person', 'page_x' => 'page-x'.
	 *
	 * @param string $slug Post type or other prefixed slug.
	 * @return string Friendlier slug without the ctc_ prefix, underscores → hyphens.
	 */
	function fcs_make_friendly( string $slug ): string {
		return str_replace( array( 'ctc_', '_' ), array( '', '-' ), $slug );
	}
}

if ( ! function_exists( 'fcs_has_title' ) ) {
	/**
	 * True when the current post has a non-empty title.
	 *
	 * Replica of ctfw_has_title().
	 *
	 * @return bool
	 */
	function fcs_has_title(): bool {
		return (bool) trim( strip_tags( (string) get_the_title() ) );
	}
}

if ( ! function_exists( 'fcs_has_content' ) ) {
	/**
	 * True when the current post has real body content.
	 *
	 * Replica of ctfw_has_content(): strips tags but keeps media/embeds so an
	 * image-only post still counts, and treats any block (`wp:`) markup as
	 * content. The page-builder branch delegates to the parent's detector while
	 * it exists (this site uses no Elementor/Beaver Builder, so it is always
	 * false and the call falls away cleanly once the parent is gone).
	 *
	 * @return bool
	 */
	function fcs_has_content(): bool {
		$content = trim( get_the_content() );

		$has = (bool) (
			strip_tags( $content, '<img><iframe><script><embed><audio><video>' )
			|| preg_match( '/wp\:/', $content )
		);

		if ( ! $has && function_exists( 'ctfw_using_builder_plugin' ) && ctfw_using_builder_plugin() ) {
			$has = true;
		}

		return $has;
	}
}

if ( ! function_exists( 'fcs_icon_class' ) ) {
	/**
	 * Echo (or return) the icon CSS class for a named UI element.
	 *
	 * Replica of maranatha_get_icon_class() + maranatha_icon_class(): the same
	 * static Elusive Icons (`el el-*`) map the parent ships. Ported whole so the
	 * child is self-contained, though only a handful of elements are in use now.
	 *
	 * @param string $element Element name, e.g. 'gallery', 'nav-left'.
	 * @param bool   $return  Return the class instead of echoing it.
	 * @return string|void Class string when $return is true.
	 */
	function fcs_icon_class( string $element, bool $return = false ) {
		$classes = array(
			'search-button'       => 'el el-search',
			'search-cancel'       => 'el el-remove-sign',
			'mobile-menu-close'   => 'el el-remove-sign',
			'nav-left'            => 'el el-chevron-left',
			'nav-right'           => 'el el-chevron-right',
			'archive-dropdown'    => 'el el-chevron-down',
			'comment-reply'       => 'el el-comment',
			'comment-edit'        => 'el el-edit',
			'edit-post'           => 'el el-edit',
			'gallery'             => 'el el-camera',
			'entry-tag'           => 'el el-tags',
			'download'            => 'el el-download-alt',
			'video-watch'         => 'el el-video',
			'video-download'      => 'el el-video',
			'audio-listen'        => 'el el-headphones',
			'audio-download'      => 'el el-headphones',
			'pdf-download'        => 'el el-file',
			'sermon-read'         => 'el el-align-justify',
			'sermon-topic'        => 'el el-folder',
			'sermon-book'         => 'el el-book',
			'sermon-series'       => 'el el-forward-alt',
			'sermon-speaker'      => 'el el-torso',
			'sermon-date'         => 'el el-calendar',
			'event-directions'    => 'el el-road',
			'calendar-remove'     => 'el el-remove',
			'calendar-prev'       => 'el el-chevron-left',
			'calendar-next'       => 'el el-chevron-right',
			'calendar-month'      => 'el el-calendar',
			'calendar-category'   => 'el el-folder',
			'location-directions' => 'el el-road',
			'map-venue'           => 'el el-flag',
			'map-phone'           => 'el el-phone-alt',
			'map-address'         => 'el el-map-marker',
			'map-times'           => 'el el-time',
			'map-email'           => 'el el-envelope',
		);

		$class = isset( $classes[ $element ] ) ? $classes[ $element ] : '';

		if ( $return ) {
			return $class;
		}

		echo $class;
	}
}
