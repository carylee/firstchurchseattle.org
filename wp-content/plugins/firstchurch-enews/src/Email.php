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

    /** Back-compat alias for the body slot's base font (the serif letter). */
    private const FONT = self::SERIF;

    /**
     * One Happening as an email-safe card (a bordered table). Mirrors the web
     * card's shape — linked title, meta line, blurb, CTA button — but inline.
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
        $ctaUrl = (string) ($view['ctaUrl'] ?? '');
        $ctaLbl = (string) ($view['ctaLabel'] ?? '');

        $titleHtml = self::esc($title);
        if ($url !== '') {
            $titleHtml = '<a href="' . self::escAttr($url) . '" style="color:' . self::MAROON . ';text-decoration:none;">' . $titleHtml . '</a>';
        }

        $rows = '<tr><td style="' . self::FONT . 'font-size:18px;font-weight:bold;line-height:1.3;color:' . self::INK . ';padding:0 0 4px;">' . $titleHtml . '</td></tr>';

        if ($showMeta && $meta !== '') {
            $rows .= '<tr><td style="' . self::FONT . 'font-size:13px;color:' . self::MUTED . ';padding:0 0 6px;">' . self::esc($meta) . '</td></tr>';
        }
        if ($blurb !== '') {
            $rows .= '<tr><td style="' . self::FONT . 'font-size:15px;line-height:1.5;color:' . self::INK . ';padding:0 0 10px;">' . self::esc($blurb) . '</td></tr>';
        }
        if ($ctaUrl !== '') {
            $label = $ctaLbl !== '' ? $ctaLbl : 'Learn more';
            $rows .= '<tr><td style="padding:0;"><a href="' . self::escAttr($ctaUrl) . '" style="' . self::FONT . 'display:inline-block;background:' . self::MAROON . ';color:#ffffff;font-size:14px;font-weight:bold;text-decoration:none;padding:8px 16px;border-radius:4px;">' . self::esc($label) . '</a></td></tr>';
        }

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
            . 'style="border:1px solid #e5e5e5;border-radius:6px;margin:0 0 16px;"><tr><td style="padding:16px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $rows . '</table>'
            . '</td></tr></table>';
    }

    /**
     * Wrap already-rendered inner body HTML in the email document scaffold: a
     * centered, width-constrained column with a hidden preheader (the preview
     * text) and a base font. $inner is trusted HTML (block render output + cards)
     * and is embedded verbatim.
     *
     * @param array{subject?:string,preview?:string,date?:string,footer?:string} $env Issue envelope.
     */
    public static function document(string $inner, array $env): string
    {
        $subject = (string) ($env['subject'] ?? 'First Church Weekly News');
        $preview = (string) ($env['preview'] ?? '');
        $footer  = (string) ($env['footer'] ?? '');

        // Hidden preheader: the inbox preview line, kept out of the visible body.
        $preheader = $preview !== ''
            ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . self::esc($preview) . '</div>'
            : '';

        // Footer (trusted HTML from the glue: social/past-issues/copyright + the
        // Mailchimp unsubscribe/address merge tags). Quiet, centered, below the body.
        $footerRow = $footer !== ''
            ? '<tr><td style="padding:16px 24px 24px;' . self::FONT . 'font-size:12px;line-height:1.6;color:'
                . self::MUTED . ';text-align:center;border-top:1px solid #eeeeee;">' . $footer . '</td></tr>'
            : '';

        return '<!DOCTYPE html><html><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . self::esc($subject) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f4f4f4;">'
            . $preheader
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f4;">'
            . '<tr><td align="center" style="padding:24px 12px;">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" '
            . 'style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;">'
            . '<tr><td style="padding:24px;' . self::FONT . 'color:' . self::INK . ';">'
            . $inner
            . '</td></tr>'
            . $footerRow
            . '</table>'
            . '</td></tr></table></body></html>';
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
