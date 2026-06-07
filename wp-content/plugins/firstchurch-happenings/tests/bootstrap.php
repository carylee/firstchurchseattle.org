<?php
/**
 * PHPUnit bootstrap.
 *
 * The plugin's pure core (src/) depends on only one WordPress primitive —
 * date_i18n() — for human date formatting. When the suite runs outside
 * WordPress we shim it to PHP's date() (the formats we use — 'F j', 'g:i a',
 * 'l' — need no locale translation, so this is behavior-faithful). Guarded with
 * function_exists() so running inside a real WP test install is harmless.
 *
 * @package FirstChurch\Happenings
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (!function_exists('date_i18n')) {
    function date_i18n(string $format, int $timestamp): string
    {
        return date($format, $timestamp);
    }
}
