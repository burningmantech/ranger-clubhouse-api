<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('position', function (Blueprint $table) {
            $table->boolean('alert_when_becomes_empty')->default(false)->nullable(false);
            $table->boolean('alert_when_no_trainers')->default(false)->nullable(false);
        });
        DB::table('position')->where('alert_when_empty', true)->update(['alert_when_no_trainers' => true]);
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
};
