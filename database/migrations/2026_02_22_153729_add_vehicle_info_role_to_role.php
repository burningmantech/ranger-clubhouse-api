<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('role')->insert(['id' => Role::VEHICLE_INFO_UPDATE, 'title' => 'Vehicle Info Update', 'new_user_eligible' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('role')->where('id', Role::VEHICLE_INFO_UPDATE)->delete();
    }
};
