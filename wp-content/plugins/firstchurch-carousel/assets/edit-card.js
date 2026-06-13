/* Carousel card edit screen: a live preview that re-renders the card (via the
 * shared renderer) as you change the title, layout, body/prompt/details, QR
 * link, background color, or featured image — and shows only the fields the
 * chosen layout actually uses. WYSIWYG authoring for the standing cards. */
import { buildStageEl } from '@fccar/stage';

( function ( $ ) {
	'use strict';

	var $thumb = $( '#fccar-edit-thumb' );
	if ( ! $thumb.length ) { return; }

	function val( id ) { var el = document.getElementById( id ); return el ? el.value : ''; }

	/** The current featured image URL (the carousel background), read from the
	 *  WP featured-image box which WP rewrites via AJAX when it changes. */
	function featuredUrl() {
		var img = document.querySelector( '#postimagediv #set-post-thumbnail img' );
		return img ? img.src : '';
	}

	function buildItem() {
		var layout = val( 'fccar_layout' ) || 'info';
		return {
			id: 'edit', source: 'card', layout: layout,
			title: $( '#title' ).val() || '',
			body: val( 'fccar_body' ),
			prompt: val( 'fccar_prompt' ),
			details: val( 'fccar_details' ),
			ctaUrl: val( 'fccar_qr_url' ),
			backgroundColor: val( 'fccar_bg_color' ),
			image: featuredUrl(),
			when: '',
			preserviceOnly: $( '#fccar_preservice' ).is( ':checked' )
		};
	}

	function paint() {
		$thumb.empty();
		var stage = buildStageEl( buildItem(), {} );
		$thumb.append( stage );
		var w = $thumb.width() || 480;
		stage.style.transform = 'scale(' + ( w / 1280 ) + ')';
	}

	/** Show only the fields the selected layout uses (data-layouts on each row). */
	function applyLayout() {
		var l = val( 'fccar_layout' ) || 'info';
		$( '.fccar-fieldrow[data-layouts]' ).each( function () {
			var uses = String( $( this ).data( 'layouts' ) ).split( ' ' );
			$( this ).toggle( uses.indexOf( l ) !== -1 );
		} );
	}

	$( document ).on( 'input change',
		'#title, #fccar_body, #fccar_prompt, #fccar_details, #fccar_qr_url, #fccar_bg_color, #fccar_layout, #fccar_preservice',
		paint );
	$( document ).on( 'change', '#fccar_layout', applyLayout );

	// The featured-image box swaps its markup via AJAX on set/remove — observe it.
	var pdiv = document.getElementById( 'postimagediv' );
	if ( pdiv && window.MutationObserver ) {
		new MutationObserver( paint ).observe( pdiv, { childList: true, subtree: true } );
	}

	applyLayout();
	paint();
}( jQuery ) );
