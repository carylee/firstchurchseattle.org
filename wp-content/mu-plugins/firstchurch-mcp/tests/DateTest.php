<?php
/**
 * Tier 2 — date/time sanitizers, publication-date handling, and status validation.
 *
 * @package FirstChurch\Mcp\Tests
 */

declare(strict_types=1);

namespace FirstChurch\Mcp\Tests;

use PHPUnit\Framework\TestCase;

final class DateTest extends TestCase
{
    public function testSanitizeDateAcceptsIsoOnly(): void
    {
        $this->assertSame('2026-06-10', fcmcp_sanitize_date('2026-06-10'));
        $this->assertSame('', fcmcp_sanitize_date('2026-6-1'), 'requires zero-padded components');
        $this->assertSame('', fcmcp_sanitize_date('June 10 2026'));
        $this->assertSame('', fcmcp_sanitize_date(''));
        $this->assertSame('', fcmcp_sanitize_date(null));
    }

    public function testSanitizeTimeRequiresHhMm(): void
    {
        $this->assertSame('09:30', fcmcp_sanitize_time('09:30'));
        $this->assertSame('', fcmcp_sanitize_time('9:30'));
        $this->assertSame('', fcmcp_sanitize_time('morning'));
    }

    public function testSanitizeDatetimeNormalizesToFullTimestamp(): void
    {
        $this->assertSame('2026-06-10 00:00:00', fcmcp_sanitize_datetime('2026-06-10'));
        $this->assertSame('2026-06-10 14:30:00', fcmcp_sanitize_datetime('2026-06-10 14:30'));
        $this->assertSame('', fcmcp_sanitize_datetime(''));
        $this->assertSame('', fcmcp_sanitize_datetime('not a date'));
    }

    public function testApplyPostDateNoOpWithoutDate(): void
    {
        $arr = array('post_title' => 'x');
        $this->assertSame($arr, fcmcp_apply_post_date($arr, array()));
    }

    public function testApplyPostDateBackdatesPastValue(): void
    {
        $arr = fcmcp_apply_post_date(array('ID' => 1), array('date' => '2020-01-02 08:00'));
        $this->assertSame('2020-01-02 08:00:00', $arr['post_date']);
        $this->assertSame('2020-01-02 08:00:00', $arr['post_date_gmt']);
        $this->assertTrue($arr['edit_date']);
    }

    public function testApplyPostDateSchedulesFutureValue(): void
    {
        $arr = fcmcp_apply_post_date(array('ID' => 1), array('date' => '2030-12-31'));
        $this->assertSame('2030-12-31 00:00:00', $arr['post_date']);
        $this->assertSame('2030-12-31 00:00:00', $arr['post_date_gmt']);
        $this->assertTrue($arr['edit_date']);
    }

    public function testNewStatusDefaultsToDraft(): void
    {
        $this->assertSame('draft', fcmcp_new_status(array()));
        $this->assertSame('draft', fcmcp_new_status(array('status' => 'garbage')));
    }

    public function testNewStatusAcceptsValidStatusesCaseInsensitively(): void
    {
        $this->assertSame('publish', fcmcp_new_status(array('status' => 'publish')));
        $this->assertSame('pending', fcmcp_new_status(array('status' => 'pending')));
        $this->assertSame('publish', fcmcp_new_status(array('status' => 'PUBLISH')));
    }
}
