<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms\Tests;

use FirstChurch\BreezeForms\Shortcode;
use PHPUnit\Framework\TestCase;

final class ShortcodeTest extends TestCase
{
    /** @var array<string,string> */
    private const MAP = ['1011854' => '603d6c56'];

    // Cycle 19 — defaults applied; missing/unknown mode falls back to button
    public function test_defaults_to_button_mode(): void
    {
        $default = Shortcode::render(['slug' => '603d6c56']);
        $this->assertStringContainsString('<a ', $default);
        $this->assertStringNotContainsString('<iframe', $default);

        $bogus = Shortcode::render(['slug' => '603d6c56', 'mode' => 'wat']);
        $this->assertStringContainsString('<a ', $bogus, 'unknown mode falls back to button');
    }

    public function test_label_attribute_is_used(): void
    {
        $html = Shortcode::render(['slug' => '603d6c56', 'label' => 'Contact us']);
        $this->assertStringContainsString('Contact us', $html);
    }

    // Cycle 20 — embed mode produces an iframe
    public function test_embed_mode_produces_iframe(): void
    {
        $html = Shortcode::render(['slug' => '603d6c56', 'mode' => 'embed']);
        $this->assertStringContainsString('<iframe', $html);
    }

    // Cycle 21 — id (no slug) resolves through the map
    public function test_id_resolves_through_map(): void
    {
        $html = Shortcode::render(['id' => '1011854'], self::MAP);
        $this->assertStringContainsString('/form/603d6c56"', $html);
    }

    // Cycle 22 — invalid / missing input returns empty string, never a fatal
    public function test_invalid_slug_returns_empty_string(): void
    {
        $this->assertSame('', Shortcode::render(['slug' => '../bad']));
        $this->assertSame('', Shortcode::render([]));
        $this->assertSame('', Shortcode::render(['id' => '999999'], self::MAP));
    }
}
