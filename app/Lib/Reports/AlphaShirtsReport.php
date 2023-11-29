<?php


namespace App\Lib\Reports;


use App\Models\Person;

class AlphaShirtsReport
{
    /**
     * Report on all Alphas and their shirt sizes so the Quartermaster can hand out shirts to
     * newly minted Shiny Pennies.
     *
     * @return mixed
     */

    public static function execute(): array
    {
        $rows = Person::select(
            'id',
            'callsign',
            'status',
            'first_name',
            'preferred_name',
            'last_name',
            'email',
            'tshirt_swag_id',
            'tshirt_secondary_swag_id',
            'long_sleeve_swag_id',
        )
            ->whereIn('status', [Person::ALPHA, Person::PROSPECTIVE])
            ->orderBy('callsign')
            ->with(['tshirt', 'tshirt_secondary', 'long_sleeve'])
            ->get();

        $alphas = [];
        foreach ($rows as $row) {
            $alphas[] = [
                'id' => $row->id,
                'callsign' => $row->callsign,
                'status' => $row->status,
                'first_name' => $row->desired_first_name(),
                'last_name' => $row->last_name,
                'email' => $row->email,
                'teeshirt_size_style' => $row->tshirt->title ?? 'Unknown',
                'tshirt_secondary_size' => $row->tshirt_secondary->title ?? 'Unknown',
                'longsleeveshirt_size_style' => $row->long_sleeve->title ?? 'Unknown',
            ];
        }

        return $alphas;
    }
}