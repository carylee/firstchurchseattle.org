<?php

declare(strict_types=1);

namespace FirstChurch\Happenings\Tests;

use FirstChurch\Happenings\Featured;
use PHPUnit\Framework\TestCase;

/**
 * Featured ranks already-projected Happenings (events AND announcements alike)
 * for the curated Featured row: by prominence weight (desc), then by date (most
 * recent/soonest-listed first), capped at a count. Operating on projected items
 * — not WP_Posts — is what lets a weighted event and a weighted announcement
 * share one row (Phase 4: "Featured spans events"). Pure (no WP).
 */
final class FeaturedTest extends TestCase
{
    /** @param array<string,mixed> $extra */
    private static function item(int $weight, string $date, array $extra = []): array
    {
        // Mirror the spine projection: weight is present only when > 0 (Item drops
        // empties), so a "normal" item simply has no weight key.
        $it = ['date' => $date] + $extra;
        if ($weight > 0) {
            $it['weight'] = $weight;
        }
        return $it;
    }

    public function test_orders_by_weight_descending(): void
    {
        $ranked = Featured::rank([
            self::item(10, '2026-06-01', ['id' => 'a']),
            self::item(20, '2026-06-01', ['id' => 'b']),
            self::item(15, '2026-06-01', ['id' => 'c']),
        ], 5);

        $this->assertSame(['b', 'c', 'a'], array_column($ranked, 'id'));
    }

    public function test_equal_weight_breaks_by_date_most_recent_first(): void
    {
        $ranked = Featured::rank([
            self::item(10, '2026-06-01', ['id' => 'old']),
            self::item(10, '2026-06-20', ['id' => 'new']),
        ], 5);

        $this->assertSame(['new', 'old'], array_column($ranked, 'id'));
    }

    public function test_a_weighted_event_and_announcement_share_one_row(): void
    {
        // The Phase 4 point: an event (carrying `when`) ranks alongside an
        // announcement purely by weight, regardless of source.
        $ranked = Featured::rank([
            self::item(10, '2026-06-05', ['id' => 'announcement-1', 'source' => 'announcement']),
            self::item(20, '2026-06-17', ['id' => 'event-7', 'source' => 'event', 'when' => 'June 17 at 7:00 pm']),
        ], 5);

        $this->assertSame(['event-7', 'announcement-1'], array_column($ranked, 'id'));
        $this->assertSame('June 17 at 7:00 pm', $ranked[0]['when']);
    }

    public function test_missing_weight_is_treated_as_zero(): void
    {
        $ranked = Featured::rank([
            self::item(0, '2026-06-10', ['id' => 'normal']),   // no weight key
            self::item(5, '2026-06-01', ['id' => 'weighted']),
        ], 5);

        $this->assertSame(['weighted', 'normal'], array_column($ranked, 'id'));
    }

    public function test_caps_at_count(): void
    {
        $ranked = Featured::rank([
            self::item(30, '2026-06-01', ['id' => 'a']),
            self::item(20, '2026-06-01', ['id' => 'b']),
            self::item(10, '2026-06-01', ['id' => 'c']),
        ], 2);

        $this->assertSame(['a', 'b'], array_column($ranked, 'id'));
    }

    public function test_non_positive_count_yields_empty(): void
    {
        $this->assertSame([], Featured::rank([self::item(10, '2026-06-01')], 0));
        $this->assertSame([], Featured::rank([self::item(10, '2026-06-01')], -3));
    }

    public function test_equal_weight_and_date_keeps_input_order(): void
    {
        // Stable: a true tie preserves the caller's order (the spine pre-sorts
        // events by date and posts by recency, so input order is meaningful).
        $ranked = Featured::rank([
            self::item(10, '2026-06-01', ['id' => 'first']),
            self::item(10, '2026-06-01', ['id' => 'second']),
        ], 5);

        $this->assertSame(['first', 'second'], array_column($ranked, 'id'));
    }

    public function test_empty_input_yields_empty(): void
    {
        $this->assertSame([], Featured::rank([], 5));
    }
}
