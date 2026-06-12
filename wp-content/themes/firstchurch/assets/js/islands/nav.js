/**
 * Header nav island: mobile menu + search toggles, touch/keyboard support for
 * the CSS dropdown menus, scroll lock, Escape-to-close.
 *
 * Progressive enhancement over header.php's markup (`[data-fcs-nav]`): the
 * desktop dropdowns already work on hover/focus via CSS; this adds
 * aria-expanded state, makes the first tap on a parent item open its submenu
 * on touch devices (second tap follows the link), and wires the two header
 * buttons.
 *
 * Exported for unit tests; mountNav() is idempotent and self-guards on markup.
 */

const OPEN_CLASS = 'fcs-nav-open';

/**
 * Close every touch-opened dropdown except the one passed.
 * @param {Element} root
 * @param {Element|null} except
 */
function closeDropdowns(root, except = null) {
	for (const link of root.querySelectorAll('.fcs-nav__list [aria-expanded="true"]')) {
		if (link !== except) link.setAttribute('aria-expanded', 'false');
	}
}

/**
 * Wire one toggle button to its `aria-controls` panel. Returns the panel.
 * @param {Document} doc
 * @param {Element} btn
 * @param {(open: boolean) => void} onToggle
 */
function wireToggle(doc, btn, onToggle) {
	const panel = doc.getElementById(btn.getAttribute('aria-controls') || '');
	if (!panel) return null;
	btn.addEventListener('click', () => {
		const open = btn.getAttribute('aria-expanded') !== 'true';
		btn.setAttribute('aria-expanded', String(open));
		panel.hidden = !open;
		onToggle(open);
	});
	return panel;
}

/**
 * Mount the nav behaviours. Safe to call on any document; does nothing
 * without the header markup.
 * @param {Document} doc
 */
export function mountNav(doc) {
	const header = doc.querySelector('[data-fcs-nav]');
	if (!header || header.dataset.fcsNavMounted) return;
	header.dataset.fcsNavMounted = 'true';

	const navToggle = header.querySelector('.fcs-nav-toggle');
	const searchToggle = header.querySelector('.fcs-search-toggle');
	const body = doc.body;

	let mobilePanel = null;
	let searchPanel = null;

	if (navToggle) {
		mobilePanel = wireToggle(doc, navToggle, (open) => {
			body.classList.toggle(OPEN_CLASS, open);
			// Opening one panel closes the other.
			if (open && searchToggle && searchToggle.getAttribute('aria-expanded') === 'true') {
				searchToggle.click();
			}
		});
	}

	if (searchToggle) {
		searchPanel = wireToggle(doc, searchToggle, (open) => {
			if (open) {
				if (navToggle && navToggle.getAttribute('aria-expanded') === 'true') navToggle.click();
				const input = searchPanel?.querySelector('input[type="search"]');
				if (input) input.focus();
			}
		});
	}

	// Desktop dropdowns: tap-to-open on devices without hover. The first
	// activation of a parent link opens its submenu instead of navigating;
	// activating it again follows the link.
	const noHover = doc.defaultView
		? doc.defaultView.matchMedia('(hover: none)')
		: { matches: false };
	for (const item of header.querySelectorAll('.fcs-nav__list > .menu-item-has-children > a')) {
		item.setAttribute('aria-haspopup', 'true');
		item.setAttribute('aria-expanded', 'false');
		item.addEventListener('click', (e) => {
			if (!noHover.matches) return;
			if (item.getAttribute('aria-expanded') !== 'true') {
				e.preventDefault();
				closeDropdowns(header, item);
				item.setAttribute('aria-expanded', 'true');
			}
		});
	}

	// Click outside the menu closes touch-opened dropdowns.
	doc.addEventListener('click', (e) => {
		if (!(e.target instanceof Element) || !e.target.closest('.fcs-nav__list')) {
			closeDropdowns(header);
		}
	});

	// Escape closes whatever is open, returning focus to its toggle.
	doc.addEventListener('keydown', (e) => {
		if (e.key !== 'Escape') return;
		closeDropdowns(header);
		for (const btn of [navToggle, searchToggle]) {
			if (btn && btn.getAttribute('aria-expanded') === 'true') {
				btn.click();
				btn.focus();
			}
		}
	});
}
