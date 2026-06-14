<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms;

/**
 * Normalizes the AI extractor's per-field "gaps" — the specific things it
 * wasn't sure about ("which lot?", "what time?") — into a clean, predictable
 * list before it's stored on the intake item. The Comms Desk renders these as a
 * checklist so review becomes "check these two things" instead of "re-read
 * everything suspiciously."
 *
 * Pure: data-in / data-out. Escaping happens at render time, not here.
 */
final class Gaps
{
    /**
     * Reduce arbitrary decoded input to a list of {field, question} entries,
     * keeping only those with a non-empty question. Anything that isn't a
     * usable array of entries yields [].
     *
     * @param mixed $raw Decoded gaps payload (array, JSON-decoded, or junk).
     * @return array<int,array{field:string,question:string}>
     */
    public static function clean($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $question = trim((string) ($entry['question'] ?? ''));
            if ($question === '') {
                continue;
            }
            $out[] = [
                'field'    => trim((string) ($entry['field'] ?? '')),
                'question' => $question,
            ];
        }
        return $out;
    }
}
