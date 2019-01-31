<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBmidTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('bmid')) {
            return;
        }
        Schema::create(
            'bmid', function (Blueprint $table) {
                $table->integer('person_id');
                $table->enum('status', array('in_prep','do_not_print','ready_to_print','ready_to_reprint_lost','ready_to_reprint_changed','issues','submitted'))->nullable();
                $table->integer('year')->default(0);
                $table->string('title1', 64)->nullable();
                $table->string('title2', 64)->nullable();
                $table->string('title3', 64)->nullable();
                $table->boolean('showers')->nullable()->default(0);
                $table->boolean('org_vehicle_insurance')->nullable()->default(0);
                $table->enum('meals', array('all','pre','post','event','pre+event','event+post','pre+post'))->nullable();
                $table->string('batch', 64)->nullable();
                $table->text('team', 65535)->nullable();
                $table->text('notes', 65535)->nullable();
                $table->dateTime('create_datetime');
                $table->timestamp('modified_datetime')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->primary(['person_id','year']);
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
        Schema::drop('bmid');
    }

}
