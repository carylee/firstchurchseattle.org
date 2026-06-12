/**
 * Skip-link island.
 *
 * The accessibility skip link (<a href="#main-content">) is rendered at
 * wp_body_open, but the parent theme's templates give <main> different ids
 * (#maranatha-content on most pages, #maranatha-home-main on the homepage), so
 * there is no single stable anchor to target. This injects a hidden, focusable
 * #main-content span at the top of whichever <main> exists and makes that <main>
 * programmatically focusable, so the link works everywhere.
 *
 * Pure and idempotent: takes the document, returns whether it wired anything.
 *
 * @param {Document} doc
 * @returns {boolean} true if the anchor was injected, false otherwise.
 */
export function wireSkipLink(doc = document) {
	const main = doc.querySelector('main[id]');
	if (!main || doc.getElementById('main-content')) {
		return false;
	}

	main.setAttribute('tabindex', '-1');

	const anchor = doc.createElement('span');
	anchor.id = 'main-content';
	anchor.setAttribute('tabindex', '-1');
	anchor.style.cssText =
		'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);';
	main.insertBefore(anchor, main.firstChild);

	return true;
}
