<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePersonPositionTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('person_position')) {
            return;
        }
        Schema::create(
            'person_position', function (Blueprint $table) {
                $table->bigInteger('person_id')->unsigned();
                $table->bigInteger('position_id')->unsigned();
                $table->primary(['person_id','position_id']);
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
        Schema::drop('person_position');
    }

}
