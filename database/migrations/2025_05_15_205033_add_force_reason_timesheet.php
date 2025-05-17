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
            $table->text('signin_force_reason')->nullable();
            $table->index(['on_duty', 'was_signin_forced']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timesheet', function (Blueprint $table) {
            $table->dropColumn('signin_force_reason');
            $table->dropIndex(['on_duty', 'was_signin_forced']);
        });
    }
};
