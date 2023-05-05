<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('slot', function (Blueprint $table) {
            $table->integer("begins_year")->nullable(false)->default(0);
            $table->integer("duration")->nullable(false)->default(0);
            $table->integer("begins_time")->nullable(false)->default(0);
            $table->integer("ends_time")->nullable(false)->default(0);
            $table->index(['begins_year', 'position_id']);
        });

        DB::table('slot')->update([
            'begins_year' => DB::raw('year(begins)'),
            'begins_time' => DB::raw('UNIX_TIMESTAMP(CONVERT_TZ(CONVERT_TZ(begins, timezone, "+00:00"), "+00:00", @@time_zone))'),
            'ends_time' => DB::raw('UNIX_TIMESTAMP(CONVERT_TZ(CONVERT_TZ(ends, timezone, "+00:00"), "+00:00", @@time_zone))'),
            'duration' => DB::raw("TIMESTAMPDIFF(second,begins,ends)"),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slot', function (Blueprint $table) {
            $table->dropColumn(['begins_year', 'duration', 'begins_time', 'ends_time']);
        });
    }
};
