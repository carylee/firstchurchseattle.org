<?php
/**
 * PHPUnit bootstrap.
 *
 * The plugin's core (src/) deliberately depends on only a tiny set of
 * WordPress primitives — the escaping/util functions below. When the test
 * suite runs *outside* WordPress we define behavior-faithful shims so the
 * assertions actually exercise escaping (a `javascript:` URL or a quote in a
 * label is genuinely neutralized), not a no-op. Each shim is guarded with
 * function_exists() so running inside a real WP test install is harmless.
 *
 * @package FirstChurch\BreezeForms
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (!function_exists('esc_html')) {
    function esc_html($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    /**
     * Faithful-enough port of WP's esc_url for display contexts: allow only
     * http/https, then encode characters that could break out of an attribute.
     */
    function esc_url($url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            return '';
        }
        $url = str_replace(
            ['"', "'", '<', '>', ' '],
            ['%22', '%27', '%3C', '%3E', '%20'],
            $url
        );
        return str_replace('&', '&#038;', $url);
    }
}

if (!function_exists('absint')) {
    function absint($number): int
    {
        return abs((int) $number);
    }
}

if (!function_exists('shortcode_atts')) {
    /**
     * Mirror of WP core: return only the keys declared in $pairs, overriding
     * defaults with any matching values from $atts.
     */
    function shortcode_atts($pairs, $atts, $shortcode = ''): array
    {
        $atts = (array) $atts;
        $out  = [];
        foreach ($pairs as $name => $default) {
            $out[$name] = array_key_exists($name, $atts) ? $atts[$name] : $default;
        }
        return $out;
    }
}
