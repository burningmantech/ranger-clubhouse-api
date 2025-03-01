<?php

use App\Models\Position;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    const array POSITIONS = [
        Position::HQ_SUPERVISOR,
        Position::HQ_TRAINER,
        Position::HQ_WINDOW,
        Position::INTERCEPT_DISPATCH,
        Position::INTERCEPT_OPERATOR,
        Position::LOGISTICS,
        Position::MENTOR,
        Position::MENTOR_LEAD,
        Position::MENTOR_SHORT,
        Position::OOD,
        Position::OPERATIONS_MANAGER,
        Position::OPERATOR,
        Position::PERSONNEL_MANAGER,
        Position::RNR,
        Position::RSCI,
        Position::RSC_SHIFT_LEAD,
        Position::RSC_WESL,
        Position::SANDMAN,
        Position::SITE_SETUP,
        Position::TOW_TRUCK_DRIVER,
        Position::TROUBLESHOOTER,
        Position::TROUBLESHOOTER_LEAL,
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Position::whereIn('id', self::POSITIONS)->update([ 'on_trainer_report' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
