<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPreEventSlotDatesToEventDates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE event_dates ADD COLUMN id INTEGER UNSIGNED PRIMARY KEY AUTO_INCREMENT");
        DB::statement("ALTER TABLE event_dates ADD COLUMN pre_event_slot_start DATETIME DEFAULT NULL");
        DB::statement("ALTER TABLE event_dates ADD COLUMN pre_event_slot_end DATETIME DEFAULT NULL");
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
