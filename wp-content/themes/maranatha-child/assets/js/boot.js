/**
 * First-party JS entry point ("boot").
 *
 * Loaded as a native ES module via the WordPress Script Modules API (see
 * inc/scripts.php) — deferred by default, no bundler, nothing built on prod.
 *
 * Pattern: small progressive-enhancement "islands". Each island is a module
 * that self-guards on the markup it needs, so boot can call them unconditionally
 * and an island simply no-ops on pages where its slot is absent. Island modules
 * are imported by their registered specifier (@firstchurch/…); WordPress emits
 * the import map and the versioned URLs.
 */
import { wireSkipLink } from '@firstchurch/skip-link';
import { mountWorshipLive } from '@firstchurch/worship-live';

/**
 * Run fn once the DOM is parsed. Script modules are deferred, so DOMContentLoaded
 * may already have fired by the time this executes — handle both cases.
 *
 * @param {() => void} fn
 */
function onReady(fn) {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', fn, { once: true });
	} else {
		fn();
	}
}

onReady(() => {
	wireSkipLink(document);
	mountWorshipLive(document);
});
