<?php
/**
 * Tier 2/3 — navigation-menu logic seams.
 *
 * Covers the pure translation/validation the menu abilities lean on:
 * fcmcp_build_menu_item_args (MCP input → wp_update_nav_menu_item data, with
 * link-target XOR validation), fcmcp_apply_menu_item_fields (shared field
 * mapping), and fcmcp_menu_item_to_array (read serializer). The WP-coupled
 * orchestration (wp_update_nav_menu_item / wp_get_nav_menu_items) is integration
 * territory and out of scope here.
 *
 * @package FirstChurch\Mcp\Tests
 */

declare(strict_types=1);

namespace FirstChurch\Mcp\Tests;

use PHPUnit\Framework\TestCase;
use WP_Error;

final class MenuTest extends TestCase
{
    /* --------------------- build_menu_item_args: links ------------------ */

    public function testPageLink(): void
    {
        $data = fcmcp_build_menu_item_args(array('page_id' => 42, 'title' => 'About'));
        $this->assertSame('post_type', $data['menu-item-type']);
        $this->assertSame('page', $data['menu-item-object']);
        $this->assertSame(42, $data['menu-item-object-id']);
        $this->assertSame('About', $data['menu-item-title']);
        $this->assertSame('publish', $data['menu-item-status']);
        $this->assertArrayNotHasKey('menu-item-url', $data);
    }

    public function testPostLink(): void
    {
        $data = fcmcp_build_menu_item_args(array('post_id' => 7));
        $this->assertSame('post_type', $data['menu-item-type']);
        $this->assertSame('post', $data['menu-item-object']);
        $this->assertSame(7, $data['menu-item-object-id']);
    }

    public function testCategoryLink(): void
    {
        $data = fcmcp_build_menu_item_args(array('category_id' => 5));
        $this->assertSame('taxonomy', $data['menu-item-type']);
        $this->assertSame('category', $data['menu-item-object']);
        $this->assertSame(5, $data['menu-item-object-id']);
    }

    public function testCustomLink(): void
    {
        $data = fcmcp_build_menu_item_args(array('url' => 'https://example.org/give', 'title' => 'Give'));
        $this->assertSame('custom', $data['menu-item-type']);
        $this->assertSame('https://example.org/give', $data['menu-item-url']);
        $this->assertSame('Give', $data['menu-item-title']);
        $this->assertArrayNotHasKey('menu-item-object-id', $data);
    }

    /* ------------------ build_menu_item_args: validation ---------------- */

    public function testCustomLinkRequiresTitle(): void
    {
        $err = fcmcp_build_menu_item_args(array('url' => 'https://example.org/give'));
        $this->assertInstanceOf(WP_Error::class, $err);
        $this->assertSame('missing_title', $err->get_error_code());
    }

    public function testNoLinkTargetIsRejected(): void
    {
        $err = fcmcp_build_menu_item_args(array('title' => 'Orphan'));
        $this->assertInstanceOf(WP_Error::class, $err);
        $this->assertSame('bad_link_target', $err->get_error_code());
    }

    public function testMultipleLinkTargetsAreRejected(): void
    {
        $err = fcmcp_build_menu_item_args(array('page_id' => 1, 'url' => 'https://x.test', 'title' => 'x'));
        $this->assertInstanceOf(WP_Error::class, $err);
        $this->assertSame('bad_link_target', $err->get_error_code());
    }

    public function testEmptyStringTargetsDoNotCount(): void
    {
        // An explicit empty url must not be treated as a provided target.
        $err = fcmcp_build_menu_item_args(array('url' => '', 'page_id' => 9));
        $this->assertSame('post_type', $err['menu-item-type']);
        $this->assertSame(9, $err['menu-item-object-id']);
    }

    /* --------------------- build_menu_item_args: fields ----------------- */

    public function testOptionalFieldsAreMapped(): void
    {
        $data = fcmcp_build_menu_item_args(array(
            'page_id'     => 1,
            'parent'      => 9,
            'position'    => 3,
            'target'      => '_blank',
            'description' => 'desc',
            'attr_title'  => 'tip',
        ));
        $this->assertSame(9, $data['menu-item-parent-id']);
        $this->assertSame(3, $data['menu-item-position']);
        $this->assertSame('_blank', $data['menu-item-target']);
        $this->assertSame('desc', $data['menu-item-description']);
        $this->assertSame('tip', $data['menu-item-attr-title']);
    }

    /* ----------------------- apply_menu_item_fields --------------------- */

    public function testApplyFieldsPreservesBaseWhenNothingProvided(): void
    {
        $base = array('menu-item-type' => 'custom', 'menu-item-url' => 'https://x.test', 'menu-item-title' => 'Keep');
        $this->assertSame($base, fcmcp_apply_menu_item_fields($base, array()));
    }

    public function testApplyFieldsNormalizesTarget(): void
    {
        $this->assertSame('_blank', fcmcp_apply_menu_item_fields(array(), array('target' => '_blank'))['menu-item-target']);
        // Any non-_blank value collapses to same-tab.
        $this->assertSame('', fcmcp_apply_menu_item_fields(array(), array('target' => 'self'))['menu-item-target']);
    }

    /* ------------------------- menu_item_to_array ----------------------- */

    public function testMenuItemSerializer(): void
    {
        $item = (object) array(
            'ID'               => 101,
            'title'            => 'About',
            'url'              => 'https://example.org/about',
            'type'             => 'post_type',
            'object'           => 'page',
            'object_id'        => 42,
            'menu_item_parent' => 0,
            'menu_order'       => 2,
            'target'           => '_blank',
        );
        $this->assertSame(
            array(
                'id'        => 101,
                'title'     => 'About',
                'url'       => 'https://example.org/about',
                'type'      => 'post_type',
                'object'    => 'page',
                'object_id' => 42,
                'parent'    => 0,
                'order'     => 2,
                'target'    => '_blank',
            ),
            fcmcp_menu_item_to_array($item)
        );
    }
}
