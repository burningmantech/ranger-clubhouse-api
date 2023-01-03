<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */

    public function up(): void
    {
        Schema::table('position', function (Blueprint $table) {
            $table->integer('team_id')->nullable(true);
            $table->boolean('all_team_members')->nullable(false)->default(false);
            $table->boolean('public_team_position')->nullable(false)->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('position', function (Blueprint $table) {
            $table->dropColumn([ 'team_id', 'all_team_members', 'public_team_position']);
        });
    }
};

