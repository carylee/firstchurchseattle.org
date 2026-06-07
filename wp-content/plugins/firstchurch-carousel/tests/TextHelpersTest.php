<?php

declare(strict_types=1);

namespace FirstChurch\Carousel\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The small pure helpers the resolver leans on: text normalization, list
 * joining, ordinals, weekday parsing, clock detection, and the feed-item
 * tightening that drops empties.
 */
final class TextHelpersTest extends TestCase
{
    public function test_text_decodes_entities_and_trims(): void
    {
        $this->assertSame("Men’s Group", fccar_text('  Men&#8217;s Group  '));
        $this->assertSame('A & B', fccar_text('A &amp; B'));
    }

    #[DataProvider('ordinals')]
    public function test_ordinal(int $n, string $expected): void
    {
        $this->assertSame($expected, fccar_ordinal($n));
    }

    public static function ordinals(): array
    {
        return [
            [1, '1st'], [2, '2nd'], [3, '3rd'], [4, '4th'],
            [11, '11th'], [12, '12th'], [13, '13th'],
            [21, '21st'], [22, '22nd'], [23, '23rd'],
        ];
    }

    public function test_join_list(): void
    {
        $this->assertSame('', fccar_join_list([]));
        $this->assertSame('A', fccar_join_list(['A']));
        $this->assertSame('A & B', fccar_join_list(['A', 'B']));
        $this->assertSame('A, B & C', fccar_join_list(['A', 'B', 'C']));
        $this->assertSame('A & B', fccar_join_list(['A', '', 'B']), 'empties are dropped');
    }

    public function test_weekday_names(): void
    {
        $this->assertSame(['Sunday', 'Thursday'], fccar_weekday_names('SU,TH'));
        $this->assertSame(['Tuesday'], fccar_weekday_names(' tu '));
        $this->assertSame([], fccar_weekday_names('XX'));
    }

    public function test_is_clocklike(): void
    {
        $this->assertTrue(fccar_is_clocklike('7:00 pm'));
        $this->assertTrue(fccar_is_clocklike('9 am'));
        $this->assertFalse(fccar_is_clocklike('Room 302'));
        $this->assertFalse(fccar_is_clocklike('After the worship service'));
    }

    public function test_item_drops_empties_keeps_always_and_true_bools(): void
    {
        $item = fccar_item([
            'id'             => 'card-1',
            'source'         => 'card',
            'layout'         => 'info',
            'title'          => 'Hi',
            'body'           => '',
            'when'           => null,
            'preserviceOnly' => false,
        ]);

        $this->assertSame('card-1', $item['id']);
        $this->assertSame('Hi', $item['title']);
        $this->assertArrayNotHasKey('body', $item, 'empty strings are dropped');
        $this->assertArrayNotHasKey('when', $item, 'nulls are dropped');
        $this->assertArrayNotHasKey('preserviceOnly', $item, 'false bools are dropped');
    }

    public function test_item_keeps_true_bool(): void
    {
        $item = fccar_item(['id' => 'c', 'source' => 'card', 'layout' => 'info', 'preserviceOnly' => true]);
        $this->assertTrue($item['preserviceOnly']);
    }
}
