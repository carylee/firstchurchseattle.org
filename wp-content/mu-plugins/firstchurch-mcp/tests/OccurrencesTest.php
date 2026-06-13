<?php
/**
 * Tier 2 — the firstchurch/list-event-occurrences expansion wrapper.
 *
 * The RRULE math lives in firstchurch-events (covered by its own OccurrencesTest);
 * here we assert the MCP wrapper's own behavior: window defaulting/clamping,
 * skip filtering, the limit/truncated contract, start-time formatting, and the
 * permission/not-found guards. The bootstrap wires the real recurrence engine in,
 * so these also exercise the end-to-end path — including the yearly-interval fix.
 *
 * @package FirstChurch\Mcp\Tests
 */

declare(strict_types=1);

namespace FirstChurch\Mcp\Tests;

use PHPUnit\Framework\TestCase;

final class OccurrencesTest extends TestCase
{
    protected function setUp(): void
    {
        fcmcp_test_reset();
        fcmcp_test_set_caps(array('read', 'edit_posts', 'edit_post'));
    }

    /** Seed a published event with a start date (+ optional time), return its id. */
    private function seedEvent(string $dtstart, string $time = '', string $status = 'publish'): int
    {
        $post = fcmcp_test_add_post(array('post_type' => 'fce_event', 'post_status' => $status, 'post_title' => 'Test Event'));
        update_post_meta($post->ID, '_fce_dtstart', $dtstart);
        if ('' !== $time) {
            update_post_meta($post->ID, '_fce_time', $time);
        }
        return $post->ID;
    }

    /** @return array<int,string> Y-m-d of each returned occurrence. */
    private function dates(array $result): array
    {
        return array_map(static fn ($o) => $o['date'], $result['occurrences']);
    }

    public function testOneOffReturnsItsSingleDate(): void
    {
        $id  = $this->seedEvent('2026-06-17');
        $out = fcmcp_list_event_occurrences(array('id' => $id, 'from_date' => '2026-06-01', 'to_date' => '2026-06-30'));
        $this->assertSame(array('2026-06-17'), $this->dates($out));
        $this->assertSame(1, $out['count']);
        $this->assertFalse($out['truncated']);
    }

    public function testOneOffOutsideWindowIsEmpty(): void
    {
        $id  = $this->seedEvent('2026-07-04');
        $out = fcmcp_list_event_occurrences(array('id' => $id, 'from_date' => '2026-06-01', 'to_date' => '2026-06-30'));
        $this->assertSame(array(), $out['occurrences']);
        $this->assertSame(0, $out['count']);
    }

    public function testWeeklyExpandsAcrossWindow(): void
    {
        $id = $this->seedEvent('2026-06-07'); // a Sunday
        fcmcp_apply_recurrence($id, array('frequency' => 'weekly'));
        $out = fcmcp_list_event_occurrences(array('id' => $id, 'from_date' => '2026-06-01', 'to_date' => '2026-06-30'));
        $this->assertSame(array('2026-06-07', '2026-06-14', '2026-06-21', '2026-06-28'), $this->dates($out));
    }

    public function testSkipDatesAreRemoved(): void
    {
        $id = $this->seedEvent('2026-06-07');
        fcmcp_apply_recurrence($id, array('frequency' => 'weekly'));
        update_post_meta($id, '_fce_skip_dates', '2026-06-14');
        $out = fcmcp_list_event_occurrences(array('id' => $id, 'from_date' => '2026-06-01', 'to_date' => '2026-06-30'));
        $this->assertSame(array('2026-06-07', '2026-06-21', '2026-06-28'), $this->dates($out));
    }

    public function testLimitTruncates(): void
    {
        $id = $this->seedEvent('2026-06-07');
        fcmcp_apply_recurrence($id, array('frequency' => 'weekly'));
        $out = fcmcp_list_event_occurrences(array('id' => $id, 'from_date' => '2026-06-01', 'to_date' => '2027-05-31', 'limit' => 3));
        $this->assertSame(array('2026-06-07', '2026-06-14', '2026-06-21'), $this->dates($out));
        $this->assertSame(3, $out['count']);
        $this->assertTrue($out['truncated']);
    }

    public function testStartCarriesTimeWhenSet(): void
    {
        $id  = $this->seedEvent('2026-06-17', '19:00');
        $out = fcmcp_list_event_occurrences(array('id' => $id, 'from_date' => '2026-06-01', 'to_date' => '2026-06-30'));
        $this->assertSame('2026-06-17 19:00', $out['occurrences'][0]['start']);
    }

    public function testAllDayStartIsNull(): void
    {
        $id  = $this->seedEvent('2026-06-17');
        $out = fcmcp_list_event_occurrences(array('id' => $id, 'from_date' => '2026-06-01', 'to_date' => '2026-06-30'));
        $this->assertNull($out['occurrences'][0]['start']);
    }

    /** End-to-end proof of the yearly-interval fix: "every 2 years" skips the off years. */
    public function testYearlyIntervalSkipsOffYears(): void
    {
        $id = $this->seedEvent('2026-07-04');
        fcmcp_apply_recurrence($id, array('frequency' => 'yearly', 'interval' => 2));
        $out = fcmcp_list_event_occurrences(array('id' => $id, 'from_date' => '2026-07-01', 'to_date' => '2030-12-31'));
        // Window clamps to from+3y (2029-07-01); interval=2 yields 2026 and 2028 only.
        $this->assertSame(array('2026-07-04', '2028-07-04'), $this->dates($out));
    }

    public function testEmptyStartReturnsNoOccurrences(): void
    {
        $post = fcmcp_test_add_post(array('post_type' => 'fce_event', 'post_title' => 'No date'));
        $out  = fcmcp_list_event_occurrences(array('id' => $post->ID));
        $this->assertSame(array(), $out['occurrences']);
        $this->assertSame(0, $out['count']);
    }

    public function testNotFoundForWrongType(): void
    {
        $post = fcmcp_test_add_post(array('post_type' => 'post'));
        $out  = fcmcp_list_event_occurrences(array('id' => $post->ID));
        $this->assertInstanceOf(\WP_Error::class, $out);
        $this->assertSame('not_found', $out->get_error_code());
    }

    public function testForbiddenForDraftWithoutEditCap(): void
    {
        fcmcp_test_set_caps(array('read')); // no edit_post
        $id  = $this->seedEvent('2026-06-17', '', 'draft');
        $out = fcmcp_list_event_occurrences(array('id' => $id));
        $this->assertInstanceOf(\WP_Error::class, $out);
        $this->assertSame('forbidden', $out->get_error_code());
    }

    public function testWindowDefaultsToOneYear(): void
    {
        $win = fcmcp_occurrence_window(array(), '2026-06-13');
        $this->assertSame('2026-06-13', $win['from']);
        $this->assertSame('2027-06-13', $win['to']);
        $this->assertSame(25, $win['limit']);
    }

    public function testWindowClampsToThreeYears(): void
    {
        $win = fcmcp_occurrence_window(array('to_date' => '2099-01-01'), '2026-06-13');
        $this->assertSame('2029-06-13', $win['to']);
    }

    public function testWindowLimitIsClamped(): void
    {
        $this->assertSame(200, fcmcp_occurrence_window(array('limit' => 9999), '2026-06-13')['limit']);
        $this->assertSame(1, fcmcp_occurrence_window(array('limit' => 0), '2026-06-13')['limit']);
    }
}
