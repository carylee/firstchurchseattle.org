<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms\Tests;

use FirstChurch\BreezeForms\Store;
use PHPUnit\Framework\TestCase;

final class StoreTest extends TestCase
{
    private const SYNCED = [['id' => '1', 'slug' => 's1', 'name' => 'Synced', 'folder_id' => '1']];
    private const BAKED  = [['id' => '9', 'slug' => 's9', 'name' => 'Baked',  'folder_id' => '1']];

    public function test_prefers_synced_when_present(): void
    {
        $this->assertSame(self::SYNCED, Store::resolve(self::SYNCED, self::BAKED));
    }

    public function test_falls_back_to_baked_when_synced_empty(): void
    {
        $this->assertSame(self::BAKED, Store::resolve([], self::BAKED));
    }

    public function test_falls_back_to_baked_when_synced_null(): void
    {
        $this->assertSame(self::BAKED, Store::resolve(null, self::BAKED));
    }
}
