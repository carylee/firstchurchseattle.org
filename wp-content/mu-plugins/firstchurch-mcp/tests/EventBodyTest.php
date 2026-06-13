<?php
/**
 * Tier 2 — get-event exposes the event body verbatim.
 *
 * fcmcp_event_to_array($post, true) must return the raw post_content as
 * `description` (the same key create/update take) and the manual excerpt, with
 * no transforms, so a get-event → update-event of an unchanged body is a true
 * no-op. List serialization ($full=false) must omit both to stay lean.
 *
 * @package FirstChurch\Mcp\Tests
 */

declare(strict_types=1);

namespace FirstChurch\Mcp\Tests;

use PHPUnit\Framework\TestCase;
use WP_Post;

final class EventBodyTest extends TestCase
{
    protected function setUp(): void
    {
        fcmcp_test_reset();
    }

    public function testListFormOmitsBody(): void
    {
        $post = fcmcp_test_add_post(array('post_type' => 'fce_event', 'post_content' => 'body', 'post_excerpt' => 'x'));
        $out  = fcmcp_event_to_array($post);
        $this->assertArrayNotHasKey('description', $out);
        $this->assertArrayNotHasKey('excerpt', $out);
    }

    public function testFullFormIncludesRawBodyAndExcerpt(): void
    {
        $body = "<!-- wp:paragraph --><p>We invite you to an evening with the &amp; \u{201c}McKibben\u{201d}\u{2026}</p><!-- /wp:paragraph -->";
        $post = fcmcp_test_add_post(array(
            'post_type'    => 'fce_event',
            'post_content' => $body,
            'post_excerpt' => 'A manual excerpt',
        ));
        $out = fcmcp_event_to_array($post, true);

        // Verbatim: block markup, entities, and curly quotes pass through untouched.
        $this->assertSame($body, $out['description']);
        $this->assertSame('A manual excerpt', $out['excerpt']);
    }

    public function testEmptyBodyIsEmptyString(): void
    {
        $post = fcmcp_test_add_post(array('post_type' => 'fce_event'));
        $out  = fcmcp_event_to_array($post, true);
        $this->assertSame('', $out['description']);
        $this->assertSame('', $out['excerpt']);
    }

    /** The no-op contract: what get-event returns is exactly what was stored. */
    public function testDescriptionRoundTripsLosslessly(): void
    {
        $body = "Line one\n\n<!-- wp:list --><ul><li>a</li></ul><!-- /wp:list -->";
        $post = fcmcp_test_add_post(array('post_type' => 'fce_event', 'post_content' => $body));
        $this->assertSame($post->post_content, fcmcp_event_to_array($post, true)['description']);
    }
}
