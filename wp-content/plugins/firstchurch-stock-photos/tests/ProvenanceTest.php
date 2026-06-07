<?php

declare(strict_types=1);

namespace FirstChurch\StockPhotos\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Covers provenance storage, the credit-line helper, and the Instant Images
 * after-upload bridge that feeds the same provenance meta.
 */
final class ProvenanceTest extends TestCase
{
    protected function setUp(): void
    {
        \fcsp_test_reset();
    }

    public function testStoreProvenanceWritesNonEmptyFields(): void
    {
        \fcsp_store_provenance(7, [
            'source'      => 'flickr',
            'creator'     => 'Jane Doe',
            'license'     => 'by-sa',
            'attribution' => 'by Jane Doe',
            'foreign_url' => 'https://flickr.com/photo/1',
        ]);

        self::assertSame('flickr', get_post_meta(7, FCSP_META_SOURCE, true));
        self::assertSame('Jane Doe', get_post_meta(7, FCSP_META_CREATOR, true));
        self::assertSame('by-sa', get_post_meta(7, FCSP_META_LICENSE, true));
        self::assertSame('https://flickr.com/photo/1', get_post_meta(7, FCSP_META_FOREIGN_URL, true));
    }

    public function testStoreProvenanceSkipsEmptyValues(): void
    {
        \fcsp_store_provenance(7, ['source' => 'flickr', 'creator' => '']);

        self::assertSame('flickr', get_post_meta(7, FCSP_META_SOURCE, true));
        self::assertSame('', get_post_meta(7, FCSP_META_CREATOR, true));
    }

    public function testCreditPrefersAttribution(): void
    {
        \fcsp_store_provenance(7, ['creator' => 'Jane', 'license' => 'by-sa', 'attribution' => 'Photo by Jane']);

        self::assertSame('Photo by Jane', \fcsp_attachment_credit(7));
    }

    public function testCreditFallsBackToCreatorAndLicense(): void
    {
        \fcsp_store_provenance(8, ['creator' => 'Jane', 'license' => 'by-sa']);

        self::assertSame('Jane · BY-SA', \fcsp_attachment_credit(8));
    }

    public function testCreditEmptyForUntrackedAttachment(): void
    {
        self::assertSame('', \fcsp_attachment_credit(999));
    }

    public function testInstantImagesBridgeStampsProvenance(): void
    {
        $callbacks = \fcsp_test_hook_callbacks('instant_images_after_upload');
        self::assertNotEmpty($callbacks, 'The bridge should register an after-upload callback.');

        $callbacks[0]([
            'attachment_id' => 9,
            'provider'      => 'unsplash',
            'original_url'  => 'https://images.unsplash.com/x.jpg',
            'caption'       => 'Photo by Bob on Unsplash',
        ]);

        self::assertSame('unsplash', get_post_meta(9, FCSP_META_SOURCE, true));
        self::assertSame('https://images.unsplash.com/x.jpg', get_post_meta(9, FCSP_META_FOREIGN_URL, true));
        self::assertSame('Photo by Bob on Unsplash', get_post_meta(9, FCSP_META_ATTRIBUTION, true));
    }

    public function testInstantImagesBridgeIgnoresMissingAttachmentId(): void
    {
        $callbacks = \fcsp_test_hook_callbacks('instant_images_after_upload');
        $callbacks[0](['provider' => 'unsplash']); // no attachment_id

        self::assertSame([], $GLOBALS['fcsp_test_meta'], 'Nothing should be written without an attachment id.');
    }
}
