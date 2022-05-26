<?php

use App\Models\Role;
use Database\Seeders\OSHACertificationSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */

    public function up()
    {
        Schema::create('certification', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable(false);
            $table->string('sl_title')->nullable(true);
            $table->boolean('on_sl_report')->nullable(false)->default(false);
            $table->text('description')->nullable(true);
            $table->boolean('is_lifetime_certification')->nullable(false)->default(false);
            $table->timestamps();
            $table->index('on_sl_report');
        });

        Schema::create('person_certification', function (Blueprint $table) {
            $table->id();
            $table->integer('person_id')->nullable(false);
            $table->integer('recorder_id')->nullable(true);
            $table->integer('certification_id')->nullable(false);
            $table->date('issued_on')->nullable(true);
            $table->date('trained_on')->nullable(true);
            $table->string('card_number')->nullable(true);
            $table->text('notes')->nullable(true);
            $table->timestamps();

            $table->index(['person_id']);
            $table->index(['certification_id']);
            $table->index(['person_id', 'certification_id']);
        });

        Role::insertOrIgnore([
            'id' => Role::CERTIFICATION_MGMT,
            'title' => 'Certification Mgmt',
            'new_user_eligible' => false,
        ]);

        (new OSHACertificationSeeder())->run();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('certification');
        Schema::dropIfExists('person_certification');
        Role::find(Role::CERTIFICATION_MGMT)?->delete();
    }
};
