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
        Schema::table('person_event', function (Blueprint $table) {
            $table->boolean('mvr_eligible')->default(false)->nullable(false);
            $table->index([ 'year', 'mvr_eligible']);
        });

        Schema::table('team', function (Blueprint $table) {
            $table->boolean('mvr_eligible')->default(false)->nullable(false);
            $table->index('mvr_eligible');
        });

        Schema::table('position', function (Blueprint $table) {
            $table->boolean('mvr_eligible')->default(false)->nullable(false);
            $table->index('mvr_eligible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('person_event', function (Blueprint $table) {
            $table->dropColumn('mvr_eligible');
        });

        Schema::table('team', function (Blueprint $table) {
            $table->dropColumn('mvr_eligible');
        });

        Schema::table('position', function (Blueprint $table) {
            $table->dropColumn('mvr_eligible');
        });
    }
};
