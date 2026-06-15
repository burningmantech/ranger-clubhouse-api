<?php

namespace Tests\Feature;

use App\Models\Alert;
use Tests\TestCase;

class AlertAttributeTest extends TestCase
{
    /**
     * The sms_only accessor is true only for the ON_SHIFT alert.
     */
    public function test_sms_only_attribute_is_true_for_on_shift(): void
    {
        $alert = new Alert();
        $alert->id = Alert::ON_SHIFT;

        $this->assertTrue($alert->sms_only);
    }

    /**
     * The sms_only accessor is false for non-ON_SHIFT alerts.
     */
    public function test_sms_only_attribute_is_false_otherwise(): void
    {
        $alert = new Alert();
        $alert->id = Alert::SHIFT_CHANGE;

        $this->assertFalse($alert->sms_only);
    }

    /**
     * The email_only accessor is true for RANGER_CONTACT.
     */
    public function test_email_only_attribute_is_true_for_ranger_contact(): void
    {
        $alert = new Alert();
        $alert->id = Alert::RANGER_CONTACT;

        $this->assertTrue($alert->email_only);
    }

    /**
     * The email_only accessor is true for MENTOR_CONTACT.
     */
    public function test_email_only_attribute_is_true_for_mentor_contact(): void
    {
        $alert = new Alert();
        $alert->id = Alert::MENTOR_CONTACT;

        $this->assertTrue($alert->email_only);
    }

    /**
     * The email_only accessor is false for other alerts.
     */
    public function test_email_only_attribute_is_false_otherwise(): void
    {
        $alert = new Alert();
        $alert->id = Alert::ON_SHIFT;

        $this->assertFalse($alert->email_only);
    }

    /**
     * The no_opt_out accessor is true for the EMERGENCY_BROADCAST alert.
     */
    public function test_no_opt_out_attribute_is_true_for_emergency_broadcast(): void
    {
        $alert = new Alert();
        $alert->id = Alert::EMERGENCY_BROADCAST;

        $this->assertTrue($alert->no_opt_out);
    }

    /**
     * The no_opt_out accessor is false for non-emergency alerts.
     */
    public function test_no_opt_out_attribute_is_false_otherwise(): void
    {
        $alert = new Alert();
        $alert->id = Alert::TICKETING;

        $this->assertFalse($alert->no_opt_out);
    }

    /**
     * The computed attributes are appended when the model is serialized to an array.
     */
    public function test_computed_attributes_are_serialized(): void
    {
        $alert = new Alert();
        $alert->id = Alert::ON_SHIFT;

        $array = $alert->toArray();

        $this->assertArrayHasKey('sms_only', $array);
        $this->assertArrayHasKey('email_only', $array);
        $this->assertArrayHasKey('no_opt_out', $array);
        $this->assertTrue($array['sms_only']);
        $this->assertFalse($array['email_only']);
        $this->assertFalse($array['no_opt_out']);
    }
}
