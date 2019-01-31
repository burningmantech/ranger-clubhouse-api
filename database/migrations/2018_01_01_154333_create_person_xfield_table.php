<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePersonXfieldTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('person_xfield')) {
            return;
        }
        Schema::create(
            'person_xfield', function (Blueprint $table) {
                $table->bigInteger('person_id')->unsigned();
                $table->bigInteger('xfield_id')->unsigned();
                $table->text('val', 65535)->comment('may be an xoption.id');
                $table->text('phonetic', 65535)->nullable()->comment('from val for search');
                $table->unique(['person_id','xfield_id'], 'person_id_and_xfield_id');
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
        Schema::drop('person_xfield');
    }

}
