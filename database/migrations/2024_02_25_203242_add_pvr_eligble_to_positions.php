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
        Schema::table('position', function (Blueprint $table) {
            $table->boolean('pvr_eligible')->default(false)->nullable(false);
            $table->index('pvr_eligible');
        });

        Schema::table('team', function (Blueprint $table) {
            $table->boolean('pvr_eligible')->default(false)->nullable(false);
            $table->index('pvr_eligible');
        });

        Schema::table('person_event', function (Blueprint $table) {
            $table->boolean('ignore_mvr')->default(false)->nullable(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('position', function (Blueprint $table) {
            $table->dropColumn('pvr_eligible');
        });
        Schema::table('team', function (Blueprint $table) {
            $table->dropColumn('pvr_eligible');
        });
        Schema::table('person_event', function (Blueprint $table) {
            $table->dropColumn('ignore_mvr');
        });
    }
};
