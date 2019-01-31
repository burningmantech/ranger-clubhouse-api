<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePersonSlotTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('person_slot')) {
            return;
        }
        Schema::create(
            'person_slot', function (Blueprint $table) {
                $table->bigInteger('id', true)->unsigned();
                $table->bigInteger('person_id')->unsigned()->index('person_slot_idx_person_id');
                $table->bigInteger('slot_id')->unsigned()->index('person_slot_idx_slot_id');
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
        Schema::drop('person_slot');
    }

}
