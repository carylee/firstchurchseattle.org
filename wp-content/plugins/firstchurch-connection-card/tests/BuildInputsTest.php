<?php

declare(strict_types=1);

namespace FirstChurch\ConnectionCard\Tests;

use PHPUnit\Framework\TestCase;

final class BuildInputsTest extends TestCase
{
    /** @return array<int,array<string,mixed>> */
    private function build(array $params): array
    {
        $opts = fcc_options();
        return fcc_build_inputs(
            $params,
            'Jane',
            'Doe',
            'jane@example.com',
            'in-person',
            'regular',
            $opts
        );
    }

    /** Index the inputs by field_id for easy assertions. */
    private function byField(array $inputs): array
    {
        $out = [];
        foreach ($inputs as $i) {
            $out[$i['field_id']][] = $i;
        }
        return $out;
    }

    public function test_minimal_submission_emits_only_the_required_block(): void
    {
        $inputs = $this->build([]);
        $ids = array_column($inputs, 'field_id');

        // attended, name, email, i_am_a — and nothing optional.
        $this->assertSame(
            [FCC_F_ATTENDED, FCC_F_NAME, FCC_F_EMAIL, FCC_F_I_AM_A],
            $ids
        );
    }

    public function test_attended_and_i_am_a_map_through_option_ids(): void
    {
        $by = $this->byField($this->build([]));
        $this->assertSame('317', $by[FCC_F_ATTENDED][0]['response']); // in-person
        $this->assertSame('243', $by[FCC_F_I_AM_A][0]['response']);   // regular
    }

    public function test_name_details_mirror_the_last_name_fallthrough(): void
    {
        $by = $this->byField($this->build([]));
        $details = $by[FCC_F_NAME][0]['details'];
        $this->assertSame('Jane', $details['first_name']);
        $this->assertSame('Doe', $details['last_name']);
        $this->assertSame('Doe', $details['value']);
        $this->assertSame('last_name', $details['part']);
    }

    public function test_newsletter_and_change_of_info_only_when_truthy(): void
    {
        $with = $this->byField($this->build(['newsletter' => true, 'change_of_info' => true]));
        $this->assertSame('239', $with[FCC_F_NEWSLETTER][0]['response']);
        $this->assertSame('240', $with[FCC_F_CHANGE_INFO][0]['response']);

        $without = $this->byField($this->build(['newsletter' => false, 'change_of_info' => '']));
        $this->assertArrayNotHasKey(FCC_F_NEWSLETTER, $without);
        $this->assertArrayNotHasKey(FCC_F_CHANGE_INFO, $without);
    }

    public function test_phone_included_and_sanitized_when_present(): void
    {
        $by = $this->byField($this->build(['phone' => '  206-555-0100 ']));
        $this->assertSame('206-555-0100', $by[FCC_F_PHONE][0]['response']);

        $blank = $this->byField($this->build(['phone' => '   ']));
        $this->assertArrayNotHasKey(FCC_F_PHONE, $blank);
    }

    public function test_address_drops_blank_parts_and_is_omitted_when_all_blank(): void
    {
        $by = $this->byField($this->build([
            'address' => ['street' => '123 Main St', 'city' => '', 'state' => 'WA', 'zip' => ''],
        ]));
        $details = $by[FCC_F_ADDRESS][0]['details'];
        $this->assertSame(['street_address' => '123 Main St', 'state' => 'WA'], $details);

        $allBlank = $this->byField($this->build([
            'address' => ['street' => '', 'city' => '', 'state' => '', 'zip' => ''],
        ]));
        $this->assertArrayNotHasKey(FCC_F_ADDRESS, $allBlank);
    }

    public function test_learn_more_keeps_whitelisted_ids_and_drops_unknown(): void
    {
        $by = $this->byField($this->build(['learn_more' => ['245', '999', '863']]));
        $responses = array_column($by[FCC_F_LEARN_MORE], 'response');
        $this->assertSame(['245', '863'], $responses);
    }

    public function test_pastor_contact_keeps_whitelisted_ids_and_drops_unknown(): void
    {
        $by = $this->byField($this->build(['pastor_contact' => ['254', '000', '255']]));
        $responses = array_column($by[FCC_F_PASTOR], 'response');
        $this->assertSame(['254', '255'], $responses);
    }

    public function test_heard_from_and_comments_included_when_present(): void
    {
        $by = $this->byField($this->build([
            'heard_from' => 'A friend invited me',
            'comments'   => 'Looking forward to it',
        ]));
        $this->assertSame('A friend invited me', $by[FCC_F_HEARD_FROM][0]['response']);
        // Comment alone stays unlabeled — byte-identical to the pre-prayer field.
        $this->assertSame('Looking forward to it', $by[FCC_F_COMMENTS][0]['response']);
    }

    public function test_prayer_request_alone_is_labeled(): void
    {
        $by = $this->byField($this->build(['prayer_request' => 'Please pray for my mother']));
        $this->assertSame("Prayer request:\nPlease pray for my mother", $by[FCC_F_COMMENTS][0]['response']);
    }

    public function test_prayer_and_comment_merge_into_one_labeled_block(): void
    {
        $by = $this->byField($this->build([
            'prayer_request' => 'Healing for a friend',
            'comments'       => 'See you Sunday',
        ]));
        $this->assertSame(
            "Prayer request:\nHealing for a friend\n\nComments:\nSee you Sunday",
            $by[FCC_F_COMMENTS][0]['response']
        );
    }

    public function test_blank_prayer_and_comment_emit_no_comments_field(): void
    {
        $by = $this->byField($this->build(['prayer_request' => '   ', 'comments' => '']));
        $this->assertArrayNotHasKey(FCC_F_COMMENTS, $by);
    }
}
