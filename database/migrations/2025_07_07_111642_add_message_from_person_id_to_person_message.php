<?php

use App\Models\Person;
use App\Models\PersonMessage;
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
        Schema::table('person_message', function (Blueprint $table) {
            $table->integer('sender_person_id')->nullable();
            $table->integer('sender_team_id')->nullable();
            $table->integer('recipient_team_id')->nullable();
            $table->integer('creator_position_id')->nullable();
            $table->enum('message_type', [
                PersonMessage::MESSAGE_TYPE_NORMAL,
                PersonMessage::MESSAGE_TYPE_CONTACT,
                PersonMessage::MESSAGE_TYPE_MENTOR,
            ])->nullable(false)->default(PersonMessage::MESSAGE_TYPE_NORMAL);
            $table->enum('sender_type', [
                PersonMessage::SENDER_TYPE_PERSON,
                PersonMessage::SENDER_TYPE_TEAM,
                PersonMessage::SENDER_TYPE_RBS,
                PersonMessage::SENDER_TYPE_OTHER
            ])->nullable()
                ->default(PersonMessage::SENDER_TYPE_PERSON);
            $table->index(['person_id', 'created_at']);
            $table->index(['person_id', 'reply_to_id']);
            $table->index([ 'sender_person_id', 'created_at']);
            $table->index(['sender_person_id', 'reply_to_id', 'created_at']);
        });
        $callsigns = [];
        PersonMessage::chunk(1000, function ($rows) use ($callsigns) {
            foreach ($rows as $row) {
                if ($row->message_from == 'The Ranger Broadcasting System') {
                    $row->sender_type = PersonMessage::SENDER_TYPE_RBS;
                    $row->save();
                    continue;
                }

                $normal = Person::normalizeCallsign($row->message_from);
                $id = $callsigns[$normal] ?? null;
                if (!$id) {
                    $id = DB::table('person')->where('callsign_normalized', $normal)->value('id');
                    if (!$id) {
                        $row->sender_type = PersonMessage::SENDER_TYPE_OTHER;
                    } else {
                        $callsigns[$normal] = $id;
                    }
                } else {
                    $row->sender_type = PersonMessage::SENDER_TYPE_PERSON;
                }

                $row->sender_person_id = $id;

                if ($row->creator_person_id) {
                    $row->creator_position_id = DB::table('timesheet')
                        ->where('person_id', $row->creator_person_id)
                        ->whereYear('on_duty', $row->created_at->year)
                        ->where('on_duty', '>=', $row->created_at)
                        ->whereRaw('? < off_duty', [$row->created_at])
                        ->value('position_id');
                }
                $row->save();
            }

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('person_message', function (Blueprint $table) {
            $table->dropColumn(['sender_person_id', 'sender_team_id', 'recipient_team_id']);
        });
    }
};
