<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePersonLanguageTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('person_language')) {
            return;
        }
        Schema::create(
            'person_language', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('person_id')->index('person_language_idx_person_id');
                $table->string('language_name', 32);
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
        Schema::drop('person_language');
    }

}
