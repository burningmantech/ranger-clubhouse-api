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
            'id' => Role::FULL_REPORT_ACCESS,
            'title' => 'Full Report Access',
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
