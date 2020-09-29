<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SqlHelper
{
    /*
     * Quote a value for use in raw SQL statements
     */

    public static function quote($value)
    {
        return DB::getPdo()->quote($value);
    }

}
