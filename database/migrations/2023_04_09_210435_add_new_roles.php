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
            ['id' => Role::REGIONAL_MANAGEMENT, 'title' => 'Regional Management'],
            ['id' => Role::PAYROLL, 'title' => 'Payroll'],
            ['id' => Role::VEHICLE_MANAGEMENT, 'title' => 'Vehicle Management'],
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
