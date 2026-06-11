<?php
/**
 * fcmcp_apply_event_fields — the scalar event meta writer shared by
 * create-event / update-event. Focused on the field→meta mapping and the
 * "only write keys that were supplied" gate.
 *
 * @package FirstChurch\Mcp\Tests
 */

declare(strict_types=1);

namespace FirstChurch\Mcp\Tests;

use PHPUnit\Framework\TestCase;

final class EventFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        fcmcp_test_reset();
    }

    public function testWritesScalarFields(): void
    {
        fcmcp_apply_event_fields(5, array(
            'start_date'       => '2026-07-01',
            'time'             => '19:00',
            'time_text'        => 'doors 6, show 7',
            'venue'            => 'Sanctuary',
            'registration_url' => 'https://example.org/rsvp',
        ));

        $this->assertSame('2026-07-01', get_post_meta(5, '_fce_dtstart', true));
        $this->assertSame('19:00', get_post_meta(5, '_fce_time', true));
        $this->assertSame('doors 6, show 7', get_post_meta(5, '_fce_time_text', true));
        $this->assertSame('Sanctuary', get_post_meta(5, '_fce_venue', true));
    }

    /** time_text is independent of the machine clock — either, both, or neither. */
    public function testTimeTextWithoutClock(): void
    {
        fcmcp_apply_event_fields(5, array( 'time_text' => '9:30 & 11:00 services' ));

        $this->assertSame('9:30 & 11:00 services', get_post_meta(5, '_fce_time_text', true));
        $this->assertSame('', get_post_meta(5, '_fce_time', true), 'time stays unset when only time_text is given');
    }

    /** Absent keys must not be written (so a partial update leaves them alone). */
    public function testOmittedFieldsAreNotWritten(): void
    {
        update_post_meta(5, '_fce_time_text', 'doors 6, show 7');
        fcmcp_apply_event_fields(5, array( 'venue' => 'Chapel' ));

        $this->assertSame('doors 6, show 7', get_post_meta(5, '_fce_time_text', true), 'untouched on partial update');
    }

    /** Passing an empty string clears the field (the documented "pass \"\" to clear"). */
    public function testEmptyStringClears(): void
    {
        update_post_meta(5, '_fce_time_text', 'doors 6, show 7');
        fcmcp_apply_event_fields(5, array( 'time_text' => '' ));

        $this->assertSame('', get_post_meta(5, '_fce_time_text', true));
    }
}
