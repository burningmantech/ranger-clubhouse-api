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
        DB::table('role')->insert([
            'id' => Role::EDIT_HANDLE_RESERVATIONS,
            'title' => 'Edit Handle Reservations',
            'new_user_eligible' => false,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
