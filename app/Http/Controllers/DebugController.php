<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;
use App\Models\Role;
use DB;


class DebugController extends ApiController
{
    public function __construct()
    {
        parent::__construct();

        if (app()->runningInConsole()) {
            return;
        }

        if (!$this->userHasRole(Role::ADMIN)) {
            $this->notPermitted('Not allowed');
        }
    }

    /*
     * Used to verify the web server timeout is set to an acceptable amount.
     */

    public function sleepTest()
    {
        $params = request()->validate([
            'time'  => 'required|integer'
        ]);

        sleep((int)$params['time']);

        return $this->success();
    }

    /*
     * Benchmark database
     */

     public function dbTest()
     {
         $timeStart = microtime(true);
         for ($i = 0; $i < 1000; $i++) {
             DB::statement("SELECT 1 FROM person WHERE id=1");
         }
         $timeTotal = microtime(true) - $timeStart;

         // return time in milliseconds
         return response()->json([ 'time' => (int)($timeTotal * 1000)]);
     }

     /*
      * Show PHP ini values
      */

      public function phpInfo() {
          phpinfo();
          die();
      }

      public function cpuInfo() {
          return response()->json([ 'cpuinfo' => file('/proc/cpuinfo') ]);
      }
}
