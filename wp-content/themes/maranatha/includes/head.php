<?php
/**
 * <head> Functions
 *
 * Also see enqueue-styles.php and enqueue-scripts.php.
 *
 * @package    Maranatha
 * @subpackage Functions
 * @copyright  Copyright (c) 2015 - 2020, ChurchThemes.com
 * @link       https://churchthemes.com/themes/maranatha
 * @license    GPLv2 or later
 * @since      1.0
 */

// No direct access.
if (! defined( 'ABSPATH' )) {
 exit;
}

/*******************************************
 * CUSTOM STYLES
 *******************************************/

/**
 * Insert custom styles (colors, fonts, background, etc.) from Customizer for frontend and Classic Editor.
 *
 * @since 1.0
 */
function maranatha_head_styles() {

	// Colors
	$main_color = ctfw_customization( 'main_color' );
	$link_color = ctfw_customization( 'link_color' );

	// Fonts
	$logo_font_stack = ctfw_font_stack( ctfw_customization( 'logo_font' ), ctfw_google_fonts( 'logo_font' ) );
	$heading_font_stack = ctfw_font_stack( ctfw_customization( 'heading_font' ), ctfw_google_fonts( 'heading_font' ) );
	$menu_font_stack = ctfw_font_stack( ctfw_customization( 'menu_font' ), ctfw_google_fonts( 'menu_font' ) );
	$body_font_stack = ctfw_font_stack( ctfw_customization( 'body_font' ), ctfw_google_fonts( 'body_font' ) );

?>
<style type="text/css">
<?php echo maranatha_style_selectors( 'logo_font' ); ?> {
	font-family: <?php echo $logo_font_stack; ?>;
}

<?php echo maranatha_style_selectors( 'heading_font' ); ?> {
	font-family: <?php echo $heading_font_stack; ?>;
}

<?php echo maranatha_style_selectors( 'menu_font' ); ?> {
	font-family: <?php echo $menu_font_stack; ?>;
}

<?php echo maranatha_style_selectors( 'body_font' ); ?> {
	font-family: <?php echo $body_font_stack; ?>;
}

<?php echo maranatha_style_selectors( 'main_color' ); ?> {
	background-color: <?php echo $main_color; ?>;
}

<?php echo maranatha_style_selectors( 'main_color_border' ); ?> {
	border-color: <?php echo $main_color; ?> !important;
}

<?php echo maranatha_style_selectors( 'main_color_text' ); ?> {
	color: <?php echo $main_color; ?> !important;
}

<?php echo maranatha_style_selectors( 'link_color' ); ?> {
	color: <?php echo $link_color; ?>;
}

<?php echo maranatha_style_selectors( 'link_color_border' ); ?> {
	border-color: <?php echo $link_color; ?>;
}

<?php echo maranatha_style_selectors( 'link_color_border_left' ); ?> {
	border-left-color: <?php echo $link_color; ?>;
}

<?php echo maranatha_style_selectors( 'link_color_bg' ); ?> {
	background-color: <?php echo $link_color; ?>;
}
</style>
<?php

}

add_action( 'wp_head', 'maranatha_head_styles' ); // add style to <head>

/**
 * Produce list of selectors for fonts, colors, etc.
 *
 * @since 1.0
 * @param string $group Group of selectors to return
 * @return string CSS selector list
 */
function maranatha_style_selectors( $group ) {

	$selectors = '';

	// Build elements array
	$groups = array(

		// Logo Font
		'logo_font' => array(
			'#maranatha-logo-text'
		),

		// Menu Font
		'menu_font' => array(
			'#maranatha-header-menu-content > li > a', // menu top-level links (dropdowns are body font)
			'.mean-container .mean-nav > ul > li > a', // mobile menu top-level dropdown links (dropdowns are body font)
		),

		// Heading Font
		'heading_font' => array(
			'.maranatha-entry-content h1',
			'.maranatha-entry-content h2:not(.maranatha-entry-short-title)',
			'.maranatha-entry-content h3',
			'.maranatha-entry-content h4',
			'.maranatha-entry-content h5',
			'.maranatha-entry-content h6',
			'.maranatha-entry-content .maranatha-h1',
			'.maranatha-entry-content .maranatha-h2',
			'.maranatha-entry-content .maranatha-h3',
			'.maranatha-entry-content .maranatha-h4',
			'.maranatha-entry-content .maranatha-h5',
			'.maranatha-entry-content .maranatha-h6',
			'.mce-content-body h1',
			'.mce-content-body h2',
			'.mce-content-body h3',
			'.mce-content-body h4',
			'.mce-content-body h5',
			'.mce-content-body h6',
			'.maranatha-home-section-content h1',
			'.maranatha-home-section-content h2',
			'#maranatha-banner-title',
			'.maranatha-widget-title',
			'#maranatha-comments-title',
			'#reply-title',
			'.maranatha-nav-block-title',
			'.maranatha-caption-image-title',
			'.has-drop-cap:not(:focus):first-letter',
		),

		// Body Font
		'body_font' => array(
			'body',
			'#cancel-comment-reply-link',
			'.maranatha-widget .maranatha-entry-short-header h3', // widget heading body font not heading
			'pre.wp-block-verse',
		),

		// Main Color (Background)
		'main_color' => array(
			'.maranatha-color-main-bg',
			'.maranatha-caption-image-title', // CT Highlight, gallery
			'.maranatha-calendar-table-header',
			'.maranatha-calendar-table-top',
			'.maranatha-calendar-table-header-row', // fills gaps in Retina when resizing
			'.has-main-background-color',
			'p.has-main-background-color',
		),

		// Main Color (Border)
		'main_color_border' => array(
			'.maranatha-calendar-table-header',
		),

		// Main Color (Text)
		'main_color_text' => array(
			'.maranatha-color-main-bg .maranatha-circle-buttons-list a:hover',
			'.has-main-color',
			'p.has-main-color',
		),

		// Link Color
		'link_color' => array(
			'a',
			'.maranatha-button',
			'.maranatha-buttons-list a',
			'.maranatha-circle-button span',
			'.maranatha-circle-buttons-list a',
			'input[type=submit]',
			'.maranatha-nav-left-right a',
			'.maranatha-pagination li > *',
			'.widget_tag_cloud a',
			'.sf-menu ul li:hover > a',
			'.sf-menu ul .sfHover > a',
			'.sf-menu ul a:focus',
			'.sf-menu ul a:hover',
			'.sf-menu ul a:active',
			'.mean-container .mean-nav ul li a',
			'#maranatha-header-search-mobile input[type=text]:not(:focus)',
			'#maranatha-map-section-info-list a:hover',
			'.wp-block-pullquote.is-style-solid-color blockquote cite a',
			'.wp-block-pullquote .has-text-color a',
			'.wp-block-file .wp-block-file__button',
			'.wp-block-file a.wp-block-file__button:visited:not(:hover)',
			'.wp-block-file a.wp-block-file__button:focus:not(:hover)',
			'.has-accent-color',
			'p.has-accent-color',
			'.wp-block-calendar #wp-calendar a',
			'.wp-block-pullquote.has-background.has-light-background-color:not(.has-text-color) a',
		),

		// Link Color (Border)
		'link_color_border' => array(
			'.maranatha-button',
			'.maranatha-buttons-list a',
			'.maranatha-circle-button span',
			'.maranatha-circle-buttons-list a',
			'input[type=submit]',
			'.maranatha-nav-left-right a:hover',
			'.maranatha-pagination a:hover',
			'.maranatha-pagination span.current',
			'.widget_tag_cloud a',
			'.mean-container .mean-nav ul li a.mean-expand',
			'#maranatha-header-search-mobile input[type=text]',
			'.wp-block-file__button',
		),

		// Link Color (Border Left)
		'link_color_border_left' => array(
			'.sf-arrows ul .sf-with-ul:after',
		),

		// Link Color (Background)
		'link_color_bg' => array(
			'.maranatha-button:hover',
			'.maranatha-buttons-list a:hover',
			'a.maranatha-circle-button span:hover',
			'.maranatha-circle-buttons-list a:hover',
			'a.maranatha-circle-button-selected span',
			'.maranatha-circle-buttons-list a.maranatha-circle-button-selected',
			'input[type=submit]:hover',
			'.maranatha-nav-left-right a:hover',
			'.maranatha-pagination a:hover',
			'.maranatha-pagination span.current',
			'.widget_tag_cloud a:hover',
			'#maranatha-sermon-download-button a.maranatha-dropdown-open',
			'.wp-block-file__button:hover',
			'.has-accent-background-color',
			'p.has-accent-background-color',
		),

	);

	// Allow filtering
	$groups = apply_filters( 'maranatha_style_selectors', $groups );

	// Build list
	if (! empty( $groups[$group] )) {
		$selectors = implode( ', ', $groups[$group] );
	}

	return $selectors;

}


/*******************************************
 * BLOCK EDITOR CUSTOM STYLES
 *******************************************/

/**
 * Block Editor <head> Styles
 *
 * Output a version of maranatha_head_styles() tailored to Gutenberg.
 *
 * This is called by ctfw_editor_styles() when specified in add_theme_support( 'ctfw-editor-styles' ) css_block_function.
 *
 * @since 1.2
 */
function maranatha_block_editor_head_styles() {

	// Colors
	$main_color = ctfw_customization( 'main_color' );
	$link_color = ctfw_customization( 'link_color' );

	// Fonts
	$logo_font_stack = ctfw_font_stack( ctfw_customization( 'logo_font' ), ctfw_google_fonts( 'logo_font' ) );
	$heading_font_stack = ctfw_font_stack( ctfw_customization( 'heading_font' ), ctfw_google_fonts( 'heading_font' ) );
	$menu_font_stack = ctfw_font_stack( ctfw_customization( 'menu_font' ), ctfw_google_fonts( 'menu_font' ) );
	$body_font_stack = ctfw_font_stack( ctfw_customization( 'body_font' ), ctfw_google_fonts( 'body_font' ) );

?>
<style type="text/css">
<?php echo maranatha_block_editor_style_selectors( 'logo_font' ); ?> {
	font-family: <?php echo $logo_font_stack; ?>;
}

<?php echo maranatha_block_editor_style_selectors( 'heading_font' ); ?> {
	font-family: <?php echo $heading_font_stack; ?> !important;
}

<?php echo maranatha_block_editor_style_selectors( 'menu_font' ); ?> {
	font-family: <?php echo $menu_font_stack; ?> !important;
}

<?php echo maranatha_block_editor_style_selectors( 'body_font' ); ?> {
	font-family: <?php echo $body_font_stack; ?> !important;
}

<?php echo maranatha_block_editor_style_selectors( 'main_color' ); ?> {
	background-color: <?php echo $main_color; ?>;
}

<?php echo maranatha_block_editor_style_selectors( 'main_color_border' ); ?> {
	border-color: <?php echo $main_color; ?> !important;
}

<?php echo maranatha_block_editor_style_selectors( 'main_color_text' ); ?> {
	color: <?php echo $main_color; ?> !important;
}

<?php echo maranatha_block_editor_style_selectors( 'link_color' ); ?> {
	color: <?php echo $link_color; ?>;
}

<?php echo maranatha_block_editor_style_selectors( 'link_color_border' ); ?> {
	border-color: <?php echo $link_color; ?>;
}

<?php echo maranatha_block_editor_style_selectors( 'link_color_border_left' ); ?> {
	border-left-color: <?php echo $link_color; ?>;
}

<?php echo maranatha_block_editor_style_selectors( 'link_color_bg' ); ?> {
	background-color: <?php echo $link_color; ?>;
}
</style>
<?php

}

/**
 * Produce list of selectors for fonts, colors, etc. for Block Editor.
 *
 * This is used by maranatha_block_editor_head_styles() for Gutenberg only.
 *
 * @since 1.0
 * @param string $group Group of selectors to return
 * @return string CSS selector list
 */
function maranatha_block_editor_style_selectors( $group ) {

	$selectors = '';

	// Build elements array
	$groups = array(

		// Menu Font
		'menu_font' => array(

		),

		// Heading Font
		'heading_font' => array(

			// Post Title.
			'.editor-post-title__input',

			// Headings.
			'.edit-post-visual-editor h1.block-editor-rich-text__editable',
			'.edit-post-visual-editor h2.block-editor-rich-text__editable',
			'.edit-post-visual-editor h3.block-editor-rich-text__editable',
			'.edit-post-visual-editor h4.block-editor-rich-text__editable',
			'.edit-post-visual-editor h5.block-editor-rich-text__editable',
			'.edit-post-visual-editor h6.block-editor-rich-text__editable',

			// Classic Block.
			'.wp-block-freeform.block-library-rich-text__tinymce h1',
			'.wp-block-freeform.block-library-rich-text__tinymce h2',
			'.wp-block-freeform.block-library-rich-text__tinymce h3',
			'.wp-block-freeform.block-library-rich-text__tinymce h4',
			'.wp-block-freeform.block-library-rich-text__tinymce h5',
			'.wp-block-freeform.block-library-rich-text__tinymce h6',

			// Dropcap.
			'.edit-post-visual-editor .has-drop-cap:not(:focus):first-letter',

		),

		// Font: Body Text.
		'body_font' => array(

			'.edit-post-visual-editor',
			'.edit-post-visual-editor p',
			'.edit-post-visual-editor .block-editor-default-block-appender input[type=text].block-editor-default-block-appender__content', // body placeholder.
			'.edit-post-visual-editor .wp-block-verse',
			'.edit-post-visual-editor .wp-block-quote .wp-block-quote__citation.block-editor-rich-text__editable',
			'.edit-post-visual-editor .block-library-list block-editor-rich-text__editable',
			'.wp-block-freeform.block-library-rich-text__tinymce',
			'.edit-post-visual-editor .wp-block-freeform ol', // Classic Editor
			'.edit-post-visual-editor .wp-block-freeform ul', // Classic Editor
			'.edit-post-visual-editor .editor-block-list__block-edit',
			'.edit-post-visual-editor .wp-block-rss',
			'.edit-post-visual-editor .wp-block-pullquote__citation.block-editor-rich-text__editable',
			'.edit-post-visual-editor .wp-block-pullquote cite',
			'.wp-block-file__button',
			'.wp-block-file__textlink .block-editor-rich-text__editable',
			'.wp-block-file__content-wrapper a.block-editor-rich-text__editable',

			'.wp-block-latest-posts',
			'.wp-block-categories',
			'.wp-block-archives',
			'.wp-block-latest-comments',
			'.wp-block-rss',
			'.wp-block-calendar',
			'.wp-block-tag-cloud',
			'.wp-block-page-list',

		),

		// Main Color (Background).
		'main_color' => array(
			'.edit-post-visual-editor__post-title-wrapper',
		),

		// Main Color (Border)
		'main_color_border' => array(

		),

		// Main Color (Text)
		'main_color_text' => array(
			'.edit-post-visual-editor .wp-block-button .wp-block-button__link',
			'.maranatha-button',
			'.wp-block-file__button',
		),

		// Link Color
		'link_color' => array(

			// Entry Content Links.
			'.wp-block-freeform.block-library-rich-text__tinymce a',
			'.block-editor-rich-text__editable a:not(.blocks-format-toolbar__link-value)',
			'.wp-block-file__textlink .block-editor-rich-text__editable',
			'.wp-block-file__content-wrapper a.block-editor-rich-text__editable',
			'.edit-post-visual-editor .wp-block-pullquote.is-style-solid-color blockquote .block-editor-rich-text__editable a',

			'.wp-block-latest-posts a',
			'.wp-block-categories a',
			'.wp-block-archives a',
			'.wp-block-latest-comments a',
			'.wp-block-rss a',
			'.wp-block-calendar a',
			'.wp-block-tag-cloud a',
			'.wp-block-page-list a',

		),

		// Link Color (Border)
		'link_color_border' => array(
			'.edit-post-visual-editor .wp-block-button .wp-block-button__link',
			'.edit-post-visual-editor .maranatha-button',
			'.edit-post-visual-editor .wp-block-file__button',
		),

		// Link Color (Border Left)
		'link_color_border_left' => array(

		),

		// Link Color (Background)
		'link_color_bg' => array(
			'.edit-post-visual-editor .wp-block-button .wp-block-button__link:hover',
			'.edit-post-visual-editor .maranatha-button:hover',
			'.edit-post-visual-editor .wp-block-file__button:hover',
		),

	);

	// Allow filtering.
	$groups = apply_filters( 'maranatha_block_editor_style_selectors', $groups );

	// Build list.
	if (! empty( $groups[ $group ] )) {
		$selectors = implode( ', ', $groups[ $group ] );
	}

	return $selectors;

}

/******************************************
 * JAVASCRIPT DETECTION
 ******************************************/

/**
 * Remove no-js and add js class to <html>
 *
 * Do this directly in <head> to it happens immediately (no wait for JS file to load or document ready)
 * This helps eliminate "flicker" effects in CSS due to a delay in classes being applied
 *
 * To Do: This could be made into a framework feature.
 *
 * @since 1.0
 */
function maranatha_head_js_classes() {

?>
<script type="text/javascript">

jQuery( 'html' )
 	.removeClass( 'no-js' )
 	.addClass( 'js' );

</script>
<?php

}

add_action( 'wp_head', 'maranatha_head_js_classes' );
