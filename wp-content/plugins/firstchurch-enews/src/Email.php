<?php

declare(strict_types=1);

namespace FirstChurch\ENews;

/**
 * The email projection of a Happening. Where the theme renders a `.fcs-card`
 * <article> styled by a stylesheet, email clients strip <style>/classes and
 * mangle flexbox layout — so this renders the same view-model as a table with
 * inline styles only. It consumes the spine's CardView output (title, url, meta,
 * blurb, ctaUrl, ctaLabel) so the email and the web agree on every card's
 * content; only the markup differs. Pure (no WordPress): escapes with
 * htmlspecialchars, so the suite runs standalone.
 */
final class Email
{
    /*
     * Brand tokens — the single source of truth for the e-news theme, mirroring
     * ../mailchimp `config.yml` (and reconciled with the web palette). Public so
     * the WordPress glue (inc/render.php) references these instead of re-hard-
     * coding hex. Inlined everywhere — email clients strip <style>/classes, so
     * there's no CSS cascade to lean on.
     */
    public const MAROON = '#800000'; // primary  — masthead, links, headings
    public const TAN    = '#e9dbb7'; // accent   — brand divider rules
    public const INK    = '#202020'; // body text
    public const MUTED  = '#656565'; // muted    — meta + footer text

    /**
     * Two stacks, matching first-church-template.html: a Helvetica/Arial sans for
     * UI + announcement bodies, and a Georgia serif reserved for the pastor's
     * letter (the issue's prose body slot).
     */
    public const SANS  = "font-family: Helvetica, Arial, sans-serif;";
    public const SERIF = "font-family: Georgia, 'Times New Roman', serif;";

    /*
     * Brand chrome (the masthead + footer furniture) — identical every week, so
     * it lives here as constants rather than as editor fields. Mirrors
     * ../mailchimp first-church-template.html / config.yml.
     */
    private const WEBSITE     = 'https://www.firstchurchseattle.org';
    private const LOGO_URL    = 'https://mcusercontent.com/18291af87fbc7224df67d6ab8/images/75afe3e5-44db-526b-00c8-6a660370291a.png';
    private const LIVESTREAM  = 'https://www.firstchurchseattle.org/livestream';
    private const INPERSON    = 'https://www.firstchurchseattle.org/visit';
    private const FACEBOOK    = 'https://www.facebook.com/firstchurchseattle';
    private const INSTAGRAM   = 'https://www.instagram.com/firstchurchseattle';
    /** "View in your browser" — resolves to the public archive when Mailchimp sends. */
    private const ARCHIVE     = '*|ARCHIVE|*';
    /** Default top-bar line; overridable per issue via $env['topbar']. */
    private const TOPBAR      = "Worship with us every Sunday at 10:30\u{00A0}AM!";

    /**
     * One Happening as an email-safe announcement block, matching ../mailchimp's
     * repeatable announcement: a maroon sans title under a short tan rule, the
     * optional image, a sans body, and a "label »" text-link CTA. Consumes the
     * same CardView the web `.fcs-card` does (title, url, meta, blurb, image,
     * ctaUrl, ctaLabel) so email and web never disagree; only the markup differs.
     * Borderless — it sits inside the white body slot, separated by spacing.
     *
     * @param array<string,mixed> $view     A CardView view-model (happenings_card_view()).
     * @param bool                $showMeta  Show the date/when line. Featured announcements
     *                                       suppress it (the same rule as the web card).
     */
    public static function card(array $view, bool $showMeta = true): string
    {
        $title  = (string) ($view['title'] ?? '');
        $url    = (string) ($view['url'] ?? '');
        $meta   = (string) ($view['meta'] ?? '');
        $blurb  = (string) ($view['blurb'] ?? '');
        $image  = (string) ($view['image'] ?? '');
        $ctaUrl = (string) ($view['ctaUrl'] ?? '');
        $ctaLbl = (string) ($view['ctaLabel'] ?? '');

        $titleHtml = self::esc($title);
        if ($url !== '') {
            $titleHtml = '<a href="' . self::escAttr($url) . '" style="color:' . self::MAROON . ';text-decoration:none;">' . $titleHtml . '</a>';
        }

        // Maroon sans heading.
        $rows = '<tr><td class="h2 text-dark" style="' . self::SANS . 'font-size:20px;line-height:26px;font-weight:bold;color:' . self::MAROON . ';padding:0 0 6px;">' . $titleHtml . '</td></tr>';

        // Short tan brand rule under the heading (44px).
        $rows .= '<tr><td style="padding:0 0 12px;"><table role="presentation" width="44" cellspacing="0" cellpadding="0" border="0"><tr>'
            . '<td height="3" style="height:3px;line-height:3px;font-size:3px;background-color:' . self::TAN . ';">&nbsp;</td></tr></table></td></tr>';

        if ($showMeta && $meta !== '') {
            $rows .= '<tr><td class="text-muted" style="' . self::SANS . 'font-size:13px;line-height:18px;color:' . self::MUTED . ';padding:0 0 10px;">' . self::esc($meta) . '</td></tr>';
        }
        if ($image !== '') {
            $rows .= '<tr><td style="padding:0 0 14px;"><img src="' . self::escAttr($image) . '" alt="" class="fluid" style="display:block;width:100%;max-width:520px;height:auto;border-radius:4px;"></td></tr>';
        }
        if ($blurb !== '') {
            $rows .= '<tr><td class="body-text text-dark" style="' . self::SANS . 'font-size:16px;line-height:25px;color:' . self::INK . ';padding:0 0 10px;">' . self::esc($blurb) . '</td></tr>';
        }
        if ($ctaUrl !== '') {
            $label = $ctaLbl !== '' ? $ctaLbl : 'Learn more';
            $rows .= '<tr><td class="body-text" style="' . self::SANS . 'font-size:16px;line-height:25px;padding:0;">'
                . '<a href="' . self::escAttr($ctaUrl) . '" target="_blank" style="color:' . self::MAROON . ';font-weight:bold;text-decoration:underline;">' . self::esc($label) . ' &raquo;</a></td></tr>';
        }

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px;"><tr><td style="padding:0;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $rows . '</table>'
            . '</td></tr></table>';
    }

    /**
     * The "From the Pastor" letter as the serif prose slot (../mailchimp's
     * pastor's letter): a maroon serif title linking to the post, the full letter
     * body (trusted the_content HTML, embedded verbatim — the staff choice to show
     * it inline), and a "Read this letter on our website »" link. Inherits the
     * body slot's serif base; the `.serif` class keeps Outlook on Georgia.
     *
     * @param array{title?:string,url?:string,body?:string} $view Resolved letter.
     */
    public static function letter(array $view): string
    {
        $title = (string) ($view['title'] ?? '');
        $url   = (string) ($view['url'] ?? '');
        $body  = (string) ($view['body'] ?? ''); // trusted HTML (the_content output)

        $out = '';
        if ($title !== '') {
            $titleHtml = self::esc($title);
            if ($url !== '') {
                $titleHtml = '<a href="' . self::escAttr($url) . '" style="color:' . self::MAROON . ';text-decoration:none;">' . $titleHtml . '</a>';
            }
            $out .= '<p class="serif text-dark" style="' . self::SERIF . 'font-size:20px;line-height:28px;font-weight:bold;color:' . self::MAROON . ';margin:0 0 14px;">' . $titleHtml . '</p>';
        }

        $out .= '<div class="serif body-text text-dark" style="' . self::SERIF . 'font-size:17px;line-height:27px;color:' . self::INK . ';">' . $body . '</div>';

        if ($url !== '') {
            $out .= '<p style="' . self::SANS . 'font-size:16px;line-height:25px;margin:16px 0 0;">'
                . '<a href="' . self::escAttr($url) . '" target="_blank" style="color:' . self::MAROON . ';font-weight:bold;text-decoration:underline;">Read this letter on our website &raquo;</a></p>';
        }

        return $out;
    }

    /**
     * Plain-text prose → paragraphs. The single converter for the "From the Pastor"
     * fallback (used when no recent letter post exists), shared by the web render
     * and the email projection so the hand-authored message reads the same on both.
     * Blank lines separate paragraphs; single newlines become <br>. Escapes input.
     */
    public static function prose(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $out = '';
        foreach (preg_split('/\R{2,}/', $text) as $para) {
            $para = trim((string) $para);
            if ($para === '') {
                continue;
            }
            $out .= '<p style="margin:0 0 16px;">' . nl2br(self::esc($para)) . '</p>';
        }

        return $out;
    }

    /**
     * Wrap already-rendered inner body HTML in the bulletproof email document —
     * the masthead + footer chrome ported from ../mailchimp's tested template
     * (topbar, logo, worship buttons, tan divider … social row, legal panel),
     * with client resets, a responsive stylesheet, dark-mode rules, and the
     * Outlook (Word-engine) conditionals. $inner is trusted HTML (the pastoral
     * letter prose + Happenings cards) and is embedded verbatim in the white
     * body slot. The Mailchimp merge tags are NOT here — they arrive via
     * $env['footer'] (built by the glue), so this stays content-agnostic.
     *
     * @param array{subject?:string,preview?:string,date?:string,footer?:string,topbar?:string} $env Issue envelope.
     */
    public static function document(string $inner, array $env): string
    {
        $subject = (string) ($env['subject'] ?? 'First Church Weekly News');
        $preview = (string) ($env['preview'] ?? '');
        $footer  = (string) ($env['footer'] ?? '');
        $topbar  = (string) ($env['topbar'] ?? self::TOPBAR);

        return self::head($subject)
            . '<body id="body" class="bg-page" style="margin:0;padding:0;width:100%;background-color:#eaeaea;">'
            . self::preheader($preview)
            . '<div role="article" aria-roledescription="email" aria-label="' . self::escAttr($subject) . '" lang="en" class="bg-page" style="background-color:#eaeaea;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="bg-page" style="background-color:#eaeaea;"><tr><td align="center" style="padding:0;">'
            . '<!--[if mso]><table role="presentation" align="center" width="600" cellspacing="0" cellpadding="0" border="0"><tr><td><![endif]-->'
            . '<table role="presentation" class="email-container" width="600" cellspacing="0" cellpadding="0" border="0" style="width:600px;max-width:600px;margin:0 auto;">'
            . self::topbarRow($topbar)
            . self::logoRow()
            . self::worshipRow()
            . self::dividerRow()
            . self::bodyRow($inner)
            . self::spacerRow()
            . self::socialRow()
            . self::footerRow($footer)
            . '</table>'
            . '<!--[if mso]></td></tr></table><![endif]-->'
            . '</td></tr></table></div></body></html>';
    }

    /** The <head>: bulletproof resets, responsive + dark-mode CSS, MSO conditionals. */
    private static function head(string $subject): string
    {
        return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "https://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'
            . '<html xmlns="https://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" lang="en"><head>'
            . '<meta charset="utf-8"><meta http-equiv="X-UA-Compatible" content="IE=edge">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="format-detection" content="telephone=no, date=no, address=no, email=no, url=no">'
            . '<meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark">'
            . '<title>' . self::esc($subject) . '</title>'
            . '<!--[if gte mso 9]><xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->'
            . '<!--[if mso]><style type="text/css">'
            . 'table, td, div, p, a, h1, h2, h3 { font-family: Helvetica, Arial, sans-serif !important; }'
            . ".serif, .serif * { font-family: Georgia, 'Times New Roman', serif !important; }"
            . '</style><![endif]-->'
            . '<style type="text/css">'
            . 'html, body { margin:0 !important; padding:0 !important; height:100% !important; width:100% !important; }'
            . '* { -ms-text-size-adjust:100%; -webkit-text-size-adjust:100%; }'
            . 'table, td { mso-table-lspace:0pt !important; mso-table-rspace:0pt !important; border-collapse:collapse !important; }'
            . 'img { -ms-interpolation-mode:bicubic; border:0; height:auto; line-height:100%; outline:none; text-decoration:none; }'
            . 'a { text-decoration:none; }'
            . 'a[x-apple-data-detectors] { color:inherit !important; text-decoration:none !important; font-size:inherit !important; font-family:inherit !important; font-weight:inherit !important; line-height:inherit !important; }'
            . 'u + #body a { color:inherit; text-decoration:none; }'
            . '#MessageViewBody a { color:inherit; text-decoration:none; }'
            . '.ExternalClass { width:100%; }'
            . '.ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div { line-height:100%; }'
            . '@media only screen and (max-width: 620px) {'
            . '.email-container { width:100% !important; margin:0 auto !important; }'
            . '.fluid { width:100% !important; max-width:100% !important; height:auto !important; }'
            . '.stack-column { display:block !important; width:100% !important; max-width:100% !important; direction:ltr !important; }'
            . '.stack-pad { padding-bottom:12px !important; }'
            . '.px { padding-left:22px !important; padding-right:22px !important; }'
            . '.btn a { display:block !important; width:auto !important; }'
            . '}'
            . '@media (prefers-color-scheme: dark) {'
            . 'body, .bg-page { background-color:#1c1c1c !important; }'
            . '.bg-card { background-color:#2a2a2a !important; }'
            . '.body-text, .text-dark { color:#e8e8e8 !important; }'
            . '.text-muted { color:#b7b7b7 !important; }'
            . '.panel { background-color:#242424 !important; }'
            . '.logo-bg { background-color:#ffffff !important; }'
            . '}'
            . '[data-ogsc] body, [data-ogsc] .bg-page { background-color:#1c1c1c !important; }'
            . '[data-ogsc] .bg-card { background-color:#2a2a2a !important; }'
            . '[data-ogsc] .body-text, [data-ogsc] .text-dark { color:#e8e8e8 !important; }'
            . '[data-ogsc] .text-muted { color:#b7b7b7 !important; }'
            . '[data-ogsc] .panel { background-color:#242424 !important; }'
            . '[data-ogsc] .logo-bg { background-color:#ffffff !important; }'
            . '</style></head>';
    }

    /** Hidden inbox-preview line, with the spacer hack that stops body text bleeding into it. */
    private static function preheader(string $preview): string
    {
        if ($preview === '') {
            return '';
        }
        return '<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;' . self::SANS . 'color:#eaeaea;">'
            . self::esc($preview)
            . str_repeat('&#847;&zwnj;&nbsp;', 9)
            . '</div>';
    }

    /** Maroon utility bar: the worship note + a "view in browser" archive link. */
    private static function topbarRow(string $topbar): string
    {
        return '<tr><td align="center" bgcolor="' . self::MAROON . '" style="background-color:' . self::MAROON . ';padding:9px 22px;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>'
            . '<td align="left" class="stack-column" style="' . self::SANS . 'font-size:12px;line-height:18px;color:#ffffff;">' . self::esc($topbar) . '</td>'
            . '<td align="right" class="stack-column" style="' . self::SANS . 'font-size:12px;line-height:18px;color:#ffffff;">'
            . '<a href="' . self::ARCHIVE . '" style="color:#ffffff;text-decoration:underline;">View in your browser</a></td>'
            . '</tr></table></td></tr>';
    }

    /** White logo header. */
    private static function logoRow(): string
    {
        return '<tr><td align="center" bgcolor="#ffffff" class="bg-card logo-bg" style="background-color:#ffffff;padding:24px 22px 8px 22px;">'
            . '<a href="' . self::escAttr(self::WEBSITE) . '" target="_blank" style="text-decoration:none;">'
            . '<img src="' . self::escAttr(self::LOGO_URL) . '" width="420" alt="First Church Seattle" class="fluid" style="display:block;width:100%;max-width:420px;height:auto;margin:0 auto;">'
            . '</a></td></tr>';
    }

    /** Two-up worship buttons (filled livestream + outlined in-person); stack on mobile. */
    private static function worshipRow(): string
    {
        $live = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="btn"><tr>'
            . '<td align="center" bgcolor="' . self::MAROON . '" style="border-radius:4px;">'
            . '<a href="' . self::escAttr(self::LIVESTREAM) . '" target="_blank" style="display:block;padding:13px 18px;' . self::SANS . 'font-size:15px;font-weight:bold;line-height:18px;color:#ffffff;text-align:center;border-radius:4px;">Worship&nbsp;Livestream</a>'
            . '</td></tr></table>';
        $inperson = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="btn"><tr>'
            . '<td align="center" bgcolor="#ffffff" style="border-radius:4px;border:2px solid ' . self::MAROON . ';">'
            . '<a href="' . self::escAttr(self::INPERSON) . '" target="_blank" style="display:block;padding:11px 18px;' . self::SANS . 'font-size:15px;font-weight:bold;line-height:18px;color:' . self::MAROON . ';text-align:center;border-radius:4px;">Worship&nbsp;In-person</a>'
            . '</td></tr></table>';

        return '<tr><td bgcolor="#ffffff" class="bg-card px" style="background-color:#ffffff;padding:18px 40px 28px 40px;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>'
            . '<!--[if mso]><td width="50%" valign="top"><![endif]-->'
            . '<td class="stack-column stack-pad" valign="top" style="padding-right:8px;">' . $live . '</td>'
            . '<!--[if mso]></td><td width="50%" valign="top"><![endif]-->'
            . '<td class="stack-column" valign="top" style="padding-left:8px;">' . $inperson . '</td>'
            . '<!--[if mso]></td><![endif]-->'
            . '</tr></table></td></tr>';
    }

    /** Full-width tan brand rule between the chrome and the body. */
    private static function dividerRow(): string
    {
        return '<tr><td bgcolor="#ffffff" class="bg-card" style="background-color:#ffffff;padding:0 40px;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>'
            . '<td height="3" style="height:3px;line-height:3px;font-size:3px;background-color:' . self::TAN . ';">&nbsp;</td>'
            . '</tr></table></td></tr>';
    }

    /** The white body slot: the pastoral letter prose + the Happenings cards, serif base. */
    private static function bodyRow(string $inner): string
    {
        return '<tr><td bgcolor="#ffffff" class="bg-card px body-text text-dark" style="background-color:#ffffff;padding:28px 40px;' . self::SERIF . 'font-size:17px;line-height:27px;color:' . self::INK . ';">'
            . $inner
            . '</td></tr>';
    }

    /** White spacer before the social row. */
    private static function spacerRow(): string
    {
        return '<tr><td bgcolor="#ffffff" class="bg-card" style="background-color:#ffffff;height:24px;line-height:24px;font-size:24px;">&nbsp;</td></tr>';
    }

    /** Maroon social row (Facebook / Instagram / website). */
    private static function socialRow(): string
    {
        $icon = static function (string $href, string $img, string $alt): string {
            return '<td style="padding:0 8px;"><a href="' . self::escAttr($href) . '" target="_blank">'
                . '<img src="' . self::escAttr($img) . '" width="28" height="28" alt="' . self::escAttr($alt) . '" style="display:block;"></a></td>';
        };

        return '<tr><td align="center" bgcolor="' . self::MAROON . '" style="background-color:' . self::MAROON . ';padding:20px 22px 16px 22px;">'
            . '<table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr>'
            . $icon(self::FACEBOOK, 'https://cdn-images.mailchimp.com/icons/social-block-v2/light-facebook-48.png', 'Facebook')
            . $icon(self::INSTAGRAM, 'https://cdn-images.mailchimp.com/icons/social-block-v2/light-instagram-48.png', 'Instagram')
            . $icon(self::WEBSITE, 'https://cdn-images.mailchimp.com/icons/social-block-v2/light-link-48.png', 'Website')
            . '</tr></table></td></tr>';
    }

    /**
     * The legal footer panel. The content (copyright, address, the Mailchimp
     * unsubscribe/preferences merge tags) is trusted HTML built by the glue and
     * embedded verbatim; with no footer the panel is omitted entirely (so a
     * footer-less render emits no merge tags).
     */
    private static function footerRow(string $footer): string
    {
        if ($footer === '') {
            return '';
        }
        return '<tr><td align="center" bgcolor="#fafafa" class="panel text-muted" style="background-color:#fafafa;padding:22px 30px 30px 30px;' . self::SANS . 'font-size:12px;line-height:20px;color:' . self::MUTED . ';">'
            . $footer
            . '</td></tr>';
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function escAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
