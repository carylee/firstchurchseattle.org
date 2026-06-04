<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms\Tests;

use FirstChurch\BreezeForms\Color;
use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase
{
    public function test_accepts_six_digit_hex(): void
    {
        $this->assertSame('92b765', Color::hex('92b765'));
    }

    public function test_strips_leading_hash_and_lowercases(): void
    {
        $this->assertSame('92b765', Color::hex('#92B765'));
    }

    public function test_accepts_three_digit_hex(): void
    {
        $this->assertSame('fff', Color::hex('fff'));
        $this->assertSame('fff', Color::hex('#FFF'));
    }

    public function test_trims_whitespace(): void
    {
        $this->assertSame('92b765', Color::hex('  92b765  '));
    }

    public function test_rejects_invalid(): void
    {
        $this->assertNull(Color::hex('zzz'));
        $this->assertNull(Color::hex('12345'));   // 5 digits
        $this->assertNull(Color::hex('1234567')); // 7 digits
        $this->assertNull(Color::hex('red'));
        $this->assertNull(Color::hex(''));
        $this->assertNull(Color::hex('92b765; }'));
    }
}
