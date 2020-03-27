<?php

use Illuminate\Database\Seeder;

class RoleTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('role')->delete();

        DB::table('role')->insert(array(
            0 =>
                array(
                    'id' => 1,
                    'title' => 'Admin (Full Access)',
                    'new_user_eligible' => 0,
                ),
            1 =>
                array(
                    'id' => 2,
                    'title' => 'View Personal Info',
                    'new_user_eligible' => 0,
                ),
            2 =>
                array(
                    'id' => 3,
                    'title' => 'View Email Address',
                    'new_user_eligible' => 0,
                ),
            3 =>
                array(
                    'id' => 4,
                    'title' => 'Grant/Revoke Position',
                    'new_user_eligible' => 0,
                ),
            4 =>
                array(
                    'id' => 5,
                    'title' => 'Edit Access Documents',
                    'new_user_eligible' => 0,
                ),
            5 =>
                array(
                    'id' => 6,
                    'title' => 'Edit BMIDs',
                    'new_user_eligible' => 0,
                ),
            6 =>
                array(
                    'id' => 7,
                    'title' => 'Edit Slots',
                    'new_user_eligible' => 0,
                ),
            7 =>
                array(
                    'id' => 11,
                    'title' => 'Login (Schedule Mode)',
                    'new_user_eligible' => 1,
                ),
            8 =>
                array(
                    'id' => 12,
                    'title' => 'Login (Management Mode)',
                    'new_user_eligible' => 0,
                ),
            9 =>
                array(
                    'id' => 13,
                    'title' => 'Intake (Records Mgmt)',
                    'new_user_eligible' => 0,
                ),
            10 =>
                array(
                    'id' => 31,
                    'title' => 'Edit Person Info',
                    'new_user_eligible' => 1,
                ),
            11 =>
                array(
                    'id' => 32,
                    'title' => 'Edit Person Schedule',
                    'new_user_eligible' => 1,
                ),
            12 =>
                array(
                    'id' => 101,
                    'title' => 'Mentor (Records Mgmt)',
                    'new_user_eligible' => 0,
                ),
            13 =>
                array(
                    'id' => 102,
                    'title' => 'Training (Verification)',
                    'new_user_eligible' => 0,
                ),
            14 =>
                array(
                    'id' => 103,
                    'title' => 'Volunteer Coordinator',
                    'new_user_eligible' => 0,
                ),
            15 =>
                array(
                    'id' => 104,
                    'title' => 'ART Trainer',
                    'new_user_eligible' => 0,
                ),
            16 =>
                array(
                    'id' => 105,
                    'title' => 'Megaphone',
                    'new_user_eligible' => 0,
                ),
            17 =>
                array(
                    'id' => 106,
                    'title' => 'Timesheet Management',
                    'new_user_eligible' => 0,
                ),
        ));


    }
}