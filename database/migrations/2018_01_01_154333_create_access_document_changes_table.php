<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAccessDocumentChangesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('access_document_changes')) {
            return;
        }
        Schema::create(
            'access_document_changes', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->string('table_name', 32);
                $table->integer('record_id');
                $table->enum('operation', array('create','modify','delete'));
                $table->text('changes', 65535)->nullable();
                $table->integer('changer_person_id');
                $table->timestamp('timestamp')->default(DB::raw('CURRENT_TIMESTAMP'));
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
        Schema::drop('access_document_changes');
    }

}
