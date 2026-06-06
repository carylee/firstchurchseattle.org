/**
 * Theme Customizer Live Preview
 */

jQuery( document ).ready( function( $ ) {

	/***************************************
	 * COLORS
	 ***************************************/

	// Main Color
	wp.customize( maranatha_customize_preview.option_id + '[main_color]', function( value ) {

		value.bind( function( to ) {

			var background_selectors, border_selectors, text_selectors;

			background_selectors = maranatha_customize_preview[ 'main_color_selectors' ];
			border_selectors = maranatha_customize_preview[ 'main_color_border_selectors' ];
			text_selectors = maranatha_customize_preview[ 'main_color_text_selectors' ];

			// Appending <style> to head with !important produces better results than $element.css()
			$( 'head' ).append( '<style type="text/css">' + background_selectors + ' { background-color: ' + to + ' !important; }</style>' );
			$( 'head' ).append( '<style type="text/css">' + border_selectors + ' { border-color: ' + to + ' !important; }</style>' );
			$( 'head' ).append( '<style type="text/css">' + text_selectors + ' { color: ' + to + ' !important; }</style>' );

		} );

	} );

	// Link Color
	wp.customize( maranatha_customize_preview.option_id + '[link_color]', function( value ) {

		value.bind( function( to ) {

			var text_selectors, border_selectors, border_left_selectors, bg_selectors;

			text_selectors = maranatha_customize_preview[ 'link_color_selectors' ];
			border_selectors = maranatha_customize_preview[ 'link_color_border_selectors' ];
			border_left_selectors = maranatha_customize_preview[ 'link_color_border_left_selectors' ];
			bg_selectors = maranatha_customize_preview[ 'link_color_bg_selectors' ];

			// Using second method to prevent all link elements from being color (menu items, logo, etc.)
			//$( selectors ).css( 'color', to );
			$( 'head' ).append( '<style type="text/css">' + text_selectors + ' { color: ' + to + '; }</style>' );
			$( 'head' ).append( '<style type="text/css">' + border_selectors + ' { border-color: ' + to + '; }</style>' );
			$( 'head' ).append( '<style type="text/css">' + border_left_selectors + ' { border-left-color: ' + to + '; }</style>' );
			$( 'head' ).append( '<style type="text/css">' + bg_selectors + ' { background-color: ' + to + '; }</style>' );

		} );

	} );

	/***************************************
	 * FONTS (GOOGLE FONTS)
	 ***************************************/

	// Change Fonts ( Menu, Heading, Body )
	$.each( [ 'logo_font', 'menu_font', 'heading_font', 'body_font' ], function( index, setting ) {

		wp.customize( maranatha_customize_preview.option_id + '[' + setting + ']', function( value ) {

			value.bind( function( to ) {

				var selectors, font;

				font = to;

				// Change font
				selectors = maranatha_customize_preview[setting + '_selectors'];
				maranatha_customize_preview_font( selectors, font );

				// Change <body> class helper (tells which font used for which set of elements)
				maranatha_update_body_font_class( setting, font ); // main.js

			} );

		} );

	} );

	/***************************************
	 * LOGO
	 ***************************************/

	// Logo Text
	wp.customize( maranatha_customize_preview.option_id + '[logo_text]', function( value ) {

		value.bind( function( to ) {
			$( '#maranatha-logo-text-inner a' ).text( to.replace( /<\/?\w+[^>]*\/?>/g, '' ) ); // strips HTML (no bold, italic, etc.)
		} );

	} );

	// Logo Text Size
	wp.customize( maranatha_customize_preview.option_id + '[logo_text_size]', function( value ) {

		value.bind( function( to ) {

			$( '#maranatha-logo-text' )
				.removeClass() // remove all classes
				.addClass( 'maranatha-logo-text-' + to );

		} );

	} );

	/***************************************
	 * MENU
	 ***************************************/

	// Re-activate dropdowns after Menu Customizer does "partial refresh" / "fast refresh"
	// https://make.wordpress.org/core/tag/menu-customizer/
	$( document ).on( 'customize-preview-menu-refreshed', function( e, params ) {

		if ( 'header' === params.wpNavMenuArgs.theme_location ) {
			maranatha_activate_menu();
		}

	} );

} );

/***************************************
 * FUNCTIONS
 ***************************************/

/**
 * Apply Font Change
 */
function maranatha_customize_preview_font( selectors, font ) {

	var family, styles, subsets, families;

	if ( selectors && font ) {

		// Prepare data
		family = font.replace( /\s/g, '+' ); // spaces to +
		styles = maranatha_customize_preview.fonts[font].sizes;
		subsets = window.parent.jQuery( 'input[data-customize-setting-link="' + maranatha_customize_preview.option_id + '[font_subsets]"]' ).val().replace( /\s/g, '' ); // remove spaces
		families = [family + ':' + styles + ':' + subsets];

		// Load font
		WebFont.load( {
			google: {
				families: families
			},
			active: function() {

				// Apply font
				jQuery( selectors ).css( 'font-family', "'" + font + "'" );

				// Reactivate menu ( sizing )
				maranatha_activate_menu();

			}
		} );

	}

}
