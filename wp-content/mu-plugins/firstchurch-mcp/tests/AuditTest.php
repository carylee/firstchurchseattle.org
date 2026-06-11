<?php
/**
 * Tier 1 — the audit trail's pure core (safety.php).
 *
 * The ring buffer, status-transition classification, and post-id filter are
 * pure functions so the accountability/rollback logic is verifiable without a
 * live WordPress; the WP glue (option I/O + hooks) is thin around them.
 *
 * @package FirstChurch\Mcp\Tests
 */

declare(strict_types=1);

namespace FirstChurch\Mcp\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuditTest extends TestCase
{
    private static function entry(int $id, string $action = 'updated'): array
    {
        return array('id' => $id, 'action' => $action, 'title' => "Post $id");
    }

    public function testAppendIsNewestFirst(): void
    {
        $log = fcmcp_audit_append(array(self::entry(1)), self::entry(2), 250);
        $this->assertSame(2, $log[0]['id'], 'newest entry must be first');
        $this->assertSame(1, $log[1]['id']);
    }

    public function testAppendCapsAtMax(): void
    {
        $log = array();
        foreach (range(1, 10) as $i) {
            $log = fcmcp_audit_append($log, self::entry($i), 3);
        }
        $this->assertCount(3, $log, 'log must not grow past the cap');
        $this->assertSame(array(10, 9, 8), array_column($log, 'id'), 'cap keeps the newest entries');
    }

    #[DataProvider('transitions')]
    public function testClassify(string $old, string $new, string $expected): void
    {
        $this->assertSame($expected, fcmcp_audit_classify($old, $new));
    }

    /** @return array<string,array{0:string,1:string,2:string}> */
    public static function transitions(): array
    {
        return array(
            'new draft'        => array('new', 'draft', 'created'),
            'auto-draft'       => array('auto-draft', 'pending', 'created'),
            'empty old'        => array('', 'publish', 'created'),
            'publish'          => array('draft', 'publish', "status: draft \u{2192} publish"),
            'to trash'         => array('publish', 'trash', 'trashed'),
            'from trash'       => array('trash', 'draft', 'restored'),
            'content edit'     => array('publish', 'publish', 'updated'),
            'unpublish'        => array('publish', 'draft', "status: publish \u{2192} draft"),
        );
    }

    public function testFilterByPostId(): void
    {
        $log = array(self::entry(7, 'trashed'), self::entry(9), self::entry(7, 'created'));
        $only7 = fcmcp_audit_filter($log, 7, 50);
        $this->assertCount(2, $only7);
        $this->assertSame(array(7, 7), array_column($only7, 'id'));
    }

    public function testFilterUnscopedRespectsLimit(): void
    {
        $log = array_map(fn($i) => self::entry($i), range(1, 20));
        $this->assertCount(5, fcmcp_audit_filter($log, 0, 5));
        $this->assertSame(array(1, 2, 3, 4, 5), array_column(fcmcp_audit_filter($log, 0, 5), 'id'));
    }
}
