<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePositionTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('position')) {
            return;
        }
        Schema::create(
            'position', function (Blueprint $table) {
                $table->bigInteger('id', true)->unsigned();
                $table->string('title', 25);
                $table->boolean('new_user_eligible')->default(0);
                $table->boolean('all_rangers')->default(0);
                $table->boolean('count_hours')->default(1);
                $table->integer('min')->default(1)->comment('Min suggested Rangers per slot');
                $table->integer('max')->nullable()->comment('Max suggested Rangers per slot');
                $table->boolean('auto_signout')->default(0)->comment('Can be auto-signed out');
                $table->boolean('on_sl_report')->nullable();
                $table->string('short_title', 6)->nullable();
                $table->string('type', 32)->nullable();
                $table->bigInteger('training_position_id')->unsigned()->nullable();
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
        Schema::drop('position');
    }

}
