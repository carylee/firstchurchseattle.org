<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms\Tests;

use FirstChurch\BreezeForms\Url;
use PHPUnit\Framework\TestCase;

/**
 * Guards the committed baked seed (data/forms.json). Any regeneration that
 * produces a malformed slug, a missing field, or a duplicate id fails here
 * instead of silently shipping a broken picker / dead link.
 */
final class SeedTest extends TestCase
{
    /** @return array<int,array<string,string>> */
    private function seed(): array
    {
        $path = __DIR__ . '/../data/forms.json';
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data, 'forms.json must decode to an array');
        return $data;
    }

    public function test_seed_is_non_empty(): void
    {
        $this->assertNotEmpty($this->seed());
    }

    public function test_every_record_is_well_formed(): void
    {
        foreach ($this->seed() as $i => $r) {
            $this->assertArrayHasKey('id', $r, "record $i");
            $this->assertArrayHasKey('slug', $r, "record $i");
            $this->assertArrayHasKey('name', $r, "record $i");
            $this->assertArrayHasKey('folder_id', $r, "record $i");
            $this->assertNotSame('', trim($r['name']), "record $i has a name");
            $this->assertNotNull(
                Url::for_slug($r['slug']),
                "record $i slug '{$r['slug']}' must be valid"
            );
        }
    }

    public function test_ids_are_unique(): void
    {
        $ids = array_column($this->seed(), 'id');
        $this->assertSame(array_values(array_unique($ids)), array_values($ids), 'duplicate form id in seed');
    }
}
