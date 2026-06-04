<?php
/**
 * Plugin Name: First Church Breeze Forms
 * Description: Surface any Breeze form as a themed button (Mode 1) or a responsive embed (Mode 2) via the [breeze_form] shortcode. No Breeze credentials required — both modes are pure markup pointing at the public form URL.
 * Version:     0.1.0
 * Author:      First Church Seattle
 *
 * @package FirstChurch\BreezeForms
 */

if (!defined('ABSPATH')) {
    exit;
}

// Production loads the small core via explicit requires (no Composer on prod);
// the test suite loads the same classes through Composer's PSR-4 autoloader.
require_once __DIR__ . '/src/Url.php';
require_once __DIR__ . '/src/Catalog.php';
require_once __DIR__ . '/src/Renderer.php';
require_once __DIR__ . '/src/Shortcode.php';

use FirstChurch\BreezeForms\Shortcode;

const FCBF_VERSION = '0.1.0';

/**
 * The optional id→slug map, generated offline from the Breeze catalog.
 *
 * @return array<string,string>
 */
function fcbf_id_slug_map(): array
{
    $file = __DIR__ . '/data/forms.php';
    if (is_readable($file)) {
        $map = require $file;
        if (is_array($map)) {
            return $map;
        }
    }
    return [];
}

/**
 * Enqueue the (tiny) stylesheet only when a [breeze_form] actually renders.
 */
function fcbf_enqueue_assets(): void
{
    wp_enqueue_style(
        'firstchurch-breeze-forms',
        plugin_dir_url(__FILE__) . 'assets/breeze-forms.css',
        [],
        FCBF_VERSION
    );
}

add_shortcode('breeze_form', function ($atts): string {
    $html = Shortcode::render(is_array($atts) ? $atts : [], fcbf_id_slug_map());
    if ($html !== '') {
        fcbf_enqueue_assets();
    }
    return $html;
});
