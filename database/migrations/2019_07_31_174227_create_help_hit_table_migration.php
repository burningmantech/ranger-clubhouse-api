<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHelpHitTableMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('help_hit', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('help_id');
            $table->unsignedInteger('person_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index([ 'help_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('help_hit');
    }
}
