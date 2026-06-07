<?php
/**
 * PHPUnit bootstrap.
 *
 * The plugin runs inside WordPress, but its core logic — Openverse search
 * (page_size tiering, the 401 degrade-and-retry), result normalization, and
 * provenance storage — only touches a small set of WP primitives. We define
 * behavior-faithful shims for those so the suite runs standalone (no DB, no WP,
 * no network), the same approach as firstchurch-breeze-forms.
 *
 * The HTTP layer is a controllable queue: tests enqueue canned responses and
 * inspect the URLs the client actually requested (to assert the page_size sent).
 * add_action/add_filter record into a registry so a registered hook callback
 * (e.g. the Instant Images provenance bridge) can be fetched and invoked.
 *
 * @package FirstChurch\StockPhotos
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

define('ABSPATH', __DIR__ . '/');

// The plugin reads these from wp-config in production; defining them here lets
// fcsp_openverse_token() attempt a token exchange, which our wp_remote_post
// shim answers based on the per-test token flag.
define('FCSP_OPENVERSE_CLIENT_ID', 'test-id');
define('FCSP_OPENVERSE_CLIENT_SECRET', 'test-secret');

/* -------------------------------------------------------------------------
 * Test-controlled state + helpers
 * ---------------------------------------------------------------------- */

$GLOBALS['fcsp_test_hooks']      = [];   // hook name => [callbacks]
$GLOBALS['fcsp_test_http_queue'] = [];   // FIFO of canned wp_remote_get responses
$GLOBALS['fcsp_test_requests']   = [];   // URLs requested via wp_remote_get
$GLOBALS['fcsp_test_token']      = null; // when a string, wp_remote_post returns it as a bearer token
$GLOBALS['fcsp_test_meta']       = [];   // attachment id => [meta key => value]

/**
 * Reset per-test mutable state. Hooks are restored to the load-time baseline
 * (captured at the end of this file) so a filter/action a test adds doesn't
 * leak into the next one.
 */
function fcsp_test_reset(): void
{
    $GLOBALS['fcsp_test_http_queue'] = [];
    $GLOBALS['fcsp_test_requests']   = [];
    $GLOBALS['fcsp_test_token']      = null;
    $GLOBALS['fcsp_test_meta']       = [];
    $GLOBALS['fcsp_test_hooks']      = $GLOBALS['fcsp_test_hooks_baseline'] ?? [];
}

/** Queue a canned response for the next wp_remote_get(). */
function fcsp_test_enqueue(int $code, $body): void
{
    $GLOBALS['fcsp_test_http_queue'][] = [
        'code' => $code,
        'body' => is_string($body) ? $body : json_encode($body),
    ];
}

/** URLs requested via wp_remote_get(), in order. */
function fcsp_test_requests(): array
{
    return $GLOBALS['fcsp_test_requests'];
}

/** Callbacks registered for a hook. */
function fcsp_test_hook_callbacks(string $hook): array
{
    return $GLOBALS['fcsp_test_hooks'][$hook] ?? [];
}

/* -------------------------------------------------------------------------
 * WordPress shims (guarded so a real WP test env is harmless)
 * ---------------------------------------------------------------------- */

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private string $code;
        private string $message;

        public function __construct(string $code = '', string $message = '')
        {
            $this->code    = $code;
            $this->message = $message;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hook, $callback, int $priority = 10, int $args = 1): bool
    {
        $GLOBALS['fcsp_test_hooks'][$hook][] = $callback;
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, $callback, int $priority = 10, int $args = 1): bool
    {
        $GLOBALS['fcsp_test_hooks'][$hook][] = $callback;
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value, ...$args)
    {
        foreach (($GLOBALS['fcsp_test_hooks'][$hook] ?? []) as $callback) {
            $value = $callback($value, ...$args);
        }
        return $value;
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return false;
    }
}

if (!function_exists('__return_false')) {
    function __return_false(): bool
    {
        return false;
    }
}

if (!function_exists('__return_true')) {
    function __return_true(): bool
    {
        return true;
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url): string
    {
        $url = trim((string) $url);
        return preg_match('#^https?://#i', $url) ? $url : '';
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $str));
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(array $args, string $url): string
    {
        $sep = (strpos($url, '?') !== false) ? '&' : '?';
        return $url . $sep . http_build_query($args);
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get(string $url, array $args = [])
    {
        $GLOBALS['fcsp_test_requests'][] = $url;
        if (empty($GLOBALS['fcsp_test_http_queue'])) {
            return new WP_Error('fcsp_test_no_response', 'No canned response queued.');
        }
        return array_shift($GLOBALS['fcsp_test_http_queue']);
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = [])
    {
        $token = $GLOBALS['fcsp_test_token'] ?? null;
        if (!is_string($token) || $token === '') {
            return new WP_Error('fcsp_test_no_token', 'No token configured for this test.');
        }
        return [
            'code' => 200,
            'body' => json_encode(['access_token' => $token, 'expires_in' => 3600, 'token_type' => 'Bearer']),
        ];
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response): int
    {
        return is_array($response) ? (int) ($response['code'] ?? 0) : 0;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response): string
    {
        return is_array($response) ? (string) ($response['body'] ?? '') : '';
    }
}

// Token caching is bypassed so each test controls auth via the token flag.
if (!function_exists('get_transient')) {
    function get_transient(string $key)
    {
        return false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $key, $value, int $expiration = 0): bool
    {
        return true;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta(int $post_id, string $key = '', bool $single = false)
    {
        return $GLOBALS['fcsp_test_meta'][$post_id][$key] ?? '';
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta(int $post_id, string $key, $value): bool
    {
        $GLOBALS['fcsp_test_meta'][$post_id][$key] = $value;
        return true;
    }
}

/* -------------------------------------------------------------------------
 * Load the plugin. is_admin() is false, so the admin screen is skipped; the
 * other includes only register hooks (recorded above) at load time.
 * ---------------------------------------------------------------------- */

require_once __DIR__ . '/../firstchurch-stock-photos.php';

// Snapshot the hooks registered at load so each test starts from a clean slate.
$GLOBALS['fcsp_test_hooks_baseline'] = $GLOBALS['fcsp_test_hooks'];
