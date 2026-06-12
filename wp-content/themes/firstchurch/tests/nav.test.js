import { Window } from 'happy-dom';
import { beforeEach, describe, expect, it } from 'vitest';
import { mountNav } from '../assets/js/islands/nav.js';

/** Build a minimal header DOM matching header.php's contract. */
function makeDoc() {
	const window = new Window();
	const doc = window.document;
	doc.body.innerHTML = `
		<header data-fcs-nav>
			<nav class="fcs-nav">
				<ul class="fcs-nav__list">
					<li class="menu-item-has-children">
						<a href="/about/">About</a>
						<ul class="sub-menu"><li><a href="/about/staff-2/">Staff</a></li></ul>
					</li>
				</ul>
			</nav>
			<button class="fcs-header__btn fcs-search-toggle" aria-expanded="false" aria-controls="fcs-search"></button>
			<button class="fcs-header__btn fcs-nav-toggle" aria-expanded="false" aria-controls="fcs-mobile"></button>
			<div id="fcs-search" hidden><form><input type="search"></form></div>
			<div id="fcs-mobile" hidden></div>
		</header>`;
	return doc;
}

describe('nav island', () => {
	let doc;

	beforeEach(() => {
		doc = makeDoc();
		mountNav(doc);
	});

	it('is idempotent', () => {
		mountNav(doc); // second mount must not double-wire
		doc.querySelector('.fcs-nav-toggle').click();
		expect(doc.getElementById('fcs-mobile').hidden).toBe(false);
	});

	it('toggles the mobile panel and locks body scroll', () => {
		const btn = doc.querySelector('.fcs-nav-toggle');
		btn.click();
		expect(btn.getAttribute('aria-expanded')).toBe('true');
		expect(doc.getElementById('fcs-mobile').hidden).toBe(false);
		expect(doc.body.classList.contains('fcs-nav-open')).toBe(true);
		btn.click();
		expect(doc.getElementById('fcs-mobile').hidden).toBe(true);
		expect(doc.body.classList.contains('fcs-nav-open')).toBe(false);
	});

	it('opening search closes the mobile panel', () => {
		doc.querySelector('.fcs-nav-toggle').click();
		doc.querySelector('.fcs-search-toggle').click();
		expect(doc.getElementById('fcs-search').hidden).toBe(false);
		expect(doc.getElementById('fcs-mobile').hidden).toBe(true);
	});

	it('marks parent menu items with aria attributes', () => {
		const parent = doc.querySelector('.menu-item-has-children > a');
		expect(parent.getAttribute('aria-haspopup')).toBe('true');
		expect(parent.getAttribute('aria-expanded')).toBe('false');
	});

	it('Escape closes an open panel', () => {
		doc.querySelector('.fcs-search-toggle').click();
		doc.dispatchEvent(
			new doc.defaultView.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
		);
		expect(doc.getElementById('fcs-search').hidden).toBe(true);
	});
});
