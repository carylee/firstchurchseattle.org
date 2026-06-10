<?php

declare(strict_types=1);

namespace FirstChurch\ConnectionCard\Tests;

use PHPUnit\Framework\TestCase;

final class HoneypotTest extends TestCase
{
    public function test_empty_honeypot_fields_pass(): void
    {
        $this->assertFalse(fcc_is_honeypot([]));
        $this->assertFalse(fcc_is_honeypot(['website' => '', 'url' => '']));
    }

    public function test_a_filled_honeypot_field_is_caught(): void
    {
        $this->assertTrue(fcc_is_honeypot(['website' => 'http://spam.example']));
        $this->assertTrue(fcc_is_honeypot(['url' => 'http://spam.example']));
    }
}
