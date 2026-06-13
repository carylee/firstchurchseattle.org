<?php

declare(strict_types=1);

namespace FirstChurch\ENews\Tests;

use PHPUnit\Framework\TestCase;

// inc/compose.php is procedural (global namespace) and guards on ABSPATH; define
// it before loading so the file doesn't exit(). It calls no WordPress functions.
if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/');
}
require_once __DIR__ . '/../inc/compose.php';

/**
 * fcen_compose_issue_body() is the API/MCP twin of the editor block template:
 * it must emit valid, spine-composed block markup so an MCP-drafted issue renders
 * and pushes identically to a hand-opened one. Pure (no WordPress).
 */
final class ComposeTest extends TestCase
{
    public function test_composes_the_three_spine_sections_with_their_windows(): void
    {
        $body = \fcen_compose_issue_body();
        $this->assertStringContainsString('<!-- wp:firstchurch/happenings {"section":"featured","count":1} /-->', $body);
        $this->assertStringContainsString('<!-- wp:firstchurch/happenings {"section":"events","weeks":1,"excludeFeatured":true} /-->', $body);
        $this->assertStringContainsString('<!-- wp:firstchurch/happenings {"section":"announcements","days":7,"excludeFeatured":true} /-->', $body);
    }

    public function test_includes_the_editorial_headings_and_footer(): void
    {
        $body = \fcen_compose_issue_body();
        foreach (['From the Pastor', 'This Week', 'News &amp; Notes', 'Recurring at First Church'] as $needle) {
            $this->assertStringContainsString($needle, $body);
        }
        $this->assertStringContainsString('E-news deadline: Tuesdays at noon', $body);
    }

    public function test_block_delimiters_are_balanced(): void
    {
        $body = \fcen_compose_issue_body();
        // Self-closing happenings blocks (/-->) plus paired open/close for the rest.
        $opens  = substr_count($body, '<!-- wp:');
        $closes = substr_count($body, '<!-- /wp:');
        $selfClosing = substr_count($body, ' /-->');
        $this->assertSame($opens, $closes + $selfClosing, 'every non-self-closing block must close');
    }

    public function test_empty_message_yields_a_self_filling_pastoral_block_with_no_fallback(): void
    {
        // No prose supplied → the block carries only its window; it self-fills from
        // the latest pastoral-letters post at render time.
        $this->assertStringContainsString('<!-- wp:firstchurch/pastoral-letter {"days":5} /-->', \fcen_compose_issue_body());
    }

    public function test_pastoral_message_becomes_the_block_fallback_attribute(): void
    {
        // The prose is now a STORED attribute (escaped at render, not HTML-escaped
        // into the markup), so it appears JSON-encoded inside the block delimiter.
        $body = \fcen_compose_issue_body('Grace & "peace" to you');
        $this->assertStringContainsString('wp:firstchurch/pastoral-letter', $body);
        $this->assertStringContainsString('"fallback":"Grace & \\"peace\\" to you"', $body);
        // No standalone Pastoral Message paragraph block anymore.
        $this->assertStringNotContainsString('<p>Grace', $body);
    }

    public function test_attribute_json_keeps_slashes_unescaped(): void
    {
        // Sanity on the encoder used for block-comment attrs (no \/ escaping).
        $this->assertSame('{"a":"x/y"}', \fcen_block_attrs_json(['a' => 'x/y']));
    }
}
