<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBmidExportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bmid_export', function (Blueprint $table) {
            $table->string('filename')->nullable(false);
            $table->string('batch_info');
            $table->text('person_ids');
            $table->integer('person_id')->nullable(false);
            $table->datetime('created_at')->nullable(false);
        });
        //
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('bmid_export');
    }
}
