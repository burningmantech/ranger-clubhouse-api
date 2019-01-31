<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePositionCreditTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('position_credit')) {
            return;
        }
        Schema::create(
            'position_credit', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('position_id');
                $table->dateTime('start_time');
                $table->dateTime('end_time');
                $table->decimal('credits_per_hour', 4);
                $table->string('description', 25);
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
        Schema::drop('position_credit');
    }

}
