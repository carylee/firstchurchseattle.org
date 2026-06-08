<?php

declare(strict_types=1);

namespace FirstChurch\StockPhotos\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Covers front-end attribution: the linked credit HTML and the [stock_credit]
 * shortcode, built from the provenance meta.
 */
final class CreditsTest extends TestCase
{
    protected function setUp(): void
    {
        \fcsp_test_reset();
    }

    public function testCreditHtmlLinksCreatorSourceAndLicense(): void
    {
        \fcsp_store_provenance(7, [
            'provider'    => 'openverse',
            'source'      => 'flickr',
            'creator'     => 'Jane Doe',
            'creator_url' => 'https://flickr.com/people/jane',
            'foreign_url' => 'https://flickr.com/photo/1',
            'license'     => 'by-sa',
            'license_url' => 'https://creativecommons.org/licenses/by-sa/2.0/',
        ]);

        $html = \fcsp_attachment_credit_html(7);

        self::assertStringContainsString('Photo by', $html);
        self::assertStringContainsString('Jane Doe', $html);
        self::assertStringContainsString('https://flickr.com/people/jane', $html);
        self::assertStringContainsString('on <a', $html);     // source link
        self::assertStringContainsString('Flickr', $html);    // ucwords of source
        self::assertStringContainsString('BY-SA', $html);     // CC code uppercased
        self::assertStringContainsString('creativecommons.org', $html);
    }

    public function testProseLicenseNotUppercased(): void
    {
        \fcsp_store_provenance(8, [
            'provider'    => 'pexels',
            'source'      => 'pexels',
            'creator'     => 'Sam Lee',
            'license'     => 'Pexels License',
            'license_url' => 'https://www.pexels.com/license/',
        ]);

        $html = \fcsp_attachment_credit_html(8);

        self::assertStringContainsString('Pexels License', $html);
        self::assertStringNotContainsString('PEXELS LICENSE', $html);
    }

    public function testCreditHtmlEmptyWithoutProvenance(): void
    {
        self::assertSame('', \fcsp_attachment_credit_html(999));
    }

    public function testCreditFallsBackToSourceWhenNoCreator(): void
    {
        \fcsp_store_provenance(9, [
            'provider'    => 'unsplash',
            'source'      => 'unsplash',
            'foreign_url' => 'https://unsplash.com/photos/abc',
        ]);

        $html = \fcsp_attachment_credit_html(9);

        self::assertStringContainsString('Image via', $html);
        self::assertStringContainsString('Unsplash', $html);
    }

    public function testShortcodeWrapsCreditForGivenId(): void
    {
        \fcsp_store_provenance(7, ['provider' => 'pexels', 'source' => 'pexels', 'creator' => 'Sam Lee']);

        $out = \fcsp_stock_credit_shortcode(['id' => '7']);

        self::assertStringStartsWith('<span class="fcsp-credit">', $out);
        self::assertStringContainsString('Sam Lee', $out);
    }

    public function testShortcodeEmptyForUntrackedId(): void
    {
        self::assertSame('', \fcsp_stock_credit_shortcode(['id' => '999']));
    }
}
