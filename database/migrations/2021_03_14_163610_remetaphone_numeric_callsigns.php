<?php

use App\Models\Person;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class RemetaphoneNumericCallsigns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $people = DB::table('person')->select('id', 'callsign')->where('callsign', 'regexp', '\d')->get();
        foreach ($people as $p) {
            DB::table('person')->where('id', $p->id)->update([
                'callsign_soundex' => metaphone(Person::spellOutNumbers(Person::normalizeCallsign($p->callsign)))
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
        //
    }
}
