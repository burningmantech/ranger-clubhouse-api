<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixBmidSchemaMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /*
         * Several things going on here
         * - Drop the old primary key of person_id & year
         * - Add a new primary key 'id'
         * - Default create_datetime to the current time.
         * - Have the pair person_id & year be a unique key.
         */

        DB::transaction(function () {
            DB::statement("ALTER TABLE bmid DROP PRIMARY KEY");
            DB::statement("ALTER TABLE bmid ADD COLUMN id BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT");
            DB::statement("ALTER TABLE bmid MODIFY COLUMN create_datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL");
            DB::statement("ALTER TABLE bmid ADD UNIQUE INDEX(person_id, year)");
        });
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
