<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreatePersonOt extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('person_online_training', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('person_id');
            $table->datetime('completed_at');
            $table->enum('type', [ 'manual-review', 'docebo' ]);
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->index([ 'person_id', 'completed_at' ]);
        });

        $rows = DB::select("SELECT person_id, passdate FROM manual_review");
        foreach ($rows as $row) {
            DB::insert("INSERT INTO person_online_training SET person_id=?, completed_at=?", [ $row->person_id, $row->passdate ]);
        }

        // Bu-bye manual review!
        DB::table('setting')->where('name', 'like', 'ManualReview%')->delete();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('person_ot');
    }
}
