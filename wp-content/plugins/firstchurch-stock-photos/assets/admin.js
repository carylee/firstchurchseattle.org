/* global fcspData */
( function () {
	'use strict';

	var form = document.getElementById( 'fcsp-search-form' );
	var queryEl = document.getElementById( 'fcsp-query' );
	var orientationEl = document.getElementById( 'fcsp-orientation' );
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

		fetch( url.toString(), {
			headers: { 'X-WP-Nonce': fcspData.nonce }
		} )
			.then( toJson )
			.then( function ( data ) {
				var results = ( data && data.results ) || [];
				if ( ! results.length ) {
					statusEl.textContent = 'No openly-licensed photos found for “' + q + '”.';
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

		var img = document.createElement( 'img' );
		img.src = item.thumbnail || item.url;
		img.alt = item.title || '';
		img.loading = 'lazy';
		card.appendChild( img );

		var meta = document.createElement( 'div' );
		meta.className = 'fcsp-meta';
		var credit = item.creator || item.source || 'Unknown';
		if ( item.foreign_url ) {
			meta.innerHTML = '<a href="' + escapeAttr( item.foreign_url ) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml( credit ) + '</a>';
		} else {
			meta.textContent = credit;
		}
		if ( item.license ) {
			var lic = document.createElement( 'div' );
			lic.className = 'fcsp-license';
			lic.textContent = item.license;
			meta.appendChild( lic );
		}
		card.appendChild( meta );

		var actions = document.createElement( 'div' );
		actions.className = 'fcsp-actions';
		var btn = document.createElement( 'button' );
		btn.className = 'button';
		btn.textContent = 'Add to Library';
		btn.addEventListener( 'click', function () {
			doImport( item, card, btn );
		} );
		actions.appendChild( btn );
		card.appendChild( actions );

		resultsEl.appendChild( card );
	}

	function doImport( item, card, btn ) {
		btn.disabled = true;
		btn.textContent = 'Adding…';

		fetch( fcspData.importUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': fcspData.nonce
			},
			body: JSON.stringify( {
				image_url: item.url,
				title: item.title,
				alt: item.title,
				openverse_id: item.id,
				creator: item.creator,
				creator_url: item.creator_url,
				license: item.license,
				license_url: item.license_url,
				attribution: item.attribution,
				source: item.source,
				foreign_url: item.foreign_url
			} )
		} )
			.then( toJson )
			.then( function ( data ) {
				if ( ! data || ! data.attachment_id ) {
					throw new Error( ( data && data.message ) || 'Import failed.' );
				}
				card.classList.add( 'fcsp-done' );
				btn.textContent = 'Added ✓';
				var link = document.createElement( 'a' );
				link.href = fcspData.mediaUrl + '?item=' + data.attachment_id;
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
				link.textContent = 'View in library';
				link.style.marginLeft = '8px';
				btn.parentNode.appendChild( link );
			} )
			.catch( function ( err ) {
				btn.disabled = false;
				btn.textContent = 'Retry';
				statusEl.textContent = 'Import failed: ' + err.message;
			} );
	}

	function toJson( res ) {
		return res.json().then( function ( body ) {
			if ( ! res.ok ) {
				throw new Error( ( body && body.message ) || ( 'HTTP ' + res.status ) );
			}
			return body;
		} );
	}

	function escapeHtml( s ) {
		var d = document.createElement( 'div' );
		d.textContent = s == null ? '' : String( s );
		return d.innerHTML;
	}

	function escapeAttr( s ) {
		return escapeHtml( s ).replace( /"/g, '&quot;' );
	}
} )();
