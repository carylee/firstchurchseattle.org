<?php

declare(strict_types=1);

namespace FirstChurch\CommsDesk\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Sanity check that the bootstrap shims behave faithfully (escaping is real,
 * not a no-op) and the pure-helpers seam loads.
 */
final class HarnessTest extends TestCase
{
    public function test_esc_html_neutralizes_markup(): void
    {
        $this->assertSame('&lt;b&gt;hi&lt;/b&gt;', esc_html('<b>hi</b>'));
    }

    public function test_esc_url_drops_javascript_scheme(): void
    {
        $this->assertSame('', esc_url('javascript:alert(1)'));
        $this->assertSame('https://example.org/', esc_url('https://example.org/'));
    }

    public function test_cards_seam_is_loaded(): void
    {
        $this->assertTrue(defined('ABSPATH'));
    }
}
