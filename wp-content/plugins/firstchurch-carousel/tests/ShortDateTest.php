<?php

declare(strict_types=1);

namespace FirstChurch\Carousel\Tests;

use PHPUnit\Framework\TestCase;

/**
 * fccar_short_date(): a compact human date label for event tiles in the Curate
 * shelf ("Jun 26"), so curators can tell candidates apart at a glance and the
 * readiness pass can flag past events.
 */
final class ShortDateTest extends TestCase
{
    public function test_formats_ymd(): void
    {
        $this->assertSame('Jun 26', fccar_short_date('2026-06-26'));
        $this->assertSame('Jan 1', fccar_short_date('2026-01-01'));
    }

    public function test_empty_for_blank_or_garbage(): void
    {
        $this->assertSame('', fccar_short_date(''));
        $this->assertSame('', fccar_short_date('not-a-date'));
    }
}
