<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SqlHelper
{
    /*
     * Grab the current timestamp from the database server
     */

    public static function now()
    {
        $result = DB::select("SELECT NOW() as tod");
        return Carbon::parse($result[0]->tod);
    }

    /*
     * Quote a value for use in raw SQL statements
     */

    public static function quote($value)
    {
        return DB::getPdo()->quote($value);
    }
}
