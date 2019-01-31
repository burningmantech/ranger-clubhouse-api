<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateRoleTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('role')) {
            return;
        }
        Schema::create(
            'role', function (Blueprint $table) {
                $table->bigInteger('id', true)->unsigned();
                $table->string('title', 25);
                $table->boolean('new_user_eligible')->default(0);
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
        Schema::drop('role');
    }

}
