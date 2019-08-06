<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use Illuminate\Support\Facades\DB;


class AllowEmojisMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tables = [
            'broadcast_message',
            'contact_log',
            'help',
            'motd',
            'person_language',
            'person_message',
            'person',
            'slot'
        ];

        DB::beginTransaction();
        // Fix the person create_date for person record created prior to 2010.
        DB::statement("ALTER TABLE `person` MODIFY COLUMN `create_date` DATETIME NULL DEFAULT CURRENT_TIMESTAMP");
        DB::statement("UPDATE  `person` SET `create_date` = NULL WHERE CAST(`create_date` AS CHAR(20)) = '0000-00-00 00:00:00'");
        foreach ($tables as $table) {
            DB::statement("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_bin");
        }
        DB::commit();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
