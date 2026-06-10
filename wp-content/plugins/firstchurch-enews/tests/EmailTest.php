<?php

declare(strict_types=1);

namespace FirstChurch\ENews\Tests;

use FirstChurch\ENews\Email;
use PHPUnit\Framework\TestCase;

/**
 * Email renders a Happening's CardView view-model (and the issue envelope) into
 * email-safe HTML: table-based, inline-styled, no class/stylesheet dependency,
 * everything escaped. Pure (no WordPress) — it's the email projection of the
 * same view-model the web .fcs-card renders.
 */
final class EmailTest extends TestCase
{
    /** @return array<string,mixed> */
    private static function view(array $overrides = []): array
    {
        return array_merge([
            'title'      => 'Open Mic Night',
            'url'        => 'https://x/events/open-mic/',
            'meta'       => 'June 11 at 6:00 pm',
            'blurb'      => 'Bring a song or a poem.',
            'image'      => '',
            'ctaUrl'     => 'https://breeze/form/123',
            'ctaLabel'   => 'Register',
            'ctaPrimary' => true,
        ], $overrides);
    }

    public function test_card_is_table_based_and_inline_styled(): void
    {
        $html = Email::card(self::view());
        // Email layout must be tables with inline styles — never CSS classes.
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('style="', $html);
        $this->assertStringNotContainsString('class="fcs-card"', $html);
    }

    public function test_card_links_the_title_and_renders_meta_blurb_and_cta(): void
    {
        $html = Email::card(self::view());
        $this->assertStringContainsString('<a href="https://x/events/open-mic/"', $html);
        $this->assertStringContainsString('Open Mic Night', $html);
        $this->assertStringContainsString('June 11 at 6:00 pm', $html);
        $this->assertStringContainsString('Bring a song or a poem.', $html);
        // The CTA is a link to the action URL, with its label.
        $this->assertStringContainsString('href="https://breeze/form/123"', $html);
        $this->assertStringContainsString('Register', $html);
    }

    public function test_card_without_url_renders_plain_title(): void
    {
        $html = Email::card(self::view(['url' => '', 'ctaUrl' => '', 'ctaLabel' => '']));
        $this->assertStringContainsString('Open Mic Night', $html);
        $this->assertStringNotContainsString('<a href', $html);
    }

    public function test_card_suppresses_meta_when_show_meta_is_false(): void
    {
        // A featured announcement suppresses its (misleading) publish date — the
        // same rule the web card applies (happenings.md §4).
        $html = Email::card(self::view(['meta' => 'June 5, 2026']), false);
        $this->assertStringNotContainsString('June 5, 2026', $html);
    }

    public function test_card_omits_cta_block_when_no_cta_url(): void
    {
        $html = Email::card(self::view(['ctaUrl' => '', 'ctaLabel' => '']));
        $this->assertStringNotContainsString('Register', $html);
    }

    public function test_card_uses_the_reconciled_brand_maroon(): void
    {
        // The email theme's palette is the First Church brand (../mailchimp
        // config.yml), exposed as one source of truth on Email — not the old
        // off-brand #7a1f2b the plugin shipped with.
        $this->assertSame('#800000', Email::MAROON);
        $html = Email::card(self::view());
        $this->assertStringContainsString('#800000', $html);
        $this->assertStringNotContainsString('#7a1f2b', $html);
    }

    public function test_card_escapes_html_in_text_fields(): void
    {
        $html = Email::card(self::view([
            'title' => 'Tom & Jerry <script>',
            'blurb' => '1 < 2 & "ok"',
        ]));
        $this->assertStringContainsString('Tom &amp; Jerry &lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('1 &lt; 2 &amp;', $html);
    }

    public function test_card_renders_the_optional_image_when_present(): void
    {
        // The CardView has always carried `image`; the announcement design finally
        // uses it (matching the template's hideable image region).
        $html = Email::card(self::view(['image' => 'https://x/uploads/open-mic.jpg']));
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('https://x/uploads/open-mic.jpg', $html);
    }

    public function test_card_omits_the_image_when_absent(): void
    {
        $html = Email::card(self::view(['image' => '']));
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_card_body_uses_the_sans_stack(): void
    {
        // Announcement bodies are sans (Helvetica/Arial), per the template; the
        // serif is reserved for the pastoral letter in the body slot.
        $html = Email::card(self::view());
        $this->assertStringContainsString('Helvetica', $html);
    }

    public function test_document_wraps_inner_in_an_email_scaffold(): void
    {
        $inner = '<h2>This Week</h2><p>hello</p>';
        $html  = Email::document($inner, [
            'subject' => 'First Church Weekly News',
            'preview' => 'Open Mic Night & more',
            'date'    => '2026-06-09',
        ]);

        $this->assertStringContainsString('<!DOCTYPE', $html);
        // The subject titles the document; the preview text rides as a preheader.
        $this->assertStringContainsString('First Church Weekly News', $html);
        $this->assertStringContainsString('Open Mic Night &amp; more', $html);
        // Constrained, centered email column.
        $this->assertStringContainsString('max-width:600px', $html);
        // Inner body HTML is embedded verbatim (already rendered HTML, not escaped).
        $this->assertStringContainsString($inner, $html);
    }

    public function test_document_emits_bulletproof_head_with_mso_and_dark_mode(): void
    {
        $html = Email::document('<p>x</p>', []);
        // Outlook (Word engine) DPI fix + dark-mode + responsive stylesheet — the
        // bulletproof scaffolding ported from ../mailchimp first-church-template.html.
        $this->assertStringContainsString('PixelsPerInch', $html);
        $this->assertStringContainsString('prefers-color-scheme: dark', $html);
        $this->assertStringContainsString('max-width: 620px', $html);
    }

    public function test_document_renders_the_masthead_chrome(): void
    {
        $html = Email::document('<p>body</p>', ['subject' => 'Weekly News']);
        // Maroon topbar with a "view in browser" archive link.
        $this->assertStringContainsString('#800000', $html);
        $this->assertStringContainsString('*|ARCHIVE|*', $html);
        $this->assertStringContainsString('View in your browser', $html);
        // Logo header.
        $this->assertStringContainsString('alt="First Church Seattle"', $html);
        // Worship CTA buttons (livestream + in-person).
        $this->assertStringContainsString('firstchurchseattle.org/livestream', $html);
        $this->assertStringContainsString('firstchurchseattle.org/visit', $html);
        // Tan brand divider.
        $this->assertStringContainsString('#e9dbb7', $html);
    }

    public function test_document_renders_a_social_row(): void
    {
        $html = Email::document('<p>body</p>', []);
        $this->assertStringContainsString('facebook.com/firstchurchseattle', $html);
        $this->assertStringContainsString('instagram.com/firstchurchseattle', $html);
    }

    public function test_document_topbar_defaults_and_is_overridable_and_escaped(): void
    {
        $default = Email::document('<p>x</p>', []);
        $this->assertStringContainsString('Worship with us', $default);

        $custom = Email::document('<p>x</p>', ['topbar' => 'Pride Sunday <3 & all']);
        $this->assertStringContainsString('Pride Sunday &lt;3 &amp; all', $custom);
        $this->assertStringNotContainsString('Pride Sunday <3', $custom);
    }

    public function test_document_body_slot_carries_the_serif_letter_font(): void
    {
        // The issue body (the pastoral letter prose) reads in the serif stack;
        // sans is reserved for chrome + announcement cards.
        $html = Email::document('<p>Dear First Church,</p>', []);
        $this->assertStringContainsString('Georgia', $html);
    }

    public function test_document_tolerates_missing_envelope_fields(): void
    {
        $html = Email::document('<p>x</p>', []);
        $this->assertStringContainsString('<!DOCTYPE', $html);
        $this->assertStringContainsString('<p>x</p>', $html);
    }

    public function test_document_appends_an_optional_footer_after_the_body(): void
    {
        // The footer (built by the WP glue) carries the social links, past-issues
        // link, copyright, and the Mailchimp unsubscribe/address merge tags so the
        // pushed draft is send-ready. It's trusted HTML, embedded verbatim and
        // positioned after the body.
        $footer = '<a href="https://x/archive">Past issues</a> *|UNSUB|*';
        $html   = Email::document('<p>body</p>', ['footer' => $footer]);

        $this->assertStringContainsString($footer, $html);
        $this->assertGreaterThan(
            strpos($html, '<p>body</p>'),
            strpos($html, 'Past issues'),
            'the footer renders after the body'
        );
    }

    public function test_document_without_footer_emits_no_merge_tags(): void
    {
        $html = Email::document('<p>x</p>', []);
        $this->assertStringNotContainsString('*|UNSUB|*', $html);
    }
}
