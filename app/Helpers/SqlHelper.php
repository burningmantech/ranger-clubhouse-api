<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SqlHelper
{
    public static function now()
    {
        $result = DB::select("SELECT NOW() as tod");
        return Carbon::parse($result[0]->tod);
    }
}
