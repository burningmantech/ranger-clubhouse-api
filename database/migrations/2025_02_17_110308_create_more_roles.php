<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    const array NEW_ROLES = [
        Role::QUARTERMASTER => 'Quartermaster',
        Role::SHIFT_MANAGEMENT => 'Shift Management',
        Role::POD_MANAGEMENT => 'Pod Management',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('role')->where('id', Role::SHIFT_MANAGEMENT_SELF)->update(['title'=> 'Shift Management Self']);
        
        foreach (self::NEW_ROLES as $id => $title) {
            DB::table('role')->insert([
                'id' => $id,
                'title' => $title,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */

    public function down(): void
    {
        foreach (self::NEW_ROLES as $id => $title) {
            DB::table('role')->where('id', $id)->delete();
        }
    }
};
