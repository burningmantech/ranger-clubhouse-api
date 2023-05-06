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
        Schema::table('position_credit', function (Blueprint $table) {
            $table->integer('start_year')->storedAs("year(start_time)");
            $table->integer('end_year')->storedAs("year(end_time)");
            $table->index(['start_year']);
            $table->index(['start_year', 'end_year']);
            $table->index(['start_year', 'position_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('position_credit', function (Blueprint $table) {
            $table->dropIndex(['start_year', 'position_id']);
            $table->dropIndex(['start_year', 'end_year']);
            $table->dropIndex(['start_year']);

            $table->dropColumn(['start_year', 'end_year']);
        });
    }
};
