<?php

namespace App\Providers;

use App\Lib\Agreements;
use App\Models\AccessDocument;
use App\Models\ActionLog;
use App\Models\Alert;
use App\Models\AlertPerson;
use App\Models\Asset;
use App\Models\AssetAttachment;
use App\Models\AssetPerson;
use App\Models\Award;
use App\Models\Bmid;
use App\Models\Broadcast;
use App\Models\Document;
use App\Models\ErrorLog;
use App\Models\EventDate;
use App\Models\HandleReservation;
use App\Models\Help;
use App\Models\Motd;
use App\Models\Person;
use App\Models\PersonAward;
use App\Models\PersonEvent;
use App\Models\PersonMentor;
use App\Models\PersonMessage;
use App\Models\PersonOnlineTraining;
use App\Models\PersonPhoto;
use App\Models\PersonPog;
use App\Models\PersonPositionLog;
use App\Models\PersonSwag;
use App\Models\Position;
use App\Models\PositionCredit;
use App\Models\Provision;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\Survey;
use App\Models\SurveyGroup;
use App\Models\SurveyQuestion;
use App\Models\Swag;
use App\Models\Timesheet;
use App\Models\TimesheetMissing;
use App\Models\Training;
use App\Models\TrainingSession;
use App\Models\Vehicle;
use App\Policies\AccessDocumentPolicy;
use App\Policies\ActionLogPolicy;
use App\Policies\AgreementsPolicy;
use App\Policies\AlertPersonPolicy;
use App\Policies\AlertPolicy;
use App\Policies\AssetAttachmentPolicy;
use App\Policies\AssetPersonPolicy;
use App\Policies\AssetPolicy;
use App\Policies\AwardPolicy;
use App\Policies\BmidPolicy;
use App\Policies\BroadcastPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\ErrorLogPolicy;
use App\Policies\EventDatePolicy;
use App\Policies\HandleReservationPolicy;
use App\Policies\HelpPolicy;
use App\Policies\MotdPolicy;
use App\Policies\PersonAwardPolicy;
use App\Policies\PersonEventPolicy;
use App\Policies\PersonMentorPolicy;
use App\Policies\PersonMessagePolicy;
use App\Policies\PersonOnlineTrainingPolicy;
use App\Policies\PersonPhotoPolicy;
use App\Policies\PersonPogPolicy;
use App\Policies\PersonPolicy;
use App\Policies\PersonPositionLogPolicy;
use App\Policies\PersonSwagPolicy;
use App\Policies\PositionCreditPolicy;
use App\Policies\PositionPolicy;
use App\Policies\ProvisionPolicy;
use App\Policies\RolePolicy;
use App\Policies\SchedulePolicy;
use App\Policies\SettingPolicy;
use App\Policies\SlotPolicy;
use App\Policies\SurveyGroupPolicy;
use App\Policies\SurveyPolicy;
use App\Policies\SurveyQuestionPolicy;
use App\Policies\SwagPolicy;
use App\Policies\TimesheetMissingPolicy;
use App\Policies\TimesheetPolicy;
use App\Policies\TrainingPolicy;
use App\Policies\TrainingSessionPolicy;
use App\Policies\VehiclePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        AccessDocument::class => AccessDocumentPolicy::class,
        ActionLog::class => ActionLogPolicy::class,
        Agreements::class => AgreementsPolicy::class,
        Award::class => AwardPolicy::class,
        Alert::class => AlertPolicy::class,
        AlertPerson::class => AlertPersonPolicy::class,
        Asset::class => AssetPolicy::class,
        AssetAttachment::class => AssetAttachmentPolicy::class,
        AssetPerson::class => AssetPersonPolicy::class,
        Bmid::class => BmidPolicy::class,
        Broadcast::class => BroadcastPolicy::class,
        Document::class => DocumentPolicy::class,
        ErrorLog::class => ErrorLogPolicy::class,
        EventDate::class => EventDatePolicy::class,
        HandleReservation::class => HandleReservationPolicy::class,
        Help::class => HelpPolicy::class,
        Motd::class => MotdPolicy::class,
        Person::class => PersonPolicy::class,
        PersonAward::class => PersonAwardPolicy::class,
        PersonEvent::class => PersonEventPolicy::class,
        PersonMentor::class => PersonMentorPolicy::class,
        PersonMessage::class => PersonMessagePolicy::class,
        PersonOnlineTraining::class => PersonOnlineTrainingPolicy::class,
        PersonPhoto::class => PersonPhotoPolicy::class,
        PersonPog::class => PersonPogPolicy::class,
        PersonPositionLog::class => PersonPositionLogPolicy::class,
        PersonSwag::class => PersonSwagPolicy::class,
        Position::class => PositionPolicy::class,
        PositionCredit::class => PositionCreditPolicy::class,
        Provision::class => ProvisionPolicy::class,
        Role::class => RolePolicy::class,
        Schedule::class => SchedulePolicy::class,
        Setting::class => SettingPolicy::class,
        Slot::class => SlotPolicy::class,
        Survey::class => SurveyPolicy::class,
        SurveyGroup::class => SurveyGroupPolicy::class,
        SurveyQuestion::class => SurveyQuestionPolicy::class,
        Swag::class => SwagPolicy::class,
        Timesheet::class => TimesheetPolicy::class,
        TimesheetMissing::class => TimesheetMissingPolicy::class,
        Training::class => TrainingPolicy::class,
        TrainingSession::class => TrainingSessionPolicy::class,
        Vehicle::class => VehiclePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('isAdmin', function (Person $user) {
            return $user->isAdmin();
        });

        Gate::define('isMentor', function (Person $user) {
            return $user->hasRole([Role::ADMIN, Role::MENTOR]);
        });

        Gate::define('isIntake', function (Person $user) {
            return $user->hasRole(Role::INTAKE);
        });

        Gate::define('isVC', function (Person $user) {
            return $user->hasRole(Role::VC);
        });

        Gate::resource('person', 'PersonPolicy');
    }
}
