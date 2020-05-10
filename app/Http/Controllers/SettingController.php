<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;

use App\Models\ErrorLog;
use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;

class SettingController extends ApiController
{
    public function index()
    {
        $this->authorize('index', Setting::class);
        return $this->success(Setting::findAll(), null, 'setting');
    }

    public function show(Setting $setting)
    {
        $this->authorize('show', Setting::class);
        return $this->success($setting);
    }

    /*
     * Create a new setting
     */

    public function store()
    {
        throw new InvalidArgumentException('Settings cannot be dynamically created');
    }

    /*
     * Update a setting
     */
    public function update(Setting $setting)
    {
        $this->authorize('update', $setting);
        $this->fromRest($setting);

        $setting->auditReason = "setting update $setting->name";
        if (!$setting->save()) {
            return $this->restError($setting);
        }

        if (!app()->isLocal()) {
            try {
                // Kick the queue workers to pick up the new settings
                Artisan::call('queue:restart');
            } catch (Exception $e) {
                ErrorLog::recordException($e, 'setting-queue-restart-exception');
            }
        }

        return $this->success($setting);
    }

    /**
     * Remove a setting
     * @param Setting $setting
     * @throws \InvalidArgumentException
     */
    public function destroy(Setting $setting)
    {
        throw new \InvalidArgumentException('Settings cannot be destroyed');
    }
}
