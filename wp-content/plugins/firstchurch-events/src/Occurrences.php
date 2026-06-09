<?php

declare(strict_types=1);

namespace FirstChurch\Events;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use FirstChurch\Events\Vendor\RRule\RRule;

/**
 * Expand an event's DTSTART + derived RRULE into concrete dates within a window.
 *
 * Pure (DateTime + the vendored rlanvin/php-rrule), so it is unit-tested outside
 * WordPress. The WP glue (inc/event.php, inc/source.php) reads meta and delegates
 * here. One-offs (no RRULE) collapse to their single DTSTART. Cancelled dates
 * (EXDATE) are filtered in PHP — robust regardless of how the RRULE was built.
 */
final class Occurrences
{
    /**
     * Every occurrence in [$from, $to] (inclusive) that isn't cancelled.
     *
     * @param array<int,string> $skip Y-m-d dates to exclude (EXDATE).
     * @return array<int,DateTimeImmutable> ascending; empty if none.
     */
    public static function between(
        string $dtstart,
        string $rrule,
        DateTimeInterface $from,
        DateTimeInterface $to,
        array $skip = []
    ): array {
        if ('' === $dtstart) {
            return [];
        }

        if ('' === $rrule) {
            $d = new DateTimeImmutable($dtstart);
            if ($d >= $from && $d <= $to && ! in_array($d->format('Y-m-d'), $skip, true)) {
                return [$d];
            }
            return [];
        }

        $out = [];
        foreach (new RRule($rrule, new DateTime($dtstart)) as $occ) {
            $o = DateTimeImmutable::createFromInterface($occ);
            if ($o < $from) {
                continue;
            }
            if ($o > $to) {
                break; // RRULE iterates ascending — nothing further is in range.
            }
            if (! in_array($o->format('Y-m-d'), $skip, true)) {
                $out[] = $o;
            }
        }
        return $out;
    }

    /**
     * First occurrence in [$from, $to] (inclusive) that isn't cancelled, or null.
     * Short-circuits — does not expand the whole window.
     *
     * @param array<int,string> $skip Y-m-d dates to exclude (EXDATE).
     */
    public static function next(
        string $dtstart,
        string $rrule,
        DateTimeInterface $from,
        DateTimeInterface $to,
        array $skip = []
    ): ?DateTimeImmutable {
        if ('' === $dtstart) {
            return null;
        }

        if ('' === $rrule) {
            $d = new DateTimeImmutable($dtstart);
            return ($d >= $from && $d <= $to && ! in_array($d->format('Y-m-d'), $skip, true)) ? $d : null;
        }

        foreach (new RRule($rrule, new DateTime($dtstart)) as $occ) {
            $o = DateTimeImmutable::createFromInterface($occ);
            if ($o < $from) {
                continue;
            }
            if ($o > $to) {
                return null;
            }
            if (! in_array($o->format('Y-m-d'), $skip, true)) {
                return $o;
            }
        }
        return null;
    }
}
