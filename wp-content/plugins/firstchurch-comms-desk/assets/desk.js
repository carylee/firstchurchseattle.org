/**
 * Comms Desk interactions: Approve & publish, Needs info, and "Add a thing".
 * Plain JS on wp.apiFetch (nonce handled for us). No build step.
 */
( function ( wp, doc ) {
	if ( ! wp || ! wp.apiFetch ) {
		return;
	}
	var apiFetch = wp.apiFetch;

	function setStatus( el, msg, kind ) {
		if ( ! el ) {
			return;
		}
		el.textContent = msg;
		el.className = 'fccd-card-status' + ( kind ? ' is-' + kind : '' );
	}

	doc.addEventListener( 'click', function ( e ) {
		var btn = e.target;

		// Approve & publish
		if ( btn.classList.contains( 'fccd-approve' ) ) {
			var card = btn.closest( '.fccd-card' );
			var draftId = card && parseInt( card.getAttribute( 'data-draft' ), 10 );
			if ( ! draftId ) {
				return;
			}
			btn.disabled = true;
			setStatus( card.querySelector( '.fccd-card-status' ), 'Publishing…' );
			apiFetch( { path: '/firstchurch/v1/comms-desk/approve', method: 'POST', data: { draft_id: draftId } } )
				.then( function () {
					card.classList.add( 'fccd-card--done' );
					setStatus( card.querySelector( '.fccd-card-status' ), 'Published ✓', 'ok' );
				} )
				.catch( function ( err ) {
					btn.disabled = false;
					setStatus( card.querySelector( '.fccd-card-status' ), 'Failed: ' + ( err && err.message ? err.message : 'error' ), 'err' );
				} );
			return;
		}

		// Needs info
		if ( btn.classList.contains( 'fccd-needsinfo' ) ) {
			var card2 = btn.closest( '.fccd-card' );
			var itemId = card2 && parseInt( card2.getAttribute( 'data-item' ), 10 );
			if ( ! itemId ) {
				return;
			}
			var question = window.prompt( 'What do you need to ask the sender? (recorded as a note for now)' );
			if ( question === null ) {
				return;
			}
			setStatus( card2.querySelector( '.fccd-card-status' ), 'Saving…' );
			apiFetch( { path: '/firstchurch/v1/comms-desk/needs-info', method: 'POST', data: { item_id: itemId, question: question } } )
				.then( function () {
					setStatus( card2.querySelector( '.fccd-card-status' ), 'Flagged — needs info', 'ok' );
				} )
				.catch( function ( err ) {
					setStatus( card2.querySelector( '.fccd-card-status' ), 'Failed: ' + ( err && err.message ? err.message : 'error' ), 'err' );
				} );
			return;
		}

		// Add a thing — toggle composer
		if ( btn.hasAttribute( 'data-fccd-addthing' ) ) {
			var box = doc.querySelector( '.fccd-addthing' );
			if ( box ) {
				box.hidden = ! box.hidden;
			}
			return;
		}

		// Add a thing — submit
		if ( btn.classList.contains( 'fccd-addthing-submit' ) ) {
			var wrap = doc.querySelector( '.fccd-addthing' );
			var subject = wrap.querySelector( '.fccd-addthing-subject' ).value.trim();
			var body = wrap.querySelector( '.fccd-addthing-body' ).value.trim();
			var status = wrap.querySelector( '.fccd-addthing-status' );
			if ( ! subject && ! body ) {
				status.textContent = 'Add a subject or some details first.';
				return;
			}
			btn.disabled = true;
			status.textContent = 'Adding…';
			apiFetch( { path: '/firstchurch/v1/intake/item', method: 'POST', data: { from: 'comms-desk', subject: subject, body: body } } )
				.then( function () {
					status.textContent = 'Added to intake — it\'ll be drafted on the next run.';
					wrap.querySelector( '.fccd-addthing-subject' ).value = '';
					wrap.querySelector( '.fccd-addthing-body' ).value = '';
					btn.disabled = false;
				} )
				.catch( function ( err ) {
					btn.disabled = false;
					status.textContent = 'Failed: ' + ( err && err.message ? err.message : 'error' );
				} );
		}
	} );
} )( window.wp, document );
