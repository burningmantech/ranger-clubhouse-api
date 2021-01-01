<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Role;

class AddLmOnPlayaToRoles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Role::find(Role::MANAGE_ON_PLAYA)) {
            Role::insert([ 'id' => Role::MANAGE_ON_PLAYA, 'title' => 'Login Manage On Playa']);
        }
        if (!Role::find(Role::TECH_NINJA)) {
            Role::insert([ 'id' => Role::TECH_NINJA, 'title' => 'Tech Ninja']);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Role::whereIn('id', [Role::MANAGE_ON_PLAYA, Role::TECH_NINJA])->delete();
    }
}
