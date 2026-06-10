<?php

declare(strict_types=1);

namespace FirstChurch\Happenings\Tests;

use FirstChurch\Happenings\Kind;
use PHPUnit\Framework\TestCase;

/**
 * Kind::derive() takes the same plain meta array as EventWhen::format(), so
 * the rhythm/group/event classification (ops/docs/event-kinds.md §3) is fully
 * unit-testable.
 */
final class KindTest extends TestCase
{
    public function test_weekly_without_end_date_is_a_rhythm(): void
    {
        $this->assertSame(Kind::RHYTHM, Kind::derive(['freq' => 'weekly']));
        $this->assertSame(Kind::RHYTHM, Kind::derive(['freq' => 'weekly', 'end_date' => '']));
    }

    public function test_bounded_weekly_series_is_an_event(): void
    {
        $this->assertSame(Kind::EVENT, Kind::derive(['freq' => 'weekly', 'end_date' => '2026-08-30']));
    }

    public function test_monthly_is_a_group(): void
    {
        $this->assertSame(Kind::GROUP, Kind::derive(['freq' => 'monthly']));
        // An end date doesn't change a group's nature — it just stops it.
        $this->assertSame(Kind::GROUP, Kind::derive(['freq' => 'monthly', 'end_date' => '2026-12-31']));
    }

    public function test_one_offs_are_events(): void
    {
        $this->assertSame(Kind::EVENT, Kind::derive([]));
        $this->assertSame(Kind::EVENT, Kind::derive(['freq' => '']));
        $this->assertSame(Kind::EVENT, Kind::derive(['freq' => 'none']));
    }

    public function test_yearly_occasions_are_events_not_groups(): void
    {
        $this->assertSame(Kind::EVENT, Kind::derive(['freq' => 'yearly']));
    }

    public function test_explicit_override_wins_over_derivation(): void
    {
        $this->assertSame(Kind::GROUP, Kind::derive(['kind' => 'group', 'freq' => 'weekly']));
        $this->assertSame(Kind::EVENT, Kind::derive(['kind' => 'event', 'freq' => 'monthly']));
        $this->assertSame(Kind::RHYTHM, Kind::derive(['kind' => 'rhythm', 'freq' => 'none']));
    }

    public function test_override_tolerates_case_and_whitespace(): void
    {
        $this->assertSame(Kind::GROUP, Kind::derive(['kind' => ' Group ', 'freq' => 'weekly']));
    }

    public function test_invalid_override_falls_back_to_derivation(): void
    {
        $this->assertSame(Kind::RHYTHM, Kind::derive(['kind' => 'banana', 'freq' => 'weekly']));
        $this->assertSame(Kind::EVENT, Kind::derive(['kind' => 'banana']));
    }
}
