<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBroadcastMessageTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('broadcast_message')) {
            return;
        }
        Schema::create(
            'broadcast_message', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->bigInteger('person_id')->nullable()->index('broadcast_message_idx_person_id');
                $table->bigInteger('broadcast_id')->nullable()->index('broadcast_message_idx_broadcast_id');
                $table->enum('direction', array('inbound','outbound'))->nullable()->default('outbound');
                $table->string('status', 32);
                $table->string('address_type', 32)->nullable();
                $table->string('address');
                $table->text('message', 65535)->nullable();
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
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
        Schema::drop('broadcast_message');
    }

}
