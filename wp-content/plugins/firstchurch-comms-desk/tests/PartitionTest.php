<?php

declare(strict_types=1);

namespace FirstChurch\CommsDesk\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Triage: split the worklist into "ready to publish" (high-confidence, complete)
 * vs "needs a look", and order each so the coordinator can blast the sure ones
 * and spend attention on the risky ones.
 */
final class PartitionTest extends TestCase
{
    private function card(array $over): array
    {
        return array_merge(
            array('type' => 'review', 'confidence' => 0.9, 'photo' => 'http://x/i.jpg', 'note' => '', 'title' => 't'),
            $over
        );
    }

    public function test_ready_requires_high_confidence_photo_and_no_note(): void
    {
        $this->assertTrue(fccd_card_is_ready($this->card(array())));
        $this->assertFalse(fccd_card_is_ready($this->card(array('confidence' => 0.5))), 'low confidence');
        $this->assertFalse(fccd_card_is_ready($this->card(array('photo' => ''))), 'no photo');
        $this->assertFalse(fccd_card_is_ready($this->card(array('note' => 'check the time'))), 'has a note');
        $this->assertFalse(fccd_card_is_ready($this->card(array('confidence' => null))), 'unknown confidence');
    }

    public function test_revisions_are_never_ready(): void
    {
        $this->assertFalse(fccd_card_is_ready(array('type' => 'revision', 'confidence' => 1.0)));
    }

    public function test_partition_groups_and_sorts(): void
    {
        $cards = array(
            $this->card(array('title' => 'A', 'confidence' => 0.82)),
            $this->card(array('title' => 'B', 'confidence' => 0.99)),
            $this->card(array('title' => 'LowC', 'confidence' => 0.40)),
            $this->card(array('title' => 'LowA', 'confidence' => 0.20)),
            array('type' => 'revision', 'title' => 'Rev', 'confidence' => 0.7),
        );
        $out = fccd_partition_cards($cards);

        // Ready: only the two complete high-confidence ones, surest first.
        $this->assertSame(array('B', 'A'), array_column($out['ready'], 'title'));
        // Needs a look: the rest, most-uncertain first (revision's 0.7 sorts among them).
        $this->assertSame(array('LowA', 'LowC', 'Rev'), array_column($out['look'], 'title'));
    }

    public function test_partition_handles_empty(): void
    {
        $out = fccd_partition_cards(array());
        $this->assertSame(array(), $out['ready']);
        $this->assertSame(array(), $out['look']);
    }
}
