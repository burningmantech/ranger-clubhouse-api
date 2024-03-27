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
        Schema::table('person_message', function (Blueprint $table) {
            $table->integer('broadcast_id')->nullable(true);
            $table->integer('reply_to_id')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('person_message', function (Blueprint $table) {
            $table->dropColumn(['broadcast_id', 'reply_to_id']);
        });
    }
};
