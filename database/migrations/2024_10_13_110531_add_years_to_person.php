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
        Schema::table('person', function (Blueprint $table) {
            $table->json('years_seen')->nullable();
            $table->json('years_as_contributor')->nullable();
            $table->json('years_as_ranger')->nullable();
            $table->json('years_combined')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('person', function (Blueprint $table) {
            $table->dropColumn(['years_seen', 'years_as_contributor', 'years_as_ranger', 'years_combined']);
        });
    }
};
