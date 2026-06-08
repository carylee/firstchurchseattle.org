<?php

declare(strict_types=1);

namespace FirstChurch\Happenings\Tests;

use FirstChurch\Happenings\EventWhen;
use PHPUnit\Framework\TestCase;

/**
 * EventWhen::format() takes a plain array of already-extracted CTC event meta
 * (no get_post_meta) so the recurrence/time formatting is fully unit-testable.
 * Dates used: 2026-06-07 is a Sunday, 2026-06-12 is a Friday.
 */
final class EventWhenTest extends TestCase
{
    public function test_weekly_pluralizes_weekday_with_clock_and_venue(): void
    {
        $this->assertSame(
            'Sundays at 10:30 am · Sanctuary',
            EventWhen::format([
                'start' => '2026-06-07',
                'freq' => 'weekly',
                'start_time' => '10:30',
                'venue' => 'Sanctuary',
            ])
        );
    }

    public function test_weekly_uses_explicit_days_csv(): void
    {
        $this->assertSame(
            'Tuesdays & Thursdays',
            EventWhen::format(['start' => '2026-06-09', 'freq' => 'weekly', 'weekly_days' => 'TU,TH'])
        );
    }

    public function test_every_other_week(): void
    {
        $this->assertSame(
            'Every other Friday',
            EventWhen::format(['start' => '2026-06-12', 'freq' => 'weekly', 'weekly_interval' => 2, 'weekly_days' => 'FR'])
        );
    }

    public function test_monthly_nth_weekday(): void
    {
        $this->assertSame(
            'Every 4th Friday',
            EventWhen::format(['start' => '2026-06-12', 'freq' => 'monthly', 'monthly_type' => 'week', 'monthly_week' => '4'])
        );
    }

    public function test_monthly_multiple_weeks(): void
    {
        $this->assertSame(
            'Every 2nd & 4th Friday',
            EventWhen::format(['start' => '2026-06-12', 'freq' => 'monthly', 'monthly_type' => 'week', 'monthly_week' => '2,4'])
        );
    }

    public function test_yearly(): void
    {
        $this->assertSame(
            'Annually on December 25',
            EventWhen::format(['start' => '2026-12-25', 'freq' => 'yearly'])
        );
    }

    public function test_one_off_with_clock_time(): void
    {
        $this->assertSame(
            'April 12 at 7:00 pm',
            EventWhen::format(['start' => '2026-04-12', 'freq' => 'none', 'start_time' => '19:00'])
        );
    }

    public function test_free_text_time_becomes_descriptor_not_clock(): void
    {
        $this->assertSame(
            'June 21 · After the worship service · Fellowship Hall',
            EventWhen::format([
                'start' => '2026-06-21',
                'freq' => 'none',
                'time_text' => 'After the worship service',
                'venue' => 'Fellowship Hall',
            ])
        );
    }

    public function test_clocklike_time_text_is_used_as_clock(): void
    {
        $this->assertSame(
            '7:30 pm',
            EventWhen::format(['start' => '', 'freq' => '', 'time_text' => '7:30 pm'])
        );
    }
}
