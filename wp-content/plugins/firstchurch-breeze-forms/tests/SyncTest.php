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

    public function test_from_json_parses_a_response_body(): void
    {
        $records = Sync::from_json((string) json_encode($this->payload()));
        $this->assertNotNull($records);
        $this->assertCount(2, $records, 'two valid forms survive normalization');
    }

    public function test_from_json_returns_null_on_unparseable_body(): void
    {
        $this->assertNull(Sync::from_json('<html>403 Forbidden</html>'));
        $this->assertNull(Sync::from_json('"a bare string"'), 'a non-array JSON value is not a form list');
    }

    public function test_from_json_handles_empty_array(): void
    {
        $this->assertSame([], Sync::from_json('[]'));
    }

    // --- descriptions: extract the leading instructional text from a form's fields ---

    public function test_lead_description_extracts_first_paragraph_cleaned(): void
    {
        $fields = [
            ['field_type' => 'name', 'name' => 'Name'],
            ['field_type' => 'paragraph', 'name' => 'Tell us how we can pray. <br /><br />All requests &amp; notes are shared.'],
            ['field_type' => 'notes', 'name' => 'Request'],
        ];
        $this->assertSame(
            'Tell us how we can pray. All requests & notes are shared.',
            Sync::lead_description($fields)
        );
    }

    public function test_lead_description_accepts_header_and_section(): void
    {
        $this->assertSame('A Heading', Sync::lead_description([['field_type' => 'header', 'name' => 'A Heading']]));
        $this->assertSame('A Section', Sync::lead_description([['field_type' => 'section', 'name' => 'A Section']]));
    }

    public function test_lead_description_skips_empty_instructional_fields(): void
    {
        $fields = [
            ['field_type' => 'paragraph', 'name' => '   '],
            ['field_type' => 'paragraph', 'name' => 'The real intro.'],
        ];
        $this->assertSame('The real intro.', Sync::lead_description($fields));
    }

    public function test_lead_description_returns_empty_when_none(): void
    {
        $fields = [
            ['field_type' => 'name', 'name' => 'Name'],
            ['field_type' => 'single_line', 'name' => 'Email'],
        ];
        $this->assertSame('', Sync::lead_description($fields));
        $this->assertSame('', Sync::lead_description([]));
    }

    public function test_with_descriptions_attaches_by_id(): void
    {
        $records = [
            ['id' => '1', 'slug' => 's1', 'name' => 'One', 'folder_id' => '0'],
            ['id' => '2', 'slug' => 's2', 'name' => 'Two', 'folder_id' => '0'],
        ];
        $out = Sync::with_descriptions($records, ['1' => 'first blurb']);
        $this->assertSame('first blurb', $out[0]['description']);
        $this->assertSame('', $out[1]['description'], 'missing id yields empty description');
        $this->assertSame('s1', $out[0]['slug'], 'existing fields are preserved');
    }
}
