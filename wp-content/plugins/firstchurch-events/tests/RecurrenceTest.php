<?php

declare(strict_types=1);

namespace FirstChurch\Events\Tests;

use FirstChurch\Events\Recurrence;
use PHPUnit\Framework\TestCase;

/**
 * Converts Church Theme Content recurrence meta into an iCalendar RRULE string
 * (RFC 5545). These are the real patterns in use on prod (5 weekly + 3 monthly).
 * Dates: 2026-06-07 = Sunday, 2026-06-12 = Friday, 2026-06-11 = Wednesday.
 */
final class RecurrenceTest extends TestCase
{
    public function test_none_or_empty_is_null(): void
    {
        $this->assertNull(Recurrence::toRrule(['recurrence' => 'none', 'start' => '2026-06-07']));
        $this->assertNull(Recurrence::toRrule(['recurrence' => '', 'start' => '2026-06-07']));
    }

    public function test_weekly_with_explicit_day(): void
    {
        $this->assertSame(
            'FREQ=WEEKLY;BYDAY=SU',
            Recurrence::toRrule(['recurrence' => 'weekly', 'weekly_days' => 'SU', 'start' => '2026-06-07'])
        );
    }

    public function test_weekly_multiple_days(): void
    {
        $this->assertSame(
            'FREQ=WEEKLY;BYDAY=TU,TH',
            Recurrence::toRrule(['recurrence' => 'weekly', 'weekly_days' => 'TU,TH', 'start' => '2026-06-09'])
        );
    }

    public function test_weekly_every_other(): void
    {
        $this->assertSame(
            'FREQ=WEEKLY;INTERVAL=2;BYDAY=FR',
            Recurrence::toRrule(['recurrence' => 'weekly', 'weekly_interval' => 2, 'weekly_days' => 'FR', 'start' => '2026-06-12'])
        );
    }

    public function test_weekly_no_day_derives_from_start(): void
    {
        // No weekly_days → use the weekday of the start date (a Sunday).
        $this->assertSame(
            'FREQ=WEEKLY;BYDAY=SU',
            Recurrence::toRrule(['recurrence' => 'weekly', 'start' => '2026-06-07'])
        );
    }

    public function test_monthly_nth_weekday(): void
    {
        // 2nd & 4th of the month, weekday taken from the start date (Friday).
        $this->assertSame(
            'FREQ=MONTHLY;BYDAY=2FR,4FR',
            Recurrence::toRrule(['recurrence' => 'monthly', 'monthly_type' => 'week', 'monthly_week' => '2,4', 'start' => '2026-06-12'])
        );
    }

    public function test_monthly_last_weekday(): void
    {
        $this->assertSame(
            'FREQ=MONTHLY;BYDAY=-1FR',
            Recurrence::toRrule(['recurrence' => 'monthly', 'monthly_type' => 'week', 'monthly_week' => 'last', 'start' => '2026-06-26'])
        );
    }

    public function test_monthly_by_day_of_month(): void
    {
        $this->assertSame(
            'FREQ=MONTHLY;BYMONTHDAY=15',
            Recurrence::toRrule(['recurrence' => 'monthly', 'monthly_type' => 'day', 'start' => '2026-06-15'])
        );
    }

    public function test_yearly(): void
    {
        $this->assertSame(
            'FREQ=YEARLY',
            Recurrence::toRrule(['recurrence' => 'yearly', 'start' => '2026-12-25'])
        );
    }

    public function test_end_date_becomes_until(): void
    {
        $this->assertSame(
            'FREQ=WEEKLY;BYDAY=SU;UNTIL=20261231T235959Z',
            Recurrence::toRrule(['recurrence' => 'weekly', 'weekly_days' => 'SU', 'start' => '2026-06-07', 'end_date' => '2026-12-31'])
        );
    }
}
