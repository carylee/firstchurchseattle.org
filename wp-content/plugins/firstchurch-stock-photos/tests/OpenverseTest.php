<?php

declare(strict_types=1);

namespace FirstChurch\StockPhotos\Tests;

use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Covers the Openverse client: result normalization and — the regression that
 * prompted this suite — page_size clamping per auth tier plus the 401
 * degrade-and-retry.
 */
final class OpenverseTest extends TestCase
{
    protected function setUp(): void
    {
        \fcsp_test_reset();
    }

    private function sampleItem(array $overrides = []): array
    {
        return array_merge([
            'id'                  => 'abc-123',
            'title'               => 'Autumn',
            'creator'             => 'Jane Doe',
            'creator_url'         => 'https://example.com/jane',
            'url'                 => 'https://img.example.com/full.jpg',
            'thumbnail'           => 'https://img.example.com/thumb.jpg',
            'foreign_landing_url' => 'https://flickr.com/photo/1',
            'license'             => 'by-sa',
            'license_url'         => 'https://creativecommons.org/licenses/by-sa/2.0/',
            'attribution'         => '"Autumn" by Jane Doe is licensed under CC BY-SA 2.0.',
            'source'              => 'flickr',
            'width'               => 1200,
            'height'              => 800,
        ], $overrides);
    }

    public function testNormalizeMapsAllFields(): void
    {
        $out = \fcsp_normalize_openverse($this->sampleItem());

        self::assertSame('abc-123', $out['id']);
        self::assertSame('https://img.example.com/full.jpg', $out['url']);
        self::assertSame('https://img.example.com/thumb.jpg', $out['thumbnail']);
        self::assertSame('https://flickr.com/photo/1', $out['foreign_url']);
        self::assertSame('by-sa', $out['license']);
        self::assertSame('flickr', $out['source']);
        self::assertSame(1200, $out['width']);
        self::assertIsInt($out['height']);
    }

    public function testNormalizeFallsBackThumbnailToUrl(): void
    {
        $item = $this->sampleItem();
        unset($item['thumbnail']);

        $out = \fcsp_normalize_openverse($item);

        self::assertSame($out['url'], $out['thumbnail']);
    }

    public function testNormalizeReturnsNullWithoutUrl(): void
    {
        $item = $this->sampleItem();
        unset($item['url']);

        self::assertNull(\fcsp_normalize_openverse($item));
    }

    public function testEmptyQueryReturnsError(): void
    {
        $result = \fcsp_search(['query' => '   ']);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('fcsp_empty_query', $result->get_error_code());
        self::assertSame([], \fcsp_test_requests(), 'No HTTP request should be made for an empty query.');
    }

    public function testAnonymousRequestClampsPageSizeTo20(): void
    {
        $GLOBALS['fcsp_test_token'] = null; // unauthenticated
        \fcsp_test_enqueue(200, ['result_count' => 5, 'page_count' => 1, 'results' => [$this->sampleItem()]]);

        $result = \fcsp_search(['query' => 'autumn', 'count' => 24]);

        self::assertIsArray($result);
        self::assertCount(1, $result['results']);
        $requests = \fcsp_test_requests();
        self::assertCount(1, $requests);
        self::assertStringContainsString('page_size=20', $requests[0]);
        self::assertStringNotContainsString('page_size=24', $requests[0]);
    }

    public function testAuthenticatedRequestAllowsLargerPageSize(): void
    {
        $GLOBALS['fcsp_test_token'] = 'bearer-xyz'; // verified credentials
        \fcsp_test_enqueue(200, ['result_count' => 5, 'page_count' => 1, 'results' => [$this->sampleItem()]]);

        \fcsp_search(['query' => 'autumn', 'count' => 24]);

        $requests = \fcsp_test_requests();
        self::assertStringContainsString('page_size=24', $requests[0]);
    }

    public function testAuthenticatedRequestClampsToTierMaximum50(): void
    {
        $GLOBALS['fcsp_test_token'] = 'bearer-xyz';
        \fcsp_test_enqueue(200, ['result_count' => 5, 'page_count' => 1, 'results' => [$this->sampleItem()]]);

        \fcsp_search(['query' => 'autumn', 'count' => 99]);

        $requests = \fcsp_test_requests();
        self::assertStringContainsString('page_size=50', $requests[0]);
    }

    public function testOverCap401DegradesTo20AndRetries(): void
    {
        // Credentials present (so first attempt asks for 50) but the API still
        // 401s — e.g. registered-but-unverified. We should retry at 20, not fail.
        $GLOBALS['fcsp_test_token'] = 'bearer-unverified';
        \fcsp_test_enqueue(401, ['detail' => 'Unauthorized']);
        \fcsp_test_enqueue(200, ['result_count' => 2, 'page_count' => 1, 'results' => [$this->sampleItem(), $this->sampleItem()]]);

        $result = \fcsp_search(['query' => 'autumn', 'count' => 50]);

        self::assertIsArray($result, 'Search should recover rather than return an error.');
        self::assertCount(2, $result['results']);
        $requests = \fcsp_test_requests();
        self::assertCount(2, $requests, 'Exactly one retry expected.');
        self::assertStringContainsString('page_size=50', $requests[0]);
        self::assertStringContainsString('page_size=20', $requests[1]);
    }

    public function testPersistent401ReturnsError(): void
    {
        $GLOBALS['fcsp_test_token'] = 'bearer-unverified';
        \fcsp_test_enqueue(401, ['detail' => 'Unauthorized']);
        \fcsp_test_enqueue(401, ['detail' => 'Unauthorized']);

        $result = \fcsp_search(['query' => 'autumn', 'count' => 50]);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('fcsp_openverse_http', $result->get_error_code());
    }
}
