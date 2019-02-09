<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSuspendedStatus extends Migration
{
    /**
     * Add the new suspended status
     *
     * @return void
     */
    public function up()
    {
        // Check to see if the status is already there.
        $result = DB::select("SHOW COLUMNS FROM person WHERE Field = 'status'");
        preg_match("/^enum\(\'(.*)\'\)$/", $result[0]->Type, $matches);
        $enums = explode("','", $matches[1]);
        if (in_array("suspended", $enums)) {
            // already have the status.
            return;
        }

        $enums[] = "suspended";
        // Newer Mysql version have problems wtih the create_date being zero -- ignore it.
        DB::select("SET sql_mode = ''");
        $enums = array_map(function ($s) { return '"'.$s.'"'; }, $enums);
        DB::select('ALTER TABLE person MODIFY COLUMN `status` ENUM('.implode(',', $enums).')');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
