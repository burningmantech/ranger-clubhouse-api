<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAlertTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('alert')) {
            return;
        }
        Schema::create(
            'alert', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->string('title')->nullable();
                $table->text('description', 65535)->nullable();
                $table->boolean('on_playa')->default(0);
                $table->timestamps();
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
        Schema::drop('alert');
    }

}
