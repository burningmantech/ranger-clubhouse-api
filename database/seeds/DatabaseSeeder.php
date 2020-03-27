<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call(RoleTableSeeder::class);
        $this->call(AlertTableSeeder::class);
        $this->call(PositionTableSeeder::class);
        $this->call(SettingTableSeeder::class);
        $this->call(EventDatesTableSeeder::class);
        $this->call(HelpTableSeeder::class);

        // $this->call(AdminPersonTableSeeder::class);
    }
}
