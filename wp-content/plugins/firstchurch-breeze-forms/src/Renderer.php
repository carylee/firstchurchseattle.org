<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms;

/**
 * Turns validated form data into escaped HTML for each mode.
 *
 * Pure string builders — no WP state, no I/O — so every branch is unit-tested.
 * All dynamic values pass through esc_url/esc_attr/esc_html before they reach
 * the markup; the array inputs are assumed already validated by the edge
 * (Url::for_slug), but escaping is applied here regardless as defense in depth.
 */
final class Renderer
{
    /** Fallback container max-width when none/invalid is supplied. */
    public const DEFAULT_MAX_WIDTH = 680;

    /** Breeze embed theming params, passed through as validated data-attributes. */
    private const THEME_KEYS = ['background_color', 'border_width', 'border_color', 'button_color'];

    /**
     * Mode 1 — a themed link styled as a button.
     *
     * @param array{url:string,label:string,new_tab?:bool} $a
     */
    public static function button(array $a): string
    {
        $href  = esc_url($a['url']);
        $label = esc_html($a['label']);

        $target = !empty($a['new_tab'])
            ? ' target="_blank" rel="noopener noreferrer"'
            : '';

        return '<a class="fcbf-button maranatha-button" href="' . $href . '"' . $target . '>'
            . $label
            . '</a>';
    }

    /**
     * Mode 2 — Breeze's official embed.
     *
     * Renders the `breeze_form_embed` container that Breeze's form_embed.js turns
     * into an auto-resizing iframe (so the embed sizes to the form — no fixed
     * height). Optional theming params (validated upstream) ride along as
     * data-attributes. A <noscript> link is the no-JS fallback.
     *
     * @param array{slug:string,subdomain:string,max_width?:int|string,
     *              background_color?:string,border_width?:string,
     *              border_color?:string,button_color?:string} $a
     */
    public static function embed(array $a): string
    {
        $slug      = (string) ($a['slug'] ?? '');
        $subdomain = (string) ($a['subdomain'] ?? '');
        $width     = self::dimension($a['max_width'] ?? 0, self::DEFAULT_MAX_WIDTH);

        $theme = '';
        foreach (self::THEME_KEYS as $key) {
            $value = (string) ($a[$key] ?? '');
            if ($value !== '') {
                $theme .= ' data-' . $key . '="' . esc_attr($value) . '"';
            }
        }

        $form_url = esc_url('https://' . $subdomain . '.breezechms.com/form/' . $slug);

        return '<div class="fcbf-embed" style="max-width:' . $width . 'px">'
            . '<div class="breeze_form_embed"'
            . ' data-subdomain="' . esc_attr($subdomain) . '"'
            . ' data-address="' . esc_attr($slug) . '"'
            . ' data-width="100%"'
            . $theme
            . '></div>'
            . '<noscript><a class="fcbf-button maranatha-button" href="' . $form_url . '">Open the form</a></noscript>'
            . '</div>';
    }

    /** Coerce a dimension to a positive int, falling back to $default. */
    private static function dimension(int|string $value, int $default): int
    {
        $n = absint($value);
        return $n > 0 ? $n : $default;
    }
}
