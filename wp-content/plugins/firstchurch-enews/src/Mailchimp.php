<?php

declare(strict_types=1);

namespace FirstChurch\ENews;

/**
 * The pure pieces of the Mailchimp Marketing API v3 integration: derive the
 * datacenter from the API key, build the base URL, shape the campaign payload
 * from an issue envelope, parse an error body, and build the admin edit URL.
 *
 * The HTTP (wp_remote_request, credentials, storing the campaign id, the admin
 * action) is WordPress glue in inc/mailchimp.php. Keeping the payload + parsing
 * here makes the fiddly, get-it-wrong-and-the-API-400s parts unit-testable
 * without a network. Pure (no WordPress).
 */
final class Mailchimp
{
    /**
     * Mailchimp keys end with their datacenter: "<hex>-us2". The API host is
     * derived from it. Empty when the key has no dash (malformed/unset).
     */
    public static function datacenter(string $apiKey): string
    {
        $pos = strrpos($apiKey, '-');
        return false === $pos ? '' : substr($apiKey, $pos + 1);
    }

    public static function apiBase(string $datacenter): string
    {
        return "https://{$datacenter}.api.mailchimp.com/3.0";
    }

    /**
     * The body for POST /campaigns (create) — a regular campaign to one audience
     * with the issue's envelope as its settings.
     *
     * @param array<string,mixed> $env subject, preview, title, from_name, reply_to.
     * @return array<string,mixed>
     */
    public static function campaignPayload(string $listId, array $env): array
    {
        return [
            'type'       => 'regular',
            'recipients' => ['list_id' => $listId],
            'settings'   => self::settings($env),
        ];
    }

    /**
     * The `settings` object, shared by create (POST) and update (PATCH).
     * preview_text is omitted when empty (Mailchimp keeps the prior value rather
     * than blanking it). title falls back to the subject — it's the internal
     * campaign name in Mailchimp's list, never shown to readers.
     *
     * @param array<string,mixed> $env
     * @return array<string,string>
     */
    public static function settings(array $env): array
    {
        $subject = (string) ($env['subject'] ?? '');
        $title   = (string) ($env['title'] ?? '');

        $settings = [
            'subject_line' => $subject,
            'title'        => '' !== $title ? $title : $subject,
            'from_name'    => (string) ($env['from_name'] ?? ''),
            'reply_to'     => (string) ($env['reply_to'] ?? ''),
        ];

        $preview = (string) ($env['preview'] ?? '');
        if ('' !== $preview) {
            $settings['preview_text'] = $preview;
        }

        return $settings;
    }

    /**
     * Turn a Mailchimp error response body into a human, surfaceable message:
     * the `detail` (or `title`), plus any per-field `errors[]`. Non-JSON bodies
     * (gateway HTML, empty) degrade to a generic line rather than throwing.
     */
    public static function errorMessage(string $body): string
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return 'Mailchimp returned an unexpected response.';
        }

        $message = (string) ($data['detail'] ?? $data['title'] ?? 'Unknown Mailchimp error.');

        if (!empty($data['errors']) && is_array($data['errors'])) {
            $parts = [];
            foreach ($data['errors'] as $err) {
                if (!is_array($err)) {
                    continue;
                }
                $field = trim((string) ($err['field'] ?? ''));
                $msg   = trim((string) ($err['message'] ?? ''));
                $parts[] = trim($field . ' ' . $msg);
            }
            $parts = array_filter($parts);
            if ($parts) {
                $message .= ' (' . implode('; ', $parts) . ')';
            }
        }

        return $message;
    }

    /** The Mailchimp admin "edit campaign" URL for a campaign's numeric web_id. */
    public static function editUrl(string $datacenter, int $webId): string
    {
        return "https://{$datacenter}.admin.mailchimp.com/campaigns/edit?id={$webId}";
    }
}
