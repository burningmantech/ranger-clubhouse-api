<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTimesheetLogTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('timesheet_log')) {
            return;
        }
        Schema::create(
            'timesheet_log', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->bigInteger('person_id');
                $table->bigInteger('create_person_id');
                $table->bigInteger('timesheet_id')->nullable();
                $table->enum('action', array('signon','signoff','update','delete','confirmed','created','verify','review'));
                $table->text('message', 65535)->nullable();
                $table->timestamp('created_at')->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->index(['person_id','action'], 'timesheet_log_idx_person_id');
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
        Schema::drop('timesheet_log');
    }

}
