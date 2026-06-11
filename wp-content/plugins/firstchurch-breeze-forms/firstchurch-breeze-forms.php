<?php
/**
 * Plugin Name: First Church Breeze Forms
 * Description: Surface any Breeze form via the [breeze_form] shortcode — as a themed button (Mode 1), a responsive embed (Mode 2), or a native in-theme form that posts straight to Breeze (Mode 3). Modes 1 & 2 need no Breeze credentials; Mode 3 renders forms with a baked field contract (currently the Prayer Requests form).
 * Version:     0.2.0
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
require_once __DIR__ . '/src/Color.php';
require_once __DIR__ . '/src/Catalog.php';
require_once __DIR__ . '/src/Renderer.php';
require_once __DIR__ . '/src/Shortcode.php';
require_once __DIR__ . '/src/Sync.php';
require_once __DIR__ . '/src/Store.php';
require_once __DIR__ . '/src/Block.php';
require_once __DIR__ . '/src/Entries.php';
require_once __DIR__ . '/src/Native.php';

// Intake queue: capture Breeze form submissions on-site (CPT + reader + MCP).
require_once __DIR__ . '/inc/intake-cpt.php';
require_once __DIR__ . '/inc/intake-reader.php';
require_once __DIR__ . '/inc/intake-mcp.php';

// Mode 3: native in-theme rendering + server-side submission to Breeze.
require_once __DIR__ . '/inc/native-submit.php';

use FirstChurch\BreezeForms\Shortcode;
use FirstChurch\BreezeForms\Store;
use FirstChurch\BreezeForms\Catalog;
use FirstChurch\BreezeForms\Sync;
use FirstChurch\BreezeForms\Block;

const FCBF_VERSION = '0.2.0';

/** Option holding the last successful Breeze sync (a list of form records). */
const FCBF_FORMS_OPTION = 'fcbf_synced_forms';

/** Option holding the unix time of the last successful sync. */
const FCBF_LAST_SYNC_OPTION = 'fcbf_last_sync';

/** Cron hook that refreshes the form list from Breeze. */
const FCBF_SYNC_HOOK = 'fcbf_sync_event';

/** Read-only public-API endpoint that lists the church's active forms. */
const FCBF_LIST_FORMS_URL = 'https://firstchurchseattle.breezechms.com/api/forms/list_forms';

/** Breeze's edge filter blocks default agents — present as a normal browser. */
const FCBF_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36';

/** Option holding id→description (the leading instructional text of each form). */
const FCBF_DESCRIPTIONS_OPTION = 'fcbf_descriptions';

/** Option holding the unix time of the last successful description sync. */
const FCBF_DESCRIPTIONS_LAST_SYNC_OPTION = 'fcbf_descriptions_last_sync';

/** Cron hook that refreshes per-form descriptions (daily — it's 1 call/form). */
const FCBF_DESCRIPTIONS_HOOK = 'fcbf_descriptions_event';

/** Per-form field endpoint; the leading paragraph/header is our "description". */
const FCBF_LIST_FIELDS_URL = 'https://firstchurchseattle.breezechms.com/api/forms/list_form_fields';

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
    $synced  = get_option(FCBF_FORMS_OPTION, null);
    $records = Store::resolve(is_array($synced) ? $synced : null, fcbf_baked_records());

    $descriptions = get_option(FCBF_DESCRIPTIONS_OPTION, []);
    return Sync::with_descriptions($records, is_array($descriptions) ? $descriptions : []);
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
 * Enqueue the stylesheet when a [breeze_form] renders, plus — for embeds —
 * Breeze's official form_embed.js, which turns the breeze_form_embed div into
 * an auto-resizing iframe. Loaded only when an embed is actually on the page.
 */
function fcbf_enqueue_assets(string $html = ''): void
{
    wp_enqueue_style('firstchurch-breeze-forms');

    if (strpos($html, 'breeze_form_embed') !== false) {
        wp_enqueue_script('breeze-form-embed', 'https://app.breezechms.com/js/form_embed.js', [], null, true);
    }
}

/**
 * Register the shared style and the "Breeze Form" editor block on init.
 *
 * The block is *dynamic*: its render_callback reuses Shortcode::render, so block
 * and shortcode emit identical, already-tested markup. The editor script + the
 * form list it needs are only set up in wp-admin, so front-end requests pay
 * nothing for the picker.
 */
function fcbf_register_assets(): void
{
    wp_register_style(
        'firstchurch-breeze-forms',
        plugin_dir_url(__FILE__) . 'assets/breeze-forms.css',
        [],
        FCBF_VERSION
    );

    if (!function_exists('register_block_type')) {
        return;
    }

    if (is_admin()) {
        wp_register_script(
            'firstchurch-breeze-forms-block',
            plugin_dir_url(__FILE__) . 'assets/block.js',
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render'],
            FCBF_VERSION,
            true
        );

        $native = fcbf_native_forms();
        $forms  = array_map(
            static fn ($r) => [
                'id'          => $r['id'],
                'slug'        => $r['slug'],
                'name'        => $r['name'],
                'description' => $r['description'] ?? '',
                // Whether this form has a baked native contract (Mode 3): the
                // editor only offers "Native" for forms it can actually render.
                'native'      => isset($native[$r['id']]),
            ],
            fcbf_records()
        );
        wp_add_inline_script(
            'firstchurch-breeze-forms-block',
            'window.fcbfForms = ' . wp_json_encode(array_values($forms)) . ';',
            'before'
        );
    }

    register_block_type('firstchurch/breeze-form', [
        'api_version'     => 3,
        'editor_script'   => 'firstchurch-breeze-forms-block',
        'style'           => 'firstchurch-breeze-forms',
        'render_callback' => 'fcbf_render_block',
        'attributes'      => [
            'slug'            => ['type' => 'string',  'default' => ''],
            'id'              => ['type' => 'string',  'default' => ''],
            'mode'            => ['type' => 'string',  'default' => 'button'],
            'title'           => ['type' => 'string',  'default' => ''],
            'label'           => ['type' => 'string',  'default' => 'Open form'],
            'newTab'          => ['type' => 'boolean', 'default' => true],
            'height'          => ['type' => 'number',  'default' => 0],
            'maxWidth'        => ['type' => 'number',  'default' => 0],
            'backgroundColor' => ['type' => 'string',  'default' => ''],
            'borderColor'     => ['type' => 'string',  'default' => ''],
            'borderWidth'     => ['type' => 'string',  'default' => ''],
            'buttonColor'     => ['type' => 'string',  'default' => ''],
        ],
    ]);
}
add_action('init', 'fcbf_register_assets');

/**
 * Render [breeze_form]/the block from shortcode-style atts.
 *
 * mode="native" (Mode 3) renders our in-theme form and posts server-side to
 * Breeze — but only for forms with a baked native contract; any other form
 * falls through to the standard button/embed path so a bad/unmapped id still
 * produces something usable rather than nothing. Modes 1 & 2 go straight to the
 * tested Shortcode::render.
 *
 * @param array<string,mixed> $atts Shortcode-style attributes.
 */
function fcbf_dispatch(array $atts): string
{
    if (strtolower((string) ($atts['mode'] ?? '')) === 'native') {
        $native = fcbf_render_native($atts);
        if ($native !== '') {
            return $native;
        }
    }

    $html = Shortcode::render($atts, fcbf_id_slug_map());
    if ($html !== '') {
        fcbf_enqueue_assets($html);
    }
    return $html;
}

/** Block front-end render — delegate to the shared dispatch path. */
function fcbf_render_block($attributes): string
{
    return fcbf_dispatch(Block::to_shortcode_atts((array) $attributes));
}

add_shortcode('breeze_form', function ($atts): string {
    return fcbf_dispatch(is_array($atts) ? $atts : []);
});

/* -------------------------------------------------------------------------
 * Runtime sync — refresh the form list from Breeze (read-only Api-Key).
 * The credential lives in wp-config.php as FCBF_BREEZE_API_KEY. Parsing is
 * Sync::from_json (unit-tested); only the HTTP call + storage live here.
 * ---------------------------------------------------------------------- */

/**
 * Fetch and normalize the live form list from Breeze.
 *
 * @return array<int,array<string,string>>|WP_Error Records, or an error.
 */
function fcbf_sync_fetch()
{
    if (!defined('FCBF_BREEZE_API_KEY') || !FCBF_BREEZE_API_KEY) {
        return new WP_Error('fcbf_no_key', 'FCBF_BREEZE_API_KEY is not configured in wp-config.php.');
    }

    $resp = wp_remote_get(FCBF_LIST_FORMS_URL, [
        'timeout'    => 20,
        'user-agent' => FCBF_USER_AGENT,
        'headers'    => ['Api-Key' => FCBF_BREEZE_API_KEY],
    ]);
    if (is_wp_error($resp)) {
        return $resp;
    }

    $code = wp_remote_retrieve_response_code($resp);
    if ($code !== 200) {
        return new WP_Error('fcbf_http', "Breeze list_forms returned HTTP {$code}.");
    }

    $records = Sync::from_json((string) wp_remote_retrieve_body($resp));
    if ($records === null) {
        return new WP_Error('fcbf_bad_body', 'Breeze list_forms returned an unparseable body.');
    }

    return $records;
}

/**
 * Run a sync: persist the list only on a successful, non-empty fetch so a
 * transient failure can never blank the picker.
 *
 * @return true|WP_Error
 */
function fcbf_sync_run()
{
    $records = fcbf_sync_fetch();
    if (is_wp_error($records)) {
        return $records;
    }
    if (empty($records)) {
        return new WP_Error('fcbf_empty', 'list_forms returned no usable forms; keeping the previous list.');
    }

    update_option(FCBF_FORMS_OPTION, $records, false);
    update_option(FCBF_LAST_SYNC_OPTION, time(), false);
    return true;
}

add_action(FCBF_SYNC_HOOK, 'fcbf_sync_run');

/**
 * Refresh each form's description (its leading instructional text).
 *
 * One call per form, so this runs DAILY, not hourly. Breeze exposes no change
 * signal (no ETag/Last-Modified, no modified date), so we re-fetch all forms.
 * Starts from the existing map and only overwrites on success, so a per-form
 * failure keeps the prior text rather than blanking it.
 *
 * @return array{ok:int,failed:int}|WP_Error
 */
function fcbf_sync_descriptions()
{
    if (!defined('FCBF_BREEZE_API_KEY') || !FCBF_BREEZE_API_KEY) {
        return new WP_Error('fcbf_no_key', 'FCBF_BREEZE_API_KEY is not configured in wp-config.php.');
    }

    $out    = (array) get_option(FCBF_DESCRIPTIONS_OPTION, []);
    $ok     = 0;
    $failed = 0;

    foreach (fcbf_records() as $record) {
        $resp = wp_remote_get(
            add_query_arg('form_id', $record['id'], FCBF_LIST_FIELDS_URL),
            ['timeout' => 20, 'user-agent' => FCBF_USER_AGENT, 'headers' => ['Api-Key' => FCBF_BREEZE_API_KEY]]
        );
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
            $failed++;
            continue;
        }
        $fields = json_decode((string) wp_remote_retrieve_body($resp), true);
        if (!is_array($fields)) {
            $failed++;
            continue;
        }

        $out[$record['id']] = Sync::lead_description($fields);
        $ok++;
        usleep(100000); // ~10 req/s — gentle on a small origin server
    }

    update_option(FCBF_DESCRIPTIONS_OPTION, $out, false);
    update_option(FCBF_DESCRIPTIONS_LAST_SYNC_OPTION, time(), false);
    return ['ok' => $ok, 'failed' => $failed];
}

add_action(FCBF_DESCRIPTIONS_HOOK, 'fcbf_sync_descriptions');

/** Ensure both recurring syncs are scheduled (self-heals after updates). */
function fcbf_ensure_scheduled(): void
{
    if (!wp_next_scheduled(FCBF_SYNC_HOOK)) {
        wp_schedule_event(time() + 60, 'hourly', FCBF_SYNC_HOOK);
    }
    if (!wp_next_scheduled(FCBF_DESCRIPTIONS_HOOK)) {
        wp_schedule_event(time() + 120, 'daily', FCBF_DESCRIPTIONS_HOOK);
    }
    if (!wp_next_scheduled(FCBF_INTAKE_HOOK)) {
        wp_schedule_event(time() + 180, 'hourly', FCBF_INTAKE_HOOK);
    }
}
add_action('init', 'fcbf_ensure_scheduled');
register_activation_hook(__FILE__, 'fcbf_ensure_scheduled');
register_deactivation_hook(__FILE__, function (): void {
    wp_clear_scheduled_hook(FCBF_SYNC_HOOK);
    wp_clear_scheduled_hook(FCBF_DESCRIPTIONS_HOOK);
    wp_clear_scheduled_hook(FCBF_INTAKE_HOOK);
});
