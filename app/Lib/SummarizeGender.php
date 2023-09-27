<?php

namespace App\Lib;

use App\Models\Person;

class SummarizeGender
{
    const NONE = '';
    const FEMALE = 'F';
    const MALE = 'M';
    const NON_BINARY = 'NB';
    const QUEER = 'Q';
    const GENDER_FLUID = 'GF';
    const TRANS_MALE = 'TM';
    const TRANS_FEMALE = 'TF';
    const TWO_SPIRIT = 'TS';


    const PERSON_GENDER = [
        Person::GENDER_CIS_FEMALE => self::FEMALE,
        Person::GENDER_CIS_MALE => self::MALE,
        Person::GENDER_FEMALE => self::FEMALE,
        Person::GENDER_FLUID => self::GENDER_FLUID,
        Person::GENDER_MALE => self::MALE,
        Person::GENDER_NON_BINARY => self::NON_BINARY,
        Person::GENDER_QUEER => self::QUEER,
        Person::GENDER_TRANS_FEMALE => self::TRANS_FEMALE,
        Person::GENDER_TRANS_MALE => self::TRANS_MALE,
        Person::GENDER_TWO_SPIRIT => self::TWO_SPIRIT,
    ];

    public static function parse(string $identity, ?string $gender): string
    {
        if ($identity == Person::GENDER_NONE) {
            return '';
        }

        if ($identity != Person::GENDER_CUSTOM) {
            return self::PERSON_GENDER[$identity] ?? '?';
        }

        if (empty($gender)) {
            return '';
        }

        $check = trim(strtolower($gender));
        if (empty($check)) {
            return '';
        }


        // Female gender
        if (preg_match('/\b(female|girl|femme|lady|she|her|woman|famale|femal|fem|cis[\s\-]?female)\b/', $check) || $check == 'f') {
            return self::FEMALE;
        }

        // Male gender
        if (preg_match('/\b(male|dude|fella|man|boy)\b/', $check) || $check == 'm') {
            return self::MALE;
        }

        // Non-Binary
        if (preg_match('/\bnon[\s\-]?binary\b/', $check)) {
            return self::NON_BINARY;
        }

        // Queer (no gender stated)
        if (preg_match('/\bqueer\b/', $check)) {
            return self::QUEER;
        }

        // Gender Fluid
        if (preg_match('/\bfluid\b/', $check)) {
            return self::GENDER_FLUID;
        }

        // Gender, "yes"? what does that even mean?
        if ($check == 'yes') {
            return '';
        }

        // Can't determine - return the value
        return $gender;
    }
}