<?php

namespace App\Lib\PositionSanityCheck;

abstract class SanityCheck
{
    abstract public static function issues(): array;
    abstract public static function repair($peopleIds): array;
}
