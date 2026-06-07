<?php

declare(strict_types=1);

namespace FirstChurch\Happenings\Tests;

use FirstChurch\Happenings\Id;
use PHPUnit\Framework\TestCase;

final class IdTest extends TestCase
{
    public function test_parses_each_source_prefix(): void
    {
        $this->assertSame(['prefix' => 'card', 'num' => 12], Id::parse('card-12'));
        $this->assertSame(['prefix' => 'event', 'num' => 7], Id::parse('event-7'));
        $this->assertSame(['prefix' => 'announcement', 'num' => 9], Id::parse('announcement-9'));
    }

    public function test_rejects_malformed(): void
    {
        $this->assertNull(Id::parse(''));
        $this->assertNull(Id::parse('bogus'));
        $this->assertNull(Id::parse('event-'));
        $this->assertNull(Id::parse('event-x'));
        $this->assertNull(Id::parse('EVENT-7'));     // uppercase
        $this->assertNull(Id::parse('event_7'));     // wrong separator
        $this->assertNull(Id::parse(' event-7'));    // leading space
    }
}
