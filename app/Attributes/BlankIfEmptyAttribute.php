<?php

namespace App\Attributes;

use Illuminate\Database\Eloquent\Casts\Attribute;

class BlankIfEmptyAttribute
{
    public static function make() : Attribute
    {
        return Attribute::make(set: fn ($value) => (empty($value) || empty(trim($value))) ? '' : $value);
    }
}
