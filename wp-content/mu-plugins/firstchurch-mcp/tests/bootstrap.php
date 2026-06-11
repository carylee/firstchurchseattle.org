<?php
/**
 * PHPUnit bootstrap for the First Church MCP Abilities mu-plugin.
 *
 * The mu-plugin (../../firstchurch-mcp-abilities.php) is procedural and coupled
 * to WordPress. Rather than stand up a full WP install (the repo's CI is
 * deliberately no-DB/no-WP), we define behavior-faithful shims for the handful
 * of WP primitives the *testable* seams touch — sanitizers, an in-memory
 * post/meta/term store, capability checks — plus collectors for the
 * registration hooks (add_action/add_filter/wp_register_ability) so we can load
 * the file and assert against the real registered ability surface.
 *
 * Anything the file only references inside un-exercised function bodies
 * (WP_Query, Red_Item, the REST request/response classes) needs no shim: PHP
 * resolves those lazily at call time, and these tests never call those paths.
 *
 * @package FirstChurch\Mcp\Tests
 */

declare(strict_types=1);

// Deterministic time math: the mu-plugin documents that strtotime()/gmdate()
// round-trip wall-clock components under WP's UTC default. Mirror that here.
date_default_timezone_set('UTC');

require_once __DIR__ . '/../vendor/autoload.php';

// The mu-plugin guards with `defined('ABSPATH') || exit;`.
if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/');
}

// WP_Error / WP_Post / is_wp_error live outside tests/ to stay clear of PSR-4.
require_once __DIR__ . '/../wp-stubs.php';

/* ---------------------------------------------------------------------------
 * Shared mutable test state. Hooks + abilities are populated once when the file
 * is required (registration time) and are effectively constant; posts/meta/
 * terms/caps/users are per-test and cleared by fcmcp_test_reset().
 * ------------------------------------------------------------------------- */
$GLOBALS['fcmcp_test'] = array(
    'hooks'        => array(), // hook name => list of ['cb'=>callable,'priority'=>int]
    'abilities'    => array(), // ability name => args array
    'categories'   => array(), // category slug => args array
    'posts'        => array(), // id => WP_Post
    'meta'         => array(), // id => [meta_key => value]
    'terms'        => array(), // taxonomy => list of term objects
    'object_terms' => array(), // post id => [taxonomy => term ids]
    'users'        => array(), // id => object{ roles: string[] }
    'caps'         => array(), // capability strings the "current user" holds
    'next_post_id' => 1,
    'next_term_id' => 1000,
);

/* --------------------------- Hook collectors ----------------------------- */

if (!function_exists('add_filter')) {
    function add_filter($hook, $cb, $priority = 10, $args = 1): bool
    {
        $GLOBALS['fcmcp_test']['hooks'][$hook][] = array('cb' => $cb, 'priority' => (int) $priority);
        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $cb, $priority = 10, $args = 1): bool
    {
        return add_filter($hook, $cb, $priority, $args);
    }
}

/** Run all callbacks registered on $hook as a filter, threading $value through. */
function fcmcp_test_apply_filters(string $hook, $value, ...$args)
{
    foreach (fcmcp_test_hook_callbacks($hook) as $cb) {
        $value = $cb($value, ...$args);
    }
    return $value;
}

/** Fire all callbacks registered on $hook as an action. */
function fcmcp_test_do_action(string $hook, ...$args): void
{
    foreach (fcmcp_test_hook_callbacks($hook) as $cb) {
        $cb(...$args);
    }
}

/** Callbacks for a hook, ordered by ascending priority (stable within a tier). */
function fcmcp_test_hook_callbacks(string $hook): array
{
    $entries = $GLOBALS['fcmcp_test']['hooks'][$hook] ?? array();
    usort($entries, static fn($a, $b) => $a['priority'] <=> $b['priority']);
    return array_map(static fn($e) => $e['cb'], $entries);
}

/* ------------------------- Ability collectors ---------------------------- */

if (!function_exists('wp_register_ability')) {
    function wp_register_ability($name, $args = array()): bool
    {
        $GLOBALS['fcmcp_test']['abilities'][$name] = $args;
        return true;
    }
}

if (!function_exists('wp_register_ability_category')) {
    function wp_register_ability_category($slug, $args = array()): bool
    {
        $GLOBALS['fcmcp_test']['categories'][$slug] = $args;
        return true;
    }
}

/** Fire the abilities/category init hooks once and return the registered set. */
function fcmcp_test_boot_abilities(): array
{
    static $booted = false;
    if (!$booted) {
        fcmcp_test_do_action('wp_abilities_api_categories_init');
        fcmcp_test_do_action('wp_abilities_api_init');
        $booted = true;
    }
    return $GLOBALS['fcmcp_test']['abilities'];
}

/* --------------------------- Capabilities -------------------------------- */

if (!function_exists('current_user_can')) {
    function current_user_can($cap, ...$args): bool
    {
        return in_array($cap, $GLOBALS['fcmcp_test']['caps'], true);
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($user_id)
    {
        return $GLOBALS['fcmcp_test']['users'][(int) $user_id] ?? false;
    }
}

/* --------------------------- Post + meta store --------------------------- */

if (!function_exists('get_post')) {
    function get_post($post = null)
    {
        if ($post instanceof WP_Post) {
            return $post;
        }
        $id = (int) $post;
        return $GLOBALS['fcmcp_test']['posts'][$id] ?? null;
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post = null)
    {
        $p = get_post($post);
        return $p ? $p->post_type : false;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($id, $key = '', $single = false)
    {
        $all = $GLOBALS['fcmcp_test']['meta'][(int) $id] ?? array();
        if ('' === $key) {
            return $all;
        }
        if ($single) {
            return $all[$key] ?? '';
        }
        return array_key_exists($key, $all) ? array($all[$key]) : array();
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($id, $key, $value): bool
    {
        $GLOBALS['fcmcp_test']['meta'][(int) $id][$key] = $value;
        return true;
    }
}

if (!function_exists('wp_set_object_terms')) {
    function wp_set_object_terms($id, $terms, $taxonomy, $append = false)
    {
        $GLOBALS['fcmcp_test']['object_terms'][(int) $id][$taxonomy] = (array) $terms;
        return (array) $terms;
    }
}

/* ------------------------------- Terms ----------------------------------- */

if (!function_exists('get_term_by')) {
    function get_term_by($field, $value, $taxonomy)
    {
        foreach ($GLOBALS['fcmcp_test']['terms'][$taxonomy] ?? array() as $t) {
            if ('slug' === $field && $t->slug === $value) {
                return $t;
            }
            if ('name' === $field && $t->name === $value) {
                return $t;
            }
            if (('id' === $field || 'term_id' === $field) && (int) $t->term_id === (int) $value) {
                return $t;
            }
        }
        return false;
    }
}

if (!function_exists('wp_insert_term')) {
    function wp_insert_term($term, $taxonomy, $args = array())
    {
        $id  = $GLOBALS['fcmcp_test']['next_term_id']++;
        $obj = fcmcp_test_make_term($id, sanitize_title((string) $term), (string) $term);
        $GLOBALS['fcmcp_test']['terms'][$taxonomy][] = $obj;
        return array('term_id' => $id, 'term_taxonomy_id' => $id);
    }
}

if (!function_exists('get_terms')) {
    function get_terms($args = array())
    {
        $taxonomy = is_array($args) ? ($args['taxonomy'] ?? '') : (string) $args;
        return $GLOBALS['fcmcp_test']['terms'][$taxonomy] ?? array();
    }
}

/* --------------------------- Sanitizers + util --------------------------- */

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str): string
    {
        $str = strip_tags((string) $str);
        $str = preg_replace('/[\r\n\t ]+/', ' ', $str);
        return trim((string) $str);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str): string
    {
        return trim(strip_tags((string) $str));
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title): string
    {
        $title = strtolower(trim((string) $title));
        $title = preg_replace('/[^a-z0-9]+/', '-', $title);
        return trim((string) $title, '-');
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url): string
    {
        return trim((string) $url);
    }
}

if (!function_exists('absint')) {
    function absint($number): int
    {
        return abs((int) $number);
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data): string
    {
        return (string) $data;
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0)
    {
        if ('timestamp' === $type) {
            return time();
        }
        if ('mysql' === $type) {
            return gmdate('Y-m-d H:i:s');
        }
        return gmdate($type);
    }
}

if (!function_exists('get_gmt_from_date')) {
    function get_gmt_from_date($string, $format = 'Y-m-d H:i:s'): string
    {
        $ts = strtotime((string) $string);
        return false === $ts ? '' : gmdate($format, $ts);
    }
}

/* -------------------------- Test-only helpers ---------------------------- */

function fcmcp_test_make_term(int $term_id, string $slug, string $name, int $count = 0): object
{
    return (object) array('term_id' => $term_id, 'slug' => $slug, 'name' => $name, 'count' => $count, 'taxonomy' => '');
}

/** Reset per-test state (posts/meta/terms/caps/users); leaves registrations intact. */
function fcmcp_test_reset(): void
{
    $GLOBALS['fcmcp_test']['posts']        = array();
    $GLOBALS['fcmcp_test']['meta']         = array();
    $GLOBALS['fcmcp_test']['terms']        = array();
    $GLOBALS['fcmcp_test']['object_terms'] = array();
    $GLOBALS['fcmcp_test']['users']        = array();
    $GLOBALS['fcmcp_test']['caps']         = array();
    $GLOBALS['fcmcp_test']['next_post_id']  = 1;
    $GLOBALS['fcmcp_test']['next_term_id'] = 1000;
}

/** Seed a post into the in-memory store; returns it. */
function fcmcp_test_add_post(array $props = array()): WP_Post
{
    if (!isset($props['ID'])) {
        $props['ID'] = $GLOBALS['fcmcp_test']['next_post_id']++;
    }
    $post = new WP_Post($props);
    $GLOBALS['fcmcp_test']['posts'][$post->ID] = $post;
    return $post;
}

/** Seed a term into a taxonomy. */
function fcmcp_test_add_term(string $taxonomy, string $slug, ?string $name = null, int $count = 0): object
{
    $id  = $GLOBALS['fcmcp_test']['next_term_id']++;
    $obj = fcmcp_test_make_term($id, $slug, $name ?? $slug, $count);
    $GLOBALS['fcmcp_test']['terms'][$taxonomy][] = $obj;
    return $obj;
}

/** Set the capabilities the faux current user holds. */
function fcmcp_test_set_caps(array $caps): void
{
    $GLOBALS['fcmcp_test']['caps'] = array_values($caps);
}

/** Register a faux user with the given roles. */
function fcmcp_test_set_user(int $id, array $roles): object
{
    $user = (object) array('ID' => $id, 'roles' => $roles);
    $GLOBALS['fcmcp_test']['users'][$id] = $user;
    return $user;
}

/* ------------------------------------------------------------------------- *
 * Load the subject under test. Top-level add_action/add_filter/const run now;
 * abilities register lazily when a test calls fcmcp_test_boot_abilities().
 * ------------------------------------------------------------------------- */
require_once __DIR__ . '/../../firstchurch-mcp-abilities.php';
