<?php

declare(strict_types=1);

namespace FirstChurch\Events\Tests;

use FirstChurch\Events\Ics;
use PHPUnit\Framework\TestCase;

/**
 * Minimal iCalendar (RFC 5545) generation — the subscription win CTC doesn't
 * give us. Tests the structure, escaping, and that RRULE/EXDATE pass through.
 */
final class IcsTest extends TestCase
{
    private function cal(array $events): string
    {
        return Ics::calendar($events, '20260608T000000Z');
    }

    public function test_wraps_in_vcalendar(): void
    {
        $out = $this->cal([]);
        $this->assertStringContainsString("BEGIN:VCALENDAR\r\n", $out);
        $this->assertStringContainsString('VERSION:2.0', $out);
        $this->assertStringContainsString('PRODID:', $out);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $out);
    }

    public function test_timed_event_with_rrule_and_exdate(): void
    {
        $out = $this->cal([[
            'uid'        => 'fce-12',
            'title'      => 'Sunday Worship',
            'dtstart'    => '2026-06-07',
            'time'       => '10:30',
            'venue'      => 'Sanctuary',
            'url'        => 'https://x/events/worship/',
            'rrule'      => 'FREQ=WEEKLY;BYDAY=SU',
            'skip_dates' => ['2026-06-14'],
        ]]);
        $this->assertStringContainsString('BEGIN:VEVENT', $out);
        $this->assertStringContainsString('UID:fce-12', $out);
        $this->assertStringContainsString('SUMMARY:Sunday Worship', $out);
        $this->assertStringContainsString('DTSTART:20260607T103000', $out);
        $this->assertStringContainsString('RRULE:FREQ=WEEKLY;BYDAY=SU', $out);
        $this->assertStringContainsString('EXDATE:20260614T103000', $out);
        $this->assertStringContainsString('LOCATION:Sanctuary', $out);
    }

    public function test_all_day_event_uses_value_date(): void
    {
        $out = $this->cal([[ 'uid' => 'fce-9', 'title' => 'Pride Sunday', 'dtstart' => '2026-06-28' ]]);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260628', $out);
    }

    public function test_escapes_special_chars(): void
    {
        $out = $this->cal([[ 'uid' => 'fce-1', 'title' => 'Coffee, Cake; & Notes', 'dtstart' => '2026-06-10' ]]);
        // commas and semicolons must be backslash-escaped in TEXT values
        $this->assertStringContainsString('SUMMARY:Coffee\\, Cake\\; & Notes', $out);
    }
}
