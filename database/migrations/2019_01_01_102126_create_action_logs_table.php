<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateActionLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('action_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('person_id')->nullable();
            $table->unsignedInteger('target_person_id')->nullable();
            $table->string('event');
            $table->text('message');
            $table->text('data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index([ 'person_id', 'created_at']);
            $table->index([ 'target_person_id', 'created_at' ]);
            $table->index([ 'person_id', 'event', 'created_at' ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('action_logs');
    }
}
