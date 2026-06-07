<?php

declare(strict_types=1);

namespace FirstChurch\Happenings;

/**
 * Text helpers for the feed. WordPress stores entity-encoded text
 * ("Men&#8217;s"); consumers want plain UTF-8 (they do their own escaping), so
 * clean() decodes and trims.
 */
final class Text
{
    public static function clean(mixed $value): string
    {
        return trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** Does this string read as a clock time ("7:00 pm", "9 am", "10:30")? */
    public static function isClocklike(string $value): bool
    {
        return (bool) preg_match('/\d{1,2}:\d{2}|\d\s*[ap]\.?m\.?/i', $value);
    }
}
