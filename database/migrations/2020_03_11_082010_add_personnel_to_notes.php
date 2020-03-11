<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPersonnelToNotes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE person_intake_note CHANGE COLUMN type type ENUM('mentor', 'rrn', 'personnel', 'vc') NOT NULL");
        Schema::table('person_intake', function ($table) {
            $table->dropColumn(['black_flag']);
            $table->integer('personnel_rank')->nullable();
        });
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
