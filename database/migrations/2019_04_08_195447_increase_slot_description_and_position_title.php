<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IncreaseSlotDescriptionAndPositionTitle extends Migration
{
    /**
     *
     */
    public function up()
    {
        Schema::table('slot', function (Blueprint $table) {
            $table->string('description', 40)->change();
        });

        Schema::table('position', function (Blueprint $table) {
            $table->string('title', 40)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
