/**
 * Comms Desk interactions (plain JS on wp.apiFetch + wp.media; no build step):
 * Approve / Needs-info / Dismiss, Add-a-thing, and the rich-card controls —
 * photo (media library + stock), CTA editing, and Breeze sign-up embed/suggest.
 */
( function ( wp, doc ) {
	if ( ! wp || ! wp.apiFetch ) {
		return;
	}
	var apiFetch = wp.apiFetch;
	var P = '/firstchurch/v1/comms-desk/';

	function setStatus( el, msg, kind ) {
		if ( el ) {
			el.textContent = msg;
			el.className = 'fccd-card-status' + ( kind ? ' is-' + kind : '' );
		}
	}
	function cardOf( el ) { return el.closest( '.fccd-card' ); }
	function draftOf( el ) { var c = cardOf( el ); return c ? parseInt( c.getAttribute( 'data-draft' ), 10 ) : 0; }
	function itemOf( el ) { var c = cardOf( el ); return c ? parseInt( c.getAttribute( 'data-item' ), 10 ) : 0; }
	function fail( msg, err ) { window.alert( msg + ': ' + ( err && err.message ? err.message : 'error' ) ); }

	function setThumb( card, url ) {
		if ( ! card || ! url ) { return; }
		var ph = card.querySelector( '.fccd-photo' );
		if ( ! ph ) { return; }
		var none = ph.querySelector( '.fccd-photo-none' );
		if ( none ) { none.remove(); }
		var img = ph.querySelector( '.fccd-photo-thumb' );
		if ( img ) { img.src = url; } else {
			img = doc.createElement( 'img' );
			img.className = 'fccd-photo-thumb';
			img.alt = '';
			img.src = url;
			ph.insertBefore( img, ph.firstChild );
		}
		var sb = ph.querySelector( '.fccd-stock' );
		if ( sb ) { sb.hidden = true; }
	}

	doc.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? ( e.target.closest( 'button, a' ) || e.target ) : e.target;
		if ( ! btn || ! btn.classList ) { return; }
		var cls = btn.classList;

		// ---- Approve & publish ----
		if ( cls.contains( 'fccd-approve' ) ) {
			var card = cardOf( btn ), draft = draftOf( btn );
			if ( ! draft ) { return; }
			btn.disabled = true;
			setStatus( card.querySelector( '.fccd-card-status' ), 'Publishing…' );
			apiFetch( { path: P + 'approve', method: 'POST', data: { draft_id: draft } } )
				.then( function ( res ) {
					card.classList.add( 'fccd-card--done' );
					var s = card.querySelector( '.fccd-card-status' );
					setStatus( s, 'Published ✓ ', 'ok' );
					// Reassurance: a way to see it live and an escape hatch.
					if ( res && res.view_url ) {
						var v = doc.createElement( 'a' );
						v.href = res.view_url; v.target = '_blank'; v.rel = 'noopener';
						v.textContent = 'View it'; s.appendChild( v );
						s.appendChild( doc.createTextNode( ' · ' ) );
					}
					var u = doc.createElement( 'button' );
					u.type = 'button'; u.className = 'button-link fccd-undo';
					u.textContent = 'Undo'; s.appendChild( u );
				} )
				.catch( function ( err ) { btn.disabled = false; setStatus( card.querySelector( '.fccd-card-status' ), 'Failed: ' + ( err && err.message || 'error' ), 'err' ); } );
			return;
		}

		// ---- Undo a publish (back to draft) ----
		if ( cls.contains( 'fccd-undo' ) ) {
			var cardU = cardOf( btn ), draftU = draftOf( btn );
			if ( ! draftU ) { return; }
			btn.disabled = true;
			apiFetch( { path: P + 'unpublish', method: 'POST', data: { draft_id: draftU } } )
				.then( function () {
					cardU.classList.remove( 'fccd-card--done' );
					var ap = cardU.querySelector( '.fccd-approve' );
					if ( ap ) { ap.disabled = false; }
					setStatus( cardU.querySelector( '.fccd-card-status' ), 'Back to draft — not published.', '' );
				} )
				.catch( function ( err ) { btn.disabled = false; fail( 'Undo failed', err ); } );
			return;
		}

		// ---- Read draft: render the full body inline ----
		if ( cls.contains( 'fccd-readdraft' ) ) {
			var cardR = cardOf( btn ), draftR = draftOf( btn );
			var body = cardR.querySelector( '.fccd-draftbody' );
			if ( ! body ) { return; }
			if ( ! body.hidden ) { body.hidden = true; btn.innerHTML = 'Read draft &#9656;'; return; }
			btn.innerHTML = 'Hide draft &#9662;';
			body.hidden = false;
			if ( body.getAttribute( 'data-loaded' ) ) { return; }
			body.innerHTML = '<em>Loading…</em>';
			apiFetch( { path: P + 'preview?draft_id=' + draftR } )
				.then( function ( res ) { body.innerHTML = res && res.html ? res.html : '<em>(empty draft)</em>'; body.setAttribute( 'data-loaded', '1' ); } )
				.catch( function ( err ) { body.innerHTML = 'Preview failed: ' + ( err && err.message || 'error' ); } );
			return;
		}

		// ---- Needs info ----
		if ( cls.contains( 'fccd-needsinfo' ) ) {
			var item = itemOf( btn );
			if ( ! item ) { return; }
			var q = window.prompt( 'What do you need to ask the sender? (recorded as a note)' );
			if ( q === null ) { return; }
			apiFetch( { path: P + 'needs-info', method: 'POST', data: { item_id: item, question: q } } )
				.then( function () { setStatus( cardOf( btn ).querySelector( '.fccd-card-status' ), 'Flagged — needs info', 'ok' ); } )
				.catch( function ( err ) { fail( 'Failed', err ); } );
			return;
		}

		// ---- Dismiss (e.g. revision with no new info) ----
		if ( cls.contains( 'fccd-dismiss' ) ) {
			var item2 = itemOf( btn ), card2 = cardOf( btn );
			if ( ! item2 ) { return; }
			btn.disabled = true;
			apiFetch( { path: P + 'dismiss', method: 'POST', data: { item_id: item2 } } )
				.then( function () { card2.classList.add( 'fccd-card--done' ); setStatus( card2.querySelector( '.fccd-card-status' ), 'Dismissed', 'ok' ); } )
				.catch( function ( err ) { btn.disabled = false; fail( 'Failed', err ); } );
			return;
		}

		// ---- Photo: media library ----
		if ( cls.contains( 'fccd-photo-media' ) ) {
			var cardM = cardOf( btn ), draftM = draftOf( btn );
			if ( ! draftM || ! wp.media ) { return; }
			var frame = wp.media( { title: 'Choose a featured image', button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } } );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				apiFetch( { path: P + 'set-photo', method: 'POST', data: { draft_id: draftM, attachment_id: att.id } } )
					.then( function ( res ) { setThumb( cardM, res.thumb ); } )
					.catch( function ( err ) { fail( 'Set photo failed', err ); } );
			} );
			frame.open();
			return;
		}

		// ---- Photo: toggle stock search ----
		if ( cls.contains( 'fccd-photo-stock-toggle' ) ) {
			var sbox = btn.closest( '.fccd-photo' ).querySelector( '.fccd-stock' );
			if ( sbox ) { sbox.hidden = ! sbox.hidden; }
			return;
		}

		// ---- Photo: run stock search ----
		if ( cls.contains( 'fccd-stock-go' ) ) {
			var wrap = btn.closest( '.fccd-stock' );
			var query = wrap.querySelector( '.fccd-stock-q' ).value.trim();
			var rEl = wrap.querySelector( '.fccd-stock-results' );
			if ( ! query ) { return; }
			rEl.textContent = 'Searching…';
			apiFetch( { path: P + 'stock-search', method: 'POST', data: { query: query } } )
				.then( function ( res ) {
					rEl.innerHTML = '';
					( res.results || [] ).slice( 0, 12 ).forEach( function ( r ) {
						var b = doc.createElement( 'button' );
						b.type = 'button'; b.className = 'fccd-stock-pick';
						b.title = r.title + ( r.creator ? ' — ' + r.creator : '' );
						b.setAttribute( 'data-meta', encodeURIComponent( JSON.stringify( r.meta || r ) ) );
						var im = doc.createElement( 'img' ); im.src = r.thumbnail; im.alt = '';
						b.appendChild( im ); rEl.appendChild( b );
					} );
					if ( ! rEl.children.length ) { rEl.textContent = 'No results.'; }
				} )
				.catch( function ( err ) { rEl.textContent = 'Search failed: ' + ( err && err.message || 'error' ); } );
			return;
		}

		// ---- Photo: pick a stock result ----
		if ( cls.contains( 'fccd-stock-pick' ) ) {
			var cardK = cardOf( btn ), draftK = draftOf( btn ), meta;
			try { meta = JSON.parse( decodeURIComponent( btn.getAttribute( 'data-meta' ) ) ); } catch ( ex ) { return; }
			btn.disabled = true;
			apiFetch( { path: P + 'stock-import', method: 'POST', data: { draft_id: draftK, meta: meta } } )
				.then( function ( res ) { setThumb( cardK, res.thumb ); } )
				.catch( function ( err ) { btn.disabled = false; fail( 'Import failed', err ); } );
			return;
		}

		// ---- CTA save ----
		if ( cls.contains( 'fccd-cta-save' ) ) {
			var cardC = cardOf( btn ), draftC = draftOf( btn );
			var prev = btn.textContent; btn.disabled = true; btn.textContent = 'Saving…';
			apiFetch( { path: P + 'save-cta', method: 'POST', data: {
				draft_id: draftC,
				cta_text: cardC.querySelector( '.fccd-cta-text' ).value,
				cta_url: cardC.querySelector( '.fccd-cta-url' ).value,
			} } )
				.then( function () { btn.textContent = 'Saved ✓'; setTimeout( function () { btn.textContent = prev; btn.disabled = false; }, 1500 ); } )
				.catch( function ( err ) { btn.textContent = prev; btn.disabled = false; fail( 'Save failed', err ); } );
			return;
		}

		// ---- Breeze: embed the detected form ----
		if ( cls.contains( 'fccd-breeze-embed' ) ) {
			var cardB = cardOf( btn ), draftB = draftOf( btn );
			btn.disabled = true;
			apiFetch( { path: P + 'breeze-embed', method: 'POST', data: { draft_id: draftB, form_id: btn.getAttribute( 'data-form' ) } } )
				.then( function () { var s = cardB.querySelector( '.fccd-edit-status' ); if ( s ) { s.textContent = '✓ embedded'; s.className = 'fccd-edit-status is-ok'; } btn.remove(); } )
				.catch( function ( err ) { btn.disabled = false; fail( 'Embed failed', err ); } );
			return;
		}

		// ---- Breeze: suggest live forms ----
		if ( cls.contains( 'fccd-breeze-suggest' ) ) {
			var list = cardOf( btn ).querySelector( '.fccd-breeze-list' );
			list.hidden = false; list.textContent = 'Loading live forms…';
			apiFetch( { path: P + 'breeze-forms' } )
				.then( function ( res ) {
					list.innerHTML = '<p class="fccd-muted">Suggestions (live Breeze forms) — pick one to embed:</p>';
					( res.forms || [] ).forEach( function ( f ) {
						var b = doc.createElement( 'button' );
						b.type = 'button'; b.className = 'button button-small fccd-breeze-pick';
						b.setAttribute( 'data-form', f.id ); b.textContent = f.name || ( '#' + f.id );
						list.appendChild( b ); list.appendChild( doc.createTextNode( ' ' ) );
					} );
					if ( ! ( res.forms || [] ).length ) { list.textContent = 'No live forms found.'; }
				} )
				.catch( function ( err ) { list.textContent = 'Failed: ' + ( err && err.message || 'error' ); } );
			return;
		}

		// ---- Breeze: pick a suggested form → embed ----
		if ( cls.contains( 'fccd-breeze-pick' ) ) {
			var cardRP = cardOf( btn ), draftRP = draftOf( btn ), label = btn.textContent;
			btn.disabled = true;
			apiFetch( { path: P + 'breeze-embed', method: 'POST', data: { draft_id: draftRP, form_id: btn.getAttribute( 'data-form' ) } } )
				.then( function () {
					var s = cardRP.querySelector( '.fccd-edit-status' ); if ( s ) { s.textContent = '✓ embedded “' + label + '”'; s.className = 'fccd-edit-status is-ok'; }
					var l = cardRP.querySelector( '.fccd-breeze-list' ); if ( l ) { l.hidden = true; }
				} )
				.catch( function ( err ) { btn.disabled = false; fail( 'Embed failed', err ); } );
			return;
		}

		// ---- Add a thing: toggle composer ----
		if ( btn.hasAttribute( 'data-fccd-addthing' ) ) {
			var box = doc.querySelector( '.fccd-addthing' );
			if ( box ) { box.hidden = ! box.hidden; }
			return;
		}

		// ---- Add a thing: submit ----
		if ( cls.contains( 'fccd-addthing-submit' ) ) {
			var w = doc.querySelector( '.fccd-addthing' );
			var subject = w.querySelector( '.fccd-addthing-subject' ).value.trim();
			var body = w.querySelector( '.fccd-addthing-body' ).value.trim();
			var st = w.querySelector( '.fccd-addthing-status' );
			if ( ! subject && ! body ) { st.textContent = 'Add a subject or some details first.'; return; }
			btn.disabled = true; st.textContent = 'Adding…';
			apiFetch( { path: '/firstchurch/v1/intake/item', method: 'POST', data: { from: 'comms-desk', subject: subject, body: body } } )
				.then( function () { st.textContent = 'Added to intake — it\'ll be drafted on the next run.'; w.querySelector( '.fccd-addthing-subject' ).value = ''; w.querySelector( '.fccd-addthing-body' ).value = ''; btn.disabled = false; } )
				.catch( function ( err ) { btn.disabled = false; st.textContent = 'Failed: ' + ( err && err.message || 'error' ); } );
		}
	} );
} )( window.wp, document );
