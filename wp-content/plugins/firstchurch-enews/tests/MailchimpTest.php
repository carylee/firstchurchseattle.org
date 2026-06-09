<?php

declare(strict_types=1);

namespace FirstChurch\ENews\Tests;

use FirstChurch\ENews\Mailchimp;
use PHPUnit\Framework\TestCase;

/**
 * Mailchimp holds the pure pieces of the Marketing-API v3 integration: derive
 * the datacenter from the API key, build the base URL, shape the campaign
 * create/patch payload from an issue envelope, and turn an API error body into a
 * human message. The HTTP itself is WP glue (inc/mailchimp.php); this is what's
 * worth testing. Pure (no WordPress).
 */
final class MailchimpTest extends TestCase
{
    public function test_datacenter_is_the_suffix_after_the_last_dash(): void
    {
        // Deliberately NOT a 32-hex string: keep the fixture clearly fake so secret
        // scanners don't mistake it for a real Mailchimp key.
        $this->assertSame('us2', Mailchimp::datacenter('example-placeholder-key-us2'));
        $this->assertSame('us21', Mailchimp::datacenter('another-placeholder-us21'));
    }

    public function test_datacenter_is_empty_for_a_key_without_a_dash(): void
    {
        $this->assertSame('', Mailchimp::datacenter('nodash'));
        $this->assertSame('', Mailchimp::datacenter(''));
    }

    public function test_api_base_targets_the_datacenter_host(): void
    {
        $this->assertSame('https://us2.api.mailchimp.com/3.0', Mailchimp::apiBase('us2'));
    }

    public function test_campaign_payload_carries_recipients_and_settings(): void
    {
        $payload = Mailchimp::campaignPayload('listABC', [
            'subject'   => 'First Church Weekly News',
            'preview'   => 'Open Mic Night & more',
            'title'     => 'E-News 2026-06-09',
            'from_name' => 'First Church Seattle',
            'reply_to'  => 'comms@firstchurchseattle.org',
        ]);

        $this->assertSame('regular', $payload['type']);
        $this->assertSame('listABC', $payload['recipients']['list_id']);
        $this->assertSame('First Church Weekly News', $payload['settings']['subject_line']);
        $this->assertSame('Open Mic Night & more', $payload['settings']['preview_text']);
        $this->assertSame('E-News 2026-06-09', $payload['settings']['title']);
        $this->assertSame('First Church Seattle', $payload['settings']['from_name']);
        $this->assertSame('comms@firstchurchseattle.org', $payload['settings']['reply_to']);
    }

    public function test_settings_omit_preview_text_when_empty(): void
    {
        $s = Mailchimp::settings(['subject' => 'Hi', 'from_name' => 'FC', 'reply_to' => 'a@b.c']);
        $this->assertArrayNotHasKey('preview_text', $s);
    }

    public function test_settings_fall_back_title_to_subject(): void
    {
        $s = Mailchimp::settings(['subject' => 'Weekly News', 'from_name' => 'FC', 'reply_to' => 'a@b.c']);
        $this->assertSame('Weekly News', $s['title']);
    }

    public function test_error_message_prefers_detail_and_appends_field_errors(): void
    {
        $body = json_encode([
            'title'  => 'Invalid Resource',
            'detail' => 'Your merge fields were invalid.',
            'status' => 400,
            'errors' => [
                ['field' => 'recipients.list_id', 'message' => 'is required'],
            ],
        ]);
        $msg = Mailchimp::errorMessage($body);
        $this->assertStringContainsString('Your merge fields were invalid.', $msg);
        $this->assertStringContainsString('recipients.list_id', $msg);
        $this->assertStringContainsString('is required', $msg);
    }

    public function test_error_message_handles_non_json(): void
    {
        $this->assertNotSame('', Mailchimp::errorMessage('<html>504 Gateway Timeout</html>'));
    }

    public function test_edit_url_points_at_the_mailchimp_admin(): void
    {
        $this->assertSame(
            'https://us2.admin.mailchimp.com/campaigns/edit?id=4242',
            Mailchimp::editUrl('us2', 4242)
        );
    }
}
