<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonFka;
use Tests\TestCase;

class PersonFkaAttributeTest extends TestCase
{
    /**
     * The fka setter trims the value and populates the normalized and soundex columns.
     */
    public function test_fka_setter_populates_derived_columns(): void
    {
        $fka = new PersonFka(['fka' => '  Hubcap  ']);

        $attributes = $fka->getAttributes();
        $normalized = Person::normalizeCallsign('Hubcap');

        $this->assertSame('Hubcap', $attributes['fka']);
        $this->assertSame($normalized, $attributes['fka_normalized']);
        $this->assertSame(metaphone(Person::spellOutNumbers($normalized)), $attributes['fka_soundex']);
    }

    /**
     * The fka setter stores a blank string and corresponding derived columns when given an empty value.
     */
    public function test_fka_setter_blanks_empty_value(): void
    {
        $fka = new PersonFka(['fka' => '']);

        $attributes = $fka->getAttributes();
        $normalized = Person::normalizeCallsign('');

        $this->assertSame('', $attributes['fka']);
        $this->assertSame($normalized, $attributes['fka_normalized']);
        $this->assertSame(metaphone(Person::spellOutNumbers($normalized)), $attributes['fka_soundex']);
    }

    /**
     * The fka setter stores a blank string and corresponding derived columns when given null.
     */
    public function test_fka_setter_blanks_null_value(): void
    {
        $fka = new PersonFka(['fka' => null]);

        $attributes = $fka->getAttributes();
        $normalized = Person::normalizeCallsign('');

        $this->assertSame('', $attributes['fka']);
        $this->assertSame($normalized, $attributes['fka_normalized']);
        $this->assertSame(metaphone(Person::spellOutNumbers($normalized)), $attributes['fka_soundex']);
    }
}
