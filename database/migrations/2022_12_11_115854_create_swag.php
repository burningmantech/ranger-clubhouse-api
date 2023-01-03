<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('swag', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('title')->nullable(false);
            $table->string('type')->nullable(false);
            $table->boolean('active')->nullable(false);
            $table->string('shirt_type')->nullable(true);
            $table->text('description')->nullable(true);
        });

        Schema::create('person_swag', function (Blueprint $table) {
            $table->id();
            $table->integer('person_id')->nullable(false);
            $table->integer('swag_id')->nullable(false);
            $table->integer('year_issued')->nullable(true);
            $table->text('notes');
            $table->index(['person_id', 'swag_id']);
            $table->timestamps();
        });

        Schema::table('person', function (Blueprint $table) {
            $table->integer('tshirt_swag_id')->nullable(true);
            $table->integer('tshirt_secondary_swag_id')->nullable(true);
            $table->integer('long_sleeve_swag_ig')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('swag');
        Schema::dropIfExists('person_swag');
        Schema::dropColumns('person', ['tshirt_swag_id', 'tshirt_secondary_swag_id', 'long_sleeve_swag_ig']);
    }
};
