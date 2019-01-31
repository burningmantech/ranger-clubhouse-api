<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateFeedbackTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('feedback')) {
            return;
        }
        Schema::create(
            'feedback', function (Blueprint $table) {
                $table->increments('id');
                $table->dateTime('created');
                $table->integer('person_id')->unsigned();
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
        Schema::drop('feedback');
    }

}
