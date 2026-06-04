<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms\Tests;

use FirstChurch\BreezeForms\Renderer;
use PHPUnit\Framework\TestCase;

final class RendererEmbedTest extends TestCase
{
    private const URL = 'https://firstchurchseattle.breezechms.com/form/603d6c56';

    // Cycle 14 — an iframe whose src is the form URL
    public function test_emits_iframe_with_src(): void
    {
        $html = Renderer::embed(['url' => self::URL, 'title' => 'Contact form']);

        $this->assertSame(1, substr_count($html, '<iframe '), 'should emit exactly one iframe');
        $this->assertStringContainsString('src="' . self::URL . '"', $html);
    }

    // Cycle 15 — a non-empty, escaped title for accessibility
    public function test_has_non_empty_escaped_title(): void
    {
        $html = Renderer::embed(['url' => self::URL, 'title' => 'A "quoted" form']);

        $this->assertMatchesRegularExpression('/title="[^"]+"/', $html, 'title must be present and non-empty');
        $this->assertStringContainsString('&quot;quoted&quot;', $html);
        $this->assertStringNotContainsString('"quoted"', $html);
    }

    // Cycle 16 — lazy loading
    public function test_iframe_is_lazy_loaded(): void
    {
        $html = Renderer::embed(['url' => self::URL, 'title' => 'x']);
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    // Cycle 17 — height + max-width are integer-coerced; junk falls back to defaults
    public function test_dimensions_are_integer_coerced(): void
    {
        $valid = Renderer::embed(['url' => self::URL, 'title' => 'x', 'height' => 900, 'max_width' => 500]);
        $this->assertStringContainsString('height="900"', $valid);
        $this->assertStringContainsString('max-width:500px', $valid);

        $junk = Renderer::embed(['url' => self::URL, 'title' => 'x', 'height' => 'abc', 'max_width' => 'xyz']);
        $this->assertStringContainsString('height="800"', $junk, 'junk height falls back to default');
        $this->assertStringContainsString('max-width:680px', $junk, 'junk width falls back to default');
        $this->assertStringNotContainsString('abc', $junk);
        $this->assertStringNotContainsString('xyz', $junk);
    }

    // Cycle 18 — src is escaped
    public function test_src_is_escaped(): void
    {
        $evil = 'https://firstchurchseattle.breezechms.com/form/x"onload=alert(1)';
        $html = Renderer::embed(['url' => $evil, 'title' => 'x']);

        $this->assertStringNotContainsString('"onload', $html);
        $this->assertStringContainsString('%22onload', $html);
    }
}
