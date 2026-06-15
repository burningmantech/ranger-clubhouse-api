<?php

namespace Tests\Feature;

use App\Models\Setting;
use Tests\TestCase;

class SettingAttributeTest extends TestCase
{
    /**
     * The type accessor resolves from the DESCRIPTIONS table keyed by name.
     */
    public function test_type_attribute_resolves_from_descriptions(): void
    {
        $setting = new Setting(['name' => 'AdminEmail']);

        $this->assertSame(Setting::TYPE_EMAIL, $setting->type);
    }

    /**
     * The description accessor resolves from the DESCRIPTIONS table keyed by name.
     */
    public function test_description_attribute_resolves_from_descriptions(): void
    {
        $setting = new Setting(['name' => 'AdminEmail']);

        $this->assertSame('Ranger Tech Team Email Address', $setting->description);
    }

    /**
     * The options accessor returns the configured options array.
     */
    public function test_options_attribute_returns_options_array(): void
    {
        $setting = new Setting(['name' => 'TicketingPeriod']);

        $this->assertIsArray($setting->options);
        $this->assertSame('offseason', $setting->options[0][0]);
    }

    /**
     * The options accessor returns null when the setting has no options.
     */
    public function test_options_attribute_is_null_when_absent(): void
    {
        $setting = new Setting(['name' => 'AdminEmail']);

        $this->assertNull($setting->options);
    }

    /**
     * The is_credential accessor reflects the credential flag.
     */
    public function test_is_credential_attribute_is_true_for_credentials(): void
    {
        $setting = new Setting(['name' => 'TwilioAuthToken']);

        $this->assertTrue($setting->is_credential);
    }

    /**
     * The is_credential accessor defaults to false for non-credentials.
     */
    public function test_is_credential_attribute_defaults_to_false(): void
    {
        $setting = new Setting(['name' => 'AdminEmail']);

        $this->assertFalse($setting->is_credential);
    }

    /**
     * All accessors fall back gracefully for an unknown setting name.
     */
    public function test_accessors_fall_back_for_unknown_setting(): void
    {
        $setting = new Setting(['name' => 'NotARealSetting']);

        $this->assertNull($setting->type);
        $this->assertNull($setting->description);
        $this->assertNull($setting->options);
        $this->assertFalse($setting->is_credential);
    }

    /**
     * The options accessor is not object-cached: it tracks a changed name.
     */
    public function test_options_attribute_is_not_object_cached(): void
    {
        $setting = new Setting(['name' => 'AdminEmail']);

        $this->assertNull($setting->options);

        $setting->name = 'TicketingPeriod';

        $this->assertIsArray($setting->options);
    }

    /**
     * The appended attributes are present when the model is serialized to an array.
     */
    public function test_appended_attributes_are_serialized(): void
    {
        $setting = new Setting(['name' => 'AdminEmail']);

        $array = $setting->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('is_credential', $array);
        $this->assertArrayHasKey('options', $array);
        $this->assertSame(Setting::TYPE_EMAIL, $array['type']);
        $this->assertFalse($array['is_credential']);
    }
}
