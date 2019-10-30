<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('setting', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 128)->unique();
            $table->text('value', 65535)->nullable();
            $table->enum('type', [ 'bool', 'string', 'integer', 'json' ]);
            $table->text('description', 65535)->nullable();
            $table->text('options', 65535)->nullable();
            $table->boolean('is_encrypted')->default(0);
            $table->boolean('environment_only')->default(0);
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
        Schema::dropIfExists('setting');
    }
}
