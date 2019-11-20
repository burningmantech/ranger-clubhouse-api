<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Timesheet;
use App\Models\Schedule;


class AddSlotIdToTimesheet extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('timesheet', function (Blueprint $table) {
            $table->bigInteger('slot_id')->nullable()->index('timesheet_slot_id');
        });

        // Pulling in all the timesheets will exceed the default memory limit
        /*ini_set('memory_limit', '2G');

        $entries = Timesheet::all();
        foreach ($entries as $entry) {
            $entry->slot_id = Schedule::findSlotSignUpByPositionTime($entry->person_id, $entry->position_id, $entry->on_duty);
            if ($entry->slot_id) {
                $entry->saveWithoutValidation();
            }
        }*/
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('timesheet', function (Blueprint $table) {
            //
            $table->dropColumn('slot_id');
        });
    }
}
