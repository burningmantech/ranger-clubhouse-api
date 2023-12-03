<?php

namespace App\Attributes;

use Illuminate\Database\Eloquent\Casts\Attribute;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneAttribute
{
    public static function make(): Attribute
    {
        return Attribute::make(
            get: function ($phone) {
                $phone = trim($phone ?? '');
                if (empty($phone)) {
                    return '';
                }

                $normalized = preg_replace("/\W+/", "", $phone);
                $len = strlen($normalized);

                if ($len == 10 || ($len == 11 && str_starts_with($normalized, '1'))) {
                    $country = 'US';
                } else {
                    $country = null;
                }

                $util = PhoneNumberUtil::getInstance();
                try {
                    $parsedPhone = $util->parse($phone, $country);
                    return $util->format($parsedPhone, $country == 'US' ? PhoneNumberFormat::NATIONAL : PhoneNumberFormat::INTERNATIONAL);
                } catch (NumberParseException $e) {
                    return $phone;
                }
            }
        );
    }
}