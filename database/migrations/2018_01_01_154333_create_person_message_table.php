<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePersonMessageTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('person_message')) {
            return;
        }
        Schema::create(
            'person_message', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('person_id');
                $table->integer('creator_person_id');
                $table->timestamp('timestamp')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->string('subject')->default('');
                $table->text('body', 65535);
                $table->boolean('delivered')->default(0);
                $table->string('message_from')->default('');
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
        Schema::drop('person_message');
    }

}
