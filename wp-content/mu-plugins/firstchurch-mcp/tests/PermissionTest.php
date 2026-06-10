<?php
/**
 * Tier 2 — permission gating (security-critical).
 *
 * Exercises the abilities' permission_callbacks (status-gated reads, capability
 * requirements) and the map_meta_cap closure that scopes the mcp_editor role to
 * the managed post types. A regression here would silently widen what an
 * app-password agent can read or write.
 *
 * @package FirstChurch\Mcp\Tests
 */

declare(strict_types=1);

namespace FirstChurch\Mcp\Tests;

use PHPUnit\Framework\TestCase;
use WP_Error;

final class PermissionTest extends TestCase
{
    protected function setUp(): void
    {
        fcmcp_test_reset();
        fcmcp_test_boot_abilities();
    }

    /** @return callable */
    private function permissionCallback(string $ability): callable
    {
        $abilities = fcmcp_test_boot_abilities();
        $this->assertArrayHasKey($ability, $abilities, "Unknown ability $ability.");
        return $abilities[$ability]['permission_callback'];
    }

    /* ----------------------- status-gated reads ------------------------- */

    public function testSearchEventsAllowsPublishedForReaders(): void
    {
        fcmcp_test_set_caps(array('read'));
        $cb = $this->permissionCallback('firstchurch/search-events');
        $this->assertTrue($cb(array('status' => 'publish')));
    }

    public function testSearchEventsBlocksDraftsWithoutEditCap(): void
    {
        fcmcp_test_set_caps(array('read'));
        $cb = $this->permissionCallback('firstchurch/search-events');
        $this->assertInstanceOf(WP_Error::class, $cb(array('status' => 'draft')));
    }

    public function testSearchEventsAllowsDraftsWithEditCap(): void
    {
        fcmcp_test_set_caps(array('read', 'edit_posts'));
        $cb = $this->permissionCallback('firstchurch/search-events');
        $this->assertTrue($cb(array('status' => 'draft')));
    }

    public function testSearchEventsDeniesWithoutReadCap(): void
    {
        fcmcp_test_set_caps(array());
        $cb = $this->permissionCallback('firstchurch/search-events');
        $this->assertFalse($cb(array('status' => 'publish')));
    }

    public function testListAnnouncementsBlocksDraftsWithoutEditCap(): void
    {
        fcmcp_test_set_caps(array('read'));
        $cb = $this->permissionCallback('firstchurch/list-announcements');
        $this->assertInstanceOf(WP_Error::class, $cb(array('status' => 'pending')));
    }

    public function testSearchPagesBlocksDraftsWithoutEditPagesCap(): void
    {
        fcmcp_test_set_caps(array('read'));
        $cb = $this->permissionCallback('firstchurch/search-pages');
        $this->assertInstanceOf(WP_Error::class, $cb(array('status' => 'draft')));

        fcmcp_test_set_caps(array('read', 'edit_pages'));
        $this->assertTrue($cb(array('status' => 'draft')));
    }

    /* ----------------------- write capability gates --------------------- */

    public function testCreateEventRequiresEditPosts(): void
    {
        $cb = $this->permissionCallback('firstchurch/create-event');
        fcmcp_test_set_caps(array());
        $this->assertFalse((bool) $cb(array()));
        fcmcp_test_set_caps(array('edit_posts'));
        $this->assertTrue((bool) $cb(array()));
    }

    public function testCreateRedirectRequiresManageRedirectsCap(): void
    {
        $cb = $this->permissionCallback('firstchurch/create-redirect');
        fcmcp_test_set_caps(array('edit_posts'));
        $this->assertFalse((bool) $cb(array()));
        fcmcp_test_set_caps(array('fcmcp_manage_redirects'));
        $this->assertTrue((bool) $cb(array()));
    }

    public function testSearchMediaRequiresUploadFiles(): void
    {
        $cb = $this->permissionCallback('firstchurch/search-media');
        fcmcp_test_set_caps(array('read'));
        $this->assertFalse((bool) $cb(array()));
        fcmcp_test_set_caps(array('upload_files'));
        $this->assertTrue((bool) $cb(array()));
    }

    /* --------------------- map_meta_cap role scoping -------------------- */

    private function mapMetaCap(): callable
    {
        $cbs = fcmcp_test_hook_callbacks('map_meta_cap');
        $this->assertNotEmpty($cbs, 'map_meta_cap filter is not registered.');
        return $cbs[0];
    }

    public function testMapMetaCapIgnoresNonGatedCaps(): void
    {
        $cb = $this->mapMetaCap();
        fcmcp_test_set_user(1, array('mcp_editor'));
        $this->assertSame(array('manage_options'), $cb(array('manage_options'), 'manage_options', 1, array(5)));
    }

    public function testMapMetaCapLeavesNonEditorRolesUntouched(): void
    {
        $cb = $this->mapMetaCap();
        fcmcp_test_set_user(1, array('editor'));
        fcmcp_test_add_post(array('ID' => 5, 'post_type' => 'attachment'));
        // A normal editor passes through unchanged even on an unmanaged type.
        $this->assertSame(array('edit_post'), $cb(array('edit_post'), 'edit_post', 1, array(5)));
    }

    public function testMapMetaCapAllowsEditorOnManagedType(): void
    {
        $cb = $this->mapMetaCap();
        fcmcp_test_set_user(1, array('mcp_editor'));
        fcmcp_test_add_post(array('ID' => 5, 'post_type' => 'ctc_event'));
        $this->assertSame(array('edit_post'), $cb(array('edit_post'), 'edit_post', 1, array(5)));
    }

    public function testMapMetaCapBlocksEditorOnUnmanagedType(): void
    {
        $cb = $this->mapMetaCap();
        fcmcp_test_set_user(1, array('mcp_editor'));
        fcmcp_test_add_post(array('ID' => 6, 'post_type' => 'attachment'));
        $this->assertSame(array('do_not_allow'), $cb(array('edit_post'), 'edit_post', 1, array(6)));
    }

    public function testMapMetaCapWithoutPostIdIsUntouched(): void
    {
        $cb = $this->mapMetaCap();
        fcmcp_test_set_user(1, array('mcp_editor'));
        // No post id in args (e.g. a create capability check) → not narrowed.
        $this->assertSame(array('edit_posts'), $cb(array('edit_posts'), 'edit_post', 1, array()));
    }
}
