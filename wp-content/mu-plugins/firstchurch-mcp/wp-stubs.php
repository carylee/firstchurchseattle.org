<?php
/**
 * Minimal WordPress class stubs for the test harness.
 *
 * Kept in the package root (not under tests/) so Composer's PSR-4 dev autoloader
 * doesn't try to map these global classes into the FirstChurch\Mcp\Tests\
 * namespace. Required from tests/bootstrap.php before the mu-plugin loads.
 *
 * @package FirstChurch\Mcp\Tests
 */

declare(strict_types=1);

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        /** @var array<string,string[]> */
        private array $errors = array();
        /** @var array<string,mixed> */
        private array $error_data = array();

        public function __construct($code = '', $message = '', $data = '')
        {
            if ('' !== $code) {
                $this->errors[$code][] = (string) $message;
                if ('' !== $data) {
                    $this->error_data[$code] = $data;
                }
            }
        }

        public function get_error_code()
        {
            $codes = array_keys($this->errors);
            return $codes[0] ?? '';
        }

        public function get_error_message($code = '')
        {
            $code = '' !== $code ? $code : $this->get_error_code();
            return $this->errors[$code][0] ?? '';
        }

        public function get_error_data($code = '')
        {
            $code = '' !== $code ? $code : $this->get_error_code();
            return $this->error_data[$code] ?? null;
        }
    }
}

if (!class_exists('WP_Post')) {
    #[\AllowDynamicProperties]
    class WP_Post
    {
        public int $ID = 0;
        public string $post_title = '';
        public string $post_status = 'publish';
        public string $post_type = 'post';
        public string $post_content = '';
        public string $post_excerpt = '';
        public int $post_parent = 0;
        public int $menu_order = 0;
        public int $post_author = 1;
        public string $post_date = '2026-01-01 00:00:00';
        public string $post_mime_type = '';

        public function __construct(array $props = array())
        {
            foreach ($props as $k => $v) {
                $this->$k = $v;
            }
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}
