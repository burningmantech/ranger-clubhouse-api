<?php

use App\Models\TeamManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('team_manager', function (Blueprint $table) {
            $table->id();
            $table->integer('person_id')->nullable(false);
            $table->integer('team_id')->nullable(false);
            $table->timestamps();
        });

        $rows = DB::table('person_team')
            ->where('is_manager', true)
            ->get();

        foreach ($rows as $row) {
            $manager = new TeamManager;
            $manager->team_id = $row->team_id;
            $manager->person_id = $row->person_id;
            $manager->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('team_manager');
    }
};
