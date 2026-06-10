<?php
/**
 * Tier 2 — fcmcp_resolve_terms: name/slug lookup with create-on-miss.
 *
 * Used by the sermon + post/category writers to turn human-supplied names or
 * slugs into term ids, creating terms that don't yet exist.
 *
 * @package FirstChurch\Mcp\Tests
 */

declare(strict_types=1);

namespace FirstChurch\Mcp\Tests;

use PHPUnit\Framework\TestCase;

final class TermResolveTest extends TestCase
{
    protected function setUp(): void
    {
        fcmcp_test_reset();
    }

    public function testResolvesExistingTermBySlug(): void
    {
        $term = fcmcp_test_add_term('ctc_sermon_speaker', 'jane-doe', 'Jane Doe');
        $ids  = fcmcp_resolve_terms('ctc_sermon_speaker', array('jane-doe'));
        $this->assertSame(array($term->term_id), $ids);
        $this->assertCount(1, $GLOBALS['fcmcp_test']['terms']['ctc_sermon_speaker'], 'no new term created');
    }

    public function testResolvesExistingTermByName(): void
    {
        $term = fcmcp_test_add_term('ctc_sermon_speaker', 'jane-doe', 'Jane Doe');
        $ids  = fcmcp_resolve_terms('ctc_sermon_speaker', array('Jane Doe'));
        $this->assertSame(array($term->term_id), $ids);
    }

    public function testCreatesMissingTerm(): void
    {
        $ids = fcmcp_resolve_terms('ctc_sermon_topic', array('Grace'));
        $this->assertCount(1, $ids);
        $this->assertCount(1, $GLOBALS['fcmcp_test']['terms']['ctc_sermon_topic']);
        // A second resolve of the same value reuses the created term.
        $again = fcmcp_resolve_terms('ctc_sermon_topic', array('Grace'));
        $this->assertSame($ids, $again);
        $this->assertCount(1, $GLOBALS['fcmcp_test']['terms']['ctc_sermon_topic'], 'no duplicate term created');
    }

    public function testSkipsEmptyValuesAndResolvesMixedList(): void
    {
        $jane = fcmcp_test_add_term('ctc_sermon_speaker', 'jane-doe', 'Jane Doe');
        $ids  = fcmcp_resolve_terms('ctc_sermon_speaker', array('Jane Doe', '', '   ', 'New Person'));
        $this->assertCount(2, $ids, 'two non-empty inputs resolve; blanks are skipped');
        $this->assertSame($jane->term_id, $ids[0]);
    }
}
