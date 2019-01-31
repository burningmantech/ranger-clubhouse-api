<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTraineeStatusTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('trainee_status')) {
            return;
        }
        Schema::create(
            'trainee_status', function (Blueprint $table) {
                $table->bigInteger('id', true)->unsigned();
                $table->bigInteger('slot_id')->unsigned()->default(0);
                $table->bigInteger('person_id')->unsigned()->default(0);
                $table->boolean('passed')->default(0);
                $table->text('notes', 65535)->nullable();
                $table->boolean('rank')->nullable();
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
        Schema::drop('trainee_status');
    }

}
