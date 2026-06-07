<?php

declare(strict_types=1);

namespace FirstChurch\Carousel\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Proves the red/green loop runs and the WP shims behave before any plugin
 * logic is exercised.
 */
final class HarnessTest extends TestCase
{
    public function test_the_loop_is_green(): void
    {
        $this->assertTrue(true);
    }

    public function test_plugin_constants_loaded(): void
    {
        $this->assertSame('carousel_card', FCCAR_CPT);
        $this->assertContains('qr_callout', FCCAR_LAYOUTS);
    }

    public function test_wp_shims_are_faithful(): void
    {
        $this->assertSame('', esc_url_raw('javascript:alert(1)'), 'esc_url_raw rejects non-http schemes');
        $this->assertSame('https://x/a%20b', esc_url_raw('https://x/a b'), 'esc_url_raw encodes spaces');
        $this->assertSame('a b', sanitize_text_field("  a\n b  "), 'sanitize_text_field collapses whitespace');
    }
}
