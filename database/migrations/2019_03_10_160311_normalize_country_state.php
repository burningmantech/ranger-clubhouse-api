<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class NormalizeCountryState extends Migration
{
    const COUNTRY_MAP = [
        ''                  => 'US',
        'Australia'         => 'AU',
        'CANADA'            => 'CA',
        'Ecuador'           => 'EC',
        'England / Germany' => 'GB',
        'Germany'           => 'DE',
        'Indonesia'         => 'ID',
        'Israel'            => 'IL',
        'South Africa'      => 'SA',
        'Sweden'            => 'SE',
        'Switzerland'       => 'CH',
        'UK'                => 'GB',
        'United Kingdom'    => 'GB',
        'United States'     => 'US',
        'USA'               => 'US',

        // Folks entering the 'county' instead of 'country'
        'Jefferson'         => 'US',
        'Murka'             => 'US',

        // Gotta love test data.
        'test'              => 'US',
        'e'                 => 'US',
        'enter later'       => 'US',
    ];

    // Fix 2 character australian hackery
    const AUSTRALIAN_STATES = [
        ''   => 'QLQ',
        'N/' => 'NSW',
        'NS' => 'NSW',
        'QL' => 'QLD',
        'VC' => 'VIC',
        'VI' => 'VIC',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach (self::COUNTRY_MAP as $country => $iso) {
            DB::statement("UPDATE person SET country='$iso' WHERE country='$country'");
        }

        // Upper case state/province for US, Canada, and Australia
        DB::statement("UPDATE person SET state=upper(state) WHERE country IN ('US', 'CA', 'AU')");

        // Fixup Australian states/territories
        foreach (self::AUSTRALIAN_STATES as $state => $correct) {
            DB::statement("UPDATE person SET state='$correct' WHERE country='AU' AND state='$state'");
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
