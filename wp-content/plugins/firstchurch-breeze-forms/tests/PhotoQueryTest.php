<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms\Tests;

use FirstChurch\BreezeForms\PhotoQuery;
use PHPUnit\Framework\TestCase;

/**
 * Deriving a tasteful stock-photo search from a draft so the Comms Desk can
 * suggest images on its own: a curated category→concept map first, then any
 * AI-suggested visual phrases, then a cleaned-up title as a last resort.
 */
final class PhotoQueryTest extends TestCase
{
    public function test_known_category_maps_to_a_visual_concept(): void
    {
        $this->assertNotSame('', PhotoQuery::forCategory('worship'));
        $this->assertNotSame('', PhotoQuery::forCategory('community'));
        // The mapped query should describe a scene, not echo the slug.
        $this->assertStringNotContainsString('worship', PhotoQuery::forCategory('worship'));
    }

    public function test_unknown_category_maps_to_nothing(): void
    {
        $this->assertSame('', PhotoQuery::forCategory('nonexistent-slug'));
        $this->assertSame('', PhotoQuery::forCategory(''));
    }

    public function test_clean_title_strips_when_suffix_dates_and_years(): void
    {
        $this->assertSame('choir concert', PhotoQuery::cleanTitle('Choir Concert 2026 | June 22'));
        $this->assertSame('youth group car wash', PhotoQuery::cleanTitle('Youth Group Car Wash'));
    }

    public function test_resolve_prefers_category_then_ai_then_title(): void
    {
        // Category wins outright.
        $this->assertSame(
            PhotoQuery::forCategory('worship'),
            PhotoQuery::resolve('worship', 'Maundy Thursday Service', array('candlelight communion'))
        );
        // No category → first non-empty AI phrase.
        $this->assertSame(
            'candlelight communion',
            PhotoQuery::resolve('', 'Maundy Thursday Service', array('  ', 'candlelight communion'))
        );
        // No category, no AI → cleaned title.
        $this->assertSame(
            'spring potluck',
            PhotoQuery::resolve('', 'Spring Potluck 2026', array())
        );
    }

    public function test_clean_candidates_trims_shape_caps_and_drops_incomplete(): void
    {
        $raw = array(
            array('thumbnail' => 't1', 'url' => 'u1', 'title' => 'a', 'creator' => 'c', 'license' => 'Pixabay License'),
            array('thumbnail' => '', 'url' => 'u2'),            // no thumbnail → dropped
            array('thumbnail' => 't3', 'url' => '', 'title' => 'x'), // no url → dropped
            array('thumbnail' => 't4', 'url' => 'u4'),
            array('thumbnail' => 't5', 'url' => 'u5'),
            array('thumbnail' => 't6', 'url' => 'u6'),
        );
        $out = PhotoQuery::cleanCandidates($raw, 2);
        $this->assertCount(2, $out);
        $this->assertSame('t1', $out[0]['thumbnail']);
        $this->assertSame('u1', $out[0]['url']);
        // The full original record rides along as 'meta' for the import call.
        $this->assertSame('Pixabay License', $out[0]['meta']['license']);
    }
}
