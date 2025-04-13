<?php

use App\Models\ActionLog;
use App\Models\PersonTeamLog;
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
        Schema::table('person_award', function (Blueprint $table) {
            $table->integer('award_id')->nullable()->change();
            $table->integer('team_id')->nullable();
            $table->integer('position_id')->nullable();
            $table->integer('year')->nullable(false);
            $table->integer('creator_person_id')->nullable(true);
            $table->index(['person_id', 'year', 'award_id']);
            $table->index(['person_id', 'year', 'team_id']);
            $table->index(['person_id', 'year', 'position_id']);
        });

        Schema::table('person', function (Blueprint $table) {
            $table->text('years_of_service')->nullable(false)->default('[]');
        });

        Schema::table('award', function (Blueprint $table) {
            $table->dropColumn(['type', 'icon']);
        });

        Schema::table('position', function (Blueprint $table) {
            $table->boolean('awards_eligible')->nullable(false)->default(false);
        });

        Schema::table('team', function (Blueprint $table) {
            $table->boolean('awards_eligible')->nullable(false)->default(false);
        });

        $this->repairTeamLog();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('person_award', function (Blueprint $table) {
            $table->dropColumn(['team_id', 'year', 'creator_person_id']);
            $table->dropIndex(['person_id', 'year', 'team_id']);
        });
        Schema::table('person', function (Blueprint $table) {
            $table->dropColumn('years_of_service');
        });
    }

    public function repairTeamLog(): void
    {
        $logs = ActionLog::whereYear('created_at', '>=', 2022)
            ->whereIn('event', ['person-team-add', 'person-team-remove'])
            ->get();

        PersonTeamLog::truncate();

        $people = [];
        foreach ($logs as $log) {
            $teamId = $log->data['team_id'];
            $personId = $log->target_person_id;
            $date = $log->created_at;

            if (!isset($people[$personId])) {
                $people[$personId] = [];
            }

            if ($log->event == 'person-team-add') {
                $people[$personId][$teamId] = DB::table('person_team_log')
                    ->insert(['person_id' => $personId,
                        'team_id' => $teamId,
                        'joined_on' => $date,
                        'created_at' => $date,
                        'updated_at' => $date]);
            } else if ($people[$personId][$teamId] ?? false) {
                DB::table('person_team_log')
                    ->where('id', $personId)
                    ->update(['left_on' => $date]);
                $people[$personId][$teamId] = null;
            }
        }
    }
};
