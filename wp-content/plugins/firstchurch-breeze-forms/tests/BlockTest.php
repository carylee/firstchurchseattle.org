<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms\Tests;

use FirstChurch\BreezeForms\Block;
use PHPUnit\Framework\TestCase;

/**
 * The block's render_callback is a thin adapter onto the already-tested
 * Shortcode::render. The only logic worth testing is the attribute mapping:
 * block attributes (JS types — booleans, numbers, camelCase) into the string
 * shortcode atts Shortcode::render expects.
 */
final class BlockTest extends TestCase
{
    public function test_maps_identity_fields(): void
    {
        $a = Block::to_shortcode_atts(['slug' => 's1', 'id' => '42', 'label' => 'Go']);
        $this->assertSame('s1', $a['slug']);
        $this->assertSame('42', $a['id']);
        $this->assertSame('Go', $a['label']);
    }

    public function test_valid_mode_passes_through_invalid_falls_back(): void
    {
        $this->assertSame('embed', Block::to_shortcode_atts(['mode' => 'embed'])['mode']);
        $this->assertSame('button', Block::to_shortcode_atts(['mode' => 'wat'])['mode']);
        $this->assertSame('button', Block::to_shortcode_atts([])['mode']);
    }

    public function test_new_tab_boolean_becomes_string(): void
    {
        $this->assertSame('true',  Block::to_shortcode_atts(['newTab' => true])['new_tab']);
        $this->assertSame('false', Block::to_shortcode_atts(['newTab' => false])['new_tab']);
        $this->assertSame('false', Block::to_shortcode_atts([])['new_tab']);
    }

    public function test_dimensions_become_strings_zero_is_dropped(): void
    {
        $a = Block::to_shortcode_atts(['height' => 900, 'maxWidth' => 720]);
        $this->assertSame('900', $a['height']);
        $this->assertSame('720', $a['max_width']);

        $zero = Block::to_shortcode_atts(['height' => 0, 'maxWidth' => 0]);
        $this->assertSame('', $zero['height'], 'unset dimension stays empty so Renderer uses its default');
        $this->assertSame('', $zero['max_width']);
    }

    public function test_empty_attributes_yield_safe_defaults(): void
    {
        $a = Block::to_shortcode_atts([]);
        $this->assertSame('', $a['slug']);
        $this->assertSame('', $a['id']);
        $this->assertSame('Open form', $a['label']);
    }

    public function test_passes_embed_theming_through(): void
    {
        $a = Block::to_shortcode_atts([
            'backgroundColor' => 'ffffff',
            'borderColor'     => '000000',
            'borderWidth'     => '2',
            'buttonColor'     => '92b765',
        ]);
        $this->assertSame('ffffff', $a['background_color']);
        $this->assertSame('000000', $a['border_color']);
        $this->assertSame('2', $a['border_width']);
        $this->assertSame('92b765', $a['button_color']);
    }
}
