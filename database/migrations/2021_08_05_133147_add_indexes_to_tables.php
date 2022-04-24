<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('timesheet', function (Blueprint $table) {
            $table->index([ 'position_id', 'on_duty' ]);
            $table->index([ 'person_id', 'on_duty' ]);
            $table->index([ 'person_id', 'position_id' ]);
            $table->index([ 'on_duty' ]);
        });

        Schema::table('position_credit', function (Blueprint $table) {
            $table->index([ 'position_id', 'start_time']);
        });

        Schema::table('person', function (Blueprint $table) {
           $table->index(['status']);
        });

        Schema::table('asset_person', function (Blueprint $table) {
            $table->index([ 'person_id', 'checked_out' ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('timesheet', function (Blueprint $table) {
            $table->dropIndex([ 'position_id', 'on_duty' ]);
            $table->dropIndex([ 'person_id', 'on_duty' ]);
            $table->dropIndex([ 'person_id', 'position_id' ]);
            $table->dropIndex([ 'on_duty' ]);
        });

        Schema::table('position_credit', function (Blueprint $table) {
            $table->dropIndex([ 'position_id', 'start_time']);
        });

        Schema::table('person', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('asset_person', function (Blueprint $table) {
            $table->dropIndex([ 'person_id', 'checked_out' ]);
        });
    }
}
