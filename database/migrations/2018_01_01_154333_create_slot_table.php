<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSlotTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('slot')) {
            return;
        }
        Schema::create(
            'slot', function (Blueprint $table) {
                $table->bigInteger('id', true)->unsigned();
                $table->dateTime('begins')->index('slot_idx_begins');
                $table->dateTime('ends');
                $table->bigInteger('position_id')->unsigned()->index('slot_idx_position_id');
                $table->string('description', 25)->nullable()->comment('("day", "night", etc.)');
                $table->bigInteger('signed_up')->unsigned()->default(0);
                $table->bigInteger('max')->unsigned()->default(0)->comment('if trainee slot, this is * slot[trainer_slot_id].signed_up');
                $table->string('url')->nullable();
                $table->bigInteger('trainer_slot_id')->unsigned()->nullable()->comment('if trainee slot, slot.id for corresponding trainer slot, else null');
                $table->bigInteger('trainee_slot_id')->unsigned()->nullable()->comment('if trainer slot, slot.id for corresponding trainee slot, else null');
                $table->bigInteger('training_id')->unsigned()->default(0);
                $table->integer('min')->default(1);
                $table->boolean('active')->default(0);
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
        Schema::drop('slot');
    }

}
