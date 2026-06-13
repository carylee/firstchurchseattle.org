<?php

declare(strict_types=1);

namespace FirstChurch\Events;

/**
 * Converts Church Theme Content recurrence meta into an iCalendar RRULE string
 * (RFC 5545) — the standard that rlanvin/php-rrule (and Google/Apple/Outlook)
 * speak. Returns null for non-recurring events. DTSTART is NOT included; pass
 * the start date to the RRule constructor separately.
 *
 * This is the load-bearing piece of evaluating a lean events backend: if these
 * conversions are right, the recurrence "moat" is a solved, off-the-shelf concern.
 *
 * @phpstan-type CtcMeta array{
 *   recurrence?:string, start?:string, weekly_interval?:int|string,
 *   weekly_days?:string, monthly_type?:string, monthly_week?:string, end_date?:string
 * }
 */
final class Recurrence
{
    private const DAYS = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];

    /** @param array<string,mixed> $e */
    public static function toRrule(array $e): ?string
    {
        $freq = strtolower((string) ($e['recurrence'] ?? ''));
        if ($freq === '' || $freq === 'none') {
            return null;
        }

        $start = (string) ($e['start'] ?? '');
        $ts    = $start !== '' ? strtotime($start) : false;

        $parts = [];

        if ($freq === 'weekly') {
            $parts[] = 'FREQ=WEEKLY';
            $interval = max(1, (int) ($e['weekly_interval'] ?? 1));
            if ($interval > 1) {
                $parts[] = 'INTERVAL=' . $interval;
            }
            $days = self::dayCodes((string) ($e['weekly_days'] ?? ''));
            if (!$days && $ts !== false) {
                $days = [self::weekday($ts)];
            }
            if ($days) {
                $parts[] = 'BYDAY=' . implode(',', $days);
            }
        } elseif ($freq === 'monthly') {
            $parts[] = 'FREQ=MONTHLY';
            if ((string) ($e['monthly_type'] ?? 'day') === 'week') {
                $weekday = $ts !== false ? self::weekday($ts) : '';
                $byday   = [];
                foreach (array_filter(array_map('trim', explode(',', (string) ($e['monthly_week'] ?? '')))) as $w) {
                    $ord     = strtolower($w) === 'last' ? '-1' : (string) (int) $w;
                    $byday[] = $ord . $weekday;
                }
                if ($byday) {
                    $parts[] = 'BYDAY=' . implode(',', $byday);
                }
            } elseif ($ts !== false) {
                $parts[] = 'BYMONTHDAY=' . (int) date('j', $ts);
            }
        } elseif ($freq === 'yearly') {
            $parts[] = 'FREQ=YEARLY';
            // "Every N years" reuses the shared interval meta (weekly_interval).
            $interval = max(1, (int) ($e['weekly_interval'] ?? 1));
            if ($interval > 1) {
                $parts[] = 'INTERVAL=' . $interval;
            }
        } else {
            return null;
        }

        $end = (string) ($e['end_date'] ?? '');
        if ($end !== '') {
            $et = strtotime($end);
            if ($et !== false) {
                $parts[] = 'UNTIL=' . date('Ymd', $et) . 'T235959Z';
            }
        }

        return implode(';', $parts);
    }

    /** A CSV of CTC weekday codes (SU,MO,… — already RRULE-compatible) → cleaned list. */
    private static function dayCodes(string $csv): array
    {
        $out = [];
        foreach (array_filter(array_map('trim', explode(',', $csv))) as $code) {
            $code = strtoupper(substr($code, 0, 2));
            if (in_array($code, self::DAYS, true)) {
                $out[] = $code;
            }
        }

        return $out;
    }

    private static function weekday(int $ts): string
    {
        return self::DAYS[(int) date('w', $ts)];
    }
}
