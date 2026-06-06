/*
 * Live carousel player. Renders the resolved feed (window.FCCAR.items) as the
 * six card layouts, scales a fixed 1280×720 stage to the viewport, crossfades
 * through the deck on a timer, and silently re-pulls the feed for freshness.
 *
 * The browser is the renderer here — the same HTML/CSS the slides app rasterizes
 * into GIF frames, played live instead. QR codes are generated client-side with
 * the vendored qrcode-generator (assets/vendor/qrcode-generator.js).
 *
 * "Close enough" by intent: this approximates the slides card look rather than
 * reproducing its baked font-size auto-fit. See ops/docs/carousel-source-of-truth.md.
 */
( function () {
	'use strict';

	var FCCAR = window.FCCAR || { items: [], seconds: 7, variant: 'preservice' };
	var deck  = document.getElementById( 'fccar-deck' );
	var empty = document.getElementById( 'fccar-empty' );
	var hasQR = ( typeof window.qrcode !== 'undefined' );

	var state = { items: [], idx: 0, timer: null, sig: '' };

	// --- text helpers --------------------------------------------------------
	function esc( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	}
	function attr( s ) {
		return String( s == null ? '' : s ).replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' );
	}
	function nl2br( s ) { return esc( s ).replace( /\n/g, '<br>' ); }

	/** Body text → bullet/paragraph divs (info cards carry "- " bullets). */
	function bodyHtml( body ) {
		return String( body || '' ).split( '\n' ).map( function ( line ) {
			var t = line.trim();
			if ( ! t ) { return ''; }
			return t.indexOf( '- ' ) === 0
				? '<div class="ann-bullet">' + esc( t.slice( 2 ) ) + '</div>'
				: '<div>' + esc( t ) + '</div>';
		} ).join( '' );
	}

	// --- QR ------------------------------------------------------------------
	/** Append slides-style UTMs without clobbering any the URL already carries. */
	function withUtm( url, campaign ) {
		try {
			var u = new URL( url, window.location.origin );
			if ( ! u.searchParams.has( 'utm_source' ) ) { u.searchParams.set( 'utm_source', 'carousel' ); }
			if ( ! u.searchParams.has( 'utm_medium' ) ) { u.searchParams.set( 'utm_medium', 'screen_qr' ); }
			if ( ! u.searchParams.has( 'utm_campaign' ) ) { u.searchParams.set( 'utm_campaign', 'service_' + campaign ); }
			return u.toString();
		} catch ( e ) {
			return url;
		}
	}

	/** Smallest QR that fits, as a data-URL <img>. Returns '' if unavailable. */
	function qrDataUrl( text ) {
		if ( ! hasQR || ! text ) { return ''; }
		for ( var t = 0; t <= 40; t++ ) {
			try {
				var qr = window.qrcode( t, 'M' );
				qr.addData( text );
				qr.make();
				return qr.createDataURL( 8, 32 ); // cellSize 8px, 4-module quiet zone
			} catch ( e ) { /* too small for this version — grow */ }
		}
		return '';
	}

	function qrHtml( url, cls, campaign ) {
		var d = url ? qrDataUrl( withUtm( url, campaign ) ) : '';
		return d ? '<span class="ann-qr ' + cls + '"><img src="' + attr( d ) + '" alt=""></span>' : '';
	}

	// --- backgrounds ---------------------------------------------------------
	function photoBg( item, fallback ) {
		if ( item.image ) {
			return { style: '', layers: '<img class="ann-bg" src="' + attr( item.image ) + '" alt=""><div class="ann-grad"></div>' };
		}
		return { style: 'background-color:' + ( item.backgroundColor || fallback ), layers: '' };
	}

	// --- the six layouts -----------------------------------------------------
	// Each returns { cls, style, html } for an .ann-stage.
	var LAYOUTS = {
		intro: function ( it ) {
			var bg = photoBg( it, '#2A4D6E' );
			return { cls: 'ann-intro', style: bg.style, html: bg.layers +
				( it.title ? '<div class="ann-headline">' + esc( it.title ) + '</div>' : '' ) +
				'<div class="ann-rule ann-rule-center"></div>' +
				'<div class="ann-intro-body">' + nl2br( it.body ) + '</div>' };
		},
		divider: function ( it, c ) {
			var bg = photoBg( it, '#2A2A2A' );
			return { cls: 'ann-divider', style: bg.style, html: bg.layers +
				'<div class="ann-center"><div class="ann-title ann-title-xl">' + nl2br( it.title ) + '</div>' +
				'<div class="ann-rule ann-rule-center"></div></div>' +
				qrHtml( it.ctaUrl, 'ann-qr-corner', c ) };
		},
		qr_callout: function ( it, c ) {
			var prompt = it.prompt || it.body || it.title || '';
			return { cls: 'ann-callout', style: 'background-color:' + ( it.backgroundColor || '#1F1F1F' ), html:
				'<div class="ann-prompt">' + nl2br( prompt ) + '</div>' +
				'<div class="ann-rule ann-rule-center"></div>' +
				qrHtml( it.ctaUrl, 'ann-qr-big', c ) };
		},
		event: function ( it, c ) {
			var bg = photoBg( it, '#2A2A2A' );
			return { cls: 'ann-event', style: bg.style, html: bg.layers +
				'<div class="ann-center"><div class="ann-title ann-title-lg">' + nl2br( it.title ) + '</div>' +
				'<div class="ann-rule ann-rule-center"></div>' +
				'<div class="ann-sub">' + ( it.when ? '<div class="ann-when">' + esc( it.when ) + '</div>' : '' ) + '</div></div>' +
				qrHtml( it.ctaUrl, 'ann-qr-corner', c ) };
		},
		info: function ( it, c ) {
			var bg = photoBg( it, '#2A2A2A' );
			return { cls: 'ann-info', style: bg.style, html: bg.layers +
				'<div class="ann-left"><div class="ann-title ann-title-left">' + nl2br( it.title ) + '</div>' +
				'<div class="ann-rule ann-rule-full"></div>' +
				'<div class="ann-body">' + bodyHtml( it.body ) + '</div></div>' +
				qrHtml( it.ctaUrl, 'ann-qr-corner ann-qr-lg', c ) };
		},
		feature: function ( it, c ) {
			var cover = it.image ? '<img class="ann-cover" src="' + attr( it.image ) + '" alt="">' : '';
			return { cls: 'ann-feature', style: 'background-color:' + ( it.backgroundColor || '#2A2A2A' ), html: cover +
				'<div class="ann-feature-right"><div class="ann-title ann-title-md">' + nl2br( it.title ) + '</div>' +
				'<div class="ann-details">' + nl2br( it.details ) + '</div>' +
				'<div class="ann-rule ann-rule-left"></div>' +
				qrHtml( it.ctaUrl, 'ann-qr-feature', c ) + '</div>' };
		}
	};

	function buildStage( item ) {
		var fn = LAYOUTS[ item.layout ] || LAYOUTS.info;
		var r  = fn( item, FCCAR.campaign );
		var el = document.createElement( 'div' );
		el.className = 'ann-stage ' + r.cls;
		if ( r.style ) { el.setAttribute( 'style', r.style ); }
		el.innerHTML = r.html;
		return el;
	}

	// --- deck + loop ---------------------------------------------------------
	function render( items ) {
		state.items = items || [];
		deck.innerHTML = '';
		empty.hidden = state.items.length > 0;
		state.items.forEach( function ( it ) { deck.appendChild( buildStage( it ) ); } );
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
		state.idx = ( state.idx + 1 ) % state.items.length;
		show( state.idx );
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
