<?php

declare(strict_types=1);

namespace FirstChurch\Happenings\Tests;

use FirstChurch\Happenings\Layout;
use PHPUnit\Framework\TestCase;

final class LayoutTest extends TestCase
{
    public function test_when_present_is_event(): void
    {
        $this->assertSame('event', Layout::detect('Bible Study', 'body', 'Sundays', ''));
        $this->assertSame('event', Layout::detect('', '', 'Tuesdays', ''));
    }

    public function test_title_and_body_is_info(): void
    {
        $this->assertSame('info', Layout::detect('Name Tag', 'Find one at the desk', '', ''));
    }

    public function test_cta_with_title_or_body_is_qr_callout(): void
    {
        $this->assertSame('qr_callout', Layout::detect('Give', '', '', 'https://give'));
        $this->assertSame('qr_callout', Layout::detect('', 'Scan to give', '', 'https://give'));
    }

    public function test_title_only_is_divider(): void
    {
        $this->assertSame('divider', Layout::detect('Upcoming Events', '', '', ''));
    }

    public function test_body_only_falls_back_to_info(): void
    {
        $this->assertSame('info', Layout::detect('', 'just a body', '', ''));
        $this->assertSame('info', Layout::detect('', '', '', ''));
    }
}
