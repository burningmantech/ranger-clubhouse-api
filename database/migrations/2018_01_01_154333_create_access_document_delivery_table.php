<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAccessDocumentDeliveryTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('access_document_delivery')) {
            return;
        }
        Schema::create(
            'access_document_delivery', function (Blueprint $table) {
                $table->integer('person_id');
                $table->integer('year');
                $table->enum('method', array('will_call','mail'));
                $table->string('street', 100)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('state', 2)->nullable();
                $table->string('postal_code', 10)->nullable();
                $table->enum('country', array('United States','Canada'))->nullable();
                $table->timestamp('modified_date')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->primary(['person_id','year']);
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
        Schema::drop('access_document_delivery');
    }

}
