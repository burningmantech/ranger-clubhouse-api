<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAccessDocumentTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('access_document')) {
            return;
        }
        Schema::create(
            'access_document', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->integer('person_id');
                $table->enum('type', array('gift_ticket','reduced_price_ticket','staff_credential','vehicle_pass','work_access_pass','work_access_pass_so'));
                $table->enum('status', array('qualified','claimed','banked','submitted','used','cancelled','expired'));
                $table->integer('source_year')->nullable();
                $table->dateTime('access_date')->nullable();
                $table->boolean('access_any_time')->nullable()->default(0);
                $table->text('name', 65535)->nullable();
                $table->text('comments', 65535)->nullable();
                $table->date('expiry_date')->nullable();
                $table->dateTime('create_date');
                $table->timestamp('modified_date')->default(DB::raw('CURRENT_TIMESTAMP'));
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
        Schema::drop('access_document');
    }

}
