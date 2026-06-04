<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms;

/**
 * Pure logic behind the [breeze_form] shortcode.
 *
 * Kept free of WP registration/enqueue so it can be unit-tested directly:
 * given raw attributes (and an optional id→slug map) it resolves the form URL
 * and dispatches to the right renderer. Invalid input degrades to an empty
 * string — a shortcode must never fatal a page.
 */
final class Shortcode
{
    /** @return array<string,string> */
    public static function defaults(): array
    {
        return [
            'slug'      => '',
            'id'        => '',
            'mode'      => 'button',
            'label'     => 'Open form',
            'new_tab'   => 'true',
            'title'     => '',
            'height'    => '',
            'max_width' => '',
        ];
    }

    /**
     * @param array<string,mixed>  $atts Raw shortcode attributes.
     * @param array<string,string> $map  id => slug (from data/forms.php at the edge).
     */
    public static function render(array $atts, array $map = []): string
    {
        $a = shortcode_atts(self::defaults(), $atts, 'breeze_form');

        $slug = trim((string) $a['slug']);
        if ($slug === '' && (string) $a['id'] !== '') {
            $slug = (string) (Catalog::slug_for_id((string) $a['id'], $map) ?? '');
        }

        $url = Url::for_slug($slug);
        if ($url === null) {
            return '';
        }

        if (strtolower((string) $a['mode']) === 'embed') {
            return Renderer::embed([
                'url'       => $url,
                'title'     => (string) ($a['title'] !== '' ? $a['title'] : $a['label']),
                'height'    => (string) $a['height'],
                'max_width' => (string) $a['max_width'],
            ]);
        }

        return Renderer::button([
            'url'     => $url,
            'label'   => (string) $a['label'],
            'new_tab' => self::truthy((string) $a['new_tab']),
        ]);
    }

    private static function truthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}
