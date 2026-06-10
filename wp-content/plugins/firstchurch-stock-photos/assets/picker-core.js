/* global fcspData */
/**
 * Shared search/import core for every Stock Photos surface.
 *
 * UI-agnostic: it knows nothing about the DOM. Each surface (the standalone
 * Media ▸ Stock Photos page, the wp.media modal tab, the block editor) renders
 * its own chrome and calls into `window.fcspStock` for the two REST round-trips.
 * Both endpoints + the nonce arrive via the localized `fcspData` object.
 */
( function () {
	'use strict';

	var api = ( window.fcspStock = window.fcspStock || {} );

	/** Resolve a fetch() Promise to JSON, surfacing REST error messages. */
	function toJson( res ) {
		return res.json().then( function ( body ) {
			if ( ! res.ok ) {
				throw new Error( ( body && body.message ) || ( 'HTTP ' + res.status ) );
			}
			return body;
		} );
	}

	/**
	 * Search a provider.
	 *
	 * @param {Object} params { q, count, orientation, provider, page }
	 * @return {Promise<Object>} { results, total, page, page_count, provider }
	 */
	function search( params ) {
		params = params || {};
		var url = new URL( fcspData.searchUrl );
		url.searchParams.set( 'q', params.q || '' );
		url.searchParams.set( 'count', params.count || 24 );
		if ( params.orientation ) {
			url.searchParams.set( 'orientation', params.orientation );
		}
		if ( params.provider ) {
			url.searchParams.set( 'provider', params.provider );
		}
		if ( params.page ) {
			url.searchParams.set( 'page', params.page );
		}
		return fetch( url.toString(), {
			headers: { 'X-WP-Nonce': fcspData.nonce }
		} ).then( toJson );
	}

	/** Map a search-result item to the import endpoint's POST body. */
	function importBody( item, postId ) {
		var body = {
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
			foreign_url: item.foreign_url,
			download_location: item.download_location
		};
		if ( postId ) {
			body.post_id = postId;
		}
		return body;
	}

	/**
	 * Sideload a chosen photo into the media library (recording provenance).
	 *
	 * @param {Object} item   A search-result item.
	 * @param {number} postId Optional post to also set this image as featured on.
	 * @return {Promise<Object>} { attachment_id, attachment_url, alt, credit }
	 */
	function importPhoto( item, postId ) {
		return fetch( fcspData.importUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': fcspData.nonce
			},
			body: JSON.stringify( importBody( item, postId ) )
		} ).then( toJson );
	}

	api.toJson = toJson;
	api.search = search;
	api.importBody = importBody;
	api.importPhoto = importPhoto;
} )();
