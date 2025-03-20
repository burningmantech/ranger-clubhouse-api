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
        Schema::table('request_log', function (Blueprint $table) {
            $table->longText('request_payload')->nullable();
            $table->longText('response_payload')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_log', function (Blueprint $table) {
            $table->dropColumn([ 'request_payload', 'response_payload' ]);
        });
    }
};
