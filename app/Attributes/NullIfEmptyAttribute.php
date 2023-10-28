<?php

namespace App\Attributes;

use Illuminate\Database\Eloquent\Casts\Attribute;

class NullIfEmptyAttribute
{
    public static function make(): Attribute
    {
        return Attribute::make(set: function ($value) {
            if (!empty($value)) {
                $value = trim($value);
            }
            return empty($value) ? null : $value;
        });
    }
}