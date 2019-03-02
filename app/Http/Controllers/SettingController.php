<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;

use App\Models\Setting;

class SettingController extends ApiController
{
    public function index()
    {
        $this->authorize('index', Setting::class);

        $settings = Setting::findAll();
        foreach ($settings as $setting) {
            $setting->config_value = config(strpos($setting->name, '.') === false ? ('clubhouse'.$setting->name) : $setting->name);
        }
        return $this->success($settings, null, 'setting');
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
        $this->authorize('create', Setting::class);

        $setting = new Setting;
        $this->fromRest($setting);

        if (!$setting->save()) {
            return $this->restError($setting);
        }

        return $this->success($setting);
    }

    /*
     * Update a setting
     */
    public function update(Setting $setting)
    {
        $this->authorize('update', $setting);

        $this->fromRest($setting);

        if (!$setting->save()) {
            return $this->restError($setting);
        }

        return $this->success($setting);
    }

    /**
     * Remove a setting
     *
     */

    public function destroy(Setting $setting)
    {
        $this->authorize('destroy', $setting);

        $setting->delete();

        return $this->restDeleteSuccess();
    }
}
