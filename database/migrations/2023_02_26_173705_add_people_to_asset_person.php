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
        Schema::table('asset_person', function (Blueprint $table) {
            $table->integer('check_out_person_id')->nullable(true);
            $table->integer('check_in_person_id')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_person', function (Blueprint $table)  {
            $table->dropColumn([ 'check_out_person_id', 'check_in_person_id']);
        });
    }
};
