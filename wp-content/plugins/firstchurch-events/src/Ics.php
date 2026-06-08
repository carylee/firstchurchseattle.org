<?php

declare(strict_types=1);

namespace FirstChurch\Events;

/**
 * Minimal iCalendar (RFC 5545) generator — the subscription feed CTC doesn't
 * give us well. Emits a VCALENDAR of VEVENTs that carry RRULE + EXDATE, so a
 * subscriber's Google/Apple calendar expands recurrence and drops cancelled
 * occurrences natively. Pure string building (testable).
 *
 * Event keys: uid, title, dtstart (YYYY-MM-DD), time (HH:MM, optional → all-day),
 * venue, url, rrule (string), skip_dates (YYYY-MM-DD[]).
 */
final class Ics
{
    /**
     * @param array<int,array<string,mixed>> $events
     * @param string $dtstamp UTC stamp "Ymd\THis\Z" (passed in for determinism)
     */
    public static function calendar(array $events, string $dtstamp): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//First Church Seattle//Events Spike//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];
        foreach ($events as $e) {
            foreach (self::vevent($e, $dtstamp) as $line) {
                $lines[] = $line;
            }
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    /** @param array<string,mixed> $e @return list<string> */
    private static function vevent(array $e, string $dtstamp): array
    {
        $date    = str_replace('-', '', (string) ($e['dtstart'] ?? ''));
        $time    = (string) ($e['time'] ?? '');
        $hasTime = preg_match('/^\d{1,2}:\d{2}/', $time) === 1;
        $clock   = $hasTime ? str_replace(':', '', substr($time, 0, 5)) . '00' : '';

        $out   = ['BEGIN:VEVENT'];
        $out[] = 'UID:' . self::esc((string) ($e['uid'] ?? ''));
        $out[] = 'DTSTAMP:' . $dtstamp;
        $out[] = $hasTime ? 'DTSTART:' . $date . 'T' . $clock : 'DTSTART;VALUE=DATE:' . $date;
        $out[] = 'SUMMARY:' . self::esc((string) ($e['title'] ?? ''));
        if (!empty($e['venue'])) {
            $out[] = 'LOCATION:' . self::esc((string) $e['venue']);
        }
        if (!empty($e['url'])) {
            $out[] = 'URL:' . self::esc((string) $e['url']);
        }
        if (!empty($e['rrule'])) {
            $out[] = 'RRULE:' . (string) $e['rrule'];
        }
        foreach ((array) ($e['skip_dates'] ?? []) as $d) {
            $d     = str_replace('-', '', (string) $d);
            $out[] = $hasTime ? 'EXDATE:' . $d . 'T' . $clock : 'EXDATE;VALUE=DATE:' . $d;
        }
        $out[] = 'END:VEVENT';

        return $out;
    }

    private static function esc(string $s): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $s);
    }

    /** RFC 5545 line folding: continuation lines begin with a space. */
    private static function fold(string $line): string
    {
        return strlen($line) <= 73 ? $line : implode("\r\n ", str_split($line, 73));
    }
}
