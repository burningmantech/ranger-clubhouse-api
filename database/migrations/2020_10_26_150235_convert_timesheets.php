<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ConvertTimesheets extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE timesheet CHANGE COLUMN review_status review_status enum('approved', 'rejected', 'pending', 'unverified', 'verified') default 'unverified'");
        DB::statement("UPDATE timesheet SET review_status='verified' WHERE verified is true ");
        DB::statement("UPDATE timesheet SET review_status='unverified' WHERE verified is false and review_status='pending'");
        DB::statement("ALTER TABLE timesheet_log CHANGE COLUMN action action enum('unconfirmed', 'confirmed', 'signon', 'signoff', 'created', 'update', 'delete', 'review', 'verify', 'unverified') not null");
        DB::statement("ALTER TABLE timesheet_log CHANGE COLUMN created_at created_at DATETIME NOT NULL");
        DB::statement("ALTER TABLE timesheet_log ADD COLUMN year INTEGER NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE timesheet_log ADD COLUMN data LONGTEXT");
        DB::statement("ALTER TABLE timesheet DROP COLUMN verified");
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
