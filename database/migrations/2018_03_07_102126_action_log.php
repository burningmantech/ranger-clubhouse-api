<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ActionLog extends Migration
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
            $table->timestamps();
            $table->index([ 'person_id']);
            $table->index([ 'target_person_id' ]);
            $table->index([ 'person_id', 'event' ]);
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
