<?php
/**
 * PHPUnit bootstrap.
 *
 * inc/form.php is the plugin's pure core — params validation + the params ->
 * Breeze inputs projection. It leans on a handful of WordPress sanitizers; when
 * the suite runs outside WordPress we shim them faithfully enough for the values
 * the tests exercise. Each shim is function_exists()-guarded so running inside a
 * real WP test install is harmless.
 *
 * @package FirstChurch\ConnectionCard
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        // Strip tags, collapse runs of whitespace, trim — WP's behavior for the
        // single-line text we handle (names, phone, address parts).
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($str)));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $str): string
    {
        // Like sanitize_text_field but newlines survive (multi-line comments).
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

require_once __DIR__ . '/../inc/form.php';
