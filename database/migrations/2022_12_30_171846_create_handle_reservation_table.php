<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('handle_reservation', function (Blueprint $table) {
            $table->id();
            $table->string('handle')->nullable(false);
            $table->enum('reservation_type', array('brc_term', 'deceased_person', 'dismissed_person', 'radio_jargon', 'ranger_term', 'slur', 'twii_person', 'uncategorized'))->nullable(false);
            $table->date('start_date')->nullable(false);
            $table->date('end_date')->nullable();
            $table->string('reason')->nullable();
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
        Schema::dropIfExists('handle_reservation');
    }
};
