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
        Schema::table('timesheet', function (Blueprint $table) {
            $table->dropColumn('notes');
            $table->dropColumn('reviewer_notes');
            $table->integer('desired_position_id')->nullable(true);
            $table->datetime('desired_on_duty')->nullable(true);
            $table->datetime('desired_off_duty')->nullable(true);
        });

        Schema::table('timesheet_missing', function (Blueprint $table) {
            $table->dropColumn('notes');
            $table->dropColumn('reviewer_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timesheet', function (Blueprint $table) {
            //
        });
    }
};
