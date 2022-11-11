<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('position', function (Blueprint $table) {
            $table->boolean('on_trainer_report')->default(false)->nullable(false)->after('on_sl_report');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('position', function (Blueprint $table) {
            $table->dropColumn('on_trainer_report');
        });
    }
};
