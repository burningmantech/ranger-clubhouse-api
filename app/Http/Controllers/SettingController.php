<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;

use App\Models\Setting;

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
        throw new \InvalidArgumentException('Settings cannot be dynamically created');
    }

    /*
     * Update a setting
     */
    public function update(Setting $setting)
    {
        $this->authorize('update', $setting);

        $this->fromRest($setting);

        $changes = $setting->getChangedValues();
        if (!$setting->save()) {
            return $this->restError($setting);
        }

        if (!empty($changes)) {
            $changes['name'] = $setting->name;
            $this->log('setting-update', "setting update $setting->name", $changes);
        }

        return $this->success($setting);
    }

    /**
     * Remove a setting
     *
     */

    public function destroy(Setting $setting)
    {
        throw new \InvalidArgumentException('Settings cannot be destroyed');
    }
}
