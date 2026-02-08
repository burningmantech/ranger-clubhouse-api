<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mentee_status', function (Blueprint $table) {
            $table->integer('rank')->nullable(false)->unsigned()->default(0)->change();
        });
        Schema::table('trainee_status', function (Blueprint $table) {
            $table->integer('rank')->nullable()->unsigned()->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
