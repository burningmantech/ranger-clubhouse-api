<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOshaToPerson extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('person', function (Blueprint $table) {
            $table->boolean('osha10')->default(0);
            $table->boolean('osha30')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('person', function (Blueprint $table) {
            $table->dropColumn([ 'osha10', 'osha30' ]);
        });
    }
}
