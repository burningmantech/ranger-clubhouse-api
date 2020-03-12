<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Models\ErrorLog;

use Illuminate\Support\Facades\DB;

class TaskLog extends ApiModel
{
    public $table = 'task_log';
    protected $primaryKey = 'name';
    protected $guarded = [];

    const DEFAULT_LAST_TIME = 2;

    /**
     * A hack replacement for Laravel's task onOneServer() method which prevents a task
     * from being run on more than one API instance. onOneServer() requires an external atomic cache
     * (redis, memcache) etc to use.
     *
     * Recommended usage is:
     *
     * if (TaskLog::attemptToStart('clubhouse-hourly-report') == true) {
     *    do something awesome!
     * } else {
     *    $this->info('command is already running.');
     *    return;
     * }
     *
     * @param string $name Name of the task / command to check
     * @param int $lastMins how many minutes ago when the task is considered not running
     * @return bool true if the task has not been run within PREVENT_RUN_MINS minutes.
     */

    public static function attemptToStart(string $name, int $lastMins = self::DEFAULT_LAST_TIME) : bool
    {
        // Attempt to lock the table
        DB::unprepared('LOCK TABLES task_log WRITE');
        try {
            // Check to see when the task last run
            $task = self::select(
                'task_log.*',
                DB::raw("(started_at >= DATE_SUB(NOW(), INTERVAL $lastMins MINUTE)) AS is_running")
            )->where('name', $name)->first();

            if ($task) {
                if ($task->is_running) {
                    // Still running
                    DB::unprepared('UNLOCK TABLES');
                    return false;
                }
            } else {
                $task = new TaskLog(['name' => $name]);
            }

            $task->started_at = now();
            $task->save();
            DB::unprepared('UNLOCK TABLES');
            return true;
        } catch (\Exception $e) {
            DB::unprepared('UNLOCK TABLES');
            ErrorLog::recordException($e, 'task-log-exception');
            return false;
        }
    }
}