<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTrainingTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('training')) {
            return;
        }
        Schema::create(
            'training', function (Blueprint $table) {
                $table->bigInteger('id', true)->unsigned();
                $table->bigInteger('slot_id')->unsigned()->index('training_idx_slot_id');
                $table->string('url', 100)->nullable();
                $table->bigInteger('wrangler_id')->unsigned()->nullable();
                $table->string('address', 100)->nullable();
                $table->string('city', 50)->nullable();
                $table->string('state', 2)->nullable();
                $table->string('name', 100)->nullable();
                $table->text('details', 65535)->nullable();
                $table->string('contact', 200)->nullable();
                $table->enum('status', array('killed','maybe','pending','yes'))->nullable();
                $table->text('comment', 65535)->nullable();
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
        Schema::drop('training');
    }

}
