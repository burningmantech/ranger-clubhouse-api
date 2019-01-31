<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateLogTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('log')) {
            return;
        }
        Schema::create(
            'log', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->bigInteger('user_person_id')->unsigned()->nullable()->index('log_idx_user_person_id')->comment('person_id of user making change');
                $table->bigInteger('current_person_id')->unsigned()->nullable()->index('log_idx_current_person_id')->comment('person_id of person affected by change (if any)');
                $table->timestamp('occurred')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('time log entry created');
                $table->string('event', 500)->comment('summary of what was done');
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
        Schema::drop('log');
    }

}
