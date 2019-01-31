<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateEarlyArrivalTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('early_arrival')) {
            return;
        }
        Schema::create(
            'early_arrival', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('person_id')->default(0);
                $table->string('arrival', 32)->nullable();
                $table->string('so_full_name', 64)->nullable();
                $table->boolean('confirm')->default(0);
                $table->text('justification', 65535)->nullable();
                $table->integer('year')->default(0);
                $table->string('approved', 16)->default('pending');
                $table->timestamp('timestamp')->default(DB::raw('CURRENT_TIMESTAMP'));
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
        Schema::drop('early_arrival');
    }

}
