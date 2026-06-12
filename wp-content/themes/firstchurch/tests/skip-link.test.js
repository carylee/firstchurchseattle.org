import { beforeEach, describe, expect, it } from 'vitest';
import { wireSkipLink } from '../assets/js/islands/skip-link.js';

// The theme renders the skip link's <a href="#main-content"> at wp_body_open,
// but the templates' <main> uses varying ids (#maranatha-content,
// #maranatha-home-main). wireSkipLink() makes the link work on every page by
// injecting a stable #main-content target into whichever <main> exists and
// making it focusable. This mirrors the old inline wp_head script, now a module.

describe('wireSkipLink', () => {
	beforeEach(() => {
		document.head.innerHTML = '';
		document.body.innerHTML = '';
	});

	it('injects a #main-content anchor as the first child of <main>', () => {
		document.body.innerHTML = '<main id="maranatha-content"><p>hi</p></main>';

		const result = wireSkipLink(document);

		const main = document.querySelector('main');
		const anchor = document.getElementById('main-content');
		expect(result).toBe(true);
		expect(anchor).not.toBeNull();
		expect(anchor.parentElement).toBe(main);
		expect(main.firstChild).toBe(anchor);
	});

	it('makes the <main> programmatically focusable (tabindex=-1)', () => {
		document.body.innerHTML = '<main id="maranatha-home-main"></main>';

		wireSkipLink(document);

		expect(document.querySelector('main').getAttribute('tabindex')).toBe('-1');
	});

	it('is idempotent — a second call adds no duplicate anchor', () => {
		document.body.innerHTML = '<main id="maranatha-content"></main>';

		wireSkipLink(document);
		const second = wireSkipLink(document);

		expect(second).toBe(false);
		expect(document.querySelectorAll('#main-content')).toHaveLength(1);
	});

	it('does nothing when there is no <main id>', () => {
		document.body.innerHTML = '<div>no main here</div>';

		expect(wireSkipLink(document)).toBe(false);
		expect(document.getElementById('main-content')).toBeNull();
	});
});
