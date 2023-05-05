<?php

use App\Models\Slot;
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
            $table->string('timezone_abbr');
        });

        $rows = Slot::all();
        foreach ($rows as $row) {
            $tz = $row->begins_adjusted->format('T');
            if (str_starts_with($tz, '+') || str_starts_with($tz, '-')) {
                $tz = 'UTC' . $tz;
            }
            DB::table('slot')->where('id', $row->id)->update([ 'timezone_abbr' => $tz]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slot', function (Blueprint $table) {
            $table->dropColumn('timezone_abbr');
        });
    }
};
