<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTimesheetTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('timesheet')) {
            return;
        }
        Schema::create(
            'timesheet', function (Blueprint $table) {
                $table->bigInteger('id', true)->unsigned();
                $table->bigInteger('position_id')->unsigned()->index('timesheet_idx_position_id');
                $table->bigInteger('person_id')->unsigned()->index('timesheet_idx_person_id');
                $table->dateTime('on_duty')->comment('date/time that person signed onto duty');
                $table->dateTime('off_duty')->nullable()->comment('date/time that person signed off of duty');
                $table->boolean('verified')->default(0);
                $table->dateTime('verified_at')->nullable();
                $table->bigInteger('verified_person_id')->nullable();
                $table->text('notes', 65535)->nullable();
                $table->enum('review_status', array('approved','rejected','pending'))->default('pending');
                $table->bigInteger('reviewer_person_id')->nullable();
                $table->text('reviewer_notes', 65535)->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->index(['verified','review_status'], 'timesheet_idx_verified');
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
        Schema::drop('timesheet');
    }

}
