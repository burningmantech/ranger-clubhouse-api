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
        Schema::table('handle_reservation', function (Blueprint $table) {
            $table->integer('twii_year')->nullable(true)->default(null);
            $table->dropColumn('start_date');
            $table->renameColumn('end_date', 'expires_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('handle_reservation', function (Blueprint $table) {
            $table->dropColumn('twii_year');
        });
    }
};
