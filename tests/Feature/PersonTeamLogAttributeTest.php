<?php

namespace Tests\Feature;

use App\Models\PersonTeamLog;
use Tests\TestCase;

class PersonTeamLogAttributeTest extends TestCase
{
    /**
     * The left_on setter parses a date string into a Carbon value.
     */
    public function test_left_on_setter_parses_date_string(): void
    {
        $log = new PersonTeamLog(['left_on' => '2024-08-30']);

        $this->assertSame('2024-08-30', $log->left_on->format('Y-m-d'));
    }

    /**
     * The left_on setter stores null when given an empty value.
     */
    public function test_left_on_setter_nulls_empty_value(): void
    {
        $log = new PersonTeamLog(['left_on' => '']);

        $this->assertNull($log->left_on);
    }

    /**
     * The left_on setter stores null when given null.
     */
    public function test_left_on_setter_nulls_null_value(): void
    {
        $log = new PersonTeamLog(['left_on' => null]);

        $this->assertNull($log->left_on);
    }
}
