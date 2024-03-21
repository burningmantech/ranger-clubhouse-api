<?php

namespace App\Attributes;

use Illuminate\Database\Eloquent\Casts\Attribute;

class BlankIfEmptyAttribute
{
    public static function make(): Attribute
    {
        return Attribute::make(set: function ($value) {
            if (is_string($value)) {
                $value = trim($value);
                return strlen($value) ? $value : '';
            } else if (is_numeric($value)) {
                return $value;
            } else {
                return empty($value) ? $value : '';
            }
        });
    }
}
