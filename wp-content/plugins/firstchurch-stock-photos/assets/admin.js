/* global fcspData */
( function () {
	'use strict';

	var form = document.getElementById( 'fcsp-search-form' );
	var queryEl = document.getElementById( 'fcsp-query' );
	var orientationEl = document.getElementById( 'fcsp-orientation' );
	var providerEl = document.getElementById( 'fcsp-provider' );
	var statusEl = document.getElementById( 'fcsp-status' );
	var resultsEl = document.getElementById( 'fcsp-results' );

	if ( ! form ) {
		return;
	}

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		search();
	} );

	function search() {
		var q = queryEl.value.trim();
		if ( ! q ) {
			return;
		}
		resultsEl.innerHTML = '';
		statusEl.textContent = 'Searching…';

		var url = new URL( fcspData.searchUrl );
		url.searchParams.set( 'q', q );
		url.searchParams.set( 'count', '24' );
		if ( orientationEl.value ) {
			url.searchParams.set( 'orientation', orientationEl.value );
		}
		if ( providerEl && providerEl.value ) {
			url.searchParams.set( 'provider', providerEl.value );
		}

		fetch( url.toString(), {
			headers: { 'X-WP-Nonce': fcspData.nonce }
		} )
			.then( toJson )
			.then( function ( data ) {
				var results = ( data && data.results ) || [];
				if ( ! results.length ) {
					statusEl.textContent = 'No photos found for “' + q + '”.';
					return;
				}
				statusEl.textContent = 'Showing ' + results.length + ' of ' + ( data.total || results.length ) + ' results.';
				results.forEach( renderCard );
			} )
			.catch( function ( err ) {
				statusEl.textContent = 'Search failed: ' + err.message;
			} );
	}

	function renderCard( item ) {
		var card = document.createElement( 'div' );
		card.className = 'fcsp-card';

		// Clickable thumbnail -> full-size preview.
		var thumb = document.createElement( 'button' );
		thumb.type = 'button';
		thumb.className = 'fcsp-thumb';
		thumb.title = 'Click to preview full size';
		thumb.addEventListener( 'click', function () {
			openLightbox( item );
		} );

		var img = document.createElement( 'img' );
		img.src = item.thumbnail || item.url;
		img.alt = item.title || '';
		img.loading = 'lazy';
		thumb.appendChild( img );

		if ( item.width && item.height ) {
			var dims = document.createElement( 'span' );
			dims.className = 'fcsp-dims';
			dims.textContent = item.width + ' × ' + item.height;
			thumb.appendChild( dims );
		}
		card.appendChild( thumb );

		var meta = document.createElement( 'div' );
		meta.className = 'fcsp-meta';
		meta.appendChild( creditNode( item ) );
		if ( item.license ) {
			meta.appendChild( licenseNode( item ) );
		}
		card.appendChild( meta );

		var actions = document.createElement( 'div' );
		actions.className = 'fcsp-actions';
		var btn = document.createElement( 'button' );
		btn.className = 'button';
		btn.textContent = 'Add to Library';
		wireImport( btn, item, function ( data ) {
			card.classList.add( 'fcsp-done' );
			btn.parentNode.appendChild( libraryLink( data ) );
		} );
		actions.appendChild( btn );
		card.appendChild( actions );

		resultsEl.appendChild( card );
	}

	/* ---- Shared bits ---- */

	function creditNode( item ) {
		var credit = item.creator || item.source || 'Unknown';
		if ( item.foreign_url ) {
			var a = document.createElement( 'a' );
			a.href = item.foreign_url;
			a.target = '_blank';
			a.rel = 'noopener noreferrer';
			a.textContent = credit;
			return a;
		}
		var span = document.createElement( 'span' );
		span.textContent = credit;
		return span;
	}

	function licenseNode( item ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'fcsp-license';
		if ( item.license_url ) {
			var a = document.createElement( 'a' );
			a.href = item.license_url;
			a.target = '_blank';
			a.rel = 'noopener noreferrer';
			a.textContent = item.license;
			wrap.appendChild( a );
		} else {
			wrap.textContent = item.license;
		}
		return wrap;
	}

	function libraryLink( data ) {
		var link = document.createElement( 'a' );
		link.href = fcspData.mediaUrl + '?item=' + data.attachment_id;
		link.target = '_blank';
		link.rel = 'noopener noreferrer';
		link.textContent = 'View in library';
		link.className = 'fcsp-lib-link';
		return link;
	}

	function wireImport( btn, item, onSuccess ) {
		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			btn.textContent = 'Adding…';
			importRequest( item )
				.then( function ( data ) {
					if ( ! data || ! data.attachment_id ) {
						throw new Error( ( data && data.message ) || 'Import failed.' );
					}
					btn.textContent = 'Added ✓';
					onSuccess( data );
				} )
				.catch( function ( err ) {
					btn.disabled = false;
					btn.textContent = 'Retry';
					statusEl.textContent = 'Import failed: ' + err.message;
				} );
		} );
	}

	function importRequest( item ) {
		return fetch( fcspData.importUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': fcspData.nonce
			},
			body: JSON.stringify( {
				image_url: item.url,
				title: item.title,
				alt: item.title,
				provider: item.provider,
				openverse_id: item.id,
				creator: item.creator,
				creator_url: item.creator_url,
				license: item.license,
				license_url: item.license_url,
				attribution: item.attribution,
				source: item.source,
				foreign_url: item.foreign_url
			} )
		} ).then( toJson );
	}

	/* ---- Lightbox ---- */

	var lb = null;

	function ensureLightbox() {
		if ( lb ) {
			return lb;
		}
		lb = document.createElement( 'div' );
		lb.className = 'fcsp-lightbox';
		lb.hidden = true;
		lb.innerHTML =
			'<div class="fcsp-lb-backdrop"></div>' +
			'<div class="fcsp-lb-panel" role="dialog" aria-modal="true" aria-label="Photo preview">' +
			'<button type="button" class="fcsp-lb-close" aria-label="Close">×</button>' +
			'<div class="fcsp-lb-stage"></div>' +
			'<div class="fcsp-lb-info"></div>' +
			'</div>';
		document.body.appendChild( lb );
		lb.querySelector( '.fcsp-lb-backdrop' ).addEventListener( 'click', closeLightbox );
		lb.querySelector( '.fcsp-lb-close' ).addEventListener( 'click', closeLightbox );
		document.addEventListener( 'keydown', function ( e ) {
			if ( ! lb.hidden && e.key === 'Escape' ) {
				closeLightbox();
			}
		} );
		return lb;
	}

	function openLightbox( item ) {
		ensureLightbox();
		var stage = lb.querySelector( '.fcsp-lb-stage' );
		var info = lb.querySelector( '.fcsp-lb-info' );
		stage.innerHTML = '';
		info.innerHTML = '';

		// Start from the (already-loaded) thumbnail, then swap to the original
		// once it has decoded — so the panel never flashes blank on big files.
		var img = document.createElement( 'img' );
		img.alt = item.title || '';
		img.src = item.thumbnail || item.url;
		stage.appendChild( img );
		if ( item.url && item.url !== img.src ) {
			var full = new Image();
			full.onload = function () {
				img.src = item.url;
			};
			full.src = item.url;
		}

		if ( item.title ) {
			var h = document.createElement( 'h2' );
			h.className = 'fcsp-lb-title';
			h.textContent = item.title;
			info.appendChild( h );
		}

		var rows = document.createElement( 'div' );
		rows.className = 'fcsp-lb-rows';
		rows.appendChild( infoRow( 'By', creditNode( item ) ) );
		if ( item.license ) {
			rows.appendChild( infoRow( 'License', licenseNode( item ) ) );
		}
		if ( item.width && item.height ) {
			rows.appendChild( infoRow( 'Size', textNode( item.width + ' × ' + item.height + ' px' ) ) );
		}
		if ( item.provider ) {
			rows.appendChild( infoRow( 'Provider', textNode( item.provider ) ) );
		}
		info.appendChild( rows );

		if ( item.attribution ) {
			var attr = document.createElement( 'p' );
			attr.className = 'fcsp-lb-attribution';
			attr.textContent = item.attribution;
			info.appendChild( attr );
		}

		var actions = document.createElement( 'div' );
		actions.className = 'fcsp-lb-actions';
		var addBtn = document.createElement( 'button' );
		addBtn.className = 'button button-primary';
		addBtn.textContent = 'Add to Library';
		wireImport( addBtn, item, function ( data ) {
			actions.appendChild( libraryLink( data ) );
		} );
		actions.appendChild( addBtn );
		info.appendChild( actions );

		lb.hidden = false;
		lb.querySelector( '.fcsp-lb-close' ).focus();
	}

	function closeLightbox() {
		if ( lb ) {
			lb.hidden = true;
			lb.querySelector( '.fcsp-lb-stage' ).innerHTML = '';
		}
	}

	function infoRow( label, valueNode ) {
		var row = document.createElement( 'div' );
		row.className = 'fcsp-lb-row';
		var l = document.createElement( 'span' );
		l.className = 'fcsp-lb-label';
		l.textContent = label;
		row.appendChild( l );
		row.appendChild( valueNode );
		return row;
	}

	function textNode( text ) {
		var span = document.createElement( 'span' );
		span.textContent = text;
		return span;
	}

	/* ---- Utils ---- */

	function toJson( res ) {
		return res.json().then( function ( body ) {
			if ( ! res.ok ) {
				throw new Error( ( body && body.message ) || ( 'HTTP ' + res.status ) );
			}
			return body;
		} );
	}
} )();
