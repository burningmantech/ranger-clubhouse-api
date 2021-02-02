<?php

use App\Models\Person;
use App\Models\Timesheet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPersonStatusToTimesheet extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('timesheet', function (Blueprint $table) {
            $table->boolean('is_non_ranger')->nullable(false)->default(false);
        });

        $ids = Person::where('status', Person::NON_RANGER)->pluck('id');
        if (!empty($ids)) {
            Timesheet::whereIn('person_id', $ids)->update(['is_non_ranger' => true]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('timesheet', function (Blueprint $table) {
            $table->dropColumn('person_status');
        });
    }
}
