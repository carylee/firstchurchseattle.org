<?php

declare(strict_types=1);

namespace FirstChurch\CommsDesk\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The "What was sent" disclosure and the elevated AI-note callout — the two
 * trust affordances that let the coordinator sanity-check a draft against its
 * source before publishing (intake-spine.md §11: guard voice over-correction).
 */
final class OriginalTest extends TestCase
{
    public function test_original_renders_collapsible_qa_with_contact(): void
    {
        $html = fccd_render_original(
            array(
                array('label' => 'What is the event?', 'value' => 'Choir concert'),
                array('label' => 'When?', 'value' => 'June 22 at 7pm'),
            ),
            array('name' => 'Jane Smith', 'email' => 'jane@example.org', 'phone' => '')
        );

        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('What was sent', $html);
        $this->assertStringContainsString('Jane Smith', $html);
        $this->assertStringContainsString('jane@example.org', $html);
        $this->assertStringContainsString('What is the event?', $html);
        $this->assertStringContainsString('Choir concert', $html);
    }

    public function test_original_escapes_markup_in_values(): void
    {
        $html = fccd_render_original(
            array(array('label' => 'Note', 'value' => '<script>alert(1)</script>')),
            array()
        );
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_original_is_empty_when_nothing_to_show(): void
    {
        $this->assertSame('', fccd_render_original(array(), array()));
        $this->assertSame('', fccd_render_original(array(), array('name' => '', 'email' => '')));
    }

    public function test_note_callout_is_empty_for_blank_note(): void
    {
        $this->assertSame('', fccd_render_note_callout(''));
        $this->assertSame('', fccd_render_note_callout('   '));
    }

    public function test_note_callout_wraps_and_escapes_a_real_note(): void
    {
        $html = fccd_render_note_callout('Couldn\'t confirm the end time — guessed 8pm. <b>check</b>');
        $this->assertStringContainsString('fccd-note-callout', $html);
        $this->assertStringContainsString('guessed 8pm', $html);
        $this->assertStringNotContainsString('<b>check</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
    }
}
