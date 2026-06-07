/* Carousel curation screen — a WYSIWYG deck editor. Each card is a real,
 * scaled-down render of the live carousel card (via the shared FCCarCard
 * renderer), laid out in a wrapping grid. Drag a thumbnail to reorder, click it
 * to add/remove, and open its editor to override title/when/background/
 * preservice — the thumbnail re-renders live as you type. Save POSTs the ordered
 * references + overrides to the deck endpoint. Plain jQuery + jquery-ui-sortable
 * + wp.media — no build step. */
( function ( $ ) {
	'use strict';

	var D = window.FCCAR || { deck: [], available: [], restUrl: '', nonce: '' };
	var Card = window.FCCarCard;
	var $deck = $( '#fccar-deck' );
	var $avail = $( '#fccar-available' );
	var $status = $( '#fccar-status' );
	var $count = $( '#fccar-deck-count' );

	function esc( s ) { return $( '<div/>' ).text( s == null ? '' : String( s ) ).html(); }
	function attr( s ) { return esc( s ).replace( /"/g, '&quot;' ); }

	function entryById( id ) {
		for ( var i = 0; i < D.deck.length; i++ ) {
			if ( D.deck[ i ].id === id ) { return D.deck[ i ]; }
		}
		return null;
	}

	/** The effective card (source values + overrides) the thumbnail renders. */
	function effItem( e ) {
		return {
			id: e.id, source: e.source, layout: e.layout,
			title: e.title || e.srcTitle,
			when: e.when || e.srcWhen,
			image: e.image || e.srcImage,
			body: e.body, prompt: e.prompt, details: e.details,
			ctaUrl: e.ctaUrl, backgroundColor: e.backgroundColor,
			preserviceOnly: e.preserviceOnly
		};
	}

	/** Render the scaled card preview into a tile's .fccar-thumb. */
	function paintThumb( $tile, e ) {
		var $thumb = $tile.find( '.fccar-thumb' );
		$thumb.empty();
		var stage = Card.buildStage( effItem( e ), {} );
		$thumb.append( stage );
		var w = $thumb.width() || 240;
		stage.style.transform = 'scale(' + ( w / 1280 ) + ')';
	}

	function badge( e ) {
		return '<span class="fccar-badge fccar-badge--' + esc( e.source ) + '">' + esc( e.layout ) + '</span>';
	}

	function deckTile( e ) {
		var presvc = e.preserviceOnly ? '<span class="fccar-badge fccar-badge--pre" title="Preservice-only">PRE</span>' : '';
		return $(
			'<li class="fccar-tile" data-id="' + attr( e.id ) + '">' +
				'<div class="fccar-thumb" title="Drag to reorder"></div>' +
				'<div class="fccar-tile-bar">' +
					badge( e ) + presvc +
					'<span class="fccar-tname">' + esc( e.title || e.srcTitle ) + '</span>' +
					'<button type="button" class="fccar-edit" title="Edit overrides">✎</button>' +
					'<button type="button" class="fccar-remove" title="Remove from deck">✕</button>' +
				'</div>' +
				'<div class="fccar-editor" hidden>' +
					'<label class="fccar-field"><span>Title</span>' +
						'<input type="text" class="fccar-f-title" value="' + attr( e.title ) + '" placeholder="' + attr( e.srcTitle ) + '"></label>' +
					'<label class="fccar-field"><span>When</span>' +
						'<input type="text" class="fccar-f-when" value="' + attr( e.when ) + '" placeholder="' + attr( e.srcWhen || '—' ) + '"></label>' +
					'<div class="fccar-field"><span>Background</span>' +
						'<span class="fccar-bg-buttons">' +
							'<button type="button" class="button button-small fccar-bg">' + ( ( e.image || e.srcImage ) ? 'Replace…' : 'Choose…' ) + '</button> ' +
							'<button type="button" class="button-link fccar-bg-clear"' + ( e.image ? '' : ' style="display:none"' ) + '>Clear</button>' +
						'</span></div>' +
					'<label class="fccar-presvc"><input type="checkbox" class="fccar-f-presvc"' + ( e.preserviceOnly ? ' checked' : '' ) + '> Preservice-only</label>' +
				'</div>' +
			'</li>'
		);
	}

	function availTile( r ) {
		return $(
			'<li class="fccar-tile fccar-tile--avail" data-id="' + attr( r.id ) + '">' +
				'<div class="fccar-thumb" title="Add to deck"></div>' +
				'<div class="fccar-tile-bar">' +
					badge( r ) +
					'<span class="fccar-tname">' + esc( r.srcTitle ) + '</span>' +
					'<button type="button" class="button button-small fccar-add">+ Add</button>' +
				'</div>' +
			'</li>'
		);
	}

	function renderDeck() {
		$deck.empty();
		D.deck.forEach( function ( e ) {
			var $t = deckTile( e );
			$deck.append( $t );
			paintThumb( $t, e );
		} );
		$count.text( '(' + D.deck.length + ')' );
	}
	function renderAvail() {
		$avail.empty();
		D.available.forEach( function ( r ) {
			var $t = availTile( r );
			$avail.append( $t );
			paintThumb( $t, r );
		} );
	}

	/* ---- ordering ---- */
	$deck.sortable( {
		items: '> .fccar-tile',
		placeholder: 'fccar-placeholder',
		forcePlaceholderSize: true,
		tolerance: 'pointer',
		cancel: 'input,textarea,button,a,.fccar-editor',
		stop: function () {
			var order = $deck.children( '.fccar-tile' ).map( function () { return String( $( this ).data( 'id' ) ); } ).get();
			D.deck.sort( function ( a, b ) { return order.indexOf( a.id ) - order.indexOf( b.id ); } );
		}
	} );

	/* ---- add / remove ---- */
	$avail.on( 'click', '.fccar-add, .fccar-thumb', function () {
		var id = String( $( this ).closest( '.fccar-tile' ).data( 'id' ) );
		var i = D.available.findIndex( function ( r ) { return r.id === id; } );
		if ( i < 0 ) { return; }
		var r = D.available.splice( i, 1 )[ 0 ];
		D.deck.push( $.extend( {}, r, { title: '', when: '', image: '', preserviceOnly: !!r.preserviceOnly } ) );
		renderDeck();
		renderAvail();
	} );

	$deck.on( 'click', '.fccar-remove', function () {
		var id = String( $( this ).closest( '.fccar-tile' ).data( 'id' ) );
		var i = D.deck.findIndex( function ( e ) { return e.id === id; } );
		if ( i < 0 ) { return; }
		var e = D.deck.splice( i, 1 )[ 0 ];
		D.available.unshift( $.extend( {}, e, { title: '', when: '', image: '' } ) );
		renderDeck();
		renderAvail();
	} );

	/* ---- open / close the per-tile editor ---- */
	$deck.on( 'click', '.fccar-edit', function () {
		var $ed = $( this ).closest( '.fccar-tile' ).find( '.fccar-editor' );
		$ed.prop( 'hidden', ! $ed.prop( 'hidden' ) );
	} );

	/* ---- per-entry overrides: edit the model + repaint the thumb live ---- */
	function liveUpdate( el, apply ) {
		var $tile = $( el ).closest( '.fccar-tile' );
		var e = entryById( String( $tile.data( 'id' ) ) );
		if ( ! e ) { return; }
		apply( e, $tile );
		$tile.find( '.fccar-tname' ).text( e.title || e.srcTitle );
		paintThumb( $tile, e );
	}
	$deck.on( 'input', '.fccar-f-title', function () { liveUpdate( this, function ( e ) { e.title = this.value; }.bind( this ) ); } );
	$deck.on( 'input', '.fccar-f-when', function () { liveUpdate( this, function ( e ) { e.when = this.value; }.bind( this ) ); } );
	$deck.on( 'change', '.fccar-f-presvc', function () {
		var checked = this.checked;
		liveUpdate( this, function ( e, $tile ) {
			e.preserviceOnly = checked;
			$tile.find( '.fccar-badge--pre' ).remove();
			if ( checked ) { $tile.find( '.fccar-tile-bar .fccar-badge' ).first().after( '<span class="fccar-badge fccar-badge--pre" title="Preservice-only">PRE</span>' ); }
		} );
	} );

	$deck.on( 'click', '.fccar-bg', function () {
		var $tile = $( this ).closest( '.fccar-tile' );
		var e = entryById( String( $tile.data( 'id' ) ) );
		if ( ! e || ! window.wp || ! window.wp.media ) { return; }
		var frame = window.wp.media( { title: 'Background image', multiple: false, library: { type: 'image' } } );
		frame.on( 'select', function () {
			var a = frame.state().get( 'selection' ).first().toJSON();
			e.image = a.url;
			$tile.find( '.fccar-bg' ).text( 'Replace…' );
			$tile.find( '.fccar-bg-clear' ).show();
			paintThumb( $tile, e );
		} );
		frame.open();
	} );
	$deck.on( 'click', '.fccar-bg-clear', function () {
		var $tile = $( this ).closest( '.fccar-tile' );
		var e = entryById( String( $tile.data( 'id' ) ) );
		if ( ! e ) { return; }
		e.image = '';
		$( this ).hide();
		$tile.find( '.fccar-bg' ).text( e.srcImage ? 'Replace…' : 'Choose…' );
		paintThumb( $tile, e );
	} );

	/* ---- save / reset ---- */
	function post( body, done ) {
		$status.removeClass( 'is-error is-ok' ).text( 'Saving…' );
		fetch( D.restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': D.nonce },
			body: JSON.stringify( body )
		} ).then( function ( r ) { return r.ok ? r.json() : Promise.reject( r ); } )
			.then( function ( j ) { done( j ); } )
			.catch( function () { $status.addClass( 'is-error' ).text( 'Save failed.' ); } );
	}

	$( '#fccar-save' ).on( 'click', function () {
		var deck = D.deck.map( function ( e ) {
			return { id: e.id, title: e.title || '', when: e.when || '', image: e.image || '', preserviceOnly: !!e.preserviceOnly };
		} );
		post( { deck: deck }, function ( j ) {
			$status.addClass( 'is-ok' ).text( 'Saved ' + ( j.count != null ? j.count : deck.length ) + ' cards.' );
		} );
	} );

	$( '#fccar-reset' ).on( 'click', function () {
		if ( ! window.confirm( 'Discard the curated deck and revert to the auto-assembled default?' ) ) { return; }
		post( { reset: true }, function () { window.location.reload(); } );
	} );

	renderDeck();
	renderAvail();
}( jQuery ) );
