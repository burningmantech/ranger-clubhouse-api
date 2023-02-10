<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('position', function (Blueprint $table) {
            $table->enum('team_category', ['public', 'all_members', 'optional'])->default('public')->nullable(false);
        });

        DB::table('position')->where('public_team_position', true)->update(['team_category' => 'public']);
        DB::table('position')->where('all_team_members', true)->update(['team_category' => 'all_members']);
        DB::table('position')
            ->where('all_team_members', false)
            ->where('public_team_position', false)
            ->update(['team_category' => 'optional']);

        Schema::table('position', function (Blueprint $table) {
            $table->dropColumn(['all_team_members', 'public_team_position']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};

