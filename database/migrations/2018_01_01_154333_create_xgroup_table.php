<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateXgroupTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('xgroup')) {
            return;
        }
        Schema::create(
            'xgroup', function (Blueprint $table) {
                $table->bigInteger('id', true)->unsigned();
                $table->smallInteger('seq')->unsigned()->default(550)->index('xgroup_idx_seq')->comment('loc of group on screen');
                $table->string('title', 25);
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
        Schema::drop('xgroup');
    }

}
