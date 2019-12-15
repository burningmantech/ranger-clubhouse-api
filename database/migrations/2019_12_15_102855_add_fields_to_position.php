<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

class AddFieldsToPosition extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('position', function (Blueprint $table) {
            $table->string('slot_full_email', 200)->nullable();
            $table->boolean('prevent_multiple_enrollments')->default(false);
        });

        // slot_full_email replaces TrainingFullEmail setting
        Setting::where('name', 'TrainingFullEmail')->delete();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('position', function (Blueprint $table) {
            $table->dropColumn([ 'slot_full_email', 'prevent_multiple_enrollments' ]);
        });
    }
}
