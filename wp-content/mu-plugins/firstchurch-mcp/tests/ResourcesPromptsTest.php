<?php
/**
 * Resources + prompts: the MCP context/workflow layer.
 *
 * Verifies the registered resource/prompt abilities are discoverable by the
 * adapter (public + correctly typed) and that the content/workflow builders
 * produce well-formed output — resources return non-empty content, prompts
 * return the { description, messages[] } shape the adapter normalizes.
 *
 * @package FirstChurch\Mcp\Tests
 */

declare(strict_types=1);

namespace FirstChurch\Mcp\Tests;

use PHPUnit\Framework\TestCase;

final class ResourcesPromptsTest extends TestCase
{
    protected function setUp(): void
    {
        fcmcp_test_reset();
    }

    /** @return array<int,array{0:string,1:array<string,mixed>}> */
    private function abilitiesOfType(string $type): array
    {
        $out = array();
        foreach (fcmcp_test_boot_abilities() as $name => $args) {
            if (($args['meta']['mcp']['type'] ?? 'tool') === $type) {
                $out[] = array($name, $args);
            }
        }
        return $out;
    }

    /* ------------------------------ discovery --------------------------- */

    public function testResourcesAndPromptsAreRegistered(): void
    {
        $this->assertNotEmpty($this->abilitiesOfType('resource'), 'Expected at least one MCP resource.');
        $this->assertNotEmpty($this->abilitiesOfType('prompt'), 'Expected at least one MCP prompt.');
    }

    public function testResourceAndPromptAbilitiesArePublicSoDiscoveryFindsThem(): void
    {
        foreach (array_merge($this->abilitiesOfType('resource'), $this->abilitiesOfType('prompt')) as [$name, $args]) {
            $this->assertTrue(
                ($args['meta']['mcp']['public'] ?? false) === true,
                "$name must be mcp.public for the default server to auto-discover it."
            );
        }
    }

    public function testEveryResourceHasAUniqueUri(): void
    {
        $uris = array();
        foreach ($this->abilitiesOfType('resource') as [$name, $args]) {
            $uris[] = $args['meta']['mcp']['uri'] ?? '';
        }
        $this->assertSame($uris, array_values(array_unique($uris)), 'Resource URIs must be unique.');
    }

    /* ------------------------------ resources --------------------------- */

    public function testContentGuideResourceReturnsMarkdown(): void
    {
        $guide = fcmcp_resource_content_guide();
        $this->assertIsString($guide);
        $this->assertNotEmpty($guide);
        // Mentions the core conventions agents must follow.
        $this->assertStringContainsStringIgnoringCase('announcement', $guide);
        $this->assertStringContainsStringIgnoringCase('draft', $guide);
    }

    public function testTaxonomyVocabularyResourceReflectsLiveTerms(): void
    {
        fcmcp_test_add_term('ctc_event_category', 'youth', 'Youth', 4);

        $data = fcmcp_resource_taxonomies_data();
        $this->assertArrayHasKey('event_categories', $data);
        $this->assertArrayHasKey('post_categories', $data);
        $this->assertSame(
            array('slug' => 'youth', 'name' => 'Youth', 'count' => 4),
            $data['event_categories'][0]
        );
        // Empty taxonomies are present but empty (stable JSON shape).
        $this->assertSame(array(), $data['post_categories']);
    }

    public function testTaxonomyResourceExecuteCallbackEncodesJson(): void
    {
        $cb = fcmcp_test_boot_abilities()['firstchurch/vocabulary']['execute_callback'];
        $json = $cb();
        $this->assertIsString($json);
        $this->assertIsArray(json_decode($json, true), 'resource output must be valid JSON');
    }

    /* ------------------------------- prompts ---------------------------- */

    public function testPromptsReturnWellFormedMessages(): void
    {
        $prompts = array(
            'fcmcp_prompt_review_queue'      => array(),
            'fcmcp_prompt_draft_announcement' => array('topic' => 'Easter breakfast', 'cta_url' => 'https://x.test/rsvp'),
            'fcmcp_prompt_add_event'         => array('details' => 'Game night Friday 7pm in the Fellowship Hall'),
        );
        foreach ($prompts as $fn => $input) {
            $result = $fn($input);
            $this->assertArrayHasKey('description', $result, "$fn missing description");
            $this->assertArrayHasKey('messages', $result, "$fn missing messages");
            $this->assertNotEmpty($result['messages']);
            $msg = $result['messages'][0];
            $this->assertSame('user', $msg['role']);
            $this->assertSame('text', $msg['content']['type']);
            $this->assertNotEmpty($msg['content']['text']);
        }
    }

    public function testDraftAnnouncementPromptWeavesInTheTopicAndCta(): void
    {
        $text = fcmcp_prompt_draft_announcement(array('topic' => 'Easter breakfast', 'cta_url' => 'https://x.test/rsvp'))['messages'][0]['content']['text'];
        $this->assertStringContainsString('Easter breakfast', $text);
        $this->assertStringContainsString('https://x.test/rsvp', $text);
    }

    public function testDraftAnnouncementPromptOmitsCtaWhenAbsent(): void
    {
        $text = fcmcp_prompt_draft_announcement(array('topic' => 'Newcomer lunch'))['messages'][0]['content']['text'];
        $this->assertStringContainsString('Newcomer lunch', $text);
        $this->assertStringNotContainsString('cta_url = ', $text);
    }

    public function testAddEventPromptIncludesTheDetails(): void
    {
        $text = fcmcp_prompt_add_event(array('details' => 'Game night Friday 7pm'))['messages'][0]['content']['text'];
        $this->assertStringContainsString('Game night Friday 7pm', $text);
    }
}
