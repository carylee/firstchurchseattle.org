<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms\Tests;

use FirstChurch\BreezeForms\Native;
use PHPUnit\Framework\TestCase;

/**
 * Mode 3 — the pure native render/validate/inputs core.
 *
 * Exercised against the real baked contract (data/native-forms.json) so the
 * tests double as a guard that the shipped prayer-form map stays well-formed,
 * plus synthetic contracts for the field types the prayer form doesn't use.
 */
final class NativeTest extends TestCase
{
    /** @return array<string,mixed> */
    private function prayer(): array
    {
        $map = json_decode((string) file_get_contents(__DIR__ . '/../data/native-forms.json'), true);
        $contract = Native::resolve('213392', '', $map);
        $this->assertNotNull($contract, 'prayer contract should resolve by id');
        return $contract;
    }

    public function test_resolve_by_id_folds_in_the_id(): void
    {
        $map = ['213392' => ['slug' => '38f910', 'fields' => []]];
        $c = Native::resolve('213392', '', $map);
        $this->assertSame('213392', $c['id']);
        $this->assertSame('38f910', $c['slug']);
    }

    public function test_resolve_by_slug_when_no_id(): void
    {
        $map = ['213392' => ['slug' => '38f910', 'fields' => []]];
        $c = Native::resolve('', '38f910', $map);
        $this->assertSame('213392', $c['id']);
    }

    public function test_resolve_returns_null_for_unknown_form(): void
    {
        $this->assertNull(Native::resolve('999', 'nope', ['213392' => ['slug' => '38f910']]));
        $this->assertNull(Native::resolve('', '', ['213392' => ['slug' => '38f910']]));
    }

    public function test_baked_prayer_contract_field_ids(): void
    {
        $ids = array_column(Native::fields($this->prayer()), 'field_id', 'key');
        $this->assertSame([
            'name'           => '2147340348',
            'email'          => '2147340349',
            'phone'          => '2147340350',
            'prayer_request' => '2147340351',
            'confidential'   => '2147340352',
        ], $ids);
    }

    public function test_client_fields_is_key_type_pairs(): void
    {
        $this->assertSame(
            [
                ['key' => 'name', 'type' => 'name'],
                ['key' => 'email', 'type' => 'email'],
                ['key' => 'phone', 'type' => 'phone'],
                ['key' => 'prayer_request', 'type' => 'textarea'],
                ['key' => 'confidential', 'type' => 'checkbox'],
            ],
            Native::client_fields($this->prayer())
        );
    }

    public function test_honeypot(): void
    {
        $this->assertTrue(Native::is_honeypot(['website' => 'http://spam']));
        $this->assertTrue(Native::is_honeypot(['url' => 'x']));
        $this->assertFalse(Native::is_honeypot(['website' => '', 'name' => ['first' => 'A']]));
    }

    // --- validation -------------------------------------------------------

    private function validPrayerParams(): array
    {
        return [
            'name'           => ['first' => 'Pat', 'last' => 'Lee'],
            'email'          => 'pat@example.org',
            'phone'          => '',
            'prayer_request' => 'Please pray for my family.',
            'confidential'   => ['196'],
        ];
    }

    public function test_valid_submission_has_no_errors(): void
    {
        $this->assertSame([], Native::validate($this->prayer(), $this->validPrayerParams()));
    }

    public function test_missing_required_fields_are_reported(): void
    {
        $errors = Native::validate($this->prayer(), [
            'name'  => ['first' => '', 'last' => ''],
            'email' => 'not-an-email',
        ]);
        $this->assertContains('Please provide your first and last name.', $errors);
        $this->assertContains('Please provide a valid email address.', $errors);
        // required textarea + required choice
        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'Prayer Request')));
        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'confidential')));
    }

    public function test_optional_phone_is_not_required(): void
    {
        $errors = Native::validate($this->prayer(), $this->validPrayerParams());
        $this->assertSame([], $errors);
    }

    public function test_forged_choice_value_is_rejected_as_unanswered(): void
    {
        $params = $this->validPrayerParams();
        $params['confidential'] = ['999']; // not an offered option
        $errors = Native::validate($this->prayer(), $params);
        $this->assertNotEmpty($errors);
    }

    // --- inputs projection ------------------------------------------------

    public function test_inputs_for_full_prayer_submission(): void
    {
        $inputs = Native::inputs($this->prayer(), [
            'name'           => ['first' => 'Pat', 'last' => 'Lee'],
            'email'          => 'pat@example.org',
            'phone'          => '206-555-0100',
            'prayer_request' => "Line one\nLine two",
            'confidential'   => ['196'],
        ]);

        $this->assertSame([
            [
                'field_id'   => '2147340348',
                'response'   => 'undefined',
                'field_type' => 'name',
                'details'    => [
                    'first_name' => 'Pat',
                    'last_name'  => 'Lee',
                    'value'      => 'Lee',
                    'part'       => 'last_name',
                    'person_id'  => 0,
                ],
            ],
            ['field_id' => '2147340349', 'response' => 'pat@example.org', 'field_type' => 'text'],
            ['field_id' => '2147340350', 'response' => '206-555-0100', 'field_type' => 'text'],
            ['field_id' => '2147340351', 'response' => "Line one\nLine two", 'field_type' => 'textarea'],
            ['field_id' => '2147340352', 'response' => '196', 'field_type' => 'checkbox'],
        ], $inputs);
    }

    public function test_inputs_omit_empty_optional_fields(): void
    {
        $inputs = Native::inputs($this->prayer(), $this->validPrayerParams());
        $field_ids = array_column($inputs, 'field_id');
        $this->assertNotContains('2147340350', $field_ids, 'empty phone should be omitted');
    }

    public function test_inputs_drop_forged_checkbox_values(): void
    {
        $params = $this->validPrayerParams();
        $params['confidential'] = ['196', '999'];
        $inputs = Native::inputs($this->prayer(), $params);
        $checks = array_values(array_filter($inputs, fn ($i) => $i['field_id'] === '2147340352'));
        $this->assertCount(1, $checks);
        $this->assertSame('196', $checks[0]['response']);
    }

    public function test_radio_emits_single_input(): void
    {
        $contract = [
            'id' => '1', 'slug' => 'x', 'name' => 'Demo',
            'fields' => [[
                'key' => 'pick', 'field_id' => '42', 'type' => 'radio', 'label' => 'Pick', 'required' => true,
                'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']],
            ]],
        ];
        $inputs = Native::inputs($contract, ['pick' => 'b']);
        $this->assertSame([['field_id' => '42', 'response' => 'b', 'field_type' => 'radio']], $inputs);
        $this->assertSame([], Native::validate($contract, ['pick' => 'b']));
        $this->assertNotEmpty(Native::validate($contract, ['pick' => '']));
    }

    public function test_address_inputs(): void
    {
        $contract = [
            'id' => '1', 'slug' => 'x', 'name' => 'Demo',
            'fields' => [[
                'key' => 'addr', 'field_id' => '7', 'type' => 'address', 'label' => 'Address', 'required' => false,
            ]],
        ];
        $inputs = Native::inputs($contract, ['addr' => ['street' => '1 Main', 'city' => 'Seattle', 'state' => 'WA', 'zip' => '98101']]);
        $this->assertSame('address', $inputs[0]['field_type']);
        $this->assertSame('1 Main', $inputs[0]['details']['street_address']);
        $this->assertSame('Seattle', $inputs[0]['details']['city']);
    }

    // --- rendering --------------------------------------------------------

    public function test_render_emits_form_with_endpoint_nonce_and_fields(): void
    {
        $html = Native::render($this->prayer(), [
            'endpoint' => 'https://example.org/wp-json/firstchurch/v1/breeze-native',
            'nonce'    => 'abc123',
        ]);

        $this->assertStringContainsString('class="fcbf-native"', $html);
        $this->assertStringContainsString('data-endpoint="https://example.org/wp-json/firstchurch/v1/breeze-native"', $html);
        $this->assertStringContainsString('data-nonce="abc123"', $html);
        $this->assertStringContainsString('name="form_id" value="213392"', $html);
        // every contract field surfaces by name
        $this->assertStringContainsString('name="name_first"', $html);
        $this->assertStringContainsString('name="name_last"', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('name="prayer_request"', $html);
        $this->assertStringContainsString('name="confidential[]"', $html);
        $this->assertStringContainsString('value="196"', $html);
        // honeypot + status + themed submit
        $this->assertStringContainsString('name="website"', $html);
        $this->assertStringContainsString('fcbf-native__status', $html);
        $this->assertStringContainsString('maranatha-button', $html);
        $this->assertStringContainsString('Send prayer request', $html);
    }

    public function test_render_marks_required_fields(): void
    {
        $html = Native::render($this->prayer(), ['endpoint' => 'https://e.org/x', 'nonce' => 'n']);
        // required email input carries the required attribute; optional phone does not
        $this->assertMatchesRegularExpression('/name="email"[^>]*\srequired/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="phone"[^>]*\srequired/', $html);
    }

    public function test_render_includes_turnstile_when_sitekey_present(): void
    {
        $html = Native::render($this->prayer(), [
            'endpoint' => 'https://e.org/x', 'nonce' => 'n', 'turnstile_sitekey' => 'sitekey-xyz',
        ]);
        $this->assertStringContainsString('cf-turnstile', $html);
        $this->assertStringContainsString('data-sitekey="sitekey-xyz"', $html);
    }

    public function test_render_heading_override(): void
    {
        $html = Native::render($this->prayer(), ['endpoint' => 'https://e.org/x', 'nonce' => 'n', 'heading' => 'Submit a Prayer']);
        $this->assertStringContainsString('Submit a Prayer', $html);
        $this->assertStringNotContainsString('<h2 class="fcbf-native__heading">Prayer Requests', $html);
    }

    public function test_render_escapes_hostile_label(): void
    {
        $contract = [
            'id' => '1', 'slug' => 'x', 'name' => 'Demo',
            'fields' => [['key' => 'q', 'field_id' => '9', 'type' => 'text', 'label' => '"><script>alert(1)</script>']],
        ];
        $html = Native::render($contract, ['endpoint' => 'https://e.org/x', 'nonce' => 'n']);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
    }
}
