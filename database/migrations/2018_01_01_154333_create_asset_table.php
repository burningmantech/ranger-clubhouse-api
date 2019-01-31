<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAssetTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('asset')) {
            return;
        }
        Schema::create(
            'asset', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->enum('description', array('Radio','Vehicle','Gear','Key','Amber'))->nullable();
                $table->string('barcode', 25);
                $table->string('temp_id', 25)->nullable()->comment('placed by staff');
                $table->boolean('perm_assign')->default(0)->comment('assigned "permanently" to a person?');
                $table->timestamp('create_date')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->string('subtype', 25)->nullable();
                $table->string('model', 25)->nullable();
                $table->string('color', 25)->nullable();
                $table->string('style', 25)->nullable();
                $table->string('category', 25)->nullable();
                $table->text('notes', 65535)->nullable();
            }
        );
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('asset');
    }

}
