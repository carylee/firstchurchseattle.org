/*
 * Web glue for the shared card renderer. @church/carousel-card (built to
 * card-render.mjs) renders a card to an HTML string from PRE-RESOLVED assets;
 * this adapter supplies the web side's resolution — pass image URLs straight
 * through and generate the QR client-side from the vendored qrcode-generator
 * global (window.qrcode, loaded as a classic script first) — and returns a
 * ready .ann-stage element, the shape carousel.js / curate.js / edit-card.js /
 * list-cards.js consume. Replaces the old window.FCCarCard.buildStage so the
 * live page and the curation thumbnails render through the exact same engine
 * the slides GIF uses.
 */
import { renderCardHtml, withUtm } from '@fccar/card';

/** Smallest QR version that fits `text`, as a data URL. '' if the QR lib is
 *  absent (then the renderer simply omits the QR). */
function qrDataUrl( text ) {
	if ( ! window.qrcode || ! text ) { return ''; }
	for ( let t = 0; t <= 40; t++ ) {
		try {
			const qr = window.qrcode( t, 'M' );
			qr.addData( text );
			qr.make();
			return qr.createDataURL( 8, 32 ); // 8px cells, 4-module quiet zone
		} catch ( e ) { /* too small for this version — grow */ }
	}
	return '';
}

/** Resolve one feed item's image + QR into the renderer's `assets`. Image URLs
 *  pass through unchanged; the QR target is absolutized against the page origin
 *  (so a relative ctaUrl still scans) and UTM-tagged carousel/screen_qr. */
export function cardAssets( item, opts ) {
	opts = opts || {};
	const assets = {};
	if ( item.image ) { assets.image = item.image; }
	if ( item.logo ) { assets.logo = item.logo; }
	if ( item.ctaUrl ) {
		let target = item.ctaUrl;
		try { target = new URL( item.ctaUrl, window.location.origin ).toString(); } catch ( e ) {}
		assets.qr = qrDataUrl( withUtm( target, {
			source: 'carousel',
			medium: 'screen_qr',
			campaign: opts.campaign || undefined,
		} ) );
	}
	return assets;
}

/** Build a ready-to-mount .ann-stage element for one resolved feed item. */
export function buildStageEl( item, opts ) {
	const tmp = window.document.createElement( 'div' );
	tmp.innerHTML = renderCardHtml( item, cardAssets( item, opts ) );
	return tmp.firstElementChild;
}
