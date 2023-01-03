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
        Schema::create('position_role', function (Blueprint $table) {
            $table->integer('position_id')->nullable(false);
            $table->integer('role_id')->nullable(false);
            $table->unique(['position_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */

    public function down(): void
    {
        Schema::dropIfExists('position_role');
    }
};
