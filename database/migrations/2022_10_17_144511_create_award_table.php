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
    public function up(): void
    {
        Schema::create('award', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable(false);
            $table->string('type')->nullable(false);
            $table->string('icon')->nullable(false);
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('person_award', function (Blueprint $table) {
            $table->id();
            $table->integer('person_id')->nullable(false);
            $table->integer('award_id')->nullable(false);
            $table->text('notes');
            $table->timestamps();
            $table->index([ 'person_id', 'award_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('award');
        Schema::dropIfExists('person_award');
    }
};
