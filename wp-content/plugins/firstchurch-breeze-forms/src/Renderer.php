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
    /** Fallback iframe height when none/invalid is supplied (cross-origin = no auto-height). */
    public const DEFAULT_HEIGHT = 800;

    /** Fallback container max-width when none/invalid is supplied. */
    public const DEFAULT_MAX_WIDTH = 680;

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
     * Mode 2 — a responsive iframe wrapping the real Breeze form.
     *
     * Height is author-set (Breeze's page is cross-origin and posts no height
     * messages, so true auto-height isn't possible); junk falls back to a
     * default rather than emitting a raw attribute. The iframe is intentionally
     * NOT sandboxed: Breeze's own JS and Stripe checkout need scripts, forms,
     * same-origin and popups, and over-sandboxing silently breaks payment forms.
     *
     * @param array{url:string,title:string,height?:int|string,max_width?:int|string} $a
     */
    public static function embed(array $a): string
    {
        $src    = esc_url($a['url']);
        $title  = esc_attr($a['title']);
        $height = self::dimension($a['height'] ?? 0, self::DEFAULT_HEIGHT);
        $width  = self::dimension($a['max_width'] ?? 0, self::DEFAULT_MAX_WIDTH);

        return '<div class="fcbf-embed" style="max-width:' . $width . 'px">'
            . '<iframe class="fcbf-embed__frame"'
            . ' src="' . $src . '"'
            . ' title="' . $title . '"'
            . ' height="' . $height . '"'
            . ' loading="lazy"'
            . ' referrerpolicy="no-referrer-when-downgrade"></iframe>'
            . '</div>';
    }

    /** Coerce a dimension to a positive int, falling back to $default. */
    private static function dimension(int|string $value, int $default): int
    {
        $n = absint($value);
        return $n > 0 ? $n : $default;
    }
}
