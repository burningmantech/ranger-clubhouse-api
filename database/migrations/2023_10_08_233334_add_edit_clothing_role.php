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
        DB::table('role')->insert(['id' => Role::EDIT_CLOTHING, 'title' => 'Edit Clothing']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('role')->where('id', Role::EDIT_CLOTHING)->delete();
    }
};
