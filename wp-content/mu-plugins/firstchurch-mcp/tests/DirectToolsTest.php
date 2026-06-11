<?php
/**
 * Tier 1 — the curated direct-tool list and the adapter config filter.
 *
 * FCMCP_DIRECT_TOOLS promotes a subset of abilities to first-class MCP tools via
 * the mcp_adapter_default_server_config filter. These guard that the list names
 * only real abilities and that the filter merges/dedupes correctly.
 *
 * @package FirstChurch\Mcp\Tests
 */

declare(strict_types=1);

namespace FirstChurch\Mcp\Tests;

use PHPUnit\Framework\TestCase;

final class DirectToolsTest extends TestCase
{
    /**
     * Promoted tools registered by SIBLING plugins (not this mu-plugin), so they
     * aren't in this harness's ability set. Guarded by their own plugin's tests;
     * listed here so a typo in a mu-plugin-local promotion still fails below.
     */
    private const EXTERNAL_TOOLS = array(
        'firstchurch/list-enews', 'firstchurch/get-enews', 'firstchurch/create-enews',
        'firstchurch/update-enews', 'firstchurch/set-enews-status', 'firstchurch/preview-enews',
        'firstchurch/list-carousel-cards', 'firstchurch/create-carousel-card', 'firstchurch/update-carousel-card',
        'firstchurch/set-carousel-card-status', 'firstchurch/reorder-carousel-cards',
    );

    public function testEveryDirectToolIsARegisteredAbility(): void
    {
        $abilities = fcmcp_test_boot_abilities();
        foreach (FCMCP_DIRECT_TOOLS as $tool) {
            if (in_array($tool, self::EXTERNAL_TOOLS, true)) {
                continue; // registered by a sibling plugin (e.g. firstchurch-enews)
            }
            $this->assertArrayHasKey(
                $tool,
                $abilities,
                "FCMCP_DIRECT_TOOLS names '$tool' but no such ability is registered."
            );
        }
    }

    public function testExternalPromotedToolsAreInTheDirectList(): void
    {
        // Drift guard: the externally-registered tools we exempt above must still
        // actually be promoted in FCMCP_DIRECT_TOOLS.
        foreach (self::EXTERNAL_TOOLS as $tool) {
            $this->assertContains($tool, FCMCP_DIRECT_TOOLS, "External tool '$tool' is no longer promoted.");
        }
    }

    public function testDirectToolsHasNoDuplicates(): void
    {
        $tools = FCMCP_DIRECT_TOOLS;
        $this->assertSame(array_values(array_unique($tools)), array_values($tools), 'FCMCP_DIRECT_TOOLS contains duplicates.');
    }

    public function testAdapterFilterMergesDirectToolsAndDedupes(): void
    {
        // A pre-existing tool plus one that overlaps FCMCP_DIRECT_TOOLS.
        $existing = FCMCP_DIRECT_TOOLS[0];
        $config   = fcmcp_test_apply_filters(
            'mcp_adapter_default_server_config',
            array('tools' => array('vendor/pre-existing', $existing))
        );

        $this->assertIsArray($config);
        $this->assertArrayHasKey('tools', $config);
        $this->assertContains('vendor/pre-existing', $config['tools'], 'Pre-existing tools must be preserved.');
        foreach (FCMCP_DIRECT_TOOLS as $tool) {
            $this->assertContains($tool, $config['tools'], "Filter dropped direct tool '$tool'.");
        }
        $this->assertSame(
            array_values(array_unique($config['tools'])),
            array_values($config['tools']),
            'Merged tools list must not contain duplicates.'
        );
    }

    public function testAdapterFilterLeavesNonArrayConfigUntouched(): void
    {
        $this->assertNull(fcmcp_test_apply_filters('mcp_adapter_default_server_config', null));
    }
}
