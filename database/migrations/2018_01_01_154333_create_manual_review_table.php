<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateManualReviewTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('manual_review')) {
            return;
        }
        Schema::create(
            'manual_review', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->integer('person_id');
                $table->dateTime('passdate');
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
        Schema::drop('manual_review');
    }

}
