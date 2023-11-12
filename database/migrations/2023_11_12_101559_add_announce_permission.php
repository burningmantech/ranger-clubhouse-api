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
        DB::table('role')->insert(['id' => Role::ANNOUNCEMENT_MANAGEMENT, 'title' => 'Announcement Management']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('role')->where('id', Role::ANNOUNCEMENT_MANAGEMENT)->delete();
    }
};
