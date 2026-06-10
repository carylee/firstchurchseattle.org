/* global wp, fcspData, jQuery */
/**
 * "Stock Photos" tab inside the shared wp.media modal.
 *
 * Every classic-side media flow — Add Media, the featured-image metabox,
 * galleries — and the core Gutenberg image/cover blocks open the same
 * wp.media frame. By extending the two frame types (Post + Select) with one
 * extra menu item + content view, the picker appears in all of them at once.
 *
 * Picking a photo sideloads it through the existing REST import (window.fcspStock,
 * recording provenance) and then selects the fresh attachment in the frame's
 * library, so the host's own primary action (Insert / Set featured image)
 * finishes the job. We deliberately do NOT pass a post_id on import — that would
 * also stamp it as the featured image, which is wrong for a plain block insert.
 */
( function ( $ ) {
	'use strict';

	if ( ! window.wp || ! wp.media || ! window.fcspStock || ! window.fcspData ) {
		return;
	}

	var media = wp.media;
	var TAB = 'firstchurch-stock';
	var strings = fcspData.i18n || {};
	var title = fcspData.tabTitle || 'Stock Photos';

	/* ------------------------------------------------------------------ *
	 * State (a media "controller") — browsing-only, no toolbar of its own.
	 * ------------------------------------------------------------------ */
	var StockState = media.controller.State.extend( {
		defaults: {
			id: TAB,
			title: title,
			menu: 'default',
			content: TAB,
			toolbar: false,
			priority: 80
		}
	} );

	/* ------------------------------------------------------------------ *
	 * Content view — search box + results grid, driven by fcspStock.
	 * ------------------------------------------------------------------ */
	var StockView = media.View.extend( {
		className: 'fcsp-media-tab',

		render: function () {
			this.$el.html(
				'<form class="fcsp-media-form">' +
					'<input type="search" class="fcsp-media-q" placeholder="' + esc( strings.search || 'Search photos…' ) + '" autocomplete="off">' +
					'<button type="submit" class="button button-primary">' + esc( strings.searchBtn || 'Search' ) + '</button>' +
				'</form>' +
				'<p class="fcsp-status description" aria-live="polite"></p>' +
				'<div class="fcsp-grid"></div>'
			);
			this.$q = this.$el.find( '.fcsp-media-q' );
			this.$status = this.$el.find( '.fcsp-status' );
			this.$grid = this.$el.find( '.fcsp-grid' );
			return this;
		},

		events: {
			'submit .fcsp-media-form': 'onSubmit'
		},

		onSubmit: function ( e ) {
			e.preventDefault();
			var q = $.trim( this.$q.val() );
			if ( ! q ) {
				return;
			}
			var self = this;
			this.$grid.empty();
			this.$status.text( strings.searching || 'Searching…' );

			window.fcspStock.search( { q: q, count: 30 } )
				.then( function ( data ) {
					var results = ( data && data.results ) || [];
					if ( ! results.length ) {
						self.$status.text( strings.noResults || 'No photos found.' );
						return;
					}
					self.$status.text( '' );
					results.forEach( function ( item ) {
						self.$grid.append( self.card( item ) );
					} );
				} )
				.catch( function ( err ) {
					self.$status.text( ( strings.failed || 'Search failed.' ) + ' ' + ( err.message || '' ) );
				} );
		},

		card: function ( item ) {
			var self = this;
			var $card = $( '<div class="fcsp-card"></div>' );
			var $thumb = $( '<span class="fcsp-thumb"></span>' );
			$( '<img loading="lazy">' )
				.attr( 'src', item.thumbnail || item.url )
				.attr( 'alt', item.title || '' )
				.appendTo( $thumb );
			if ( item.width && item.height ) {
				$( '<span class="fcsp-dims"></span>' ).text( item.width + ' × ' + item.height ).appendTo( $thumb );
			}
			$card.append( $thumb );

			var credit = item.creator || item.source || '';
			if ( credit ) {
				$( '<div class="fcsp-meta"></div>' ).text( credit ).appendTo( $card );
			}

			var $btn = $( '<button type="button" class="button fcsp-use"></button>' ).text( strings.use || 'Use this photo' );
			$btn.on( 'click', function () {
				self.useItem( item, $btn );
			} );
			$( '<div class="fcsp-actions"></div>' ).append( $btn ).appendTo( $card );
			return $card;
		},

		useItem: function ( item, $btn ) {
			var self = this;
			$btn.prop( 'disabled', true ).text( strings.adding || 'Adding…' );
			window.fcspStock.importPhoto( item )
				.then( function ( data ) {
					if ( ! data || ! data.attachment_id ) {
						throw new Error( ( data && data.message ) || 'Import failed.' );
					}
					return self.selectInFrame( data.attachment_id );
				} )
				.catch( function ( err ) {
					$btn.prop( 'disabled', false ).text( strings.use || 'Use this photo' );
					self.$status.text( ( strings.failed || 'Import failed.' ) + ' ' + ( err.message || '' ) );
				} );
		},

		/**
		 * Hand the freshly imported attachment back to whatever opened the modal:
		 * jump to the frame's library-browsing state and select it, so the host's
		 * own Insert / Set-featured-image button takes over.
		 */
		selectInFrame: function ( attachmentId ) {
			var frame = this.controller;
			var attachment = media.attachment( attachmentId );
			return attachment.fetch().then( function () {
				var target = [ 'insert', 'library', 'featured-image' ].filter( function ( id ) {
					return frame.states.get( id );
				} )[ 0 ];
				if ( target ) {
					frame.setState( target );
				}
				var selection = frame.state().get( 'selection' );
				if ( selection ) {
					selection.reset( attachment ? [ attachment ] : [] );
				}
			} );
		}
	} );

	/* ------------------------------------------------------------------ *
	 * Splice the state + menu item into both frame types. Guarded so a
	 * second enqueue can't double-wrap the prototype.
	 * ------------------------------------------------------------------ */
	function extend( FrameType ) {
		if ( ! FrameType ) {
			return FrameType;
		}
		return FrameType.extend( {
			initialize: function () {
				FrameType.prototype.initialize.apply( this, arguments );
				this.states.add( [ new StockState() ] );
				this.on( 'content:create:' + TAB, this.fcspContent, this );
				this.on( 'menu:render:default', this.fcspMenu, this );
			},
			fcspContent: function ( region ) {
				region.view = new StockView( { controller: this } );
			},
			fcspMenu: function ( view ) {
				view.set( TAB, { text: title, priority: 80 } );
			}
		} );
	}

	if ( ! media.view.MediaFrame.Post.prototype.__fcsp ) {
		media.view.MediaFrame.Post = extend( media.view.MediaFrame.Post );
		media.view.MediaFrame.Post.prototype.__fcsp = true;
	}
	if ( ! media.view.MediaFrame.Select.prototype.__fcsp ) {
		media.view.MediaFrame.Select = extend( media.view.MediaFrame.Select );
		media.view.MediaFrame.Select.prototype.__fcsp = true;
	}

	function esc( s ) {
		return $( '<div>' ).text( s == null ? '' : s ).html();
	}
} )( jQuery );
