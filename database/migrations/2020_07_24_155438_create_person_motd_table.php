<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePersonMotdTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('person_motd', function (Blueprint $table) {
            $table->bigInteger('person_id')->nullable(false);
            $table->bigInteger('motd_id')->nullable(false);
            $table->dateTime('read_at')->nullable(false);
            $table->index([ 'person_id', 'motd_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('person_motd');
    }
}
