<?php

use App\Models\ActionLog;
use App\Models\PersonEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('person_event', function (Blueprint $table) {
            $table->datetime('lms_enrolled_at')->nullable(true);
        });

        foreach ([2022, 2023] as $year) {
            $rows = ActionLog::whereYear('created_at', $year)->where('event', 'lms-enrollment')->get();

            foreach ($rows as $r) {
                $pe = PersonEvent::firstOrNewForPersonYear($r->target_person_id, $year);
                $pe->lms_enrolled_at = $r->created_at;
                $pe->setAuditModel(false);
                $pe->saveWithoutValidation();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('person_event', function (Blueprint $table) {
            $table->dropColumn('lms_enrolled_at');
        });
    }
};
