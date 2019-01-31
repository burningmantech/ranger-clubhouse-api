<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateRadioEligibleTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('radio_eligible')) {
            return;
        }
        Schema::create(
            'radio_eligible', function (Blueprint $table) {
                $table->integer('person_id');
                $table->integer('year');
                $table->integer('max_radios');
                $table->primary(['person_id','year']);
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
        Schema::drop('radio_eligible');
    }

}
