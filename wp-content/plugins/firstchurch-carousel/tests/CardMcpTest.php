<?php

declare(strict_types=1);

namespace FirstChurch\Carousel\Tests;

use PHPUnit\Framework\TestCase;

/**
 * fccar_pick_card_fields(): the partial-update overlay for MCP card writes —
 * only the fields the agent actually passed are returned, so update merges them
 * onto the current card without clobbering untouched fields. Pure.
 */
final class CardMcpTest extends TestCase
{
    public function test_returns_only_provided_card_fields(): void
    {
        $picked = fccar_pick_card_fields(array(
            'id'     => 12,            // not a card field — dropped
            'status' => 'publish',     // not a card field — dropped
            'body'   => 'New body',
            'layout' => 'feature',
        ));
        $this->assertSame(array('layout' => 'feature', 'body' => 'New body'), $picked);
    }

    public function test_empty_input_yields_empty_overlay(): void
    {
        $this->assertSame(array(), fccar_pick_card_fields(array()));
    }

    public function test_keeps_falsey_values_that_were_explicitly_provided(): void
    {
        // preservice=false and an empty body are real edits, not absent keys.
        $picked = fccar_pick_card_fields(array('preservice' => false, 'body' => ''));
        $this->assertArrayHasKey('preservice', $picked);
        $this->assertFalse($picked['preservice']);
        $this->assertArrayHasKey('body', $picked);
        $this->assertSame('', $picked['body']);
    }

    public function test_overlay_merges_onto_current_without_clobbering(): void
    {
        // Mirrors fccar_mcp_update_card's merge step.
        $current = array(
            'title' => 'Welcome', 'layout' => 'intro', 'body' => 'old',
            'prompt' => '', 'details' => '', 'qr_url' => '', 'bg_color' => '', 'preservice' => false,
        );
        $merged = array_merge($current, fccar_pick_card_fields(array('body' => 'new')));
        $this->assertSame('new', $merged['body']);
        $this->assertSame('Welcome', $merged['title'], 'untouched fields survive');
        $this->assertSame('intro', $merged['layout']);
    }
}
