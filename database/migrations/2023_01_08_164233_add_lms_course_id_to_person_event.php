<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('person_event', function (Blueprint $table) {
            $table->string('lms_course_id')->nullable(true);
        });

        $rows = DB::table('person_event')
                ->join('person', 'person.id', 'person_event.person_id')
                ->select('person_event.*', 'person.lms_course')
                ->whereNotNull('person.lms_course')
                ->where('year', 2022)
                ->get();

        foreach ($rows as $row) {
            DB::table('person_event')
                ->where('person_id',$row->person_id)
                ->where('year', 2022)
                ->update([ 'lms_course_id' => $row->lms_course]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('person_event', function (Blueprint $table) {
            $table->dropColumn('lms_course_id');
        });
    }
};
