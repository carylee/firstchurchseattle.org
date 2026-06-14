<?php

declare(strict_types=1);

namespace FirstChurch\CommsDesk\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Rendering the AI's structured "gaps" as a check-these checklist on the card,
 * turning review from "re-read everything" into "confirm these two things."
 */
final class GapsRenderTest extends TestCase
{
    public function test_empty_gaps_render_nothing(): void
    {
        $this->assertSame('', fccd_render_gaps(array()));
    }

    public function test_renders_a_checklist_of_questions(): void
    {
        $html = fccd_render_gaps(array(
            array('field' => 'venue', 'question' => 'Main lot or 8th Ave lot?'),
            array('field' => '', 'question' => 'What time does it end?'),
        ));
        $this->assertStringContainsString('fccd-gaps', $html);
        $this->assertStringContainsString('Main lot or 8th Ave lot?', $html);
        $this->assertStringContainsString('What time does it end?', $html);
        // The field name is surfaced as a label when present.
        $this->assertStringContainsString('venue', $html);
    }

    public function test_escapes_markup(): void
    {
        $html = fccd_render_gaps(array(array('field' => '<i>x</i>', 'question' => '<script>alert(1)</script>')));
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<i>x</i>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
