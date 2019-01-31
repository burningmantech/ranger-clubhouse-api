<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAssetPersonTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('asset_person')) {
            return;
        }
        Schema::create(
            'asset_person', function (Blueprint $table) {
                $table->bigInteger('id', true)->unsigned();
                $table->bigInteger('person_id')->unsigned()->index('asset_person_idx_person_id');
                $table->bigInteger('asset_id')->unsigned()->index('asset_person_idx_asset_id');
                $table->dateTime('checked_out');
                $table->dateTime('checked_in')->nullable();
                $table->bigInteger('attachment_id')->unsigned()->nullable();
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
        Schema::drop('asset_person');
    }

}
