<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSessionsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('sessions')) {
            return;
        }
        Schema::create(
            'sessions', function (Blueprint $table) {
                $table->string('id', 32)->primary();
                $table->timestamp('access')->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->binary('data')->nullable();
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
        Schema::drop('sessions');
    }

}
