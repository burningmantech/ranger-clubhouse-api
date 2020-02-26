<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddIntakeRole extends Migration
{
    /**
     * Add the new INTAKE role
     *
     * @return void
     */
    public function up()
    {
        DB::table('role')->insert([ 'id' => 13, 'title' => 'Intake Management', 'new_user_eligible' => false ]);
        //
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('role')->where('id', 13)->delete();
    }
}
