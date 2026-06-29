<?php

use App\Models\Position;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $ids = DB::table('position')->where('ignore_time_check', true)->pluck('id')->toArray();

        Schema::table('position', function (Blueprint $table) {
            $table->boolean('is_checkin_time_restricted')->default(false)->nullable(false);
            $table->dropColumn('ignore_time_check');
        });

        DB::table('position')
            ->whereNotIn('id', $ids)
            ->whereIn('type', [Position::TYPE_FRONTLINE, Position::TYPE_TRAINING])
            ->update(['is_checkin_time_restricted' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('position', function (Blueprint $table) {
            $table->boolean('ignore_time_check')->default(false)->nullable(false);
            $table->dropColumn('is_checkin_time_restricted');
        });
    }
};
