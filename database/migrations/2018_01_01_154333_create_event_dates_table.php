<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateEventDatesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('event_dates')) {
            return;
        }
        Schema::create(
            'event_dates', function (Blueprint $table) {
                $table->dateTime('event_start')->nullable();
                $table->dateTime('event_end')->nullable();
                $table->dateTime('pre_event_start')->nullable();
                $table->dateTime('post_event_end')->nullable();
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
        Schema::drop('event_dates');
    }

}
