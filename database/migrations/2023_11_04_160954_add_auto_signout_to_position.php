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
            $table->boolean('auto_sign_out')->default(false)->nullable(false);
            $table->float('sign_out_hour_cap')->default(0)->nullable(false);
        });

        Schema::table('timesheet_log', function (Blueprint $table) {
            $table->integer('create_person_id')->nullable(true)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('position', function (Blueprint $table) {
            $table->dropColumn(['auto_sign_out', 'sign_out_hour-cap']);
        });
    }
};
