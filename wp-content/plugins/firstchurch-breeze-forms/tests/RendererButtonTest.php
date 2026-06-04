<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms\Tests;

use FirstChurch\BreezeForms\Renderer;
use PHPUnit\Framework\TestCase;

final class RendererButtonTest extends TestCase
{
    private const URL = 'https://firstchurchseattle.breezechms.com/form/603d6c56';

    // Cycle 9 — one anchor with the correct href
    public function test_emits_single_anchor_with_href(): void
    {
        $html = Renderer::button(['url' => self::URL, 'label' => 'Sign up']);

        $this->assertSame(1, substr_count($html, '<a '), 'should emit exactly one anchor');
        $this->assertStringContainsString('href="' . self::URL . '"', $html);
    }

    // Cycle 10 — label is rendered and HTML-escaped
    public function test_label_is_rendered_and_escaped(): void
    {
        $html = Renderer::button(['url' => self::URL, 'label' => '<script>alert(1)</script>']);

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    // Cycle 11 — new_tab toggles target + rel
    public function test_new_tab_adds_target_and_rel(): void
    {
        $opened = Renderer::button(['url' => self::URL, 'label' => 'Go', 'new_tab' => true]);
        $this->assertStringContainsString('target="_blank"', $opened);
        $this->assertStringContainsString('rel="noopener noreferrer"', $opened);

        $same = Renderer::button(['url' => self::URL, 'label' => 'Go']);
        $this->assertStringNotContainsString('target=', $same);
        $this->assertStringNotContainsString('rel=', $same);
    }

    // Cycle 12 — theme + plugin hook classes
    public function test_carries_theme_and_plugin_classes(): void
    {
        $html = Renderer::button(['url' => self::URL, 'label' => 'Go']);
        $this->assertStringContainsString('maranatha-button', $html);
        $this->assertStringContainsString('fcbf-button', $html);
    }

    // Cycle 13 — a quote in the URL cannot break out of the href attribute
    public function test_url_quote_cannot_break_out_of_attribute(): void
    {
        $evil = 'https://firstchurchseattle.breezechms.com/form/x"onmouseover=alert(1)';
        $html = Renderer::button(['url' => $evil, 'label' => 'Go']);

        $this->assertStringNotContainsString('"onmouseover', $html);
        $this->assertStringContainsString('%22onmouseover', $html);
    }
}
