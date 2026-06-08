<?php

declare(strict_types=1);

namespace FirstChurch\Happenings\Tests;

use FirstChurch\Happenings\Item;
use PHPUnit\Framework\TestCase;

final class ItemTest extends TestCase
{
    public function test_always_keeps_id_source_layout_even_when_empty(): void
    {
        $out = Item::build(['id' => 'event-1', 'source' => 'event', 'layout' => '']);
        $this->assertSame(['id' => 'event-1', 'source' => 'event', 'layout' => ''], $out);
    }

    public function test_drops_empty_string_and_null(): void
    {
        $out = Item::build(['id' => 'x', 'source' => 's', 'layout' => 'info', 'title' => '', 'image' => null]);
        $this->assertArrayNotHasKey('title', $out);
        $this->assertArrayNotHasKey('image', $out);
    }

    public function test_bool_true_kept_false_dropped(): void
    {
        $out = Item::build(['id' => 'x', 'source' => 's', 'layout' => 'info', 'preserviceOnly' => false, 'flag' => true]);
        $this->assertArrayNotHasKey('preserviceOnly', $out);
        $this->assertTrue($out['flag']);
    }

    public function test_keeps_zero_and_normal_values(): void
    {
        $out = Item::build(['id' => 'x', 'source' => 's', 'layout' => 'info', 'weight' => 0, 'n' => 5, 'title' => 'Hi']);
        $this->assertSame(0, $out['weight']);   // int 0 is a real value, not "empty"
        $this->assertSame(5, $out['n']);
        $this->assertSame('Hi', $out['title']);
    }
}
