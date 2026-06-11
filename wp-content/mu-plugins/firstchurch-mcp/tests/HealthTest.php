<?php
/**
 * Tier 1 — the pure cores of content-health + resolve-url (health.php).
 *
 * The date-window math (stale/expiring thresholds) and URL→path normalization
 * are pure, so they're verified here without a live WordPress; the WP_Query
 * audits and url_to_postid/redirect lookups are thin glue around them.
 *
 * @package FirstChurch\Mcp\Tests
 */

declare(strict_types=1);

namespace FirstChurch\Mcp\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HealthTest extends TestCase
{
    public function testDateOffsetShiftsBackwardAndForward(): void
    {
        $this->assertSame('2026-05-12', fcmcp_date_offset(-30, '2026-06-11'));
        $this->assertSame('2026-06-18', fcmcp_date_offset(7, '2026-06-11'));
        $this->assertSame('2026-06-11', fcmcp_date_offset(0, '2026-06-11'));
    }

    public function testDateOffsetCrossesMonthAndYearBoundaries(): void
    {
        $this->assertSame('2026-01-01', fcmcp_date_offset(1, '2025-12-31'));
        $this->assertSame('2025-12-31', fcmcp_date_offset(-1, '2026-01-01'));
    }

    #[DataProvider('paths')]
    public function testNormalizePath(string $input, string $expected): void
    {
        $this->assertSame($expected, fcmcp_normalize_path($input, 'https://firstchurchseattle.org'));
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function paths(): array
    {
        return array(
            'bare path'            => array('/events', '/events'),
            'no leading slash'     => array('events', '/events'),
            'trailing slash'       => array('/about/staff/', '/about/staff'),
            'same-site full url'   => array('https://firstchurchseattle.org/about/staff/', '/about/staff'),
            'foreign host'         => array('https://example.com/foo/bar', '/foo/bar'),
            'query + fragment'     => array('/give?utm=x#top', '/give'),
            'root'                 => array('/', '/'),
            'empty'                => array('', '/'),
            'double slashes'       => array('/a//b', '/a/b'),
        );
    }

    public function testHealthCheckConstantCoversTheKnownChecks(): void
    {
        // Guard against the schema enum and the runner drifting apart.
        $this->assertContains('events_missing_image', FCMCP_HEALTH_CHECKS);
        $this->assertContains('announcements_expired', FCMCP_HEALTH_CHECKS);
        $this->assertContains('stale_pages', FCMCP_HEALTH_CHECKS);
        $this->assertSame(FCMCP_HEALTH_CHECKS, array_values(array_unique(FCMCP_HEALTH_CHECKS)));
    }
}
