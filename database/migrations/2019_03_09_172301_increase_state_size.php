<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IncreaseStateSize extends Migration
{
    /**
     * Increase the state
     *
     * @return void
     */
    public function up()
    {
        DB::statement("SET SQL_MODE='ALLOW_INVALID_DATES'");
        DB::statement("ALTER TABLE person MODIFY state varchar(128) NOT NULL default ''");
        DB::statement("ALTER TABLE person MODIFY street1 varchar(128) NOT NULL default ''");
        DB::statement("ALTER TABLE person MODIFY street2 varchar(128) NOT NULL default ''");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
