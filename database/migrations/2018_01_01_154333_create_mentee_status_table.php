<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateMenteeStatusTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('mentee_status')) {
            return;
        }
        Schema::create(
            'mentee_status', function (Blueprint $table) {
                $table->bigInteger('id', true)->unsigned();
                $table->integer('mentor_year')->unsigned();
                $table->bigInteger('person_id')->unsigned()->default(0);
                $table->boolean('rank')->default(0);
                $table->text('notes', 65535)->nullable();
                $table->unique(['mentor_year','person_id'], 'mentor_year');
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
        Schema::drop('mentee_status');
    }

}
