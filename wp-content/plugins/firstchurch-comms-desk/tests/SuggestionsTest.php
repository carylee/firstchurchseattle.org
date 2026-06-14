<?php

declare(strict_types=1);

namespace FirstChurch\CommsDesk\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Rendering the pre-fetched stock-photo suggestions on an imageless card as a
 * row of one-click thumbnails — reusing the existing .fccd-stock-pick control
 * so the established import handler works unchanged.
 */
final class SuggestionsTest extends TestCase
{
    public function test_empty_renders_nothing(): void
    {
        $this->assertSame('', fccd_render_suggestions(array()));
    }

    public function test_renders_clickable_thumbnails_carrying_import_meta(): void
    {
        $cands = array(
            array(
                'thumbnail' => 'https://img/t1.jpg',
                'url'       => 'https://img/u1.jpg',
                'title'     => 'candles',
                'creator'   => 'Jane',
                'meta'      => array('url' => 'https://img/u1.jpg', 'license' => 'Pixabay License'),
            ),
        );
        $html = fccd_render_suggestions($cands);

        // Reuses the existing pick control + carries the thumbnail.
        $this->assertStringContainsString('fccd-stock-pick', $html);
        $this->assertStringContainsString('https://img/t1.jpg', $html);

        // The import payload rides in data-meta (url-encoded JSON the JS decodes).
        $this->assertMatchesRegularExpression('/data-meta="[^"]+"/', $html);
        preg_match('/data-meta="([^"]+)"/', $html, $m);
        $decoded = json_decode(rawurldecode($m[1]), true);
        $this->assertSame('https://img/u1.jpg', $decoded['url']);
        $this->assertSame('Pixabay License', $decoded['license']);
    }

    public function test_skips_candidates_without_a_thumbnail(): void
    {
        $html = fccd_render_suggestions(array(
            array('thumbnail' => '', 'url' => 'u', 'meta' => array('url' => 'u')),
        ));
        $this->assertSame('', $html);
    }

    public function test_escapes_a_hostile_thumbnail_url(): void
    {
        $html = fccd_render_suggestions(array(
            array('thumbnail' => 'javascript:alert(1)', 'url' => 'u', 'meta' => array('url' => 'u')),
        ));
        // esc_url drops the javascript: scheme → the src is neutralized.
        $this->assertStringNotContainsString('javascript:', $html);
    }
}
