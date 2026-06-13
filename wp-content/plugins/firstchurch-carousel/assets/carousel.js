/*
 * Live carousel player. Renders the resolved feed (window.FCCAR.items) via the
 * shared card renderer (card-render.mjs), scales a fixed 1280×720 stage to the
 * viewport, fades through black from card to card, and silently re-pulls the
 * feed for freshness. Card layouts + QR live in the shared renderer so the curation
 * screen's thumbnails render identically. See ops/docs/carousel-source-of-truth.md.
 */
import { buildStageEl } from '@fccar/stage';

( function () {
	'use strict';

	var FCCAR = window.FCCAR || { items: [], seconds: 7, variant: 'preservice' };
	var deck  = document.getElementById( 'fccar-deck' );
	var empty = document.getElementById( 'fccar-empty' );

	var state = { items: [], idx: 0, timer: null, sig: '' };

	// --- deck + loop ---------------------------------------------------------
	function render( items ) {
		state.items = items || [];
		deck.innerHTML = '';
		empty.hidden = state.items.length > 0;
		state.items.forEach( function ( it ) {
			deck.appendChild( buildStageEl( it, { campaign: FCCAR.campaign } ) );
		} );
		state.idx = 0;
		show( 0 );
	}

	function show( i ) {
		var stages = deck.children;
		for ( var k = 0; k < stages.length; k++ ) {
			stages[ k ].classList.toggle( 'is-active', k === i );
		}
	}

	function advance() {
		if ( state.items.length < 2 ) { return; }
		var stages = deck.children;
		var cur = stages[ state.idx ];
		var next = ( state.idx + 1 ) % state.items.length;
		state.idx = next;
		// Fade the current card out, then fade the next one in once it's gone —
		// never two cards at half-opacity with overlapping text.
		if ( cur ) { cur.classList.remove( 'is-active' ); }
		setTimeout( function () {
			if ( stages[ next ] ) { stages[ next ].classList.add( 'is-active' ); }
		}, 480 );
	}

	function startLoop() {
		clearInterval( state.timer );
		state.timer = setInterval( advance, Math.max( 3, FCCAR.seconds ) * 1000 );
	}

	// --- scale to viewport ---------------------------------------------------
	function scale() {
		var s = Math.min( window.innerWidth / 1280, window.innerHeight / 720 );
		deck.style.setProperty( '--fccar-scale', s );
	}

	// --- freshness -----------------------------------------------------------
	function sigOf( items ) { return items.map( function ( x ) { return x.id; } ).join( ',' ); }

	function refresh() {
		var url = FCCAR.restUrl + '?variant=' + encodeURIComponent( FCCAR.variant );
		fetch( url, { cache: 'no-store' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( d ) {
				if ( ! d || ! Array.isArray( d.items ) ) { return; }
				var sig = sigOf( d.items );
				if ( sig !== state.sig ) { state.sig = sig; render( d.items ); startLoop(); }
			} )
			.catch( function () { /* keep playing the current deck on a fetch error */ } );
	}

	// --- boot ----------------------------------------------------------------
	scale();
	window.addEventListener( 'resize', scale );
	state.sig = sigOf( FCCAR.items || [] );
	render( FCCAR.items || [] );
	startLoop();
	if ( FCCAR.refreshMs ) { setInterval( refresh, FCCAR.refreshMs ); }
}() );
