<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTicketTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('ticket')) {
            return;
        }
        Schema::create(
            'ticket', function (Blueprint $table) {
                $table->bigInteger('person_id');
                $table->integer('year');
                $table->enum('eligibility', array('gift','staff'));
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
        Schema::drop('ticket');
    }

}
