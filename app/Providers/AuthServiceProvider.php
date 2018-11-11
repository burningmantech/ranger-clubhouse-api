<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

use App\Models\AccessDocument;
use App\Models\AccessDocumentDelivery;
use App\Models\Alert;
use App\Models\AlertPerson;
use App\Models\Asset;
use App\Models\ManualReview;
use App\Models\Person;
use App\Models\PersonMessage;
use App\Models\Position;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Slot;
use App\Models\Timesheet;
use App\Models\TimesheetMissing;

use App\Policies\AccessDocumentDeliveryPolicy;
use App\Policies\AccessDocumentPolicy;
use App\Policies\AlertPolicy;
use App\Policies\AlertPersonPolicy;
use App\Policies\AssetPolicy;
use App\Policies\ManualReviewPolicy;
use App\Policies\PersonMessagePolicy;
use App\Policies\PersonPolicy;
use App\Policies\PositionPolicy;
use App\Policies\RolePolicy;
use App\Policies\SchedulePolicy;
use App\Policies\SlotPolicy;
use App\Policies\TimesheetPolicy;
use App\Policies\TimesheetMissingPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        AccessDocument::class => AccessDocumentPolicy::class,
        AccessDocumentDelivery::class => AccessDocumentDeliveryPolicy::class,
        Alert::class => AlertPolicy::class,
        AlertPerson::class => AlertPersonPolicy::class,
        Asset::class  => AssetPolicy::class,
        ManualReview::class => ManualReviewPolicy::class,
        Person::class => PersonPolicy::class,
        PersonMessage::class => PersonMessagePolicy::class,
        Position::class => PositionPolicy::class,
        Role::class => RolePolicy::class,
        Schedule::class => SchedulePolicy::class,
        Slot::class => SlotPolicy::class,
        Timesheet::class => TimesheetPolicy::class,
        TimesheetMissing::class => TimesheetMissingPolicy::class,

        'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::resource('person', 'PersonPolicy');
    }
}
