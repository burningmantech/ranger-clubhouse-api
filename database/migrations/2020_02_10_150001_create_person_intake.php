<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

use App\Models\Person;
use App\Models\PersonIntake;
use App\Models\PersonIntakeNote;
use App\Models\PersonStatus;

class CreatePersonIntake extends Migration
{
    /**
     * Setup to support the Unified Flagging View
     *
     * - Create person_intake to hold the {VC,RRN,Mentor} ranks, and Personnel Black Flag
     * - Create person_intake_notes to hold the year's {VC,RRN,Mentor} notes
     *
     * - Add feedback_delivered to trainee_status to indicate lead trainer gave feedback
     * - Add known_{rangers,pnvs} to person
     *
     * @return void
     */
    public function up()
    {
        Schema::create('person_intake', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('person_id');
            $table->integer('year');
            $table->integer('mentor_rank')->nullable();
            $table->integer('rrn_rank')->nullable();
            $table->integer('vc_rank')->nullable();
            $table->boolean('black_flag')->default(false);
            $table->timestamps();

            $table->unique(['person_id', 'year']);
        });

        Schema::create('person_intake_note', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('person_id');
            $table->bigInteger('person_source_id');
            $table->integer('year');
            $table->boolean('is_log')->default(false);
            $table->enum('type', ['mentor', 'rrn', 'vc']);
            $table->text('note');
            $table->timestamps();

            $table->index(['person_id', 'year']);
        });

        Schema::table('trainee_status', function (Blueprint $table) {
            $table->boolean('feedback_delivered')->default(false);
        });

        Schema::table('person', function (Blueprint $table) {
            $table->text('known_rangers')->nullable();
            $table->text('known_pnvs')->nullable();
        });

        DB::table('trainee_status')->update(['feedback_delivered' => true]);

        /*
         * Convert mentor notes and flag over to person_intake & person_intake_notes
         */
        $people = Person::where('mentors_notes', '!=', '')
                    ->orWhere('mentors_flag', true)
                    ->get();

        foreach ($people as $person) {
            $ps = PersonStatus::where('person_id', $person->id)
                ->whereIn('new_status', ['prospective', 'past prospective', 'alpha', 'bonked', 'uberbonked'])
                ->orderBy('created_at', 'desc')
                ->first();

            $date = $ps ? $ps->created_at : ($person->status_date ?? $person->create_date);
            $year = $date->year;
            if ($person->mentors_flag) {
                $intake = new PersonIntake;
                $intake->person_id = $person->id;
                $intake->year = $year;
                $intake->mentor_rank = 4;

                if (!$intake->save()) {
                    error_log("COULD NOT SAVE " . $person->id);
                }
                PersonIntakeNote::record($person->id, $year, 'mentor', "imported rank 4", true);
            }

            if (!empty($person->mentors_notes)) {
                PersonIntakeNote::record($person->id, $year, 'mentor', $person->mentors_notes);
            }

        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('person_intake');
        Schema::dropIfExists('person_intake_note');
        Schema::table('trainee_status', function (Blueprint $table) {
            $table->dropColumn('feedback_delivered');
        });
        Schema::table('person', function (Blueprint $table) {
            $table->dropColumn('known_rangers');
            $table->dropColumn('known_pnvs');
        });

    }
}
