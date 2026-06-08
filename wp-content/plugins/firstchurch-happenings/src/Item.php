<?php

declare(strict_types=1);

namespace FirstChurch\Happenings;

/**
 * Builds one feed item, dropping empty values so the JSON stays tight.
 *
 * id/source/layout are structural and always kept (even when empty). Booleans
 * are emitted only when true (mirrors the slides side's
 * `preservice_only = a.preserviceOnly || undefined`). Everything else is kept
 * unless it is null or the empty string — note an int 0 is a real value and is
 * preserved.
 */
final class Item
{
    private const ALWAYS = ['id', 'source', 'layout'];

    /**
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    public static function build(array $fields): array
    {
        $out = [];
        foreach ($fields as $key => $value) {
            if (in_array($key, self::ALWAYS, true)) {
                $out[$key] = $value;
                continue;
            }
            if (is_bool($value)) {
                if ($value) {
                    $out[$key] = true;
                }
                continue;
            }
            if ($value !== null && $value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
