<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms\Tests;

use FirstChurch\BreezeForms\Sync;
use PHPUnit\Framework\TestCase;

final class SyncTest extends TestCase
{
    /** A representative /api/forms/list_forms payload. */
    private function payload(): array
    {
        return [
            ['id' => '320238',  'name' => 'Check-in & Connection Card', 'url_slug' => '603d6c',   'folder_id' => '37830'],
            ['id' => '1011854', 'name' => '  Contact Us!  ',            'url_slug' => '603d6c56', 'folder_id' => '37830'],
            ['id' => '999',     'name' => 'Bad slug',                   'url_slug' => '../evil',  'folder_id' => '1'],
            ['id' => '',        'name' => 'No id',                      'url_slug' => 'abc123',   'folder_id' => '1'],
        ];
    }

    public function test_maps_core_fields(): void
    {
        $records = Sync::normalize($this->payload());

        $byId = array_column($records, null, 'id');
        $this->assertArrayHasKey('320238', $byId);
        $this->assertSame('603d6c', $byId['320238']['slug']);
        $this->assertSame('Check-in & Connection Card', $byId['320238']['name']);
        $this->assertSame('37830', $byId['320238']['folder_id']);
    }

    public function test_trims_name(): void
    {
        $byId = array_column(Sync::normalize($this->payload()), null, 'id');
        $this->assertSame('Contact Us!', $byId['1011854']['name']);
    }

    public function test_drops_forms_with_invalid_slug(): void
    {
        $byId = array_column(Sync::normalize($this->payload()), null, 'id');
        $this->assertArrayNotHasKey('999', $byId, 'a path-injection slug must be dropped');
    }

    public function test_drops_forms_with_missing_id(): void
    {
        $names = array_column(Sync::normalize($this->payload()), 'name');
        $this->assertNotContains('No id', $names);
    }

    public function test_sorts_by_name_case_insensitively(): void
    {
        $raw = [
            ['id' => '2', 'name' => 'banana', 'url_slug' => 'b1', 'folder_id' => '1'],
            ['id' => '1', 'name' => 'Apple',  'url_slug' => 'a1', 'folder_id' => '1'],
            ['id' => '3', 'name' => 'cherry', 'url_slug' => 'c1', 'folder_id' => '1'],
        ];
        $this->assertSame(['Apple', 'banana', 'cherry'], array_column(Sync::normalize($raw), 'name'));
    }

    public function test_empty_payload_yields_empty_list(): void
    {
        $this->assertSame([], Sync::normalize([]));
    }
}
