<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('role', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
        });

        DB::table('role')->insert([
            'id' => Role::MEGAPHONE_TEAM_ONPLAYA,
            'title' => 'Megaphone Team On Playa'
        ]);
        DB::table('role')->insert([
            'id' => Role::MEGAPHONE_EMERGENCY_ONPLAYA,
            'title' => 'Megaphone Emergency On Playa'
        ]);
        Schema::table('person_message', function (Blueprint $table) {
            $table->datetime('expires_at')->nullable(true);
        });
        Schema::table('broadcast', function (Blueprint $table) {
            $table->datetime('expires_at')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('role')->whereIn('id', [Role::MEGAPHONE_TEAM_ONPLAYA, Role::MEGAPHONE_EMERGENCY_ONPLAYA])->delete();
        Schema::table('person_message', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
        Schema::table('broadcast', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
