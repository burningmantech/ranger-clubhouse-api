<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('role')
            ->insert([
                'id' => Role::CAN_FORCE_SHIFT,
                'title' => 'Force Shift',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('role')->where('id', Role::CAN_FORCE_SHIFT)->delete();
    }
};
