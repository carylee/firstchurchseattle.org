<?php

declare(strict_types=1);

namespace FirstChurch\Carousel\Tests;

use PHPUnit\Framework\TestCase;

/**
 * fccar_detect_layout(): kept in lockstep with the slides app's
 * announcementCards() shape-detection so the explicit `layout` we emit agrees
 * with what the renderer would have inferred.
 */
final class LayoutDetectTest extends TestCase
{
    public function test_when_implies_event(): void
    {
        $this->assertSame('event', fccar_detect_layout('Bible Study', '', 'Sundays', ''));
    }

    public function test_title_and_body_is_info(): void
    {
        $this->assertSame('info', fccar_detect_layout('Heads up', 'Some body', '', ''));
    }

    public function test_cta_with_title_is_qr_callout(): void
    {
        $this->assertSame('qr_callout', fccar_detect_layout('Give', '', '', 'https://x/give'));
    }

    public function test_title_only_is_divider(): void
    {
        $this->assertSame('divider', fccar_detect_layout('Worshipping with Us', '', '', ''));
    }

    public function test_body_only_falls_back_to_info(): void
    {
        $this->assertSame('info', fccar_detect_layout('', 'just a body', '', ''));
    }

    public function test_when_wins_over_cta(): void
    {
        $this->assertSame('event', fccar_detect_layout('X', 'Y', 'Friday', 'https://x'));
    }
}
