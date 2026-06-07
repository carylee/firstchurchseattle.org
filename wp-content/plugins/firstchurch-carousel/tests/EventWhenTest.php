<?php

declare(strict_types=1);

namespace FirstChurch\Carousel\Tests;

use PHPUnit\Framework\TestCase;

/**
 * fccar_event_when(): the one bit of real formatting logic — turn CTC date +
 * recurrence + time meta into the human "when" string the event card prints.
 * Driven through the in-memory post-meta store (see bootstrap.php).
 */
final class EventWhenTest extends TestCase
{
    public function test_weekly_with_clock_time(): void
    {
        fccar_test_set_meta(101, [
            '_ctc_event_start_date'                  => '2026-06-07',
            '_ctc_event_recurrence'                  => 'weekly',
            '_ctc_event_recurrence_weekly_interval'  => '1',
            '_ctc_event_recurrence_weekly_day'       => 'SU',
            '_ctc_event_start_time'                  => '19:00',
        ]);

        $this->assertSame('Sundays at 7:00 pm', fccar_event_when(101));
    }

    public function test_every_other_week_multiple_days(): void
    {
        fccar_test_set_meta(102, [
            '_ctc_event_start_date'                 => '2026-06-09',
            '_ctc_event_recurrence'                 => 'weekly',
            '_ctc_event_recurrence_weekly_interval' => '2',
            '_ctc_event_recurrence_weekly_day'      => 'TU,TH',
            '_ctc_event_start_time'                 => '10:00',
        ]);

        $this->assertSame('Every other Tuesday & Thursday at 10:00 am', fccar_event_when(102));
    }

    public function test_monthly_nth_weekday(): void
    {
        fccar_test_set_meta(103, [
            '_ctc_event_start_date'                  => '2026-06-26', // a Friday
            '_ctc_event_recurrence'                  => 'monthly',
            '_ctc_event_recurrence_monthly_type'     => 'week',
            '_ctc_event_recurrence_monthly_week'     => '4',
            '_ctc_event_start_time'                  => '16:00',
        ]);

        $this->assertSame('Every 4th Friday at 4:00 pm', fccar_event_when(103));
    }

    public function test_one_off_date_with_time(): void
    {
        fccar_test_set_meta(104, [
            '_ctc_event_start_date' => '2026-04-12',
            '_ctc_event_recurrence' => 'none',
            '_ctc_event_start_time' => '19:00',
        ]);

        $this->assertSame('April 12 at 7:00 pm', fccar_event_when(104));
    }

    public function test_non_clock_time_becomes_descriptor_with_venue(): void
    {
        fccar_test_set_meta(105, [
            '_ctc_event_start_date'                 => '2026-06-07',
            '_ctc_event_recurrence'                 => 'weekly',
            '_ctc_event_recurrence_weekly_day'      => 'SU',
            '_ctc_event_start_time'                 => '19:00',
            '_ctc_event_time'                       => 'Room 302',
            '_ctc_event_venue'                      => 'Fellowship Hall',
        ]);

        $this->assertSame('Sundays at 7:00 pm · Room 302 · Fellowship Hall', fccar_event_when(105));
    }

    public function test_human_time_phrase_when_no_clock(): void
    {
        fccar_test_set_meta(106, [
            '_ctc_event_start_date' => '2026-04-12',
            '_ctc_event_recurrence' => 'none',
            '_ctc_event_time'       => 'After the worship service',
        ]);

        $this->assertSame('April 12 · After the worship service', fccar_event_when(106));
    }
}
