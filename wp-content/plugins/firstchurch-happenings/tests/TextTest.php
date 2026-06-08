<?php

declare(strict_types=1);

namespace FirstChurch\Happenings\Tests;

use FirstChurch\Happenings\Text;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase
{
    public function test_decodes_entities_and_trims(): void
    {
        // WP stores "Men&#8217;s" — the feed wants the real curly apostrophe.
        $this->assertSame("Men\u{2019}s Breakfast", Text::clean('  Men&#8217;s Breakfast  '));
        $this->assertSame('A & B', Text::clean('A &amp; B'));
    }

    public function test_clean_handles_non_string(): void
    {
        $this->assertSame('', Text::clean(null));
        $this->assertSame('', Text::clean(''));
    }

    public function test_is_clocklike(): void
    {
        $this->assertTrue(Text::isClocklike('7:00 pm'));
        $this->assertTrue(Text::isClocklike('9 am'));
        $this->assertTrue(Text::isClocklike('10:30'));
        $this->assertFalse(Text::isClocklike('Room 302'));
        $this->assertFalse(Text::isClocklike('After the worship service'));
        $this->assertFalse(Text::isClocklike(''));
    }
}
