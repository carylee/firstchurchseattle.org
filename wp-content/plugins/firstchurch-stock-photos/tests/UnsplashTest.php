<?php

declare(strict_types=1);

namespace FirstChurch\StockPhotos\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Covers the Unsplash adapter: registration, normalization, a dispatcher-routed
 * search (Client-ID header, per_page clamp, orientation mapping), and the
 * ToS-required download ping fired on import.
 */
final class UnsplashTest extends TestCase
{
    protected function setUp(): void
    {
        \fcsp_test_reset();
    }

    private function samplePhoto(array $overrides = []): array
    {
        return array_merge([
            'id'              => 'Dwu85P9SOIk',
            'width'           => 5000,
            'height'          => 3333,
            'description'     => null,
            'alt_description' => 'a lit candle',
            'urls'            => [
                'full'  => 'https://images.unsplash.com/photo-1?full',
                'small' => 'https://images.unsplash.com/photo-1?small',
                'thumb' => 'https://images.unsplash.com/photo-1?thumb',
            ],
            'links'           => [
                'html'              => 'https://unsplash.com/photos/Dwu85P9SOIk',
                'download_location' => 'https://api.unsplash.com/photos/Dwu85P9SOIk/download?ixid=abc',
            ],
            'user'            => [
                'name'  => 'Annie S',
                'links' => ['html' => 'https://unsplash.com/@annie'],
            ],
        ], $overrides);
    }

    public function testRegisteredAndAvailableWithKey(): void
    {
        $choices = \fcsp_provider_choices();
        self::assertArrayHasKey('unsplash', $choices);
        self::assertSame('Unsplash', $choices['unsplash']);
    }

    public function testNormalizeMapsFields(): void
    {
        $out = \fcsp_normalize_unsplash($this->samplePhoto());

        self::assertSame('Dwu85P9SOIk', $out['id']);
        self::assertSame('a lit candle', $out['title']);
        self::assertSame('Annie S', $out['creator']);
        self::assertSame('https://images.unsplash.com/photo-1?full', $out['url']);
        self::assertSame('https://images.unsplash.com/photo-1?small', $out['thumbnail']);
        self::assertSame('https://unsplash.com/photos/Dwu85P9SOIk', $out['foreign_url']);
        self::assertSame('Unsplash License', $out['license']);
        self::assertSame('unsplash', $out['source']);
        self::assertSame('Photo by Annie S on Unsplash', $out['attribution']);
        self::assertSame('https://api.unsplash.com/photos/Dwu85P9SOIk/download?ixid=abc', $out['download_location']);
    }

    public function testNormalizeReturnsNullWithoutFullUrl(): void
    {
        $photo = $this->samplePhoto();
        unset($photo['urls']['full']);

        self::assertNull(\fcsp_normalize_unsplash($photo));
    }

    public function testSearchSendsClientIdClampsPerPageAndMapsOrientation(): void
    {
        \fcsp_test_enqueue(200, [
            'total'       => 90,
            'total_pages' => 3,
            'results'     => [$this->samplePhoto()],
        ]);

        $result = \fcsp_search([
            'query'       => 'candles',
            'provider'    => 'unsplash',
            'count'       => 50,   // over Unsplash's max of 30
            'orientation' => 'square',
        ]);

        self::assertSame('unsplash', $result['provider']);
        self::assertSame(3, $result['page_count']);

        $url = \fcsp_test_requests()[0];
        self::assertStringContainsString('per_page=30', $url);
        self::assertStringContainsString('orientation=squarish', $url);

        $args = \fcsp_test_request_args()[0];
        self::assertSame('Client-ID test-unsplash-key', $args['headers']['Authorization']);
    }

    public function testAfterImportFiresDownloadPingWithAuth(): void
    {
        \fcsp_unsplash_after_import([
            'provider'          => 'unsplash',
            'download_location' => 'https://api.unsplash.com/photos/Dwu85P9SOIk/download?ixid=abc',
        ]);

        $requests = \fcsp_test_requests();
        self::assertCount(1, $requests);
        self::assertSame('https://api.unsplash.com/photos/Dwu85P9SOIk/download?ixid=abc', $requests[0]);
        self::assertSame('Client-ID test-unsplash-key', \fcsp_test_request_args()[0]['headers']['Authorization']);
    }

    public function testAfterImportNoopWithoutDownloadLocation(): void
    {
        \fcsp_unsplash_after_import(['provider' => 'unsplash']);

        self::assertSame([], \fcsp_test_requests());
    }
}
