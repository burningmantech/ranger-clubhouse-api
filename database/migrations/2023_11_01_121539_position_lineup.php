<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('position_lineup', function (Blueprint $table) {
           $table->id();
           $table->string('description')->nullable(false);
           $table->timestamps();
        });

        Schema::create('position_lineup_member', function (Blueprint $table) {
            $table->integer('position_lineup_id')->nullable(false);
            $table->integer('position_id')->nullable(false);
            $table->datetime('created_at')->nullable(false);
            $table->unique('position_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('position_lineup');
        Schema::dropIfExists('position_lineup_member');
    }
};
