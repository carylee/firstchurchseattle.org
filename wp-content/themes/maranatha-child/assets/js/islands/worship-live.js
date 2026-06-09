/**
 * Worship-live island.
 *
 * Renders a small status line on /worship/live/: "● Live now" during the Sunday
 * service, otherwise "Next service: <day> · 10:30 AM PT". Progressive
 * enhancement — the slot ships `hidden` with a static fallback nearby, so a
 * no-JS visitor still sees the standing service time.
 *
 * The schedule helpers (isLive / nextService) are pure and exported so they can
 * be unit-tested without a DOM. They live here (rather than a separate lib file)
 * so every shipped module gets a WordPress-versioned URL — there are no
 * unversioned relative sub-imports to go stale behind Cloudflare.
 */

const TZ = 'America/Los_Angeles';
const SERVICE_DOW = 0; // Sunday
const SERVICE_HOUR = 10;
const SERVICE_MINUTE = 30;
const SERVICE_DURATION_MIN = 90;
const START_MIN = SERVICE_HOUR * 60 + SERVICE_MINUTE;

const DOW = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };

const WALL = new Intl.DateTimeFormat('en-US', {
	timeZone: TZ,
	hour12: false,
	weekday: 'short',
	year: 'numeric',
	month: '2-digit',
	day: '2-digit',
	hour: '2-digit',
	minute: '2-digit',
	second: '2-digit',
});

const DAY_LABEL = new Intl.DateTimeFormat('en-US', {
	timeZone: TZ,
	weekday: 'long',
	month: 'short',
	day: 'numeric',
});

/** Wall-clock parts of an instant, in Pacific time. */
function wall(date) {
	const p = {};
	for (const part of WALL.formatToParts(date)) {
		p[part.type] = part.value;
	}
	const hour = Number(p.hour) % 24; // some engines render midnight as "24"
	return {
		weekday: DOW[p.weekday],
		year: Number(p.year),
		month: Number(p.month),
		day: Number(p.day),
		minutes: hour * 60 + Number(p.minute),
	};
}

/** Offset (Pacific wall time minus true UTC), in ms, at a given instant. */
function offsetMs(date) {
	const p = {};
	for (const part of WALL.formatToParts(date)) {
		p[part.type] = part.value;
	}
	const asUTC = Date.UTC(+p.year, +p.month - 1, +p.day, +p.hour % 24, +p.minute, +p.second);
	return asUTC - date.getTime();
}

/** The instant for a Pacific wall-clock date/time, correct across DST. */
function instantFor(year, month0, day, hour, minute) {
	const naive = Date.UTC(year, month0, day, hour, minute, 0);
	const offset = offsetMs(new Date(naive));
	let inst = new Date(naive - offset);
	const refined = offsetMs(inst);
	if (refined !== offset) {
		inst = new Date(naive - refined);
	}
	return inst;
}

/**
 * Is a Sunday service in progress at `now`?
 *
 * @param {Date} now
 * @returns {boolean}
 */
export function isLive(now = new Date()) {
	const w = wall(now);
	return (
		w.weekday === SERVICE_DOW &&
		w.minutes >= START_MIN &&
		w.minutes < START_MIN + SERVICE_DURATION_MIN
	);
}

/**
 * The instant of the next Sunday 10:30 service. While a service is in progress
 * it returns that (in-progress) service's start, so callers can render "live".
 *
 * @param {Date} now
 * @returns {Date}
 */
export function nextService(now = new Date()) {
	const w = wall(now);
	let daysAhead = (SERVICE_DOW - w.weekday + 7) % 7;
	if (daysAhead === 0 && w.minutes >= START_MIN + SERVICE_DURATION_MIN) {
		daysAhead = 7; // today's service already ended
	}
	// Advance the Pacific calendar date via UTC midnights (date math only).
	const target = new Date(Date.UTC(w.year, w.month - 1, w.day) + daysAhead * 86400000);
	return instantFor(
		target.getUTCFullYear(),
		target.getUTCMonth(),
		target.getUTCDate(),
		SERVICE_HOUR,
		SERVICE_MINUTE,
	);
}

/**
 * Fill the worship-live status slot, if present, and reveal it.
 *
 * @param {Document} doc
 * @param {Date} now
 * @returns {boolean} true if a slot was found and rendered.
 */
export function mountWorshipLive(doc = document, now = new Date()) {
	const slot = doc.querySelector('[data-island="worship-live"]');
	if (!slot) {
		return false;
	}

	if (isLive(now)) {
		slot.textContent = 'Live now'; // the pulsing dot is a CSS ::before
		slot.classList.add('is-live');
	} else {
		slot.textContent = `Next service: ${DAY_LABEL.format(nextService(now))} · 10:30 AM PT`;
		slot.classList.remove('is-live');
	}

	slot.hidden = false;
	return true;
}
