<?php

namespace App\Attributes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class NullIfEmptyAttribute
{
    public static function make() : Attribute
    {
        return Attribute::make(set: fn ($value) => empty($value) ? null : $value);
    }
}