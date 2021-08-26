<?php

namespace App\Http\Controllers;

use App\Models\ErrorLog;
use App\Models\Role;
use App\Models\Setting;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;

class SettingController extends ApiController
{
    public function index()
    {
        $this->authorize('index', Setting::class);

        $settings = Setting::findAll();

        if (!$this->userHasRole(Role::TECH_NINJA)) {
            // Redact any credentials
            foreach ($settings as $setting) {
                if ($setting->is_credential) {
                    $setting->value = null;
                }
            }
        }

        return $this->success($settings, null, 'setting');
    }

    /**
     * Show a setting. Redact the value if a credential and the user is not a Tech Ninja.
     *
     * @param Setting $setting
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(Setting $setting)
    {
        $this->authorize('show', Setting::class);
        if ($setting->is_credential && !$this->userHasRole(Role::TECH_NINJA)) {
            $setting->value = null;
        }

        return $this->success($setting);
    }

    /**
     * Create a new setting
     */

    public function store()
    {
        throw new InvalidArgumentException('Settings cannot be dynamically created');
    }

    /**
     * Update a setting
     * @param Setting $setting
     * @return JsonResponse
     * @throws AuthorizationException
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
     * @throws InvalidArgumentException
     */

    public function destroy(Setting $setting)
    {
        throw new InvalidArgumentException('Settings cannot be destroyed');
    }
}
