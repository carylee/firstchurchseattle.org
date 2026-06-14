<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms;

/**
 * Derives a tasteful stock-photo search query from an intake draft, so the
 * Comms Desk can suggest images on its own instead of making the coordinator
 * type a query.
 *
 * Strategy (best → fallback): a curated event-category → visual-concept map
 * (deterministic, free, and biased toward scenes/objects/light rather than
 * stocky photos of people), then any AI-suggested visual phrases, then a
 * cleaned-up title. Pure: data-in / data-out, no WordPress, no network.
 */
final class PhotoQuery
{
    /**
     * Event-category slug → a calm, concrete visual concept. Deliberately about
     * scenes and objects (candles, hands, nature, a shared table) — people shots
     * from stock libraries read "stocky" and risk a tone-deaf mismatch.
     *
     * @var array<string,string>
     */
    private const MAP = [
        'worship'              => 'lit candles in a quiet sanctuary',
        'adult-spirituality'   => 'open book and candle, quiet study',
        'spiritual-enrichment' => 'sunrise over a peaceful path',
        'children'             => 'children making a craft',
        'classes'             => 'open notebook and coffee on a table',
        'lunch-learn'          => 'coffee and conversation around a table',
        'community'            => 'community gathering, welcoming hands',
        'grief'                => 'a single candle, soft calm light',
        'missions'             => 'volunteers, helping hands outdoors',
        'climate-justice'      => 'green forest and sunlight',
        'social-justice'       => 'hands together in solidarity',
        'young-adults'         => 'friends gathering outdoors',
    ];

    /** The visual concept for an event category, or '' if unmapped. */
    public static function forCategory(string $slug): string
    {
        return self::MAP[trim($slug)] ?? '';
    }

    /**
     * Crude last-resort query from a title: drop a "| When" suffix and any
     * year, collapse whitespace, lowercase. Not as good as the map/AI (it can't
     * turn a proper noun into a visual), but better than nothing.
     */
    public static function cleanTitle(string $title): string
    {
        $t = explode('|', $title)[0];
        $t = preg_replace('/\b(19|20)\d{2}\b/', '', $t);   // years
        $t = preg_replace('/\s+/', ' ', (string) $t);       // collapse runs
        return strtolower(trim((string) $t));
    }

    /**
     * Resolve the best query: category map, else the first non-empty AI phrase,
     * else the cleaned title.
     *
     * @param array<int,string> $aiPhrases Visual phrases from the AI helper.
     */
    public static function resolve(string $category, string $title, array $aiPhrases = []): string
    {
        $byCat = self::forCategory($category);
        if ($byCat !== '') {
            return $byCat;
        }
        foreach ($aiPhrases as $phrase) {
            $phrase = trim((string) $phrase);
            if ($phrase !== '') {
                return $phrase;
            }
        }
        return self::cleanTitle($title);
    }

    /**
     * Reduce raw fcsp_search results to the compact shape the desk stores and
     * renders: the thumbnail/url/title/creator the card shows, plus the full
     * original record as 'meta' for the import call. Drops records missing a
     * url or thumbnail; caps the list.
     *
     * @param array<int,array<string,mixed>> $results
     * @return array<int,array{thumbnail:string,url:string,title:string,creator:string,meta:array<string,mixed>}>
     */
    public static function cleanCandidates(array $results, int $max = 4): array
    {
        $out = [];
        foreach ($results as $r) {
            if (!is_array($r)) {
                continue;
            }
            $url   = trim((string) ($r['url'] ?? ''));
            $thumb = trim((string) ($r['thumbnail'] ?? ''));
            if ($url === '' || $thumb === '') {
                continue;
            }
            $out[] = [
                'thumbnail' => $thumb,
                'url'       => $url,
                'title'     => (string) ($r['title'] ?? ''),
                'creator'   => (string) ($r['creator'] ?? ''),
                'meta'      => $r,
            ];
            if (count($out) >= max(1, $max)) {
                break;
            }
        }
        return $out;
    }
}
