<?php

declare(strict_types=1);

namespace FirstChurch\Happenings;

/**
 * Ranks already-projected Happenings for the curated "Featured" row.
 *
 * Featured is curated by prominence weight (the staff `fcs_weight`: Normal/
 * Featured/Pinned), not by recency. This orders a candidate pool by weight
 * (desc), breaking ties by date (most recent first — preserving the news row's
 * newest-first feel), and caps it.
 *
 * It operates on **projected Happening items**, not WP_Posts, which is the whole
 * point of Phase 4: a weighted event (carrying its `when` line + Register CTA)
 * and a weighted announcement are the same shape here, so they share one row.
 * A missing `weight` key means "normal" (0) — the spine drops weight 0 when it
 * projects, so absence is the common case. Pure (no WordPress).
 */
final class Featured
{
    /**
     * @param array<int,array<string,mixed>> $items Candidate Happenings (any source).
     * @param int                            $count Max items to return.
     * @return array<int,array<string,mixed>> The top $count, weight-then-date ordered.
     */
    public static function rank(array $items, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        // PHP 8's sort is stable, so a true (weight, date) tie keeps the caller's
        // order — meaningful, since the spine pre-sorts events by date and posts
        // by recency.
        usort($items, static function (array $a, array $b): int {
            $wa = (int) ($a['weight'] ?? 0);
            $wb = (int) ($b['weight'] ?? 0);
            if ($wa !== $wb) {
                return $wb <=> $wa; // higher weight first
            }
            // Dates are Y-m-d across sources, so a lexicographic compare is a date
            // compare; descending puts the later/more-recent date first.
            return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
        });

        return array_slice($items, 0, $count);
    }
}
