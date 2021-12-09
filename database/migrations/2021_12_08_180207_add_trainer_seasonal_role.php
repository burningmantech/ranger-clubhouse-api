<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

class AddTrainerSeasonalRole extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Role::insertOrIgnore(['id' => Role::TRAINER_SEASONAL, 'title' => 'Training Seasonal']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Role::where('id', Role::TRAINER_SEASONAL)->delete();
    }
}
