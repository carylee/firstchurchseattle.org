/*
 * Shared card renderer. Turns one resolved carousel item into a DOM .ann-stage
 * in one of the six layouts, generating QR codes client-side (vendored
 * qrcode-generator). Used by BOTH the live carousel (carousel.js) and the
 * curation screen's draggable thumbnails (curate.js) — so what the curator
 * drags is exactly what plays. Pair with card.css for styling.
 *
 * window.FCCarCard.buildStage(item, { campaign }) -> HTMLElement
 */
( function ( global ) {
	'use strict';

	var hasQR = ( typeof global.qrcode !== 'undefined' );

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

	/** Append slides-style UTMs without clobbering any the URL already carries. */
	function withUtm( url, campaign ) {
		try {
			var u = new URL( url, global.location.origin );
			if ( ! u.searchParams.has( 'utm_source' ) ) { u.searchParams.set( 'utm_source', 'carousel' ); }
			if ( ! u.searchParams.has( 'utm_medium' ) ) { u.searchParams.set( 'utm_medium', 'screen_qr' ); }
			if ( campaign && ! u.searchParams.has( 'utm_campaign' ) ) { u.searchParams.set( 'utm_campaign', 'service_' + campaign ); }
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
				var qr = global.qrcode( t, 'M' );
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

	function photoBg( item, fallback ) {
		if ( item.image ) {
			return { style: '', layers: '<img class="ann-bg" src="' + attr( item.image ) + '" alt=""><div class="ann-grad"></div>' };
		}
		return { style: 'background-color:' + ( item.backgroundColor || fallback ), layers: '' };
	}

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

	function buildStage( item, opts ) {
		opts = opts || {};
		var fn = LAYOUTS[ item.layout ] || LAYOUTS.info;
		var r = fn( item, opts.campaign || '' );
		var el = global.document.createElement( 'div' );
		el.className = 'ann-stage ' + r.cls;
		if ( r.style ) { el.setAttribute( 'style', r.style ); }
		el.innerHTML = r.html;
		return el;
	}

	global.FCCarCard = { buildStage: buildStage, withUtm: withUtm };
}( window ) );
