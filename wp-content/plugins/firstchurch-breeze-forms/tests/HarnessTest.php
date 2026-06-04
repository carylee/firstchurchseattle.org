<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Proves the red/green loop runs before any real behavior exists.
 */
final class HarnessTest extends TestCase
{
    public function test_the_loop_is_green(): void
    {
        $this->assertTrue(true);
    }

    public function test_wp_shims_are_loaded(): void
    {
        $this->assertSame('http://x/a&#038;b', esc_url('http://x/a&b'), 'esc_url should encode ampersands');
        $this->assertSame('', esc_url('javascript:alert(1)'), 'esc_url should reject non-http schemes');
        $this->assertSame('&quot;', esc_attr('"'), 'esc_attr should encode quotes');
    }
}
