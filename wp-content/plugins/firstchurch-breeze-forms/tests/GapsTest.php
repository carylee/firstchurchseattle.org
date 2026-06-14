<?php

declare(strict_types=1);

namespace FirstChurch\BreezeForms\Tests;

use FirstChurch\BreezeForms\Gaps;
use PHPUnit\Framework\TestCase;

/**
 * Normalizing the AI's per-field "gaps" (things it wasn't sure about) before
 * they're stored on the intake item — so the Comms Desk can render a clean,
 * trustworthy checklist of what to double-check.
 */
final class GapsTest extends TestCase
{
    public function test_non_arrays_become_empty(): void
    {
        $this->assertSame([], Gaps::clean(null));
        $this->assertSame([], Gaps::clean('what time?'));
        $this->assertSame([], Gaps::clean(42));
    }

    public function test_keeps_only_entries_with_a_question(): void
    {
        $out = Gaps::clean([
            ['field' => 'venue', 'question' => 'Main lot or 8th Ave lot?'],
            ['question' => 'What time does it start?'], // field optional
            ['field' => 'cost', 'question' => '   '],   // blank question dropped
            ['field' => 'nope'],                         // no question dropped
            'garbage',                                   // non-array entry dropped
        ]);

        $this->assertSame(
            [
                ['field' => 'venue', 'question' => 'Main lot or 8th Ave lot?'],
                ['field' => '', 'question' => 'What time does it start?'],
            ],
            $out
        );
    }

    public function test_trims_whitespace(): void
    {
        $out = Gaps::clean([['field' => '  venue  ', 'question' => '  which lot?  ']]);
        $this->assertSame([['field' => 'venue', 'question' => 'which lot?']], $out);
    }

    public function test_roundtrips_through_json(): void
    {
        $clean = Gaps::clean([['field' => 'time', 'question' => 'start time?']]);
        $this->assertSame($clean, Gaps::clean(json_decode(json_encode($clean), true)));
    }
}
