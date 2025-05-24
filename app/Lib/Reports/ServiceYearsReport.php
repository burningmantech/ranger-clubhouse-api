<?php


namespace App\Lib\Reports;


use App\Models\Person;
use App\Models\Position;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ServiceYearsReport
{
    /**
     * Build a Service Years report
     *
     * @param bool $showAll false if only report on active status rangers, otherwise everyone.
     * @return array|Collection
     */

    public static function execute(): array
    {
        $rows = Person::select(
                'id',
                'callsign',
                'first_name',
                'preferred_name',
                'last_name',
                'status',
                'years_of_service'
            )
            ->where('status', Person::ACTIVE)
            ->get()
            ->keyBy('id');

        if (empty($rows)) {
            return [];
        }

        $people = [];
        foreach ($rows as $row) {
            $tally = count($row->years_of_service);
            $people[] = [
                'id' => $row->id,
                'callsign' => $row->callsign,
                'status' => $row->status,
                'first_name' => $row->desired_first_name(),
                'last_name' => $row->last_name,
                'years_of_service' => $row->years_of_service,
                'first_year' => $tally ? min($row->years_of_service) : 'n/a',
                'last_year' => $tally ? max($row->years_of_service) : 'n/a',
                'years' => $tally
            ];
        }

        usort($people, function ($a, $b) {
            if ($a['years'] == $b['years']) {
                return strcasecmp($a['years'], $b['years']);
            } else {
                return $b['years'] - $a['years'];
            }
        });

        return collect($people)->groupBy('years')->map(function ($folks, $year) {
            return ['years' => $year, 'people' => $folks];
        })->values()->toArray();
    }
}