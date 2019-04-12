<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Person;

class AddCallsignNormalizedToPerson extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('person', function (Blueprint $table) {
            $table->string('callsign_normalized', 128);
            $table->string('callsign_soundex', 128);
            $table->index('callsign_normalized');
            $table->index('callsign_soundex');
        });

        $rows = Person::all();

        foreach ($rows as $row) {
            $row->callsign = $row->callsign;
            $row->saveWithoutValidation();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('person', function (Blueprint $table) {
            $table->dropColumn(['callsign_normalized', 'callsign_soundex']);
        });
    }
}
