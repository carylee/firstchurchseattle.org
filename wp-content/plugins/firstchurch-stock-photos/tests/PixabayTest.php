<?php

declare(strict_types=1);

namespace FirstChurch\StockPhotos\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Covers the Pixabay adapter: registration, normalization, and a dispatcher-
 * routed search (key in query, per_page clamp to 3..200, orientation mapping,
 * safesearch + photo type).
 */
final class PixabayTest extends TestCase
{
    protected function setUp(): void
    {
        \fcsp_test_reset();
    }

    private function sampleHit(array $overrides = []): array
    {
        return array_merge([
            'id'            => 195893,
            'pageURL'       => 'https://pixabay.com/photos/candle-195893/',
            'tags'          => 'candle, flame, light',
            'previewURL'    => 'https://cdn.pixabay.com/candle_150.jpg',
            'webformatURL'  => 'https://cdn.pixabay.com/candle_640.jpg',
            'largeImageURL' => 'https://cdn.pixabay.com/candle_1280.jpg',
            'imageWidth'    => 4000,
            'imageHeight'   => 2600,
            'user'          => 'Pixabayer',
            'user_id'       => 9876,
        ], $overrides);
    }

    public function testRegisteredAndAvailableWithKey(): void
    {
        $choices = \fcsp_provider_choices();
        self::assertArrayHasKey('pixabay', $choices);
        self::assertSame('Pixabay', $choices['pixabay']);
    }

    public function testNormalizeMapsFields(): void
    {
        $out = \fcsp_normalize_pixabay($this->sampleHit());

        self::assertSame('195893', $out['id']);
        self::assertSame('candle, flame, light', $out['title']);
        self::assertSame('Pixabayer', $out['creator']);
        self::assertSame('https://pixabay.com/users/Pixabayer-9876/', $out['creator_url']);
        self::assertSame('https://cdn.pixabay.com/candle_1280.jpg', $out['url']);
        self::assertSame('https://cdn.pixabay.com/candle_640.jpg', $out['thumbnail']);
        self::assertSame('https://pixabay.com/photos/candle-195893/', $out['foreign_url']);
        self::assertSame('Pixabay License', $out['license']);
        self::assertSame('pixabay', $out['source']);
        self::assertSame('Image by Pixabayer from Pixabay', $out['attribution']);
        self::assertSame(4000, $out['width']);
    }

    public function testNormalizeReturnsNullWithoutLargeImage(): void
    {
        $hit = $this->sampleHit();
        unset($hit['largeImageURL']);

        self::assertNull(\fcsp_normalize_pixabay($hit));
    }

    public function testSearchSendsKeyClampsPerPageAndMapsOrientation(): void
    {
        \fcsp_test_enqueue(200, [
            'total'     => 500,
            'totalHits' => 500,
            'hits'      => [$this->sampleHit()],
        ]);

        $result = \fcsp_search([
            'query'       => 'candles',
            'provider'    => 'pixabay',
            'count'       => 500,   // over Pixabay's max of 200
            'orientation' => 'wide',
        ]);

        self::assertSame('pixabay', $result['provider']);
        self::assertSame(3, $result['page_count']); // ceil(500 / 200)

        $url = \fcsp_test_requests()[0];
        self::assertStringContainsString('per_page=200', $url);
        self::assertStringContainsString('orientation=horizontal', $url);
        self::assertStringContainsString('safesearch=true', $url);
        self::assertStringContainsString('image_type=photo', $url);
        self::assertStringContainsString('key=test-pixabay-key', $url);
    }

    public function testSearchClampsTinyPerPageUpToMinimum(): void
    {
        \fcsp_test_enqueue(200, ['total' => 1, 'totalHits' => 1, 'hits' => [$this->sampleHit()]]);

        \fcsp_search(['query' => 'candles', 'provider' => 'pixabay', 'count' => 1]);

        self::assertStringContainsString('per_page=3', \fcsp_test_requests()[0]);
    }
}
