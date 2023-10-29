<?php

use App\Models\HandleReservation;
use App\Models\Person;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        for ($year = 2019; $year <= 2023; $year++) {
            $personStatus = DB::table('person_status')
                ->select('person_status.*', 'person.callsign', 'ps.callsign as callsign_source')
                ->join('person', 'person.id', 'person_status.person_id')
                ->leftJoin('person as ps', 'person_status.person_source_id', 'ps.id')
                ->where('new_status', Person::DECEASED)
                ->whereYear('person_status.created_at', $year)
                ->get();

            foreach ($personStatus as $person) {
                $callsign = $person->callsign;
                if (preg_match("/\d+$/", $callsign)) {
                    continue;
                }
                $date = Carbon::parse($person->created_at);
                $exists = HandleReservation::where(['handle' => $callsign, 'reservation_type' => HandleReservation::TYPE_DECEASED_PERSON])->exists();
                if ($exists) {
                    continue;
                }
                HandleReservation::insert([
                    'handle' => $callsign,
                    'reservation_type' => HandleReservation::TYPE_DECEASED_PERSON,
                    'expires_on' => (string)($date->clone()->addYears(Person::GRIEVING_PERIOD_YEARS)),
                    'reason' => 'marked deceased by ' . $person->callsign_source . ' on ' . $date->format('Y-m-d'),
                    'updated_at' => (string)$date,
                    'created_at' => (string)$date,
                ]);
            }
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
