<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTraineeNote extends Migration
{
    /**
     * Create the trainee_note to handle multiple notes stored in for a training record.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trainee_note', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('person_id');
            $table->integer('person_source_id')->nullable();
            $table->integer('slot_id');
            $table->text('note');
            $table->boolean('is_log')->default(false);
            $table->timestamps();
            $table->index( [ 'person_id', 'slot_id' ]);
        });

        /*
         * Convert the notes over
         */
        $rows = DB::select('SELECT trainee_status.*, slot.ends FROM trainee_status JOIN slot ON slot_id=slot.id WHERE LENGTH(notes) > 0');

        foreach ($rows as $row) {
            DB::table('trainee_note')->insert([
                'person_id' => $row->person_id,
                'slot_id' => $row->slot_id,
                'person_source_id' => null,
                'note' => $row->notes,
                'created_at' => (string) $row->ends,
                'updated_at' => (string) $row->ends,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trainee_note');
    }
}
