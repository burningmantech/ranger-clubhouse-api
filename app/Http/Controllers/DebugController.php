<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use JetBrains\PhpStorm\NoReturn;

class DebugController extends ApiController
{
    /**
     * Used to verify the web server timeout is set to an acceptable amount.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function sleepTest(): JsonResponse
    {
        $this->authorize('isAdmin');

        $params = request()->validate([
            'time' => 'required|integer'
        ]);

        sleep((int)$params['time']);

        return $this->success();
    }

    /**
     * Benchmark database
     */

    public function dbTest(): JsonResponse
    {
        $this->authorize('isAdmin');

        $timeStart = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            DB::statement("SELECT 1 FROM person WHERE id=1");
        }
        $timeTotal = microtime(true) - $timeStart;

        // return time in milliseconds
        return response()->json(['time' => (int)($timeTotal * 1000)]);
    }

    /**
     * Show PHP ini values
     *
     * @throws AuthorizationException
     */

    #[NoReturn] public function phpInfo(): void
    {
        $this->authorize('isAdmin');
        phpinfo();
        die();
    }

    /**
     * Return the CPU info
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function cpuInfo(): JsonResponse
    {
        $this->authorize('isAdmin');
        return response()->json(['cpuinfo' => file('/proc/cpuinfo')]);
    }
}
