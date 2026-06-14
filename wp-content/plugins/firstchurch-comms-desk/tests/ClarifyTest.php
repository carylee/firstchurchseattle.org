<?php

declare(strict_types=1);

namespace FirstChurch\CommsDesk\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Closing the loop: "Needs info" should produce a real, ready-to-send message
 * to the submitter and move the item out of the active queue so it stops
 * nagging until a reply comes back.
 */
final class ClarifyTest extends TestCase
{
    public function test_mailto_carries_recipient_subject_and_question(): void
    {
        $url = fccd_clarification_mailto('jane@example.org', 'Choir Concert', 'What time does it start?');
        $this->assertStringStartsWith('mailto:jane@example.org?', $url);
        $this->assertStringContainsString('subject=', $url);
        $this->assertStringContainsString('body=', $url);
        // The event title and question survive into the encoded message.
        $this->assertStringContainsString(rawurlencode('Choir Concert'), $url);
        $this->assertStringContainsString(rawurlencode('What time does it start?'), $url);
    }

    public function test_mailto_encodes_unsafe_characters_so_params_dont_break(): void
    {
        $url = fccd_clarification_mailto('a@b.org', 'A & B', 'cost? venue=lot');
        $this->assertStringNotContainsString(' ', $url);
        // A raw & / = inside the body would split the mailto params — must be encoded.
        $this->assertStringContainsString('%26', $url); // &
        $this->assertStringContainsString('%3D', $url); // =
    }

    public function test_mailto_is_empty_without_a_recipient(): void
    {
        $this->assertSame('', fccd_clarification_mailto('', 'T', 'q'));
        $this->assertSame('', fccd_clarification_mailto('not-an-email', 'T', 'q'));
    }

    public function test_split_awaiting_separates_parked_items(): void
    {
        $cards = array(
            array('item_id' => 1, 'awaiting' => false, 'title' => 'active1'),
            array('item_id' => 2, 'awaiting' => true, 'title' => 'parked'),
            array('item_id' => 3, 'title' => 'active2'), // no flag = active
        );
        $out = fccd_split_awaiting($cards);
        $this->assertSame(array('active1', 'active2'), array_column($out['active'], 'title'));
        $this->assertSame(array('parked'), array_column($out['awaiting'], 'title'));
    }
}
