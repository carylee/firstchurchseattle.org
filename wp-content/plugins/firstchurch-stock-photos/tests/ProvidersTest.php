<?php

declare(strict_types=1);

namespace FirstChurch\StockPhotos\Tests;

use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Covers the provider registry + the fcsp_search() dispatcher: default routing,
 * provider stamping, and rejection of unknown/unavailable providers.
 */
final class ProvidersTest extends TestCase
{
    protected function setUp(): void
    {
        \fcsp_test_reset();
    }

    public function testOpenverseIsRegisteredAndAvailable(): void
    {
        $providers = \fcsp_providers();

        self::assertArrayHasKey('openverse', $providers);
        self::assertTrue($providers['openverse']['available']);
        self::assertArrayHasKey('openverse', \fcsp_provider_choices());
    }

    public function testDefaultsToOpenverseAndStampsProvider(): void
    {
        $GLOBALS['fcsp_test_token'] = null;
        \fcsp_test_enqueue(200, [
            'result_count' => 1,
            'page_count'   => 1,
            'results'      => [['url' => 'https://img.example.com/a.jpg', 'thumbnail' => 'https://img.example.com/t.jpg']],
        ]);

        $result = \fcsp_search(['query' => 'candles']); // no provider given

        self::assertSame('openverse', $result['provider']);
        self::assertSame('openverse', $result['results'][0]['provider']);
    }

    public function testUnknownProviderReturnsErrorWithoutHttp(): void
    {
        $result = \fcsp_search(['query' => 'candles', 'provider' => 'nope']);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('fcsp_bad_provider', $result->get_error_code());
        self::assertSame([], \fcsp_test_requests());
    }

    public function testUnavailableProviderIsRejected(): void
    {
        // Register a provider that exists but reports itself unconfigured.
        add_filter('fcsp_providers', static function (array $providers): array {
            $providers['demo'] = ['label' => 'Demo', 'search' => '__return_false', 'available' => false];
            return $providers;
        });

        $result = \fcsp_search(['query' => 'candles', 'provider' => 'demo']);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('fcsp_provider_unavailable', $result->get_error_code());
    }
}
