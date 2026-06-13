<?php
/**
 * Tier 2 — the event recurrence engine (the gnarliest logic in the file).
 *
 * Round-trips rule arrays through fcmcp_apply_recurrence (writes FCE meta) and
 * fcmcp_recurrence_to_array (reads it back), plus spot-checks the raw meta.
 *
 * @package FirstChurch\Mcp\Tests
 */

declare(strict_types=1);

namespace FirstChurch\Mcp\Tests;

use PHPUnit\Framework\TestCase;

final class RecurrenceTest extends TestCase
{
    protected function setUp(): void
    {
        fcmcp_test_reset();
    }

    public function testWeeklyRoundTrip(): void
    {
        fcmcp_apply_recurrence(5, array(
            'frequency'   => 'weekly',
            'interval'    => 2,
            'weekly_days' => array('MO', 'WE'),
            'end_date'    => '2026-12-31',
        ));

        $this->assertSame('weekly', get_post_meta(5, '_fce_recurrence', true));
        $this->assertSame('2', get_post_meta(5, '_fce_weekly_interval', true));
        $this->assertSame('MO,WE', get_post_meta(5, '_fce_weekly_days', true));

        $out = fcmcp_recurrence_to_array(5);
        $this->assertSame('weekly', $out['frequency']);
        $this->assertSame(2, $out['interval']);
        $this->assertSame(array('MO', 'WE'), $out['weekly_days']);
        $this->assertSame('2026-12-31', $out['end_date']);
    }

    public function testWeeklyWithoutDaysDefaultInterval(): void
    {
        fcmcp_apply_recurrence(5, array('frequency' => 'weekly'));
        $this->assertSame('1', get_post_meta(5, '_fce_weekly_interval', true), 'interval defaults to 1');
        $this->assertSame('', get_post_meta(5, '_fce_weekly_days', true));
        $this->assertSame(array(), fcmcp_recurrence_to_array(5)['weekly_days']);
    }

    public function testWeeklyNormalizesAndFiltersDayCodes(): void
    {
        fcmcp_apply_recurrence(5, array(
            'frequency'   => 'weekly',
            'weekly_days' => array('mo', 'Friday', 'XX', 'su'),
        ));
        $this->assertSame('MO,FR,SU', get_post_meta(5, '_fce_weekly_days', true));
    }

    public function testMonthlyByDay(): void
    {
        fcmcp_apply_recurrence(5, array('frequency' => 'monthly', 'interval' => 3, 'monthly_type' => 'day'));
        $this->assertSame('3', get_post_meta(5, '_fce_weekly_interval', true));
        $this->assertSame('day', get_post_meta(5, '_fce_monthly_type', true));
        $this->assertSame('', get_post_meta(5, '_fce_monthly_week', true));

        $out = fcmcp_recurrence_to_array(5);
        $this->assertSame('monthly', $out['frequency']);
        $this->assertSame(3, $out['interval']);
        $this->assertSame('day', $out['monthly_type']);
        $this->assertSame(array(), $out['monthly_weeks']);
    }

    public function testMonthlyByWeek(): void
    {
        fcmcp_apply_recurrence(5, array(
            'frequency'     => 'monthly',
            'monthly_type'  => 'week',
            'monthly_weeks' => array('1', 'last', '9'),
        ));
        $this->assertSame('week', get_post_meta(5, '_fce_monthly_type', true));
        $this->assertSame('1,last', get_post_meta(5, '_fce_monthly_week', true));
        $this->assertSame(array('1', 'last'), fcmcp_recurrence_to_array(5)['monthly_weeks']);
    }

    public function testYearly(): void
    {
        fcmcp_apply_recurrence(5, array('frequency' => 'yearly', 'end_date' => '2030-01-01'));
        $out = fcmcp_recurrence_to_array(5);
        $this->assertSame('yearly', $out['frequency']);
        $this->assertSame('2030-01-01', $out['end_date']);
        $this->assertSame(1, $out['interval'], 'yearly defaults to every 1 year');
    }

    public function testYearlyEveryOtherRoundTrip(): void
    {
        fcmcp_apply_recurrence(5, array('frequency' => 'yearly', 'interval' => 2));
        $this->assertSame('2', get_post_meta(5, '_fce_weekly_interval', true), 'interval persists for yearly');

        $out = fcmcp_recurrence_to_array(5);
        $this->assertSame('yearly', $out['frequency']);
        $this->assertSame(2, $out['interval']);
    }

    public function testClearClearsRecurrence(): void
    {
        fcmcp_apply_recurrence(5, array('frequency' => 'weekly', 'weekly_days' => array('MO')));
        fcmcp_apply_recurrence(5, array('frequency' => 'none'));
        $this->assertSame('', get_post_meta(5, '_fce_recurrence', true));
        $this->assertSame(array('frequency' => 'none'), fcmcp_recurrence_to_array(5));
    }

    public function testInvalidFrequencyClearsRecurrence(): void
    {
        fcmcp_apply_recurrence(5, array('frequency' => 'biweekly'));
        $this->assertSame('', get_post_meta(5, '_fce_recurrence', true));
    }

    public function testEmptyFrequencyReadsAsNone(): void
    {
        $this->assertSame(array('frequency' => 'none'), fcmcp_recurrence_to_array(999));
    }

    public function testWeeklyNoDaysClearsDaysMeta(): void
    {
        fcmcp_apply_recurrence(5, array('frequency' => 'weekly', 'weekly_days' => array()));
        $this->assertSame('', get_post_meta(5, '_fce_weekly_days', true));
    }

    public function testMonthlyWithWeekTokensAutoModeToWeek(): void
    {
        fcmcp_apply_recurrence(5, array(
            'frequency'     => 'monthly',
            'monthly_weeks' => array('2', '4'),
        ));
        $this->assertSame('week', get_post_meta(5, '_fce_monthly_type', true));
    }

    public function testWeeklyDaysDefaultToClean(): void
    {
        fcmcp_apply_recurrence(5, array('frequency' => 'weekly'));
        $this->assertSame('', get_post_meta(5, '_fce_weekly_days', true));
    }
}
