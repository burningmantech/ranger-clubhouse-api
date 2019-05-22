<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIdToPersonMentor extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::transaction(function () {
            DB::statement("ALTER TABLE person_mentor DROP PRIMARY KEY");
            DB::statement("ALTER TABLE person_mentor ADD COLUMN id BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT");
            DB::statement("ALTER TABLE person_mentor ADD UNIQUE INDEX(person_id, mentor_id, mentor_year)");
            // lower case the mentor_status
            DB::statement("ALTER TABLE person_mentor CHANGE COLUMN STATUS status enum('pass','bonk','self-bonk', 'pending')");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('person_mentor', function (Blueprint $table) {
            //
        });
    }
}
