<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms\Tests;

use FirstChurch\BreezeForms\Url;
use PHPUnit\Framework\TestCase;

final class UrlTest extends TestCase
{
    // Cycle 1
    public function test_builds_canonical_url_from_a_valid_slug(): void
    {
        $this->assertSame(
            'https://firstchurchseattle.breezechms.com/form/abc123',
            Url::for_slug('abc123')
        );
    }

    // Cycle 2 — real slugs seen in the catalog
    public function test_accepts_real_catalog_slugs(): void
    {
        $this->assertSame(
            'https://firstchurchseattle.breezechms.com/form/603d6c56',
            Url::for_slug('603d6c56')
        );
        $this->assertSame(
            'https://firstchurchseattle.breezechms.com/form/603d6c',
            Url::for_slug('603d6c')
        );
    }

    // Cycle 3 — empty / whitespace-only
    public function test_rejects_empty_or_blank_slug(): void
    {
        $this->assertNull(Url::for_slug(''));
        $this->assertNull(Url::for_slug('   '));
    }

    // Cycle 4 — path / scheme injection
    public function test_rejects_slugs_with_path_or_scheme_characters(): void
    {
        $this->assertNull(Url::for_slug('../../evil'));
        $this->assertNull(Url::for_slug('a/b'));
        $this->assertNull(Url::for_slug('javascript:alert(1)'));
        $this->assertNull(Url::for_slug('has space'));
        $this->assertNull(Url::for_slug('dot.dot'));
        $this->assertNull(Url::for_slug('quote"out'));
    }

    // Cycle 5 — surrounding whitespace is trimmed before validation
    public function test_trims_surrounding_whitespace(): void
    {
        $this->assertSame(
            'https://firstchurchseattle.breezechms.com/form/abc123',
            Url::for_slug('  abc123  ')
        );
    }
}
