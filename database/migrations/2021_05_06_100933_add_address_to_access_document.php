<?php

use App\Models\AccessDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddAddressToAccessDocument extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('access_document', function (Blueprint $table) {
            $table->enum('delivery_method', [ 'none', 'postal', 'will_call', 'email' ])->default('none')->nullable(false);
            $table->string('street1')->default('');
            $table->string('street2')->default('');
            $table->string('city')->default('');
            $table->string('state')->default('');
            $table->string('postal_code')->default('');
            $table->string('country')->default('');
        });

        DB::table('access_document')
            ->where('type', AccessDocument::STAFF_CREDENTIAL)
            ->whereIn('status', [ AccessDocument::USED, AccessDocument::SUBMITTED, AccessDocument::QUALIFIED])
            ->update([ 'delivery_method' => AccessDocument::DELIVERY_WILL_CALL]);

        DB::table('access_document')
            ->whereIn('type', [ AccessDocument::WAP, AccessDocument::WAPSO ])
            ->whereIn('status', [ AccessDocument::USED, AccessDocument::SUBMITTED, AccessDocument::QUALIFIED])
            ->update([ 'delivery_method' => AccessDocument::DELIVERY_EMAIL]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('access_document', function (Blueprint $table) {
            $table->dropColumn('delivery_method');
            $table->dropColumn('street1');
            $table->dropColumn('street2');
            $table->dropColumn('city');
            $table->dropColumn('state');
            $table->dropColumn('postal_code');
            $table->dropColumn('country');
        });
    }
}
