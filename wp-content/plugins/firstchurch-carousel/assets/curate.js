/* Carousel curation screen. Renders two lists from the localized view model
 * (window.FCCAR): the ordered deck and the available candidates. Drag reorders,
 * add/remove move items between the lists, and per-entry overrides (title, when,
 * background, preservice-only) edit the model in place. Save POSTs the ordered
 * references + overrides to the deck endpoint. Plain jQuery + jquery-ui-sortable
 * + wp.media — no build step. */
( function ( $ ) {
	'use strict';

	var D = window.FCCAR || { deck: [], available: [], restUrl: '', nonce: '' };
	var $deck = $( '#fccar-deck' );
	var $avail = $( '#fccar-available' );
	var $status = $( '#fccar-status' );
	var $count = $( '#fccar-deck-count' );

	function esc( s ) {
		return $( '<div/>' ).text( s == null ? '' : String( s ) ).html();
	}
	function attr( s ) {
		return esc( s ).replace( /"/g, '&quot;' );
	}
	function entryById( id ) {
		for ( var i = 0; i < D.deck.length; i++ ) {
			if ( D.deck[ i ].id === id ) {
				return D.deck[ i ];
			}
		}
		return null;
	}
	function badges( row ) {
		return (
			'<span class="fccar-badge fccar-badge--' + esc( row.source ) + '">' + esc( row.source ) + '</span>' +
			'<span class="fccar-badge fccar-badge--layout">' + esc( row.layout ) + '</span>'
		);
	}

	function deckRow( e ) {
		var title = e.title || e.srcTitle;
		var when = e.when || e.srcWhen;
		var bg = e.image || e.srcImage;
		return $(
			'<li class="fccar-item" data-id="' + attr( e.id ) + '">' +
				'<span class="fccar-handle" title="Drag to reorder">⋮⋮</span>' +
				'<div class="fccar-main">' +
					'<div class="fccar-head">' + badges( e ) +
						'<strong class="fccar-title">' + esc( title ) + '</strong>' +
						'<button type="button" class="fccar-remove" title="Remove from deck">✕</button>' +
					'</div>' +
					'<div class="fccar-meta">' + esc( when ) + '</div>' +
					'<details class="fccar-overrides">' +
						'<summary>Overrides</summary>' +
						'<label>Title <input type="text" class="fccar-f-title" value="' + attr( e.title ) + '" placeholder="' + attr( e.srcTitle ) + '"></label>' +
						'<label>When <input type="text" class="fccar-f-when" value="' + attr( e.when ) + '" placeholder="' + attr( e.srcWhen || '—' ) + '"></label>' +
						'<label class="fccar-bg-row">Background ' +
							'<button type="button" class="button fccar-bg">Choose…</button> ' +
							'<button type="button" class="button-link fccar-bg-clear"' + ( e.image ? '' : ' style="display:none"' ) + '>clear</button>' +
							'<span class="fccar-bg-url">' + esc( bg ) + '</span>' +
						'</label>' +
						'<label class="fccar-presvc"><input type="checkbox" class="fccar-f-presvc"' + ( e.preserviceOnly ? ' checked' : '' ) + '> Preservice-only</label>' +
					'</details>' +
				'</div>' +
			'</li>'
		);
	}

	function availRow( r ) {
		return $(
			'<li class="fccar-item fccar-item--avail" data-id="' + attr( r.id ) + '">' +
				'<div class="fccar-main">' +
					'<div class="fccar-head">' + badges( r ) +
						'<strong class="fccar-title">' + esc( r.srcTitle ) + '</strong>' +
						'<button type="button" class="button fccar-add">+ Add</button>' +
					'</div>' +
					'<div class="fccar-meta">' + esc( r.srcWhen ) + '</div>' +
				'</div>' +
			'</li>'
		);
	}

	function renderDeck() {
		$deck.empty();
		D.deck.forEach( function ( e ) { $deck.append( deckRow( e ) ); } );
		$count.text( '(' + D.deck.length + ')' );
	}
	function renderAvail() {
		$avail.empty();
		D.available.forEach( function ( r ) { $avail.append( availRow( r ) ); } );
	}

	/* ---- ordering ---- */
	$deck.sortable( {
		handle: '.fccar-handle',
		placeholder: 'fccar-placeholder',
		stop: function () {
			var order = $deck.children( '.fccar-item' ).map( function () { return $( this ).data( 'id' ); } ).get();
			D.deck.sort( function ( a, b ) { return order.indexOf( a.id ) - order.indexOf( b.id ); } );
		}
	} );

	/* ---- add / remove ---- */
	$avail.on( 'click', '.fccar-add', function () {
		var id = $( this ).closest( '.fccar-item' ).data( 'id' );
		var i = D.available.findIndex( function ( r ) { return r.id === id; } );
		if ( i < 0 ) { return; }
		var r = D.available.splice( i, 1 )[ 0 ];
		D.deck.push( { id: r.id, source: r.source, layout: r.layout, srcTitle: r.srcTitle, srcWhen: r.srcWhen, srcImage: r.srcImage, title: '', when: '', image: '', preserviceOnly: !!r.preserviceOnly } );
		renderDeck();
		renderAvail();
	} );

	$deck.on( 'click', '.fccar-remove', function () {
		var id = $( this ).closest( '.fccar-item' ).data( 'id' );
		var i = D.deck.findIndex( function ( e ) { return e.id === id; } );
		if ( i < 0 ) { return; }
		var e = D.deck.splice( i, 1 )[ 0 ];
		D.available.unshift( { id: e.id, source: e.source, layout: e.layout, srcTitle: e.srcTitle, srcWhen: e.srcWhen, srcImage: e.srcImage, preserviceOnly: !!e.preserviceOnly } );
		renderDeck();
		renderAvail();
	} );

	/* ---- per-entry overrides (edit model in place; no re-render) ---- */
	$deck.on( 'input', '.fccar-f-title', function () {
		var e = entryById( $( this ).closest( '.fccar-item' ).data( 'id' ) );
		if ( e ) { e.title = this.value; $( this ).closest( '.fccar-item' ).find( '.fccar-title' ).text( e.title || e.srcTitle ); }
	} );
	$deck.on( 'input', '.fccar-f-when', function () {
		var e = entryById( $( this ).closest( '.fccar-item' ).data( 'id' ) );
		if ( e ) { e.when = this.value; $( this ).closest( '.fccar-item' ).find( '.fccar-meta' ).text( e.when || e.srcWhen ); }
	} );
	$deck.on( 'change', '.fccar-f-presvc', function () {
		var e = entryById( $( this ).closest( '.fccar-item' ).data( 'id' ) );
		if ( e ) { e.preserviceOnly = this.checked; }
	} );
	$deck.on( 'click', '.fccar-bg', function () {
		var $item = $( this ).closest( '.fccar-item' );
		var e = entryById( $item.data( 'id' ) );
		if ( ! e || ! window.wp || ! window.wp.media ) { return; }
		var frame = window.wp.media( { title: 'Background image', multiple: false, library: { type: 'image' } } );
		frame.on( 'select', function () {
			var a = frame.state().get( 'selection' ).first().toJSON();
			e.image = a.url;
			$item.find( '.fccar-bg-url' ).text( a.url );
			$item.find( '.fccar-bg-clear' ).show();
		} );
		frame.open();
	} );
	$deck.on( 'click', '.fccar-bg-clear', function () {
		var $item = $( this ).closest( '.fccar-item' );
		var e = entryById( $item.data( 'id' ) );
		if ( e ) { e.image = ''; $item.find( '.fccar-bg-url' ).text( e.srcImage || '' ); $( this ).hide(); }
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
} )( jQuery );
