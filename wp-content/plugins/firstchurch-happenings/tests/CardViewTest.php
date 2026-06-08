<?php

declare(strict_types=1);

namespace FirstChurch\Happenings\Tests;

use FirstChurch\Happenings\CardView;
use PHPUnit\Framework\TestCase;

/**
 * CardView turns a Happening feed item into the flat view-model the /engage
 * cards render — picking the right meta line, blurb, and call-to-action per
 * source. Pure (no WP).
 */
final class CardViewTest extends TestCase
{
    public function test_event_with_registration_says_register(): void
    {
        $v = CardView::fromHappening([
            'source' => 'event',
            'title'  => 'Open Mic Night',
            'when'   => 'June 11 at 6:00 pm',
            'url'    => 'https://x/events/open-mic/',
            'ctaUrl' => 'https://breeze/form/123',
            'image'  => 'https://x/img.jpg',
        ]);
        $this->assertSame('June 11 at 6:00 pm', $v['meta']);
        $this->assertSame('https://breeze/form/123', $v['ctaUrl']);
        $this->assertSame('Register', $v['ctaLabel']);
        $this->assertTrue($v['ctaPrimary'], 'a real registration link is the primary action');
        $this->assertSame('https://x/events/open-mic/', $v['url']);
        $this->assertSame('https://x/img.jpg', $v['image']);
    }

    public function test_event_without_registration_says_event_details_and_links_to_page(): void
    {
        // The spine sets ctaUrl = registration||permalink, so no-registration
        // means ctaUrl === url.
        $v = CardView::fromHappening([
            'source' => 'event',
            'title'  => 'Sunday Worship',
            'when'   => 'Sundays at 10:30 am',
            'url'    => 'https://x/events/worship/',
            'ctaUrl' => 'https://x/events/worship/',
        ]);
        $this->assertSame('Event details', $v['ctaLabel']);
        $this->assertSame('https://x/events/worship/', $v['ctaUrl']);
        $this->assertFalse($v['ctaPrimary'], 'a bare permalink is a secondary/fallback action');
        $this->assertSame('', $v['blurb']);
    }

    public function test_announcement_with_cta_uses_its_label_and_date(): void
    {
        $v = CardView::fromHappening([
            'source'  => 'announcement',
            'title'   => 'All Church Conference',
            'body'    => 'Join us via Zoom on June 17.',
            'date'    => '2026-06-05',
            'url'     => 'https://x/conf/',
            'ctaUrl'  => 'https://breeze/rsvp',
            'ctaText' => 'RSVP',
        ]);
        $this->assertSame('June 5, 2026', $v['meta']);
        $this->assertSame('Join us via Zoom on June 17.', $v['blurb']);
        $this->assertSame('https://breeze/rsvp', $v['ctaUrl']);
        $this->assertSame('RSVP', $v['ctaLabel']);
        $this->assertTrue($v['ctaPrimary'], 'an explicit CTA is the primary action');
    }

    public function test_announcement_cta_without_text_defaults_to_learn_more(): void
    {
        $v = CardView::fromHappening([
            'source' => 'announcement',
            'title'  => 'Volunteers Needed',
            'date'   => '2026-05-30',
            'ctaUrl' => 'https://x/signup',
        ]);
        $this->assertSame('Learn more', $v['ctaLabel']);
        $this->assertSame('https://x/signup', $v['ctaUrl']);
        $this->assertTrue($v['ctaPrimary']);
    }

    public function test_announcement_without_cta_falls_back_to_read_more_on_the_permalink(): void
    {
        // Every card should carry an action — with no explicit CTA we fall back
        // to the post permalink, labeled "Read more" and styled as secondary.
        $v = CardView::fromHappening([
            'source' => 'announcement',
            'title'  => 'A note',
            'date'   => '2026-05-30',
            'url'    => 'https://x/a-note/',
        ]);
        $this->assertSame('https://x/a-note/', $v['ctaUrl']);
        $this->assertSame('Read more', $v['ctaLabel']);
        $this->assertFalse($v['ctaPrimary'], 'a read-more fallback is secondary');
    }

    public function test_announcement_without_cta_or_permalink_emits_no_button(): void
    {
        $v = CardView::fromHappening([
            'source' => 'announcement',
            'title'  => 'A note',
            'date'   => '2026-05-30',
        ]);
        $this->assertSame('', $v['ctaUrl']);
        $this->assertSame('', $v['ctaLabel']);
        $this->assertFalse($v['ctaPrimary']);
    }

    public function test_missing_date_yields_empty_meta(): void
    {
        $v = CardView::fromHappening(['source' => 'announcement', 'title' => 'x']);
        $this->assertSame('', $v['meta']);
    }
}
