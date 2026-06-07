/* Carousel curation screen — a WYSIWYG deck editor with an adaptive slide-over
 * editor. Each card is a real, scaled-down render of the live carousel card (via
 * the shared FCCarCard renderer). Drag a deck thumbnail to reorder, click an
 * available one to add it, and click ✎ to open the drawer:
 *
 *   - a STANDING CARD opens a full content editor (layout/title/body/prompt/
 *     details/QR/background) that saves the card itself over REST — no leaving
 *     the workbench; "+ New standing card" opens the same drawer empty.
 *   - an EVENT or ANNOUNCEMENT opens an OVERRIDE editor (title/when/background/
 *     preservice) plus a deep link to edit the original — the public post is
 *     never touched; overrides ride along in the deck.
 *
 * Plain jQuery + jquery-ui-sortable + wp.media — no build step. */
( function ( $ ) {
	'use strict';

	var D = window.FCCAR || { deck: [], available: [], layouts: [], restUrl: '', restCardUrl: '', nonce: '' };
	var Card = window.FCCarCard;
	var $deck = $( '#fccar-deck' );
	var $avail = $( '#fccar-available' );
	var $status = $( '#fccar-status' );
	var $count = $( '#fccar-deck-count' );
	var $dirty = $( '#fccar-dirty' );

	/* ---- unsaved-work protection: a dirty flag, a beforeunload guard, and a
	 * localStorage draft so an accidental reload/navigation never costs an
	 * afternoon of curation. We intentionally do NOT autosave to the live feed —
	 * "Save deck" stays the explicit publish, so a half-built deck never reaches
	 * the kiosk. The draft persists the in-progress arrangement locally only. */
	var DRAFT_KEY = 'fccar_deck_draft';
	var dirty = false;
	var draftTimer = null;

	function saveDraft() {
		try { window.localStorage.setItem( DRAFT_KEY, JSON.stringify( { savedAt: Date.now(), deck: D.deck } ) ); } catch ( e ) {}
	}
	function clearDraft() {
		try { window.localStorage.removeItem( DRAFT_KEY ); } catch ( e ) {}
	}
	function markDirty() {
		dirty = true;
		$dirty.prop( 'hidden', false );
		clearTimeout( draftTimer );
		draftTimer = setTimeout( saveDraft, 600 );
	}
	function markClean() {
		dirty = false;
		$dirty.prop( 'hidden', true );
		clearTimeout( draftTimer );
		clearDraft();
	}

	$( window ).on( 'beforeunload', function ( ev ) {
		if ( ! dirty ) { return undefined; }
		( ev || window.event ).returnValue = 'You have unsaved changes to the carousel deck.';
		return 'You have unsaved changes to the carousel deck.';
	} );

	// Which card fields each layout actually uses (mirrors the metabox's data-layouts).
	var FIELD_LAYOUTS = {
		body:     [ 'intro', 'info' ],
		prompt:   [ 'qr_callout' ],
		details:  [ 'feature' ],
		qr_url:   [ 'divider', 'qr_callout', 'event', 'info', 'feature' ],
		bg_color: [ 'divider', 'qr_callout', 'feature' ]
	};

	function esc( s ) { return $( '<div/>' ).text( s == null ? '' : String( s ) ).html(); }
	function attr( s ) { return esc( s ).replace( /"/g, '&quot;' ); }

	function entryById( id ) {
		for ( var i = 0; i < D.deck.length; i++ ) {
			if ( D.deck[ i ].id === id ) { return D.deck[ i ]; }
		}
		return null;
	}

	/** The effective card (source values + overrides) a thumbnail renders. */
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

	/** Render the scaled card preview into a well element, sized to its width. */
	function paintWell( $well, e ) {
		$well.empty();
		var stage = Card.buildStage( effItem( e ), {} );
		$well.append( stage );
		var w = $well.width() || 240;
		stage.style.transform = 'scale(' + ( w / 1280 ) + ')';
	}
	function paintThumb( $tile, e ) { paintWell( $tile.find( '.fccar-thumb' ), e ); }

	function badge( e ) {
		return '<span class="fccar-badge fccar-badge--' + esc( e.source ) + '">' + esc( e.layout ) + '</span>';
	}
	function preBadge() {
		return '<span class="fccar-badge fccar-badge--pre" title="Preservice-only">PRE</span>';
	}

	function dateTag( r ) {
		return r.srcDate ? '<span class="fccar-tdate">' + esc( r.srcDate ) + '</span>' : '';
	}

	function deckTile( e ) {
		return $(
			'<li class="fccar-tile" data-id="' + attr( e.id ) + '">' +
				'<div class="fccar-thumb" title="Drag to reorder"></div>' +
				'<div class="fccar-tile-bar">' +
					badge( e ) + ( e.preserviceOnly ? preBadge() : '' ) +
					'<span class="fccar-tname">' + esc( e.title || e.srcTitle ) + '</span>' +
					dateTag( e ) +
					'<button type="button" class="fccar-edit" title="Edit">✎</button>' +
					'<button type="button" class="fccar-remove" title="Remove from deck">✕</button>' +
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
					dateTag( r ) +
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
	var availFilter = { q: '', src: 'all' };

	function availMatches( r ) {
		if ( 'all' !== availFilter.src && r.source !== availFilter.src ) { return false; }
		if ( availFilter.q && String( r.srcTitle || '' ).toLowerCase().indexOf( availFilter.q ) === -1 ) { return false; }
		return true;
	}

	function renderAvail() {
		$avail.empty();
		var shown = 0;
		D.available.forEach( function ( r ) {
			if ( ! availMatches( r ) ) { return; }
			var $t = availTile( r );
			$avail.append( $t );
			paintThumb( $t, r );
			shown++;
		} );
		$( '#fccar-avail-count' ).text( '(' + shown + ')' );
		$( '#fccar-avail-empty' ).prop( 'hidden', shown > 0 || D.available.length === 0 );
	}

	$( '#fccar-avail-search' ).on( 'input', function () { availFilter.q = this.value.trim().toLowerCase(); renderAvail(); } );
	$( '.fccar-chips' ).on( 'click', 'button', function () {
		availFilter.src = $( this ).data( 'src' );
		$( '.fccar-chips button' ).removeClass( 'is-active' );
		$( this ).addClass( 'is-active' );
		renderAvail();
	} );

	/* ---- ordering ---- */
	$deck.sortable( {
		items: '> .fccar-tile',
		placeholder: 'fccar-placeholder',
		forcePlaceholderSize: true,
		tolerance: 'pointer',
		cancel: 'input,textarea,button,a',
		stop: function () {
			var order = $deck.children( '.fccar-tile' ).map( function () { return String( $( this ).data( 'id' ) ); } ).get();
			D.deck.sort( function ( a, b ) { return order.indexOf( a.id ) - order.indexOf( b.id ); } );
			markDirty();
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
		markDirty();
	} );

	$deck.on( 'click', '.fccar-remove', function () {
		var id = String( $( this ).closest( '.fccar-tile' ).data( 'id' ) );
		var i = D.deck.findIndex( function ( e ) { return e.id === id; } );
		if ( i < 0 ) { return; }
		var e = D.deck.splice( i, 1 )[ 0 ];
		D.available.unshift( $.extend( {}, e, { title: '', when: '', image: '' } ) );
		renderDeck();
		renderAvail();
		markDirty();
	} );

	/* ================= the adaptive drawer ================= */

	var $drawer = $( '#fccar-drawer' );
	var $back = $( '#fccar-drawer-backdrop' );
	var W = null;        // working entry being edited
	var isCard = false;  // card content edit vs. override edit
	var isNew = false;   // creating a new standing card

	function field( label, name, type, value, ph ) {
		return '<label class="fccar-field"><span>' + esc( label ) + '</span>' +
			'<input type="' + type + '" class="fccar-d-' + name + '" value="' + attr( value ) + '"' +
			( ph ? ' placeholder="' + attr( ph ) + '"' : '' ) + '></label>';
	}
	function textarea( label, name, value, hint ) {
		return '<label class="fccar-field"><span>' + esc( label ) +
			( hint ? ' <em>' + esc( hint ) + '</em>' : '' ) + '</span>' +
			'<textarea class="fccar-d-' + name + '" rows="3">' + esc( value ) + '</textarea></label>';
	}

	function cardFormHtml() {
		var opts = ( D.layouts || [] ).map( function ( l ) {
			return '<option value="' + attr( l ) + '"' + ( l === W.layout ? ' selected' : '' ) + '>' + esc( l ) + '</option>';
		} ).join( '' );
		var hasImg = !!( W.srcImage );
		return field( 'Title', 'title', 'text', W.srcTitle || '' ) +
			'<label class="fccar-field"><span>Layout</span><select class="fccar-d-layout">' + opts + '</select></label>' +
			'<div data-when="body">' + textarea( 'Body', 'body', W.body || '', 'info cards: one "- " per bullet' ) + '</div>' +
			'<div data-when="prompt">' + textarea( 'Prompt', 'prompt', W.prompt || '' ) + '</div>' +
			'<div data-when="details">' + textarea( 'Details', 'details', W.details || '' ) + '</div>' +
			'<div data-when="qr_url">' + field( 'QR link', 'qr_url', 'url', W.ctaUrl || '' ) + '</div>' +
			'<div data-when="bg_color">' + field( 'Background color', 'bg_color', 'text', W.backgroundColor || '', '#1F1F1F' ) + '</div>' +
			'<div class="fccar-field"><span>Background image</span><span class="fccar-bg-buttons">' +
				'<button type="button" class="button button-small fccar-bg">' + ( hasImg ? 'Replace…' : 'Choose…' ) + '</button> ' +
				'<button type="button" class="button-link fccar-bg-clear"' + ( hasImg ? '' : ' style="display:none"' ) + '>Clear</button>' +
			'</span></div>' +
			'<label class="fccar-presvc"><input type="checkbox" class="fccar-d-presvc"' + ( W.preserviceOnly ? ' checked' : '' ) + '> Preservice-only</label>' +
			'<div class="fccar-drawer-actions">' +
				'<button type="button" class="button button-primary fccar-d-save">' + ( isNew ? 'Add card' : 'Save card' ) + '</button>' +
				'<button type="button" class="button fccar-d-cancel">Cancel</button>' +
			'</div>';
	}

	function overrideFormHtml() {
		var num = String( W.id ).replace( /^[a-z]+-/, '' );
		var editUrl = D.adminPostUrl + '?post=' + encodeURIComponent( num ) + '&action=edit';
		var kind = 'event' === W.source ? 'event' : 'post';
		var hasImg = !!( W.image );
		return '<p class="fccar-drawer-note">Overrides only change how this ' + esc( kind ) +
				' appears in the carousel — the original isn\'t touched. ' +
				'<a href="' + attr( editUrl ) + '" target="_blank" rel="noopener">Edit the full ' + esc( kind ) + ' ↗</a></p>' +
			field( 'Title', 'title', 'text', W.title || '', W.srcTitle ) +
			field( 'When', 'when', 'text', W.when || '', W.srcWhen || '—' ) +
			'<div class="fccar-field"><span>Background</span><span class="fccar-bg-buttons">' +
				'<button type="button" class="button button-small fccar-bg">' + ( ( W.image || W.srcImage ) ? 'Replace…' : 'Choose…' ) + '</button> ' +
				'<button type="button" class="button-link fccar-bg-clear"' + ( hasImg ? '' : ' style="display:none"' ) + '>Clear</button>' +
			'</span></div>' +
			'<label class="fccar-presvc"><input type="checkbox" class="fccar-d-presvc"' + ( W.preserviceOnly ? ' checked' : '' ) + '> Preservice-only</label>' +
			'<div class="fccar-drawer-actions">' +
				'<button type="button" class="button button-primary fccar-d-cancel">Done</button>' +
			'</div>';
	}

	function renderDrawer() {
		var title = isNew ? 'New standing card' : ( isCard ? 'Edit standing card' : 'Edit ' + ( 'event' === W.source ? 'event' : 'announcement' ) + ' overrides' );
		$drawer.html(
			'<div class="fccar-drawer-head">' +
				'<h2>' + esc( title ) + '</h2>' +
				'<button type="button" class="fccar-drawer-x fccar-d-cancel" aria-label="Close">✕</button>' +
			'</div>' +
			'<div class="fccar-drawer-preview"><div class="fccar-thumb fccar-d-preview"></div></div>' +
			'<div class="fccar-drawer-body">' + ( isCard ? cardFormHtml() : overrideFormHtml() ) + '</div>'
		);
		applyCardFieldVisibility();
		repaintWorking();
	}

	function applyCardFieldVisibility() {
		if ( ! isCard ) { return; }
		$drawer.find( '[data-when]' ).each( function () {
			var uses = FIELD_LAYOUTS[ $( this ).data( 'when' ) ] || [];
			$( this ).toggle( uses.indexOf( W.layout ) !== -1 );
		} );
	}

	/** Repaint the drawer preview and, for an in-deck entry, its tile. */
	function repaintWorking() {
		paintWell( $drawer.find( '.fccar-d-preview' ), W );
		if ( ! isNew ) {
			var $tile = $deck.children( '.fccar-tile' ).filter( function () { return String( $( this ).data( 'id' ) ) === String( W.id ); } );
			if ( $tile.length ) {
				paintThumb( $tile, W );
				$tile.find( '.fccar-tname' ).text( W.title || W.srcTitle );
				$tile.find( '.fccar-badge--pre' ).remove();
				if ( W.preserviceOnly ) { $tile.find( '.fccar-badge' ).first().after( preBadge() ); }
				$tile.find( '.fccar-badge' ).first().text( W.layout );
			}
		}
	}

	function openDrawer( entry, opts ) {
		opts = opts || {};
		isNew = !! opts.isNew;
		isCard = isNew || ( entry && 'card' === entry.source );
		// Card edits are transactional (committed on Save); override edits ride
		// the live deck entry so they repaint the tile as you type.
		W = isCard ? $.extend( {}, entry ) : entry;
		renderDrawer();
		$back.prop( 'hidden', false );
		$drawer.prop( 'hidden', false ).attr( 'aria-hidden', 'false' ).addClass( 'is-open' );
		setTimeout( function () { $drawer.find( 'input,select,textarea' ).first().trigger( 'focus' ); }, 30 );
	}

	function closeDrawer() {
		$drawer.removeClass( 'is-open' ).prop( 'hidden', true ).attr( 'aria-hidden', 'true' ).empty();
		$back.prop( 'hidden', true );
		W = null; isCard = false; isNew = false;
	}

	/* open triggers */
	$deck.on( 'click', '.fccar-edit', function () {
		var e = entryById( String( $( this ).closest( '.fccar-tile' ).data( 'id' ) ) );
		if ( e ) { openDrawer( e ); }
	} );
	$( '#fccar-new-card' ).on( 'click', function () {
		openDrawer( { id: '', source: 'card', layout: 'info', srcTitle: '', srcImage: '', imageId: 0,
			body: '', prompt: '', details: '', ctaUrl: '', backgroundColor: '', preserviceOnly: false,
			title: '', when: '', image: '', srcWhen: '' }, { isNew: true } );
	} );
	$back.on( 'click', closeDrawer );
	$( document ).on( 'keydown', function ( ev ) { if ( 27 === ev.keyCode && ! $drawer.prop( 'hidden' ) ) { closeDrawer(); } } );
	$drawer.on( 'click', '.fccar-d-cancel', closeDrawer );

	/* live field edits. Override-mode edits mutate the live deck entry, so they
	 * mark the deck dirty; card-mode edits are transactional (committed on Save
	 * card over REST) and don't. */
	function touched() { if ( ! isCard ) { markDirty(); } }
	function bindIn( sel, fn ) {
		$drawer.on( 'input', sel, function () { fn( this.value, this ); repaintWorking(); touched(); } );
	}
	// Card-mode writers (edit the card's own fields, stored as source values).
	bindIn( '.fccar-d-title', function ( v ) { if ( isCard ) { W.srcTitle = v; } else { W.title = v; } } );
	bindIn( '.fccar-d-when', function ( v ) { W.when = v; } );
	bindIn( '.fccar-d-body', function ( v ) { W.body = v; } );
	bindIn( '.fccar-d-prompt', function ( v ) { W.prompt = v; } );
	bindIn( '.fccar-d-details', function ( v ) { W.details = v; } );
	bindIn( '.fccar-d-qr_url', function ( v ) { W.ctaUrl = v; } );
	bindIn( '.fccar-d-bg_color', function ( v ) { W.backgroundColor = v; } );
	$drawer.on( 'change', '.fccar-d-layout', function () { W.layout = this.value; applyCardFieldVisibility(); repaintWorking(); } );
	$drawer.on( 'change', '.fccar-d-presvc', function () { W.preserviceOnly = this.checked; repaintWorking(); touched(); } );

	$drawer.on( 'click', '.fccar-bg', function () {
		if ( ! window.wp || ! window.wp.media ) { return; }
		var frame = window.wp.media( { title: 'Background image', multiple: false, library: { type: 'image' } } );
		frame.on( 'select', function () {
			var a = frame.state().get( 'selection' ).first().toJSON();
			if ( isCard ) { W.srcImage = a.url; W.imageId = a.id; } else { W.image = a.url; }
			$drawer.find( '.fccar-bg' ).text( 'Replace…' );
			$drawer.find( '.fccar-bg-clear' ).show();
			repaintWorking();
			touched();
		} );
		frame.open();
	} );
	$drawer.on( 'click', '.fccar-bg-clear', function () {
		if ( isCard ) { W.srcImage = ''; W.imageId = 0; } else { W.image = ''; }
		$( this ).hide();
		$drawer.find( '.fccar-bg' ).text( ( isCard ? W.srcImage : ( W.image || W.srcImage ) ) ? 'Replace…' : 'Choose…' );
		repaintWorking();
		touched();
	} );

	/* save a standing card over REST, then fold the result back into the deck */
	$drawer.on( 'click', '.fccar-d-save', function () {
		var $btn = $( this ).prop( 'disabled', true ).text( 'Saving…' );
		var payload = {
			id: W.id || undefined,
			title: W.srcTitle || '', layout: W.layout || 'info',
			body: W.body || '', prompt: W.prompt || '', details: W.details || '',
			qr_url: W.ctaUrl || '', bg_color: W.backgroundColor || '',
			preservice: W.preserviceOnly ? 1 : 0, image_id: W.imageId || 0
		};
		fetch( D.restCardUrl, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': D.nonce },
			body: JSON.stringify( payload )
		} ).then( function ( r ) { return r.ok ? r.json() : r.json().then( function ( j ) { return Promise.reject( j ); } ); } )
			.then( function ( j ) {
				var it = j.item || {};
				var merged = {
					id: j.id, source: 'card', layout: it.layout || 'info',
					srcTitle: it.title || '', srcWhen: '', srcImage: it.image || '', imageId: j.imageId || 0,
					body: it.body || '', prompt: it.prompt || '', details: it.details || '',
					ctaUrl: it.ctaUrl || '', backgroundColor: it.backgroundColor || '',
					preserviceOnly: !! it.preserviceOnly, title: '', when: '', image: ''
				};
				if ( isNew ) {
					D.deck.push( merged );
				} else {
					var e = entryById( W.id );
					if ( e ) { $.extend( e, merged ); }
				}
				closeDrawer();
				renderDeck();
				markDirty(); // the card persisted, but its deck membership/order hasn't
				flash( 'is-ok', 'Card saved — Save deck to keep it in the lineup.' );
			} )
			.catch( function ( j ) {
				$btn.prop( 'disabled', false ).text( isNew ? 'Add card' : 'Save card' );
				flash( 'is-error', ( j && j.message ) ? j.message : 'Card save failed.' );
			} );
	} );

	/* ================= inline deck preview (the "show", not just the cards) ====
	 * Plays the CURRENT in-memory arrangement — what you'd publish, not the saved
	 * feed — so you can watch flow + timing before committing. Mirrors the kiosk's
	 * fade-through-black loop, fed from effItem() and filtered by variant. */
	var PV = { $modal: $( '#fccar-preview-modal' ), $deck: $( '#fccar-preview-deck' ), $empty: $( '#fccar-preview-empty' ),
		$counter: $( '#fccar-pv-counter' ), $play: $( '#fccar-pv-play' ),
		idx: 0, timer: null, playing: true, variant: 'preservice', secs: 5, n: 0 };

	function pvItems() {
		return D.deck.map( effItem ).filter( function ( it ) {
			return 'postservice' === PV.variant ? ! it.preserviceOnly : true;
		} );
	}
	function pvScale() {
		var wrap = PV.$deck.parent();
		var s = Math.min( wrap.width() / 1280, wrap.height() / 720 );
		PV.$deck[ 0 ].style.setProperty( '--fccar-pv-scale', s > 0 ? s : 1 );
	}
	function pvShow( i ) {
		var st = PV.$deck.children();
		for ( var k = 0; k < st.length; k++ ) { st[ k ].classList.toggle( 'is-active', k === i ); }
		PV.$counter.text( PV.n ? ( i + 1 ) + ' / ' + PV.n : '' );
	}
	function pvRender() {
		var items = pvItems();
		PV.n = items.length;
		PV.$deck.empty();
		items.forEach( function ( it ) {
			var stage = Card.buildStage( it, {} );
			stage.className += ' fccar-pv-stage';
			PV.$deck.append( stage );
		} );
		PV.$empty.prop( 'hidden', PV.n > 0 );
		PV.idx = 0;
		pvScale();
		pvShow( 0 );
	}
	function pvAdvance() {
		if ( PV.n < 2 ) { return; }
		var st = PV.$deck.children();
		var cur = st[ PV.idx ];
		PV.idx = ( PV.idx + 1 ) % PV.n;
		var next = PV.idx;
		if ( cur ) { cur.classList.remove( 'is-active' ); }
		setTimeout( function () { if ( st[ next ] ) { st[ next ].classList.add( 'is-active' ); } pvShow( next ); }, 420 );
	}
	function pvLoop() { clearInterval( PV.timer ); if ( PV.playing ) { PV.timer = setInterval( pvAdvance, PV.secs * 1000 ); } }
	function pvSetPlaying( on ) { PV.playing = on; PV.$play.text( on ? '⏸' : '▶' ); pvLoop(); }

	function pvOpen() {
		PV.$modal.prop( 'hidden', false ).attr( 'aria-hidden', 'false' ).addClass( 'is-open' );
		pvRender();
		pvSetPlaying( true );
	}
	function pvClose() {
		clearInterval( PV.timer );
		PV.$modal.removeClass( 'is-open' ).prop( 'hidden', true ).attr( 'aria-hidden', 'true' );
		PV.$deck.empty();
	}

	$( '#fccar-preview' ).on( 'click', pvOpen );
	$( '#fccar-pv-close' ).on( 'click', pvClose );
	$( '#fccar-pv-play' ).on( 'click', function () { pvSetPlaying( ! PV.playing ); } );
	$( '#fccar-pv-next' ).on( 'click', function () { pvSetPlaying( false ); pvAdvance(); } );
	$( '#fccar-pv-prev' ).on( 'click', function () {
		pvSetPlaying( false );
		if ( PV.n < 2 ) { return; }
		var st = PV.$deck.children();
		st[ PV.idx ].classList.remove( 'is-active' );
		PV.idx = ( PV.idx - 1 + PV.n ) % PV.n;
		st[ PV.idx ].classList.add( 'is-active' );
		pvShow( PV.idx );
	} );
	PV.$modal.find( '.fccar-preview-variant button' ).on( 'click', function () {
		PV.variant = $( this ).data( 'variant' );
		PV.$modal.find( '.fccar-preview-variant button' ).removeClass( 'is-active' );
		$( this ).addClass( 'is-active' );
		pvRender();
		pvLoop();
	} );
	$( window ).on( 'resize', function () { if ( ! PV.$modal.prop( 'hidden' ) ) { pvScale(); } } );
	$( document ).on( 'keydown', function ( ev ) { if ( 27 === ev.keyCode && ! PV.$modal.prop( 'hidden' ) ) { pvClose(); } } );

	/* ---- deck save / reset ---- */
	function flash( cls, msg ) { $status.removeClass( 'is-error is-ok' ).addClass( cls ).text( msg ); }

	function post( body, done ) {
		flash( '', 'Saving…' );
		fetch( D.restUrl, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': D.nonce },
			body: JSON.stringify( body )
		} ).then( function ( r ) { return r.ok ? r.json() : Promise.reject( r ); } )
			.then( function ( j ) { done( j ); } )
			.catch( function () { flash( 'is-error', 'Save failed.' ); } );
	}

	$( '#fccar-save' ).on( 'click', function () {
		var deck = D.deck.map( function ( e ) {
			return { id: e.id, title: e.title || '', when: e.when || '', image: e.image || '', preserviceOnly: !!e.preserviceOnly };
		} );
		post( { deck: deck }, function ( j ) {
			markClean();
			flash( 'is-ok', 'Published ' + ( j.count != null ? j.count : deck.length ) + ' cards to the live carousel.' );
		} );
	} );

	$( '#fccar-reset' ).on( 'click', function () {
		if ( ! window.confirm( 'Discard the curated deck and revert to the auto-assembled default?' ) ) { return; }
		post( { reset: true }, function () { markClean(); window.location.reload(); } );
	} );

	/* ---- draft restore: offer to bring back an unsaved prior session ---- */
	var pool = {};
	D.deck.concat( D.available ).forEach( function ( r ) { pool[ r.id ] = r; } );

	function applyDraftDeck( draftDeck ) {
		D.deck = draftDeck.slice();
		var inDeck = {};
		D.deck.forEach( function ( e ) { inDeck[ e.id ] = true; } );
		D.available = Object.keys( pool ).filter( function ( id ) { return ! inDeck[ id ]; } )
			.map( function ( id ) { return $.extend( {}, pool[ id ], { title: '', when: '', image: '' } ); } );
		renderDeck();
		renderAvail();
	}

	function maybeOfferDraft() {
		var raw;
		try { raw = window.localStorage.getItem( DRAFT_KEY ); } catch ( e ) { return; }
		if ( ! raw ) { return; }
		var draft;
		try { draft = JSON.parse( raw ); } catch ( e ) { clearDraft(); return; }
		if ( ! draft || ! Array.isArray( draft.deck ) ) { clearDraft(); return; }

		var when = draft.savedAt ? new Date( draft.savedAt ).toLocaleString() : 'a previous session';
		var $banner = $(
			'<div class="notice notice-warning fccar-draft-banner"><p>' +
				'You have <strong>unsaved deck changes</strong> from ' + esc( when ) + '. ' +
				'<button type="button" class="button button-small" id="fccar-draft-restore">Restore them</button> ' +
				'<button type="button" class="button-link" id="fccar-draft-discard">Discard</button>' +
			'</p></div>'
		);
		$( '.fccar-curate > h1' ).after( $banner );
		$banner.on( 'click', '#fccar-draft-restore', function () {
			applyDraftDeck( draft.deck );
			markDirty();
			$banner.remove();
			flash( 'is-ok', 'Restored your unsaved deck — Save to publish.' );
		} );
		$banner.on( 'click', '#fccar-draft-discard', function () { clearDraft(); $banner.remove(); } );
	}

	renderDeck();
	renderAvail();
	maybeOfferDraft();
}( jQuery ) );
