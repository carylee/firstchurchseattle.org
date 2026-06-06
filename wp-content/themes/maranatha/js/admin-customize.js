/**
 * Theme Customizer Controls
 *
 * For conditional display of fields, polling every half-second instead of on( 'click' ), on( 'change' ), etc.
 * to make sure state is always correct. This makes sure initial state is always correct too.
 */

jQuery( document ).ready( function( $ ) {

	/***************************************
	 * DETECT CHANGES
	 ***************************************/

	// Continuously check controls for changes
	// .on( 'change' ) cannot help with changes to non-form elements, such as images
	$.doTimeout( 500, function() {

		var bottom_left_sticky, $header_items, $custom_content, logo_type, $logo_hidpi_control, $logo_image, $logo_hidpi, $logo_text, $logo_text_size;

		/**************************************
		 * FOOTER
		 **************************************/

		// "Bottom Left Sticky" has changed
		bottom_left_sticky = $( "input[data-customize-setting-link^='" + maranatha_customize.option_id + "[bottom_left_sticky]']:radio:checked" ).val();

			// Show/hide header right event/sermon/posts limit field if selected
			$header_items = $( '#customize-control-' + maranatha_customize.option_id + '-bottom_left_sticky_items_limit' );
			if ( 'sermons' == bottom_left_sticky || 'events' == bottom_left_sticky || 'posts' == bottom_left_sticky ) {
				$header_items.show();
			} else {
				$header_items.hide();
			}

			// Show/hide "Custom Content" textarea if selected
			$custom_content = $( '#customize-control-' + maranatha_customize.option_id + '-bottom_left_sticky_content' );
			if ( bottom_left_sticky == 'content' ) {
				$custom_content.show();
			} else {
				$custom_content.hide();
			}

		/**************************************
		 * LOGO
		 **************************************/

		// Logo type
		logo_type = $( "input[data-customize-setting-link^='" + maranatha_customize.option_id + "[logo_type]']:radio:checked" ).val();

		// Logo controls
		$logo_hidpi_control = $( '#customize-control-' + maranatha_customize.option_id + '-logo_hidpi' );

		// Show/hide "Logo Image"
		$logo_image = $( '#customize-control-' + maranatha_customize.option_id + '-logo_image' );
		$logo_hidpi = $( '#customize-control-' + maranatha_customize.option_id + '-logo_hidpi' );
		if ( 'image' == logo_type ) {

			$logo_image.show();

			// Show HiDPI Logo control only while Logo uploaded ( and not using Text logo )
			if ( $( "#customize-control-" + maranatha_customize.option_id + "-logo_image .thumbnail-image .attachment-thumb" ).length ) {
				$logo_hidpi_control.show();
			} else {
				$logo_hidpi_control.hide();
			}

		} else {
			$logo_image.hide();
			$logo_hidpi_control.hide();
		}

		// Show/hide "Logo Text" and "Logo Text Size"
		$logo_text = $( '#customize-control-' + maranatha_customize.option_id + '-logo_text' );
		$logo_text_size = $( '#customize-control-' + maranatha_customize.option_id + '-logo_text_size' );
		if ( logo_type == 'text' ) {
			$logo_text.show();
			$logo_text_size.show();
		} else {
			$logo_text.hide();
			$logo_text_size.hide();
		}

		/**************************************/

		// Keep checking for logo changes
		return true;

	} );

} );
