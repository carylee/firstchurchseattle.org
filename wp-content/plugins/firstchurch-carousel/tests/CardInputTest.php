<?php

declare(strict_types=1);

namespace FirstChurch\Carousel\Tests;

use PHPUnit\Framework\TestCase;

/**
 * fccar_sanitize_card_input(): the single validation gate for a standing card's
 * fields, shared by the classic metabox save and the Curate drawer's REST save.
 * Pure: raw assoc in, clean assoc out.
 */
final class CardInputTest extends TestCase
{
    public function test_defaults_layout_to_info_when_missing(): void
    {
        $c = fccar_sanitize_card_input([]);
        $this->assertSame('info', $c['layout']);
    }

    public function test_rejects_unknown_layout(): void
    {
        $c = fccar_sanitize_card_input(['layout' => 'banner']);
        $this->assertSame('info', $c['layout']);
    }

    public function test_keeps_valid_layout(): void
    {
        $c = fccar_sanitize_card_input(['layout' => 'qr_callout']);
        $this->assertSame('qr_callout', $c['layout']);
    }

    public function test_sanitizes_title_as_single_line(): void
    {
        $c = fccar_sanitize_card_input(['title' => "  Hearing  <b>Devices</b>  "]);
        $this->assertSame('Hearing Devices', $c['title']);
    }

    public function test_preserves_newlines_in_body(): void
    {
        $c = fccar_sanitize_card_input(['body' => "- one\n- two"]);
        $this->assertSame("- one\n- two", $c['body']);
    }

    public function test_drops_dangerous_qr_url(): void
    {
        $this->assertSame('', fccar_sanitize_card_input(['qr_url' => 'javascript:alert(1)'])['qr_url']);
        $this->assertSame('https://x/give', fccar_sanitize_card_input(['qr_url' => 'https://x/give'])['qr_url']);
    }

    public function test_validates_bg_color_hex(): void
    {
        $this->assertSame('#7FA888', fccar_sanitize_card_input(['bg_color' => '#7FA888'])['bg_color']);
        $this->assertSame('', fccar_sanitize_card_input(['bg_color' => 'red'])['bg_color']);
    }

    public function test_coerces_preservice_bool(): void
    {
        $this->assertTrue(fccar_sanitize_card_input(['preservice' => '1'])['preservice']);
        $this->assertTrue(fccar_sanitize_card_input(['preservice' => true])['preservice']);
        $this->assertFalse(fccar_sanitize_card_input([])['preservice']);
        $this->assertFalse(fccar_sanitize_card_input(['preservice' => '0'])['preservice']);
        $this->assertFalse(fccar_sanitize_card_input(['preservice' => ''])['preservice']);
    }

    public function test_image_id_is_absint(): void
    {
        $this->assertSame(42, fccar_sanitize_card_input(['image_id' => '42'])['image_id']);
        $this->assertSame(0, fccar_sanitize_card_input([])['image_id']);
        $this->assertSame(7, fccar_sanitize_card_input(['image_id' => -7])['image_id']);
    }

    public function test_returns_all_keys(): void
    {
        $c = fccar_sanitize_card_input([]);
        foreach (['title', 'layout', 'body', 'prompt', 'details', 'qr_url', 'bg_color', 'preservice', 'image_id'] as $k) {
            $this->assertArrayHasKey($k, $c, "missing key: $k");
        }
    }
}
