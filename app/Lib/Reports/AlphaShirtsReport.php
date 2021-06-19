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

    public static function execute()
    {
        return Person::select(
            'id',
            'callsign',
            'status',
            'first_name',
            'last_name',
            'email',
            'longsleeveshirt_size_style',
            'teeshirt_size_style'
        )
            ->whereIn('status', [Person::ALPHA, Person::PROSPECTIVE])
            ->orderBy('callsign')
            ->get();

    }
}