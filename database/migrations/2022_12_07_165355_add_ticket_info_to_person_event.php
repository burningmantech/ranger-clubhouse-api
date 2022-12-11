<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('person_event', function (Blueprint $table) {
            $table->datetime('ticketing_started_at')->nullable(true);
            $table->datetime('ticketing_last_visited_at')->nullable(true);
            $table->datetime('ticketing_finished_at')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('person_event', function (Blueprint $table) {
            $table->dropColumn(['ticketing_started_at', 'ticketing_last_visited_at', 'ticketing_finished_at']);
        });
    }
};
