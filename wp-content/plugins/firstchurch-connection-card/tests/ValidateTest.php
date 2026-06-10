<?php

declare(strict_types=1);

namespace FirstChurch\ConnectionCard\Tests;

use PHPUnit\Framework\TestCase;

final class ValidateTest extends TestCase
{
    private function valid(): array
    {
        return [
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
            'email'      => 'jane@example.com',
            'attended'   => 'in-person',
            'i_am_a'     => 'regular',
        ];
    }

    public function test_a_complete_submission_has_no_errors(): void
    {
        $this->assertSame([], fcc_validate($this->valid(), fcc_options()));
    }

    public function test_missing_name_is_reported(): void
    {
        $p = $this->valid();
        $p['last_name'] = '   ';
        $errors = fcc_validate($p, fcc_options());
        $this->assertContains('Please provide your first and last name.', $errors);
    }

    public function test_invalid_email_is_reported(): void
    {
        $p = $this->valid();
        $p['email'] = 'not-an-email';
        $errors = fcc_validate($p, fcc_options());
        $this->assertContains('Please provide a valid email address.', $errors);
    }

    public function test_bad_attended_and_i_am_a_are_reported(): void
    {
        $p = $this->valid();
        $p['attended'] = 'maybe';
        $p['i_am_a']   = 'tourist';
        $errors = fcc_validate($p, fcc_options());
        $this->assertContains('Please choose Online or In-person.', $errors);
        $this->assertContains('Please choose how you relate to First Church.', $errors);
    }
}
