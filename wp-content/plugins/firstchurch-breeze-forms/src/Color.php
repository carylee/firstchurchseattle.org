<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms;

/**
 * Validates hex colors for the Breeze embed theming params.
 *
 * Breeze wants the bare hex (no leading '#'), so this normalizes and validates:
 * a value becomes part of a URL/data-attribute, so anything that isn't 3 or 6
 * hex digits is rejected outright.
 */
final class Color
{
    public static function hex(string $value): ?string
    {
        $value = ltrim(strtolower(trim($value)), '#');

        return preg_match('/^([0-9a-f]{3}|[0-9a-f]{6})$/', $value) ? $value : null;
    }
}
