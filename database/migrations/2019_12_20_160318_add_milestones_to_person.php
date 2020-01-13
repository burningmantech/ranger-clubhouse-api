<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMilestonesToPerson extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('person', function (Blueprint $table) {
            $table->boolean('has_reviewed_pi')->default(false);
            $table->timestamp('logged_in_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            //
        });

        // Look for the last login action log record.
        DB::select("UPDATE person SET logged_in_at=(SELECT created_at FROM action_logs WHERE person_id=person.id AND event='auth-login' ORDER BY created_at DESC LIMIT 1)");
        // .. otherwise try to fill the time in via the Clubhouse 1 log.
        DB::select("UPDATE person SET logged_in_at=(SELECT occurred FROM log WHERE user_person_id=person.id  ORDER BY occurred DESC LIMIT 1) WHERE person.logged_in_at is null");


        // Look to see when they were last seen.
        DB::select("UPDATE person SET last_seen_at=(SELECT created_at FROM action_logs WHERE person_id=person.id AND target_person_id IS NULL ORDER BY created_at DESC LIMIT 1)");
        // .. otherwise try to fill the time in via the Clubhouse 1 log.
        DB::select("UPDATE person SET last_seen_at=(SELECT occurred FROM log WHERE user_person_id=person.id  ORDER BY occurred DESC LIMIT 1) WHERE person.last_seen_at is null");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('person', function (Blueprint $table) {
            $table->dropColumn([ 'has_reviewed_pi', 'logged_in_at', 'last_seen_at' ]);
            //
        });
    }
}
