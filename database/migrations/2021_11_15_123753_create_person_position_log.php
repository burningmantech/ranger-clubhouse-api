<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('person_position_log', function (Blueprint $table) {
            $table->id();
            $table->integer('position_id')->nullable(false);
            $table->integer('person_id')->nullable(false);
            $table->date('joined_on')->nullable(false);
            $table->date('left_on')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('person_position_log');
    }
};

