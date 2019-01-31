<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateXfieldTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('xfield')) {
            return;
        }
        Schema::create(
            'xfield', function (Blueprint $table) {
                $table->bigInteger('id', true)->unsigned();
                $table->smallInteger('seq')->unsigned()->default(550)->comment('loc of field on screen (xgroup.seq takes precedence)');
                $table->bigInteger('xgroup_id')->unsigned()->nullable()->index('xfield_idx_xgroup_id');
                $table->string('title', 25);
                $table->bigInteger('role_id_edit')->unsigned()->nullable()->comment('if required to edit');
                $table->bigInteger('role_id_view')->unsigned()->nullable()->comment('if required to view');
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
        Schema::drop('xfield');
    }

}
