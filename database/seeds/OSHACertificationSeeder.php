<?php

namespace Database\Seeders;

use App\Models\Certification;
use App\Models\PersonCertification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OSHACertificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $osha10 = Certification::create([
            'title' => 'OSHA-10',
            'is_lifetime_certification' => true,
        ]);

        $osha30 = Certification::create([
            'title' => 'OSHA-30',
            'is_lifetime_certification' => true,
        ]);

        $people = DB::table('person')
            ->select('id', 'osha10', 'osha30')
            ->where(function ($w) {
                $w->where('osha10', true);
                $w->orWhere('osha30', true);
            })->get();

        foreach ($people as $person) {
            if ($person->osha10) {
                PersonCertification::create([
                    'person_id' => $person->id,
                    'certification_id' => $osha10->id,
                ]);
            }

            if ($person->osha30) {
                PersonCertification::create([
                    'person_id' => $person->id,
                    'certification_id' => $osha30->id,
                ]);
            }
        }
    }
}
