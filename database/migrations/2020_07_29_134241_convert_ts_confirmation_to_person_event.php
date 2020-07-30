<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Person;
use App\Models\PersonEvent;

class ConvertTsConfirmationToPersonEvent extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach (Person::where('timesheet_confirmed',true)->cursor() as $person ) {
            $event = PersonEvent::firstOrNewForPersonYear($person->id, 2019);
            $event->setAuditModel(false);
            $event->timesheet_confirmed = true;
            $event->timesheet_confirmed_at = $person->timesheet_confirmed_at;
            $event->saveWithoutValidation();
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
