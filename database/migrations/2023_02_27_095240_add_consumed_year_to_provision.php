<?php

use App\Models\Provision;
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
        Schema::table('provision', function (Blueprint $table) {
            $table->integer('consumed_year')->nullable(true);
        });

        DB::table('provision')->where('status', Provision::USED)->update(['consumed_year' => 2022]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provision', function (Blueprint $table) {
            $table->dropColumn('consumed_year');
        });
    }
};
