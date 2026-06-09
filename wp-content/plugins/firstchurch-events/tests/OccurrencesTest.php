<?php

declare(strict_types=1);

namespace FirstChurch\Events\Tests;

use DateTimeImmutable;
use FirstChurch\Events\Occurrences;
use PHPUnit\Framework\TestCase;

/**
 * Expanding DTSTART + RRULE into concrete dates within a window — the calendar
 * grid's per-day source. Dates: 2026-06-07 = Sunday, 2026-06-14/21/28 = Sundays;
 * 2026-06-11 = Thursday.
 */
final class OccurrencesTest extends TestCase
{
    private function d(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd);
    }

    /** @return array<int,string> */
    private function ymd(array $occ): array
    {
        return array_map(static fn ($o) => $o->format('Y-m-d'), $occ);
    }

    public function test_one_off_in_window(): void
    {
        $occ = Occurrences::between('2026-06-17', '', $this->d('2026-06-01'), $this->d('2026-06-30'));
        $this->assertSame(['2026-06-17'], $this->ymd($occ));
    }

    public function test_one_off_outside_window_is_empty(): void
    {
        $this->assertSame([], Occurrences::between('2026-07-04', '', $this->d('2026-06-01'), $this->d('2026-06-30')));
    }

    public function test_empty_dtstart_is_empty(): void
    {
        $this->assertSame([], Occurrences::between('', 'FREQ=WEEKLY;BYDAY=SU', $this->d('2026-06-01'), $this->d('2026-06-30')));
    }

    public function test_weekly_expands_every_occurrence_in_window(): void
    {
        $occ = Occurrences::between(
            '2026-06-07', // dtstart: a Sunday
            'FREQ=WEEKLY;BYDAY=SU',
            $this->d('2026-06-01'),
            $this->d('2026-06-30')
        );
        $this->assertSame(['2026-06-07', '2026-06-14', '2026-06-21', '2026-06-28'], $this->ymd($occ));
    }

    public function test_skip_dates_are_excluded(): void
    {
        $occ = Occurrences::between(
            '2026-06-07',
            'FREQ=WEEKLY;BYDAY=SU',
            $this->d('2026-06-01'),
            $this->d('2026-06-30'),
            ['2026-06-14']
        );
        $this->assertSame(['2026-06-07', '2026-06-21', '2026-06-28'], $this->ymd($occ));
    }

    public function test_window_bounds_are_inclusive(): void
    {
        $occ = Occurrences::between('2026-06-07', 'FREQ=WEEKLY;BYDAY=SU', $this->d('2026-06-07'), $this->d('2026-06-21'));
        $this->assertSame(['2026-06-07', '2026-06-14', '2026-06-21'], $this->ymd($occ));
    }

    public function test_next_returns_first_in_window(): void
    {
        $next = Occurrences::next('2026-06-07', 'FREQ=WEEKLY;BYDAY=SU', $this->d('2026-06-10'), $this->d('2026-12-31'));
        $this->assertNotNull($next);
        $this->assertSame('2026-06-14', $next->format('Y-m-d'));
    }

    public function test_next_skips_cancelled_occurrence(): void
    {
        $next = Occurrences::next(
            '2026-06-07',
            'FREQ=WEEKLY;BYDAY=SU',
            $this->d('2026-06-10'),
            $this->d('2026-12-31'),
            ['2026-06-14']
        );
        $this->assertNotNull($next);
        $this->assertSame('2026-06-21', $next->format('Y-m-d'));
    }

    public function test_next_returns_null_when_none_in_window(): void
    {
        $this->assertNull(Occurrences::next('2026-06-07', 'FREQ=WEEKLY;BYDAY=SU', $this->d('2026-06-08'), $this->d('2026-06-13')));
    }
}
