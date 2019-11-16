<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTrainerStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trainer_status', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('person_id')->unsigned();
            $table->bigInteger('trainer_slot_id')->unsigned();
            $table->bigInteger('slot_id')->unsigned();
            $table->enum('status', [ 'attended', 'no-show', 'pending' ])->default('attended');
            $table->unique(['person_id','slot_id'], 'person_id_and_slot_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trainer_status');
    }
}
