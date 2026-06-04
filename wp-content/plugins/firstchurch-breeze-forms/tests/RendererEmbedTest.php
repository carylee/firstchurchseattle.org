<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms\Tests;

use FirstChurch\BreezeForms\Renderer;
use PHPUnit\Framework\TestCase;

/**
 * Mode 2 uses Breeze's official embed: a `breeze_form_embed` div that their
 * form_embed.js turns into an auto-resizing iframe. We render the (escaped) div
 * + a no-JS fallback link.
 */
final class RendererEmbedTest extends TestCase
{
    private function base(array $extra = []): array
    {
        return array_merge(['slug' => '603d6c56', 'subdomain' => 'firstchurchseattle'], $extra);
    }

    public function test_emits_breeze_embed_div(): void
    {
        $html = Renderer::embed($this->base());
        $this->assertStringContainsString('class="breeze_form_embed"', $html);
        $this->assertStringContainsString('data-subdomain="firstchurchseattle"', $html);
        $this->assertStringContainsString('data-address="603d6c56"', $html);
        $this->assertStringContainsString('data-width="100%"', $html);
    }

    public function test_theming_attrs_included_when_present(): void
    {
        $html = Renderer::embed($this->base([
            'background_color' => 'ffffff',
            'border_width'     => '0',
            'border_color'     => '000000',
            'button_color'     => '92b765',
        ]));
        $this->assertStringContainsString('data-background_color="ffffff"', $html);
        $this->assertStringContainsString('data-border_width="0"', $html);
        $this->assertStringContainsString('data-border_color="000000"', $html);
        $this->assertStringContainsString('data-button_color="92b765"', $html);
    }

    public function test_theming_attrs_omitted_when_empty(): void
    {
        $html = Renderer::embed($this->base());
        $this->assertStringNotContainsString('data-button_color', $html);
        $this->assertStringNotContainsString('data-background_color', $html);
    }

    public function test_has_noscript_fallback_link(): void
    {
        $html = Renderer::embed($this->base());
        $this->assertStringContainsString('<noscript>', $html);
        $this->assertStringContainsString(
            'href="https://firstchurchseattle.breezechms.com/form/603d6c56"',
            $html
        );
    }

    public function test_container_max_width_is_integer_coerced(): void
    {
        $this->assertStringContainsString('max-width:500px', Renderer::embed($this->base(['max_width' => 500])));
        $this->assertStringContainsString('max-width:680px', Renderer::embed($this->base(['max_width' => 'junk'])));
    }

    public function test_values_are_escaped(): void
    {
        $html = Renderer::embed($this->base(['background_color' => 'a"b']));
        $this->assertStringContainsString('&quot;', $html);
        $this->assertStringNotContainsString('a"b', $html);
    }
}
