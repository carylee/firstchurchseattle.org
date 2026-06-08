<?php

declare(strict_types=1);

namespace FirstChurch\Happenings;

/**
 * Feed ids are "{source}-{postId}" — e.g. "event-7269", "announcement-7426",
 * "card-7439". Parsing one back into its parts is how item_by_id() dispatches a
 * deck reference to the right source.
 */
final class Id
{
    /** @return array{prefix:string,num:int}|null */
    public static function parse(string $id): ?array
    {
        if (preg_match('/^([a-z]+)-(\d+)$/', $id, $m)) {
            return ['prefix' => $m[1], 'num' => (int) $m[2]];
        }

        return null;
    }
}
