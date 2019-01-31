<?php

use Illuminate\Database\Seeder;

class AlertTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('alert')->delete();
        
        \DB::table('alert')->insert(array (
            0 => 
            array (
                'id' => 1,
                'title' => 'Shift Changes',
                'description' => 'Be notified when a shift has been rescheduled, or canceled.
',
                'on_playa' => 1,
                'created_at' => '2018-07-28 15:42:55',
                'updated_at' => '2018-07-29 20:05:15',
            ),
            1 => 
            array (
                'id' => 2,
                'title' => 'Shift Muster',
                'description' => 'More people are needed for a shift, or leadership/cadre messages.',
                'on_playa' => 1,
                'created_at' => '2018-07-28 15:42:55',
                'updated_at' => '2018-07-28 15:42:55',
            ),
            2 => 
            array (
                'id' => 3,
                'title' => 'Emergency Broadcast',
                'description' => 'All hands on deck. Cannot be opted out from.',
                'on_playa' => 1,
                'created_at' => '2018-07-28 15:42:55',
                'updated_at' => '2018-07-28 15:42:55',
            ),
            3 => 
            array (
                'id' => 4,
                'title' => 'Ranger Socials',
                'description' => 'Shiny Pin Ceremony. Tuesday Social. Other social events.',
                'on_playa' => 1,
                'created_at' => '2018-07-28 15:42:55',
                'updated_at' => '2018-07-29 20:02:14',
            ),
            4 => 
            array (
                'id' => 5,
                'title' => 'Ticketing',
                'description' => 'Event tickets and Work Access Passes are available to be claimed. Event ticket acceptance windows closings. ',
                'on_playa' => 0,
                'created_at' => '2018-07-28 15:42:55',
                'updated_at' => '2018-07-28 15:42:55',
            ),
            5 => 
            array (
                'id' => 6,
                'title' => 'On Shift',
                'description' => 'When on shift, SMS messages may be sent notifying a radio repeater is down, or radio channel/city split is about to happen.',
                'on_playa' => 1,
                'created_at' => '2018-07-28 15:42:55',
                'updated_at' => '2018-07-28 15:42:55',
            ),
            6 => 
            array (
                'id' => 7,
                'title' => 'Training',
                'description' => 'Training now open. New training sessions available.',
                'on_playa' => 0,
                'created_at' => '2018-07-28 15:42:55',
                'updated_at' => '2018-07-28 15:42:55',
            ),
            7 => 
            array (
                'id' => 8,
                'title' => 'Clubhouse Messages',
                'description' => 'Be notified when a Clubhouse message is sent to you during the event.',
                'on_playa' => 1,
                'created_at' => '2018-07-28 15:42:55',
                'updated_at' => '2018-07-28 15:42:55',
            ),
            8 => 
            array (
                'id' => 9,
                'title' => 'Clubhouse Messages',
                'description' => 'Be notified when a Clubhouse message is sent to you before or after the event',
                'on_playa' => 0,
                'created_at' => '2018-07-28 15:42:55',
                'updated_at' => '2018-07-28 15:42:55',
            ),
            9 => 
            array (
                'id' => 10,
                'title' => 'Shift Changes',
                'description' => 'Before the event happens, you may be notified about a pre-event or on playa shift that has been recheduled or canceled.',
                'on_playa' => 0,
                'created_at' => '2018-07-30 17:33:00',
                'updated_at' => '2018-07-30 17:33:00',
            ),
            10 => 
            array (
                'id' => 11,
                'title' => 'Shift Muster',
                'description' => 'Before the event happens, you may be notified about a pre-event or on playa shift that is understaffed.',
                'on_playa' => 0,
                'created_at' => '2018-07-30 17:33:00',
                'updated_at' => '2018-07-30 17:33:00',
            ),
            11 => 
            array (
                'id' => 12,
                'title' => 'Ranger Contact Email',
                'description' => 'Your fellow Rangers, using the "Contact Ranger" interface, may send an email to you via the Clubhouse. Your email address is not reveal to Ranger sending the message.',
                'on_playa' => 0,
                'created_at' => '2018-10-29 14:12:39',
                'updated_at' => '2018-10-29 14:12:39',
            ),
            12 => 
            array (
                'id' => 13,
                'title' => 'Mentor Contact Email',
                'description' => 'Your Mentors may contact you via an email sent by the Clubhouse. Your email address is not reveal to your Mentor.',
                'on_playa' => 0,
                'created_at' => '2018-10-29 14:12:39',
                'updated_at' => '2018-10-29 14:12:39',
            ),
        ));
        
        
    }
}