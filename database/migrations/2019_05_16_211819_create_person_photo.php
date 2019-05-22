<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePersonPhoto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('person_photo', function (Blueprint $table) {
            $table->bigInteger('person_id');
            $table->primary('person_id');
            $table->enum('status', [ 'approved', 'rejected', 'submitted', 'missing' ])->default('missing');
            $table->string('lambase_image')->default('');
            $table->datetime('lambase_date')->nullable();
            $table->datetime('expired_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('person_photo');
    }
}
