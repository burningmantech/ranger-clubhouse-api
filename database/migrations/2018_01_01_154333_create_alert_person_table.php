<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAlertPersonTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('alert_person')) {
            return;
        }
        Schema::create(
            'alert_person', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->bigInteger('person_id');
                $table->bigInteger('alert_id');
                $table->boolean('use_sms')->default(1);
                $table->boolean('use_email')->default(1);
                $table->timestamps();
                $table->unique(['person_id','alert_id'], 'person_id');
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
        Schema::drop('alert_person');
    }

}
