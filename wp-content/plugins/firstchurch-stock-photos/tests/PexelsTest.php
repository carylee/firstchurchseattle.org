<?php

declare(strict_types=1);

namespace FirstChurch\StockPhotos\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Covers the Pexels adapter: registration/availability, result normalization,
 * and that a search routed through the dispatcher sends the API key, clamps
 * per_page to Pexels' maximum, and maps orientation.
 */
final class PexelsTest extends TestCase
{
    protected function setUp(): void
    {
        \fcsp_test_reset();
    }

    private function samplePhoto(array $overrides = []): array
    {
        return array_merge([
            'id'               => 12345,
            'width'            => 4000,
            'height'           => 3000,
            'url'              => 'https://www.pexels.com/photo/12345/',
            'photographer'     => 'Sam Lee',
            'photographer_url' => 'https://www.pexels.com/@sam',
            'alt'              => 'A candle',
            'src'              => [
                'original' => 'https://images.pexels.com/photos/12345/original.jpg',
                'medium'   => 'https://images.pexels.com/photos/12345/medium.jpg',
            ],
        ], $overrides);
    }

    public function testRegisteredAndAvailableWithKey(): void
    {
        $choices = \fcsp_provider_choices();
        self::assertArrayHasKey('pexels', $choices);
        self::assertSame('Pexels', $choices['pexels']);
    }

    public function testNormalizeMapsFields(): void
    {
        $out = \fcsp_normalize_pexels($this->samplePhoto());

        self::assertSame('12345', $out['id']);
        self::assertSame('A candle', $out['title']);
        self::assertSame('Sam Lee', $out['creator']);
        self::assertSame('https://images.pexels.com/photos/12345/original.jpg', $out['url']);
        self::assertSame('https://images.pexels.com/photos/12345/medium.jpg', $out['thumbnail']);
        self::assertSame('https://www.pexels.com/photo/12345/', $out['foreign_url']);
        self::assertSame('Pexels License', $out['license']);
        self::assertSame('pexels', $out['source']);
        self::assertSame('Photo by Sam Lee on Pexels', $out['attribution']);
        self::assertSame(4000, $out['width']);
    }

    public function testNormalizeReturnsNullWithoutOriginal(): void
    {
        $photo = $this->samplePhoto();
        unset($photo['src']['original']);

        self::assertNull(\fcsp_normalize_pexels($photo));
    }

    public function testSearchSendsKeyClampsPerPageAndMapsOrientation(): void
    {
        \fcsp_test_enqueue(200, [
            'total_results' => 160,
            'per_page'      => 80,
            'page'          => 1,
            'photos'        => [$this->samplePhoto()],
        ]);

        $result = \fcsp_search([
            'query'       => 'candles',
            'provider'    => 'pexels',
            'count'       => 90,   // over Pexels' max of 80
            'orientation' => 'wide',
        ]);

        self::assertSame('pexels', $result['provider']);
        self::assertSame('pexels', $result['results'][0]['provider']);
        self::assertSame(2, $result['page_count']); // ceil(160 / 80)

        $url = \fcsp_test_requests()[0];
        self::assertStringContainsString('per_page=80', $url);
        self::assertStringContainsString('orientation=landscape', $url);

        $args = \fcsp_test_request_args()[0];
        self::assertSame('test-pexels-key', $args['headers']['Authorization']);
    }
}
