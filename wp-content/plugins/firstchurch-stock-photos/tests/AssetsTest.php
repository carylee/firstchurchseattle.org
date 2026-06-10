<?php

declare(strict_types=1);

namespace FirstChurch\StockPhotos\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Covers the surface-gating layer that decides WHERE the in-editor picker
 * loads — the heart of "available wherever you insert media". The actual
 * wp.media / block-editor JS is verified manually in DDEV (no JS harness);
 * here we pin the PHP decision + that the cross-surface hooks are wired.
 */
final class AssetsTest extends TestCase
{
    protected function setUp(): void
    {
        \fcsp_test_reset();
    }

    public function testEditorScreensIncludePostAndUpload(): void
    {
        $screens = \fcsp_picker_editor_screens();

        self::assertContains('post', $screens, 'classic + block post editor');
        self::assertContains('upload', $screens, 'media library');
    }

    public function testPickerLoadsOnEditorScreens(): void
    {
        self::assertTrue(\fcsp_should_load_picker_on_screen('post'));
        self::assertTrue(\fcsp_should_load_picker_on_screen('upload'));
    }

    public function testPickerStaysOffUnrelatedScreens(): void
    {
        self::assertFalse(\fcsp_should_load_picker_on_screen('dashboard'));
        self::assertFalse(\fcsp_should_load_picker_on_screen(''));
        self::assertFalse(\fcsp_should_load_picker_on_screen(null));
    }

    public function testEditorScreensAreFilterable(): void
    {
        add_filter('fcsp_picker_editor_screens', static function (array $screens): array {
            $screens[] = 'widgets';
            return $screens;
        });

        self::assertTrue(\fcsp_should_load_picker_on_screen('widgets'));
    }

    public function testCrossSurfaceEnqueueHooksAreRegistered(): void
    {
        // assets.php loads unconditionally (unlike admin.php), so its enqueue
        // wiring is present even though is_admin() is false under test.
        self::assertNotEmpty(
            \fcsp_test_hook_callbacks('admin_enqueue_scripts'),
            'picker must hook admin_enqueue_scripts for the wp.media modal surfaces'
        );
    }
}
