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

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        // Strip tags, collapse runs of whitespace, trim — WP's behavior for the
        // single-line text the native forms handle (names, phone, address parts).
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($str)));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $str): string
    {
        // Like sanitize_text_field but newlines survive (multi-line requests).
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string
    {
        return trim((string) preg_replace('/[^a-zA-Z0-9.@_+\-]/', '', $email));
    }
}

if (!function_exists('is_email')) {
    function is_email(string $email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) ?: false;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, int $options = 0, int $depth = 512): string
    {
        return (string) json_encode($data, $options, $depth);
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
