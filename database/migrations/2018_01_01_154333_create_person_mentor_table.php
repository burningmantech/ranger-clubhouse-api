<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePersonMentorTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('person_mentor')) {
            return;
        }
        Schema::create(
            'person_mentor', function (Blueprint $table) {
                $table->bigInteger('person_id')->unsigned();
                $table->bigInteger('mentor_id')->unsigned();
                $table->integer('mentor_year')->unsigned();
                $table->enum('STATUS', array('pass','bonk','self-bonk','pending'))->nullable();
                $table->text('notes', 65535)->nullable();
                $table->primary(['person_id','mentor_id','mentor_year']);
            }
        );
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('person_mentor');
    }

}
