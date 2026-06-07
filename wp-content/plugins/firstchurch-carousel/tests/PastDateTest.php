<?php

declare(strict_types=1);

namespace FirstChurch\Carousel\Tests;

use PHPUnit\Framework\TestCase;

/**
 * fccar_is_past_date(): the readiness pass flags deck events whose date has
 * already gone by. Date-only comparison — an event happening *today* is not
 * past.
 */
final class PastDateTest extends TestCase
{
    public function test_yesterday_is_past(): void
    {
        $this->assertTrue(fccar_is_past_date('2020-01-01', '2026-06-07'));
    }

    public function test_today_is_not_past(): void
    {
        $this->assertFalse(fccar_is_past_date('2026-06-07', '2026-06-07'));
    }

    public function test_future_is_not_past(): void
    {
        $this->assertFalse(fccar_is_past_date('2026-12-01', '2026-06-07'));
    }

    public function test_blank_or_garbage_is_not_past(): void
    {
        $this->assertFalse(fccar_is_past_date('', '2026-06-07'));
        $this->assertFalse(fccar_is_past_date('not-a-date', '2026-06-07'));
    }
}
