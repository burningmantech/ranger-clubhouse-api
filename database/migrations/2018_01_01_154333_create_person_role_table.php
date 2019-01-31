<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePersonRoleTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('person_role')) {
            return;
        }
        Schema::create(
            'person_role', function (Blueprint $table) {
                $table->bigInteger('person_id')->unsigned();
                $table->bigInteger('role_id')->unsigned();
                $table->unique(['person_id','role_id'], 'person_id_and_role_id');
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
        Schema::drop('person_role');
    }

}
