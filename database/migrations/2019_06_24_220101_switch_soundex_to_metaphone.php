<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use App\Models\Person;

class SwitchSoundexToMetaphone extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $people = Person::all();
        foreach ($people as $person) {
            // callsign setter will take of things.
            $person->callsign = $person->callsign;
            $person->saveWithoutValidation();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
