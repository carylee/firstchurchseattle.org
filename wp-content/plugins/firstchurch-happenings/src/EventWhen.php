<?php

declare(strict_types=1);

namespace FirstChurch\Happenings;

/**
 * Formats a CTC event's date/recurrence/time into the human "when" string an
 * event card prints — e.g. "Sundays at 10:30 am · Sanctuary", "Every other
 * Friday", "Every 4th Friday", "April 12 at 7:00 pm".
 *
 * format() takes a plain array of already-extracted meta (no get_post_meta), so
 * all the recurrence/time logic is unit-testable. The WP reader
 * happenings_event_when() builds that array from the _ctc_event_* meta.
 *
 * @phpstan-type EventFields array{
 *   start?:string, freq?:string, venue?:string, start_time?:string,
 *   time_text?:string, weekly_interval?:int|string, weekly_days?:string,
 *   monthly_type?:string, monthly_week?:string
 * }
 */
final class EventWhen
{
    /** @param array<string,mixed> $e */
    public static function format(array $e): string
    {
        $start   = (string) ($e['start'] ?? '');
        $freq    = (string) ($e['freq'] ?? '');
        $venue   = (string) ($e['venue'] ?? '');
        $whenTs  = $start ? strtotime($start) : false;

        $lead = '';
        if ($freq !== '' && $freq !== 'none') {
            $lead = self::recurrence($e, $freq, $whenTs);
        } elseif ($whenTs) {
            $lead = date_i18n('F j', $whenTs); // "April 12"
        }

        // The time slot. Prefer the machine start_time (a real clock value); the
        // human time_text field is free text staff sometimes fill with a room or
        // a phrase ("After the worship service"). A clock value joins with
        // " at "; anything else becomes a trailing " · " descriptor.
        $clock = '';
        $descr = '';
        $st    = trim((string) ($e['start_time'] ?? ''));
        $human = (string) ($e['time_text'] ?? '');
        if (preg_match('/^\d{1,2}:\d{2}/', $st)) {
            $clock = date_i18n('g:i a', (int) strtotime('2000-01-01 ' . $st));
            if ($human !== '' && !Text::isClocklike($human)) {
                $descr = $human;
            }
        } elseif ($human !== '') {
            Text::isClocklike($human) ? ($clock = $human) : ($descr = $human);
        }

        $out = $lead;
        if ($clock !== '') {
            $out = trim($out . ($out !== '' ? ' at ' : '') . $clock);
        }
        foreach ([$descr, $venue] as $tail) {
            if ($tail !== '') {
                $out = $out !== '' ? $out . ' · ' . $tail : $tail;
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $e
     * @param int|false           $whenTs
     */
    private static function recurrence(array $e, string $freq, $whenTs): string
    {
        $weekday = $whenTs ? date_i18n('l', $whenTs) : '';

        if ($freq === 'weekly') {
            $interval = max(1, (int) ($e['weekly_interval'] ?? 0));
            $names    = self::weekdayNames((string) ($e['weekly_days'] ?? ''));
            if (!$names && $weekday) {
                $names = [$weekday];
            }
            $plural = array_map(static fn (string $n): string => $n . 's', $names);
            $joined = self::joinList($plural);
            if ($interval >= 2) {
                return $interval === 2
                    ? 'Every other ' . self::joinList($names)
                    : 'Every ' . self::ordinal($interval) . ' week (' . $joined . ')';
            }

            return $joined; // "Sundays", "Tuesdays & Thursdays"
        }

        if ($freq === 'monthly') {
            $type = (string) ($e['monthly_type'] ?? '');
            if ($type === 'week') {
                $weeks  = array_filter(array_map('trim', explode(',', (string) ($e['monthly_week'] ?? ''))));
                $labels = array_map(
                    static fn (string $w): string => strtolower($w) === 'last' ? 'last' : self::ordinal((int) $w),
                    $weeks
                );
                $wk = $labels ? self::joinList($labels) : '';

                return trim('Every ' . trim($wk . ' ' . $weekday)); // "Every 4th Friday"
            }

            return $weekday ? 'Monthly on ' . $weekday : 'Monthly';
        }

        if ($freq === 'yearly') {
            return $whenTs ? 'Annually on ' . date_i18n('F j', $whenTs) : 'Annually';
        }

        return '';
    }

    /**
     * Map a CSV of CTC weekday codes (SU,MO,…) to full weekday names, in order.
     *
     * @return list<string>
     */
    private static function weekdayNames(string $csv): array
    {
        $map = [
            'SU' => 'Sunday', 'MO' => 'Monday', 'TU' => 'Tuesday', 'WE' => 'Wednesday',
            'TH' => 'Thursday', 'FR' => 'Friday', 'SA' => 'Saturday',
        ];
        $out = [];
        foreach (array_filter(array_map('trim', explode(',', $csv))) as $code) {
            $code = strtoupper(substr($code, 0, 2));
            if (isset($map[$code])) {
                $out[] = $map[$code];
            }
        }

        return $out;
    }

    private static function ordinal(int $n): string
    {
        $suffix = 'th';
        if ($n % 100 < 11 || $n % 100 > 13) {
            $suffix = ['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th';
        }

        return $n . $suffix;
    }

    /**
     * "A", "A & B", "A, B & C".
     *
     * @param array<int,string> $items
     */
    private static function joinList(array $items): string
    {
        $items = array_values(array_filter($items));
        $n     = count($items);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return $items[0];
        }
        $last = array_pop($items);

        return implode(', ', $items) . ' & ' . $last;
    }
}
