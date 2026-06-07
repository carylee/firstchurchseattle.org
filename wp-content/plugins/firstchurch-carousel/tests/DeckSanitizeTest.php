<?php

declare(strict_types=1);

namespace FirstChurch\Carousel\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * fccar_sanitize_deck_entry(): the gatekeeper for what the Curate screen can
 * persist into the saved deck — a list of {id, title, when, image,
 * preserviceOnly} reference+override entries.
 */
final class DeckSanitizeTest extends TestCase
{
    public function test_rejects_non_array(): void
    {
        $this->assertNull(fccar_sanitize_deck_entry('nope'));
    }

    #[DataProvider('badIds')]
    public function test_rejects_malformed_ids(string $id): void
    {
        $this->assertNull(fccar_sanitize_deck_entry(['id' => $id]));
    }

    public static function badIds(): array
    {
        return [
            'no prefix'      => ['12'],
            'unknown source' => ['sermon-12'],
            'no number'      => ['card-'],
            'trailing junk'  => ['card-12x'],
            'empty'          => [''],
        ];
    }

    #[DataProvider('goodIds')]
    public function test_accepts_valid_ids(string $id): void
    {
        $entry = fccar_sanitize_deck_entry(['id' => $id]);
        $this->assertNotNull($entry);
        $this->assertSame($id, $entry['id']);
    }

    public static function goodIds(): array
    {
        return [['card-1'], ['event-42'], ['announcement-7']];
    }

    public function test_sanitizes_overrides_and_coerces_preservice(): void
    {
        $entry = fccar_sanitize_deck_entry([
            'id'             => 'event-3',
            'title'          => "  Bible\nStudy  ",
            'when'           => 'Sundays at 7pm',
            'image'          => 'https://x/p.jpg',
            'preserviceOnly' => '1',
        ]);

        $this->assertSame('Bible Study', $entry['title']);
        $this->assertSame('Sundays at 7pm', $entry['when']);
        $this->assertSame('https://x/p.jpg', $entry['image']);
        $this->assertTrue($entry['preserviceOnly']);
    }

    public function test_drops_dangerous_image_url(): void
    {
        $entry = fccar_sanitize_deck_entry(['id' => 'card-1', 'image' => 'javascript:alert(1)']);
        $this->assertSame('', $entry['image']);
    }

    public function test_defaults_empty_overrides(): void
    {
        $entry = fccar_sanitize_deck_entry(['id' => 'card-1']);
        $this->assertSame('', $entry['title']);
        $this->assertSame('', $entry['when']);
        $this->assertSame('', $entry['image']);
        $this->assertFalse($entry['preserviceOnly']);
    }
}
