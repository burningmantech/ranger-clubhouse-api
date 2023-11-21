<?php

namespace App\Http\Controllers;

use App\Lib\ClubhouseCache;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SettingController extends ApiController
{
    /**
     * Retrieve all settings. Redact credentials if user is not a tech ninja.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function index(): JsonResponse
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

    public function show(Setting $setting): JsonResponse
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
     *
     * @param Setting $setting
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */
    public function update(Setting $setting): JsonResponse
    {
        $this->authorize('update', $setting);
        prevent_if_ghd_server('Settings update');

        $this->fromRest($setting);

        $setting->auditReason = "setting update $setting->name";
        if (!$setting->save()) {
            return $this->restError($setting);
        }

        // Dump the entire app cache just in case
        ClubhouseCache::flush();

        Setting::setCached($setting->name, $setting->value);
        Setting::kickQueues();

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
