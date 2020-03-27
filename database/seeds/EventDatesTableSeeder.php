<?php

use Illuminate\Database\Seeder;

class EventDatesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('event_dates')->delete();
        
        \DB::table('event_dates')->insert(array (
            0 => 
            array (
                'event_start' => '2014-08-24 10:00:00',
                'event_end' => '2014-09-01 10:00:00',
                'pre_event_start' => '2014-01-01 00:00:00',
                'post_event_end' => '2014-12-31 23:59:59',
                'id' => 1,
                'pre_event_slot_start' => NULL,
                'pre_event_slot_end' => NULL,
            ),
            1 => 
            array (
                'event_start' => '2013-08-25 18:00:00',
                'event_end' => '2013-09-03 00:00:00',
                'pre_event_start' => '2013-01-01 00:00:00',
                'post_event_end' => '2013-12-31 23:59:59',
                'id' => 2,
                'pre_event_slot_start' => NULL,
                'pre_event_slot_end' => NULL,
            ),
            2 => 
            array (
                'event_start' => '2015-08-30 10:00:00',
                'event_end' => '2015-09-07 10:00:00',
                'pre_event_start' => '2015-01-01 00:00:00',
                'post_event_end' => '2015-12-31 23:59:59',
                'id' => 3,
                'pre_event_slot_start' => NULL,
                'pre_event_slot_end' => NULL,
            ),
            3 => 
            array (
                'event_start' => '2016-08-28 00:00:00',
                'event_end' => '2016-09-05 10:00:00',
                'pre_event_start' => '2016-01-01 00:00:00',
                'post_event_end' => '2016-12-31 23:59:59',
                'id' => 4,
                'pre_event_slot_start' => NULL,
                'pre_event_slot_end' => NULL,
            ),
            4 => 
            array (
                'event_start' => '2017-08-27 00:00:00',
                'event_end' => '2017-09-05 00:00:00',
                'pre_event_start' => '2017-01-01 00:00:00',
                'post_event_end' => '2017-12-31 23:59:59',
                'id' => 5,
                'pre_event_slot_start' => NULL,
                'pre_event_slot_end' => NULL,
            ),
            5 => 
            array (
                'event_start' => '2018-08-26 00:00:00',
                'event_end' => '2018-09-04 00:00:00',
                'pre_event_start' => '2018-01-01 00:00:00',
                'post_event_end' => '2018-12-31 23:59:59',
                'id' => 6,
                'pre_event_slot_start' => NULL,
                'pre_event_slot_end' => NULL,
            ),
            6 => 
            array (
                'event_start' => '2019-08-25 00:01:00',
                'event_end' => '2019-09-02 23:59:59',
                'pre_event_start' => '2019-01-01 00:00:00',
                'post_event_end' => '2019-12-31 23:59:00',
                'id' => 7,
                'pre_event_slot_start' => '2019-08-15 00:00:00',
                'pre_event_slot_end' => '2019-08-23 00:00:00',
            ),
            7 => 
            array (
                'event_start' => '2020-08-30 00:01:00',
                'event_end' => '2020-09-07 23:59:00',
                'pre_event_start' => '2020-01-01 00:00:00',
                'post_event_end' => '2020-12-31 23:59:00',
                'id' => 8,
                'pre_event_slot_start' => '2020-08-20 00:00:00',
                'pre_event_slot_end' => '2020-08-28 00:00:00',
            ),
        ));
        
        
    }
}