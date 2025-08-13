<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Role::insert([
            'id' => Role::EDIT_EMERGENCY_CONTACT,
            'title' => 'Edit Emergency Contact Year Round',
            'new_user_eligible' => false
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
