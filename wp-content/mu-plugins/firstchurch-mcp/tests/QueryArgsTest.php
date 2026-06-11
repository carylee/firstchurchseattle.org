<?php
/**
 * Tier 3 — the WP_Query arg builders extracted from the search abilities.
 *
 * These assert the translation from MCP input (status, date range, taxonomy,
 * free text, ordering) into WP_Query args, without standing up WP_Query itself.
 *
 * @package FirstChurch\Mcp\Tests
 */

declare(strict_types=1);

namespace FirstChurch\Mcp\Tests;

use PHPUnit\Framework\TestCase;

final class QueryArgsTest extends TestCase
{
    protected function setUp(): void
    {
        fcmcp_test_reset();
    }

    /* ------------------------------- events ------------------------------ */

    public function testEventDefaultsToPublishedFromToday(): void
    {
        $args = fcmcp_build_event_query_args(array());
        $this->assertSame('ctc_event', $args['post_type']);
        $this->assertSame('publish', $args['post_status']);
        $this->assertSame(20, $args['posts_per_page']);
        $this->assertSame(array('start' => 'ASC'), $args['orderby']);
        $this->assertSame('_ctc_event_start_date', $args['meta_query'][0]['key']);
        $this->assertSame('>=', $args['meta_query'][0]['compare']);
        $this->assertSame(gmdate('Y-m-d'), $args['meta_query'][0]['value']);
        $this->assertArrayNotHasKey('s', $args);
        $this->assertArrayNotHasKey('tax_query', $args);
    }

    public function testEventFiltersAndStatusAnyExpands(): void
    {
        $args = fcmcp_build_event_query_args(array(
            'from_date' => '2026-01-01',
            'to_date'   => '2026-03-31',
            'category'  => 'Youth Group',
            'search'    => '  potluck ',
            'status'    => 'any',
            'order'     => 'desc',
            'limit'     => 5,
        ));
        $this->assertSame(array('publish', 'draft', 'pending', 'future'), $args['post_status']);
        $this->assertSame(5, $args['posts_per_page']);
        $this->assertSame(array('start' => 'DESC'), $args['orderby']);
        $this->assertSame('potluck', $args['s']);
        $this->assertSame('youth-group', $args['tax_query'][0]['terms']);
        // Lower bound (>= from_date) and upper bound (<= to_date), plus the
        // named 'start' element WP_Query uses for meta-value ordering.
        $this->assertSame('2026-01-01', $args['meta_query'][0]['value']);
        $this->assertSame('<=', $args['meta_query'][1]['compare']);
        $this->assertSame('2026-03-31', $args['meta_query'][1]['value']);
        $this->assertSame('_ctc_event_start_date', $args['meta_query']['start']['key']);
    }

    public function testEventLimitIsClamped(): void
    {
        $this->assertSame(100, fcmcp_build_event_query_args(array('limit' => 9999))['posts_per_page']);
        $this->assertSame(1, fcmcp_build_event_query_args(array('limit' => 0))['posts_per_page']);
    }

    /* ------------------------------- posts ------------------------------- */

    public function testPostCategoryAndSinceDate(): void
    {
        $args = fcmcp_build_post_query_args(array('category' => 'News Updates', 'since_date' => '2026-01-01'));
        $this->assertSame('post', $args['post_type']);
        $this->assertSame('news-updates', $args['category_name']);
        $this->assertSame('2026-01-01', $args['date_query'][0]['after']);
        $this->assertTrue($args['date_query'][0]['inclusive']);
    }

    public function testPostIgnoresInvalidSinceDate(): void
    {
        $args = fcmcp_build_post_query_args(array('since_date' => 'whenever'));
        $this->assertArrayNotHasKey('date_query', $args);
    }

    /* ------------------------------- pages ------------------------------- */

    public function testPageParentAndOrdering(): void
    {
        $args = fcmcp_build_page_query_args(array('parent' => '42', 'search' => 'About'));
        $this->assertSame('page', $args['post_type']);
        $this->assertSame('menu_order title', $args['orderby']);
        $this->assertSame(42, $args['post_parent']);
        $this->assertSame('About', $args['s']);
    }

    /* --------------------------- announcements --------------------------- */

    public function testAnnouncementUsesAnnouncementsCategory(): void
    {
        $cat  = fcmcp_test_add_term('category', 'announcements', 'Announcements');
        $args = fcmcp_build_announcement_query_args(array('status' => 'any'));
        $this->assertSame('post', $args['post_type']);
        $this->assertSame($cat->term_id, $args['cat']);
        $this->assertSame(array('publish', 'draft', 'pending', 'future'), $args['post_status']);
    }
}
