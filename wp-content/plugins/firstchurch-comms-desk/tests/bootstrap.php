<?php
/**
 * PHPUnit bootstrap for the Comms Desk.
 *
 * The plugin's UI glue (desk.php, the REST handlers) is procedural and coupled
 * to WordPress, but its *logic* — which cards are publish-ready, how the queue
 * partitions, how the original submission / AI gaps / clarification mailto are
 * built — is factored into pure helpers in inc/cards.php that depend only on a
 * tiny set of WP primitives. We define behavior-faithful shims for those (so
 * the assertions genuinely exercise escaping, not a no-op) and load just that
 * file. The side-effectful files (add_action/REST registration) are never
 * required here.
 *
 * @package FirstChurch\CommsDesk\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// inc/cards.php guards with `defined('ABSPATH') || exit;`.
if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/');
}

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
    /** Display-context port of WP's esc_url: only http/https (+ mailto), then neutralize attribute-breakers. */
    function esc_url($url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^(https?://|mailto:)#i', $url)) {
            return '';
        }
        $url = str_replace(['"', "'", '<', '>', ' '], ['%22', '%27', '%3C', '%3E', '%20'], $url);
        return str_replace('&', '&#038;', $url);
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text): void
    {
        echo esc_attr($text);
    }
}

require_once __DIR__ . '/../inc/cards.php';
