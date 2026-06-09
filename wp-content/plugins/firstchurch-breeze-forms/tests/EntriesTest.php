<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms\Tests;

use FirstChurch\BreezeForms\Entries;
use PHPUnit\Framework\TestCase;

final class EntriesTest extends TestCase
{
    /** A representative /api/forms/list_form_fields payload (id → label/type). */
    private function fields(): array
    {
        return [
            ['id' => '10', 'field_type' => 'name',          'name' => 'Your Name'],
            ['id' => '11', 'field_type' => 'email_address', 'name' => 'Email'],
            ['id' => '12', 'field_type' => 'phone',         'name' => 'Phone'],
            ['id' => '13', 'field_type' => 'single_line',   'name' => 'Event Name'],
            ['id' => '14', 'field_type' => 'paragraph',     'name' => 'Tell us about your event &amp; needs.<br>'],
            ['id' => '15', 'field_type' => 'checkbox',      'name' => 'Where should this appear?'],
            ['id' => '16', 'field_type' => 'date',          'name' => 'Event Date'],
            ['field_type' => 'single_line', 'name' => 'No id field'], // dropped (no id)
        ];
    }

    /** A representative /api/forms/list_form_entries payload. */
    private function entries(): array
    {
        return [
            [
                'id'         => '5001',
                'created_on' => '2026-06-08 14:30:00',
                'response'   => [
                    '10' => ['first' => 'Jane', 'last' => 'Doe'],
                    '11' => 'jane@example.com',
                    '12' => '206-555-0100',
                    '13' => 'All Church Picnic',
                    '14' => 'echoed instructional text',     // skipped: instructional type
                    '15' => ['Website', 'E-news'],           // list value → joined
                    '16' => '2026-07-04',
                    '99' => 'orphan answer',                 // no field def → key as label
                ],
            ],
            ['created_on' => '2026-06-08 15:00:00', 'response' => []], // dropped: no id
        ];
    }

    public function test_field_map_builds_id_to_label_and_type(): void
    {
        $map = Entries::field_map($this->fields());

        $this->assertSame('Event Name', $map['13']['label']);
        $this->assertSame('single_line', $map['13']['type']);
        $this->assertArrayNotHasKey('', $map, 'a field with no id is dropped');
    }

    public function test_field_map_cleans_html_and_entities_in_labels(): void
    {
        $map = Entries::field_map($this->fields());
        $this->assertSame('Tell us about your event & needs.', $map['14']['label']);
    }

    public function test_from_json_parses_a_response_body(): void
    {
        $raw = Entries::from_json((string) json_encode($this->entries()));
        $this->assertNotNull($raw);
        $this->assertCount(2, $raw);
    }

    public function test_from_json_returns_null_on_unparseable_body(): void
    {
        $this->assertNull(Entries::from_json('<html>403 Forbidden</html>'));
        $this->assertNull(Entries::from_json('"a bare string"'));
    }

    public function test_from_json_handles_empty_array(): void
    {
        $this->assertSame([], Entries::from_json('[]'));
    }

    public function test_flatten_value_handles_scalars_and_arrays(): void
    {
        $this->assertSame('hi', Entries::flatten_value('  hi  '));
        $this->assertSame('42', Entries::flatten_value(42));
        $this->assertSame('Website, E-news', Entries::flatten_value(['Website', '', 'E-news']));
        $this->assertSame('', Entries::flatten_value(null));
    }

    public function test_normalize_extracts_contact(): void
    {
        $records = Entries::normalize($this->entries(), Entries::field_map($this->fields()), 'Event Request Form');
        $rec     = $records[0];

        $this->assertSame('Jane Doe', $rec['contact']['name'], 'name object joined first+last with a space');
        $this->assertSame('jane@example.com', $rec['contact']['email']);
        $this->assertSame('206-555-0100', $rec['contact']['phone']);
    }

    public function test_normalize_builds_qa_rows_and_skips_contact_and_instructional(): void
    {
        $records = Entries::normalize($this->entries(), Entries::field_map($this->fields()), 'Event Request Form');
        $rows    = array_column($records[0]['responses'], 'value', 'label');

        $this->assertSame('All Church Picnic', $rows['Event Name']);
        $this->assertSame('Website, E-news', $rows['Where should this appear?']);
        $this->assertSame('2026-07-04', $rows['Event Date']);
        $this->assertArrayNotHasKey('Your Name', $rows, 'contact fields are lifted out, not repeated as Q&A');
        $this->assertArrayNotHasKey('Tell us about your event & needs.', $rows, 'instructional fields carry no answer');
        $this->assertSame('orphan answer', $rows['99'], 'an answer with no field def falls back to its field id as label');
    }

    public function test_normalize_drops_entries_without_id(): void
    {
        $records = Entries::normalize($this->entries(), Entries::field_map($this->fields()), 'Event Request Form');
        $this->assertCount(1, $records);
        $this->assertSame('5001', $records[0]['entry_id']);
    }

    public function test_title_prefers_event_name_answer(): void
    {
        $records = Entries::normalize($this->entries(), Entries::field_map($this->fields()), 'Event Request Form');
        $this->assertSame('All Church Picnic', $records[0]['title']);
    }

    public function test_title_falls_back_to_form_submitter_date(): void
    {
        // A submission with no title-ish field: fall back to "Form — Who — Date".
        $fields  = [['id' => '20', 'field_type' => 'name', 'name' => 'Name']];
        $entries = [['id' => '7', 'created_on' => '2026-06-08 09:00:00', 'response' => ['20' => ['first' => 'Sam', 'last' => 'Lee']]]];

        $records = Entries::normalize($entries, Entries::field_map($fields), 'Event Request Form');
        $this->assertSame('Event Request Form — Sam Lee — 2026-06-08', $records[0]['title']);
    }
}
