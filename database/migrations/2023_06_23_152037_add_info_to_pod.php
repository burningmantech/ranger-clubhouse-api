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
        Schema::table('pod', function (Blueprint $table) {
            $table->string('location')->nullable(true);
            $table->enum('transport', [ 'foot', 'bicycle', 'vehicle' ])->default('foot')->nullable(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pod', function (Blueprint $table) {
            $table->dropColumn([ 'location', 'transport']);
        });
    }
};
