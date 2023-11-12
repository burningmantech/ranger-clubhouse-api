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
        Schema::table('slot', function (Blueprint $table) {
            $table->integer('parent_signup_slot_id')->nullable(true);
            $table->index('parent_signup_slot_id');
        });

        Schema::table('person', function (Blueprint $table) {
            $table->dropColumn('active_next_event');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slot', function (Blueprint $table) {
            $table->dropColumn('parent_signup_slot_id');
        });
    }
};
