<?php

declare(strict_types=1);

namespace FirstChurch\Happenings;

/**
 * Classifies an event into one of three kinds so surfaces can treat them
 * differently (ops/docs/event-kinds.md):
 *
 *   rhythm — a standing weekly pattern with no end date (the Sunday liturgy:
 *            breakfast, centering prayer, worship). How the church works, not
 *            news: belongs in an "Every Sunday" strip, not a date-sorted rail,
 *            where its always-imminent next occurrence would win every slot.
 *   group  — a monthly recurring gathering (book club, support group). An
 *            ongoing community you join; the cadence matters more than any
 *            particular next date.
 *   event  — everything time-bound: one-offs, bounded weekly series (weekly
 *            WITH an end date — promotable, not furniture), and yearly
 *            occasions. These own the date-sorted rails.
 *
 * derive() takes the same plain array of already-extracted meta that
 * EventWhen::format() consumes (no get_post_meta), so the rule is
 * unit-testable. An explicit 'kind' field — the _fce_kind override meta — wins
 * over derivation, for the cases the rule misreads (e.g. a weekly drop-in that
 * should read as a group).
 */
final class Kind
{
    public const RHYTHM = 'rhythm';
    public const GROUP  = 'group';
    public const EVENT  = 'event';

    public const ALL = [self::RHYTHM, self::GROUP, self::EVENT];

    /** @param array<string,mixed> $e Same fields array as EventWhen::format(), plus optional 'kind'/'end_date'. */
    public static function derive(array $e): string
    {
        $override = strtolower(trim((string) ($e['kind'] ?? '')));
        if (in_array($override, self::ALL, true)) {
            return $override;
        }

        $freq = strtolower(trim((string) ($e['freq'] ?? '')));
        if ($freq === 'weekly') {
            return trim((string) ($e['end_date'] ?? '')) === '' ? self::RHYTHM : self::EVENT;
        }
        if ($freq === 'monthly') {
            return self::GROUP;
        }

        return self::EVENT; // one-offs ('', 'none') and yearly occasions
    }
}
