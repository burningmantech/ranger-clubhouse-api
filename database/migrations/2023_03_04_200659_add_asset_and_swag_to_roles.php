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
        DB::table('role')->insert(['id' => Role::EDIT_ASSETS, 'title' => 'Edit Asset Records']);
        DB::table('role')->insert(['id' => Role::EDIT_SWAG, 'title' => 'Edit Swag Records']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};