<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms\Tests;

use FirstChurch\BreezeForms\Catalog;
use PHPUnit\Framework\TestCase;

final class CatalogTest extends TestCase
{
    /** @var array<string,string> */
    private const MAP = [
        '1011854' => '603d6c56',
        '213392'  => 'a1b2c3',
    ];

    // Cycle 6 — known id resolves to its slug
    public function test_known_id_resolves_to_slug(): void
    {
        $this->assertSame('603d6c56', Catalog::slug_for_id('1011854', self::MAP));
    }

    // Cycle 7 — unknown id resolves to null
    public function test_unknown_id_returns_null(): void
    {
        $this->assertNull(Catalog::slug_for_id('999999', self::MAP));
    }

    // Cycle 8 — empty map returns null
    public function test_empty_map_returns_null(): void
    {
        $this->assertNull(Catalog::slug_for_id('1011854', []));
    }
}
