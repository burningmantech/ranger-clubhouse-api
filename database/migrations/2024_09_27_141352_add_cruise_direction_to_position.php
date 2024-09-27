<?php

use App\Models\Position;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('position', function (Blueprint $table) {
            $table->boolean('cruise_direction')->default(false)->nullable(false);
            $table->index('cruise_direction');
        });
        
        $positions = [
            Position::DIRT,
            Position::DIRT_GREEN_DOT,
            Position::DIRT_POST_EVENT,
            Position::DIRT_PRE_EVENT,
            Position::DIRT_SHINY_PENNY,
            Position::GREEN_DOT_MENTEE, 
            Position::GREEN_DOT_MENTOR,
            Position::TROUBLESHOOTER, 
            Position::TROUBLESHOOTER_MENTEE, 
            Position::TROUBLESHOOTER_MENTOR,
            Position::DOUBLE_OH_7,
            Position::RNR
        ];

        foreach($positions as $id) {
            $position = Position::find($id);
            $position->cruise_direction = true;
            $position->auditReason = "Cruise Direction flag deployment";
            $position->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('position', function (Blueprint $table) {
            $table->dropColumn('cruise_direction');
        });
    }
};
