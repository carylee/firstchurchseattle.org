<?php

declare(strict_types=1);

namespace FirstChurch\Happenings;

/**
 * Flattens a Happening feed item into the view-model the /engage cards render:
 * a title + link, a meta line, an optional blurb, an image, and a call-to-action
 * (url + label). The per-source choices — an event's "Register" vs "Event
 * details", an announcement's date line and CTA label — live here so the theme
 * block stays thin markup. Pure (no WordPress).
 */
final class CardView
{
    /**
     * @param array<string,mixed> $item A resolved Happening (see inc/sources.php).
     * @return array{title:string,url:string,meta:string,blurb:string,image:string,ctaUrl:string,ctaLabel:string}
     */
    public static function fromHappening(array $item): array
    {
        $source = (string) ($item['source'] ?? '');
        $url    = (string) ($item['url'] ?? '');

        $view = [
            'title'      => (string) ($item['title'] ?? ''),
            'url'        => $url,
            'meta'       => '',
            'blurb'      => '',
            'image'      => (string) ($item['image'] ?? ''),
            'ctaUrl'     => '',
            'ctaLabel'   => '',
            // Whether the CTA is the item's own action (Register / explicit CTA)
            // vs. a fallback to the permalink (Event details / Read more). The
            // theme renders fallbacks in a quieter style so a real sign-up link
            // stands out.
            'ctaPrimary' => false,
        ];

        if ($source === 'event') {
            $cta = (string) ($item['ctaUrl'] ?? '');
            // The spine sets ctaUrl = registration||permalink; a ctaUrl that
            // differs from the permalink is a real registration link.
            $hasRegistration    = ($cta !== '' && $cta !== $url);
            $view['meta']       = (string) ($item['when'] ?? '');
            $view['ctaUrl']     = $cta !== '' ? $cta : $url;
            $view['ctaLabel']   = $hasRegistration ? 'Register' : 'Event details';
            $view['ctaPrimary'] = $hasRegistration;

            return $view;
        }

        // Announcements (and any other source) read as dated news. Every card
        // carries an action: an explicit CTA when set, otherwise a "Read more"
        // fallback to the permalink so no card ends in dead space.
        $view['meta']  = self::humanDate((string) ($item['date'] ?? ''));
        $view['blurb'] = (string) ($item['body'] ?? '');
        $cta           = (string) ($item['ctaUrl'] ?? '');
        if ($cta !== '') {
            $text               = (string) ($item['ctaText'] ?? '');
            $view['ctaUrl']     = $cta;
            $view['ctaLabel']   = $text !== '' ? $text : 'Learn more';
            $view['ctaPrimary'] = true;
        } elseif ($url !== '') {
            $view['ctaUrl']   = $url;
            $view['ctaLabel'] = 'Read more';
        }

        return $view;
    }

    /** "2026-06-05" → "June 5, 2026"; "" → "". */
    private static function humanDate(string $date): string
    {
        if ($date === '') {
            return '';
        }
        $ts = strtotime($date);

        return $ts ? date_i18n('F j, Y', $ts) : '';
    }
}
