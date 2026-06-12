import { beforeEach, describe, expect, it } from 'vitest';
import { isLive, mountWorshipLive, nextService } from '../assets/js/islands/worship-live.js';

// Sunday worship is 10:30 AM Pacific. These pin the timezone math (incl. DST):
// in summer 10:30 PDT = 17:30Z, in winter 10:30 PST = 18:30Z.
const at = (iso) => new Date(iso);

describe('isLive', () => {
	it('is true during the Sunday window (PDT)', () => {
		expect(isLive(at('2026-06-14T17:45:00Z'))).toBe(true); // Sun 10:45 PDT
	});
	it('is true during the Sunday window (PST)', () => {
		expect(isLive(at('2026-01-18T18:45:00Z'))).toBe(true); // Sun 10:45 PST
	});
	it('is false after the service ends', () => {
		expect(isLive(at('2026-06-14T19:30:00Z'))).toBe(false); // Sun 12:30 PDT
	});
	it('is false before the service starts', () => {
		expect(isLive(at('2026-01-18T17:00:00Z'))).toBe(false); // Sun 09:00 PST
	});
	it('is false on a non-Sunday', () => {
		expect(isLive(at('2026-06-13T18:00:00Z'))).toBe(false); // Sat 11:00 PDT
	});
});

describe('nextService', () => {
	it('returns the upcoming Sunday 10:30 from a weekday (PDT = 17:30Z)', () => {
		expect(nextService(at('2026-06-17T12:00:00Z')).toISOString()) // Wed
			.toBe('2026-06-21T17:30:00.000Z');
	});
	it('returns the in-progress service while it is live', () => {
		expect(nextService(at('2026-06-14T17:45:00Z')).toISOString()) // Sun 10:45 PDT
			.toBe('2026-06-14T17:30:00.000Z');
	});
	it('rolls to next week once the service has ended', () => {
		expect(nextService(at('2026-06-14T19:30:00Z')).toISOString()) // Sun 12:30 PDT
			.toBe('2026-06-21T17:30:00.000Z');
	});
	it('is DST-correct in winter (10:30 PST = 18:30Z)', () => {
		expect(nextService(at('2026-01-14T12:00:00Z')).getUTCHours()).toBe(18); // Wed
	});
});

describe('mountWorshipLive', () => {
	beforeEach(() => {
		document.body.innerHTML = '';
	});

	it('does nothing (and returns false) without a slot', () => {
		expect(mountWorshipLive(document, at('2026-06-17T12:00:00Z'))).toBe(false);
	});

	it('shows the next service when not live', () => {
		document.body.innerHTML = '<p data-island="worship-live" hidden></p>';
		const ok = mountWorshipLive(document, at('2026-06-17T12:00:00Z')); // Wed
		const slot = document.querySelector('[data-island="worship-live"]');
		expect(ok).toBe(true);
		expect(slot.hidden).toBe(false);
		expect(slot.textContent).toMatch(/next service/i);
	});

	it('shows a live indicator during the service', () => {
		document.body.innerHTML = '<p data-island="worship-live" hidden></p>';
		mountWorshipLive(document, at('2026-06-14T17:45:00Z')); // Sun 10:45 PDT
		const slot = document.querySelector('[data-island="worship-live"]');
		expect(slot.textContent).toMatch(/live/i);
	});
});
