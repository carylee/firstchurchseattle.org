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
require_once __DIR__ . '/src/Sync.php';
require_once __DIR__ . '/src/Store.php';

use FirstChurch\BreezeForms\Shortcode;
use FirstChurch\BreezeForms\Store;
use FirstChurch\BreezeForms\Catalog;

const FCBF_VERSION = '0.1.0';

/** Option holding the last successful Breeze sync (a list of form records). */
const FCBF_FORMS_OPTION = 'fcbf_synced_forms';

/**
 * The baked seed shipped with the plugin (data/forms.json): the always-present
 * fallback used until the first successful runtime sync.
 *
 * @return array<int,array<string,string>>
 */
function fcbf_baked_records(): array
{
    $file = __DIR__ . '/data/forms.json';
    if (is_readable($file)) {
        $data = json_decode((string) file_get_contents($file), true);
        if (is_array($data)) {
            return $data;
        }
    }
    return [];
}

/**
 * The effective form list: last-good synced data if present, else the seed.
 *
 * @return array<int,array<string,string>>
 */
function fcbf_records(): array
{
    $synced = get_option(FCBF_FORMS_OPTION, null);
    return Store::resolve(is_array($synced) ? $synced : null, fcbf_baked_records());
}

/**
 * id→slug map derived from the effective form list (for [breeze_form id="…"]).
 *
 * @return array<string,string>
 */
function fcbf_id_slug_map(): array
{
    return Catalog::map_from_records(fcbf_records());
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
