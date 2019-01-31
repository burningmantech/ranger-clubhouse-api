<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTimesheetMissingTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('timesheet_missing')) {
            return;
        }
        Schema::create(
            'timesheet_missing', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->bigInteger('person_id');
                $table->bigInteger('create_person_id');
                $table->bigInteger('position_id');
                $table->dateTime('on_duty');
                $table->dateTime('off_duty');
                $table->string('partner');
                $table->text('notes', 65535)->nullable();
                $table->enum('review_status', array('approved','rejected','pending'))->nullable()->default('pending');
                $table->bigInteger('reviewer_person_id')->nullable();
                $table->text('reviewer_notes', 65535)->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->timestamp('created_at')->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->index(['person_id','review_status'], 'timesheet_missing_idx_person_id');
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
        Schema::drop('timesheet_missing');
    }

}
