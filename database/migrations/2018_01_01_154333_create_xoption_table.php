<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateXoptionTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('xoption')) {
            return;
        }
/*        Schema::create(
            'xoption', function (Blueprint $table) {
                $table->bigInteger('id', true)->unsigned();
                $table->bigInteger('xfield_id')->unsigned()->index('xoption_idx_xfield_id');
                $table->smallInteger('seq')->unsigned()->default(0)->comment('sort order of value in select drop-down');
                $table->text('val', 65535);
                $table->unique(['xfield_id','val'], 'xfield_id_and_val');
            }
        );*/
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('xoption');
    }

}
