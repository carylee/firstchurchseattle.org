<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms\Tests;

use FirstChurch\BreezeForms\Entries;
use PHPUnit\Framework\TestCase;

final class EntriesTest extends TestCase
{
    /**
     * A representative /api/forms/list_form_fields payload, shaped like the live
     * Breeze API: each field has a definition row `id` AND a distinct `field_id`
     * (entry responses are keyed by `field_id`), email/phone are `single_line`,
     * and choice fields carry an `options` list of `{option_id, name}`.
     */
    private function fields(): array
    {
        return [
            // Instructional paragraph whose prompt contains "email" — must be
            // dropped as chrome, and must NOT be mistaken for the email field.
            ['id' => '75906238', 'field_id' => '2147341378', 'field_type' => 'paragraph',
                'name' => 'Submit at least three weeks prior for bulletin, email &amp; website publicity.<br>'],
            ['id' => '75906239', 'field_id' => '2147341367', 'field_type' => 'name',        'name' => 'Your Name'],
            // Email & phone are single_line on the live form — caught by label, not type.
            ['id' => '75906240', 'field_id' => '2147341368', 'field_type' => 'single_line', 'name' => 'Email'],
            ['id' => '75906241', 'field_id' => '2147341379', 'field_type' => 'single_line', 'name' => 'Phone'],
            ['id' => '75906244', 'field_id' => '2147341370', 'field_type' => 'single_line', 'name' => 'Event Title'],
            ['id' => '75906250', 'field_id' => '2147342508', 'field_type' => 'multiple_choice', 'name' => 'External guest speaker?',
                'options' => [
                    ['id' => '98023436', 'option_id' => '971', 'name' => 'Yes'],
                    ['id' => '98023437', 'option_id' => '972', 'name' => 'No'],
                ]],
            ['id' => '75906251', 'field_id' => '2147342190', 'field_type' => 'checkbox', 'name' => 'Where should this appear?',
                'options' => [
                    ['id' => '1', 'option_id' => '855', 'name' => 'Website'],
                    ['id' => '2', 'option_id' => '856', 'name' => 'E-news'],
                ]],
            ['id' => '75906245', 'field_id' => '2147342328', 'field_type' => 'date',  'name' => 'Event Date'],
            ['id' => '75906260', 'field_id' => '2147341373', 'field_type' => 'notes', 'name' => 'Event Description'],
            ['field_type' => 'single_line', 'name' => 'No id field'], // dropped: no field_id or id
        ];
    }

    /** A representative /api/forms/list_form_entries payload (responses keyed by field_id). */
    private function entries(): array
    {
        return [
            [
                'id'         => '5001',
                'oid'        => '57833',
                'form_id'    => '762687',
                'created_on' => '2026-06-08 14:30:00',
                'person_id'  => null,
                'response'   => [
                    // Name object as the live API returns it: name parts + bookkeeping keys.
                    '2147341367' => ['id' => '21193406', 'oid' => '57833', 'first_name' => 'Jane', 'last_name' => 'Doe', 'created_on' => '2026-06-08 14:30:00'],
                    '2147341368' => 'jane@example.com',
                    '2147341379' => '206-555-0100',
                    '2147341370' => 'All Church Picnic',
                    '2147341378' => 'echoed instructional text',                              // skipped: instructional type
                    '2147342508' => ['value' => '971', 'name' => null],                       // single choice → "Yes"
                    '2147342190' => [['name' => null, 'value' => '855'], ['name' => null, 'value' => '856']], // checkbox → "Website, E-news"
                    '2147342328' => '2026-07-04',
                    '2147341373' => 'Bring a dish to share!',
                    '2147349999' => 'orphan answer',                                          // no field def → key by field id
                ],
            ],
            ['oid' => '57833', 'created_on' => '2026-06-08 15:00:00', 'response' => []], // dropped: no id
        ];
    }

    public function test_field_map_keys_on_field_id_not_row_id(): void
    {
        $map = Entries::field_map($this->fields());

        $this->assertSame('Event Title', $map['2147341370']['label']);
        $this->assertSame('single_line', $map['2147341370']['type']);
        $this->assertArrayNotHasKey('75906244', $map, 'the field-definition row id is not the join key');
        $this->assertArrayNotHasKey('', $map, 'a field with no field_id/id is dropped');
    }

    public function test_field_map_falls_back_to_id_when_no_field_id(): void
    {
        $map = Entries::field_map([['id' => '42', 'field_type' => 'single_line', 'name' => 'Legacy']]);
        $this->assertSame('Legacy', $map['42']['label']);
    }

    public function test_field_map_cleans_html_and_entities_in_labels(): void
    {
        $map = Entries::field_map($this->fields());
        $this->assertSame('Submit at least three weeks prior for bulletin, email & website publicity.', $map['2147341378']['label']);
    }

    public function test_field_map_resolves_options_to_id_label_pairs(): void
    {
        $map = Entries::field_map($this->fields());
        $this->assertSame(['971' => 'Yes', '972' => 'No'], $map['2147342508']['options']);
        $this->assertSame(['855' => 'Website', '856' => 'E-news'], $map['2147342190']['options']);
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

    public function test_flatten_value_resolves_choice_options(): void
    {
        $opts = ['971' => 'Yes', '972' => 'No'];
        // Single choice → option label, not the bare option id.
        $this->assertSame('Yes', Entries::flatten_value(['value' => '971', 'name' => null], $opts));
        // Checkbox (list of choice objects) → joined labels.
        $this->assertSame(
            'Website, E-news',
            Entries::flatten_value([['name' => null, 'value' => '855'], ['name' => null, 'value' => '856']], ['855' => 'Website', '856' => 'E-news'])
        );
        // An embedded name wins over the option lookup.
        $this->assertSame('Custom', Entries::flatten_value(['value' => '855', 'name' => 'Custom'], ['855' => 'Website']));
        // An unknown option id degrades to the raw id rather than vanishing.
        $this->assertSame('999', Entries::flatten_value(['value' => '999', 'name' => null], $opts));
        // An empty selection flattens to ''.
        $this->assertSame('', Entries::flatten_value(['value' => '', 'name' => null], $opts));
    }

    public function test_normalize_extracts_contact_and_drops_name_bookkeeping(): void
    {
        $records = Entries::normalize($this->entries(), Entries::field_map($this->fields()), 'Event Request Form');
        $rec     = $records[0];

        $this->assertSame('Jane Doe', $rec['contact']['name'], 'name joins first+last, ignoring id/oid/created_on');
        $this->assertSame('jane@example.com', $rec['contact']['email'], 'single_line "Email" field caught by label');
        $this->assertSame('206-555-0100', $rec['contact']['phone'], 'single_line "Phone" field caught by label');
    }

    public function test_normalize_builds_qa_rows_with_readable_labels_and_options(): void
    {
        $records = Entries::normalize($this->entries(), Entries::field_map($this->fields()), 'Event Request Form');
        $rows    = array_column($records[0]['responses'], 'value', 'label');

        $this->assertSame('All Church Picnic', $rows['Event Title']);
        $this->assertSame('Yes', $rows['External guest speaker?'], 'choice answer resolved to its option label');
        $this->assertSame('Website, E-news', $rows['Where should this appear?']);
        $this->assertSame('2026-07-04', $rows['Event Date']);
        $this->assertSame('Bring a dish to share!', $rows['Event Description']);
        $this->assertArrayNotHasKey('Your Name', $rows, 'contact fields are lifted out, not repeated as Q&A');
        $this->assertArrayNotHasKey('Email', $rows, 'the email field is lifted into contact, not duplicated');
        $this->assertArrayNotHasKey('Submit at least three weeks prior for bulletin, email & website publicity.', $rows, 'instructional chrome carries no answer');
        $this->assertSame('orphan answer', $rows['2147349999'], 'an answer with no field def falls back to its field id as label');
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
        $fields  = [['id' => '99', 'field_id' => '2147300020', 'field_type' => 'name', 'name' => 'Name']];
        $entries = [[
            'id'         => '7',
            'created_on' => '2026-06-08 09:00:00',
            'response'   => ['2147300020' => ['id' => '1', 'oid' => '2', 'first_name' => 'Sam', 'last_name' => 'Lee', 'created_on' => '2026-06-08 09:00:00']],
        ]];

        $records = Entries::normalize($entries, Entries::field_map($fields), 'Event Request Form');
        $this->assertSame('Event Request Form — Sam Lee — 2026-06-08', $records[0]['title']);
    }
}
