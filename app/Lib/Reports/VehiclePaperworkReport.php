<?php

namespace App\Lib\Reports;

use Illuminate\Support\Facades\DB;

class VehiclePaperworkReport
{

    /**
     * Report on people who either have signed the motorpool agreement, and/or have org insurance.
     *
     * @return array
     */

    public static function execute(): array
    {
        return DB::table('person')
            ->select(
                'id',
                'callsign',
                'status',
                DB::raw('IFNULL(person_event.signed_motorpool_agreement, false) AS signed_motorpool_agreement'),
                DB::raw('IFNULL(person_event.org_vehicle_insurance, false) AS org_vehicle_insurance')
            )->join('person_event', function ($j) {
                $j->on('person_event.person_id', 'person.id');
                $j->where('person_event.year', current_year());
                $j->where(function ($q) {
                    $q->where('signed_motorpool_agreement', true);
                    $q->orWhere('org_vehicle_insurance', true);
                });
            })
            ->orderBy('callsign')
            ->get()
            ->toArray();
    }

}