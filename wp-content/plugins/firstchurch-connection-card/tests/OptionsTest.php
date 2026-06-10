<?php

declare(strict_types=1);

namespace FirstChurch\ConnectionCard\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The option ids and Breeze field ids are the contract with Breeze form 320238.
 * A stray edit here silently breaks submission, so pin the values.
 */
final class OptionsTest extends TestCase
{
    public function test_option_ids_are_pinned(): void
    {
        $opts = fcc_options();
        $this->assertSame(['online' => '316', 'in-person' => '317'], $opts['attended']);
        $this->assertSame(
            ['first-time' => '241', 'second-time' => '242', 'regular' => '243', 'member' => '244'],
            $opts['i_am_a']
        );
        $this->assertSame('239', $opts['newsletter']);
        $this->assertSame('240', $opts['change_info']);
        $this->assertSame(['254', '255'], $opts['pastor']);
    }

    public function test_learn_more_choices_match_their_option_whitelist(): void
    {
        // Every label key must be an accepted learn_more option id, and vice
        // versa — the rendered checkboxes and the server whitelist can't drift.
        // (PHP coerces the numeric-string choice keys to ints, so normalize.)
        $choiceKeys = array_map('strval', array_keys(fcc_learn_more_choices()));
        $whitelist  = fcc_options()['learn_more'];
        sort($choiceKeys);
        sort($whitelist);
        $this->assertSame($whitelist, $choiceKeys);
    }
}
