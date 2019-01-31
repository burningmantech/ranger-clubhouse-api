<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAssetAttachmentTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('asset_attachment')) {
            return;
        }
        Schema::create(
            'asset_attachment', function (Blueprint $table) {
                $table->bigInteger('id')->unsigned()->primary();
                $table->enum('parent_type', array('Radio','Vehicle'));
                $table->string('description', 32);
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
        Schema::drop('asset_attachment');
    }

}
