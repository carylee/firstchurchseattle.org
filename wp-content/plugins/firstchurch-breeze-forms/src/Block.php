<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms;

/**
 * The "Breeze Form" block.
 *
 * It's a dynamic block: the editor JS only collects attributes, and the
 * front-end render reuses Shortcode::render — so block and shortcode produce
 * identical, already-tested markup. This class holds the one piece of logic
 * worth unit-testing: turning JS-typed block attributes into the string atts
 * Shortcode::render expects.
 */
final class Block
{
    /**
     * @param array<string,mixed> $a Block attributes (as WP passes them to render_callback).
     * @return array<string,string>
     */
    public static function to_shortcode_atts(array $a): array
    {
        $rawMode  = (string) ($a['mode'] ?? 'button');
        $mode     = in_array($rawMode, ['button', 'embed'], true) ? $rawMode : 'button';
        $height   = (int) ($a['height'] ?? 0);
        $maxWidth = (int) ($a['maxWidth'] ?? 0);

        return [
            'slug'      => (string) ($a['slug'] ?? ''),
            'id'        => (string) ($a['id'] ?? ''),
            'mode'      => $mode,
            'label'     => (string) ($a['label'] ?? 'Open form'),
            'new_tab'   => !empty($a['newTab']) ? 'true' : 'false',
            'height'    => $height > 0 ? (string) $height : '',
            'max_width' => $maxWidth > 0 ? (string) $maxWidth : '',
        ];
    }
}
