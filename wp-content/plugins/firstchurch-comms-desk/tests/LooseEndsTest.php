<?php

declare(strict_types=1);

namespace FirstChurch\CommsDesk\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Making "Loose ends" actionable in place: a one-click photo finder for events
 * missing an image (reusing the stock widget), and extend/unpublish for
 * announcements — the pure seams behind those controls.
 */
final class LooseEndsTest extends TestCase
{
    public function test_loose_photo_widget_carries_post_id_and_prefilled_query(): void
    {
        $html = fccd_render_loose_photo(123, 'lit candles in a quiet sanctuary');
        // Reuses the existing stock widget so the established toggle/search/pick path works.
        $this->assertStringContainsString('fccd-photo', $html);
        $this->assertStringContainsString('fccd-photo-stock-toggle', $html);
        $this->assertStringContainsString('fccd-stock-q', $html);
        // The post id is reachable for the import call, and the query is pre-filled.
        $this->assertStringContainsString('data-draft="123"', $html);
        $this->assertStringContainsString('value="lit candles in a quiet sanctuary"', $html);
    }

    public function test_loose_photo_widget_escapes_the_query(): void
    {
        $html = fccd_render_loose_photo(1, '"><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_extend_expiry_adds_days_from_the_later_of_today_or_current(): void
    {
        // Current expiry in the future → extend from the current date.
        $this->assertSame('2026-07-31', fccd_extend_expiry('2026-07-01', '2026-06-13', 30));
        // Already expired (or empty) → extend from today, so it actually comes back.
        $this->assertSame('2026-07-13', fccd_extend_expiry('2026-05-01', '2026-06-13', 30));
        $this->assertSame('2026-07-13', fccd_extend_expiry('', '2026-06-13', 30));
    }

    public function test_extend_expiry_handles_garbage_dates_gracefully(): void
    {
        $this->assertSame('2026-07-13', fccd_extend_expiry('not-a-date', '2026-06-13', 30));
    }
}
