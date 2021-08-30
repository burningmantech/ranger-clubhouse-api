<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
*/


/*
 * API which do not require an authorized user
 */


use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'api',
], function ($router) {
    Route::get('config', 'ConfigController@show');

    Route::post('auth/login', 'AuthController@login');
    Route::post('auth/reset-password', 'AuthController@resetPassword');

    Route::post('person/register', 'PersonController@register');

    Route::post('error-log/record', 'ErrorLogController@record');
    Route::post('action-log/record', 'ActionLogController@record');

    Route::match(['GET', 'POST'], 'sms/inbound', 'SmsController@inbound');

    // Only used for development.
    if (app()->isLocal()) {
        // Serve up files in exports, photos, and staging
        Route::get('{file}', 'FileController@serve')->where('file', '(exports|photos|staging)/.*');
    }
});


/*
 * API which require an authorized user
 */

Route::group([
    'middleware' => ['api', 'auth'],
], function ($router) {

    Route::post('auth/logout', 'AuthController@logout');
    Route::post('auth/refresh', 'AuthController@refresh');

    Route::post('access-document/bump-expiration', 'AccessDocumentController@bumpExpiration');
    Route::get('access-document/current', 'AccessDocumentController@current');
    Route::get('access-document/expiring', 'AccessDocumentController@expiring');
    Route::post('access-document/mark-submitted', 'AccessDocumentController@markSubmitted');
    Route::post('access-document/grant-waps', 'AccessDocumentController@grantWAPs');
    Route::post('access-document/grant-alpha-waps', 'AccessDocumentController@grantAlphaWAPs');
    Route::post('access-document/grant-vps', 'AccessDocumentController@grantVehiclePasses');
    Route::post('access-document/set-staff-credentials-access-date', 'AccessDocumentController@setStaffCredentialsAccessDate');
    Route::post('access-document/clean-access-documents', 'AccessDocumentController@cleanAccessDocsFromPriorEvent');
    Route::post('access-document/bank-access-documents', 'AccessDocumentController@bankAccessDocuments');
    Route::post('access-document/expire-access-documents', 'AccessDocumentController@expireAccessDocuments');
    Route::patch('access-document/statuses', 'AccessDocumentController@statuses');
    Route::resource('access-document', 'AccessDocumentController');

    Route::resource('access-document-delivery', 'AccessDocumentDeliveryController');

    Route::resource('action-log', 'ActionLogController', ['only' => 'index']);
    Route::resource('clubhouse1-log', 'Clubhouse1LogController', ['only' => 'index']);

    Route::resource('alert', 'AlertController');

    Route::post('asset/checkout', 'AssetController@checkout');
    Route::get('asset/{asset}/history', 'AssetController@history');
    Route::post('asset/{asset}/checkin', 'AssetController@checkin');
    Route::resource('asset', 'AssetController');
    Route::resource('asset-attachment', 'AssetAttachmentController');

    Route::get('asset-person/radio-checkout-report', 'AssetPersonController@radioCheckoutReport');
    Route::post('asset-person/checkout', 'AssetPersonController@checkout');
    Route::post('asset-person/{asset_person}/checkin', 'AssetPersonController@checkin');
    Route::resource('asset-person', 'AssetPersonController');

    Route::post('bmid/export', 'BmidController@export');
    Route::get('bmid/exports', 'BmidController@exportList');
    Route::get('bmid/manage', 'BmidController@manage');
    Route::get('bmid/manage-person', 'BmidController@managePerson');
    Route::get('bmid/sanity-check', 'BmidController@sanityCheck');
    Route::post('bmid/set-bmid-titles', 'BmidController@setBMIDTitles');
    Route::resource('bmid', 'BmidController');

    Route::get('broadcast', 'BroadcastController@index');
    Route::get('broadcast/messages', 'BroadcastController@messages');

    Route::post('bulk-upload', 'BulkUploadController@update');

    Route::resource('document', 'DocumentController');

    Route::get('callsigns', 'CallsignsController@index');
    Route::get('handles', 'HandleController@index');

    Route::get('contact/log', 'ContactController@showLog');
    Route::post('contact/send', 'ContactController@send');

    Route::get('debug/sleep-test', 'DebugController@sleepTest');
    Route::get('debug/db-test', 'DebugController@dbTest');
    Route::get('debug/phpinfo', 'DebugController@phpInfo');
    Route::get('debug/cpuinfo', 'DebugController@cpuInfo');

    Route::get('language/speakers', 'LanguageController@speakers');
    Route::resource('language', 'LanguageController');

    Route::delete('error-log/purge', 'ErrorLogController@purge');
    Route::resource('error-log', 'ErrorLogController', ['only' => 'index']);

    Route::get('event-dates/year', 'EventDatesController@showYear');
    Route::resource('event-dates', 'EventDatesController');

    Route::get('intake', 'IntakeController@index');
    Route::get('intake/spigot', 'IntakeController@spigot');
    Route::get('intake/{person}/history', 'IntakeController@history');
    Route::post('intake/{person}/note', 'IntakeController@appendNote');
    Route::post('intake/{person_intake_note}/update-note', 'IntakeController@updateNote');
    Route::delete('intake/{person_intake_note}/delete-note', 'IntakeController@deleteNote');

    Route::post('maintenance/update-positions', 'MaintenanceController@updatePositions');
    Route::post('maintenance/mark-off-site', 'MaintenanceController@markOffSite');
    Route::post('maintenance/deauthorize-assets', 'MaintenanceController@deauthorizeAssets');
    Route::post('maintenance/reset-pnvs', 'MaintenanceController@resetPNVs');
    Route::post('maintenance/reset-past-prospectives', 'MaintenanceController@resetPassProspectives');
    Route::post('maintenance/archive-messages', 'MaintenanceController@archiveMessages');

    Route::get('online-training/config', 'OnlineTrainingController@config');
    Route::get('online-training/courses', 'OnlineTrainingController@courses');
    Route::get('online-training/enrollment', 'OnlineTrainingController@enrollment');
    Route::get('online-training', 'OnlineTrainingController@index');
    Route::post('online-training/{person}/setup', 'OnlineTrainingController@setupPerson');

    Route::patch('messages/{person_message}/markread', 'PersonMessageController@markread');
    Route::resource('messages', 'PersonMessageController', ['only' => ['index', 'store', 'destroy']]);

    Route::get('mentor/alphas', 'MentorController@alphas');
    Route::get('mentor/alpha-schedule', 'MentorController@alphaSchedule');
    Route::get('mentor/mentees', 'MentorController@mentees');
    Route::get('mentor/mentors', 'MentorController@mentors');
    Route::post('mentor/mentor-assignment', 'MentorController@mentorAssignment');
    Route::post('mentor/convert', 'MentorController@convert');
    Route::get('mentor/verdicts', 'MentorController@verdicts');

    Route::get('motd/bulletin', 'MotdController@bulletin');
    Route::post('motd/{motd}/markread', 'MotdController@markRead');
    Route::resource('motd', 'MotdController');

    Route::get('person/alpha-shirts', 'PersonController@alphaShirts');
    Route::get('person/languages', 'PersonController@languagesReport');
    Route::get('person/by-location', 'PersonController@peopleByLocation');
    Route::get('person/by-role', 'PersonController@peopleByRole');
    Route::get('person/by-status', 'PersonController@peopleByStatus');
    Route::get('person/by-status-change', 'PersonController@peopleByStatusChange');
    Route::post('person/bulk-lookup', 'PersonController@bulkLookup');

    Route::get('person/{person}/alerts', 'AlertPersonController@index');
    Route::patch('person/{person}/alerts', 'AlertPersonController@update');

    Route::get('person/{person}/mentees', 'PersonController@mentees');
    Route::get('person/{person}/mentors', 'PersonController@mentors');
    Route::get('person/{person}/credits', 'PersonController@credits');
    Route::get('person/{person}/milestones', 'PersonController@milestones');
    Route::get('person/{person}/timesheet-summary', 'PersonController@timesheetSummary');
    Route::get('person/{person}/schedule/permission', 'PersonScheduleController@permission');
    Route::get('person/{person}/schedule/recommendations', 'PersonScheduleController@recommendations');
    Route::get('person/{person}/schedule/imminent', 'PersonScheduleController@imminent');
    Route::get('person/{person}/schedule/expected', 'PersonScheduleController@expected');
    Route::get('person/{person}/schedule/summary', 'PersonScheduleController@scheduleSummary');
    Route::get('person/{person}/schedule/log', 'PersonScheduleController@scheduleLog');
    Route::resource('person/{person}/schedule', 'PersonScheduleController', ['only' => ['index', 'store', 'destroy']]);

    Route::get('person/{person}/positions', 'PersonController@positions');
    Route::post('person/{person}/positions', 'PersonController@updatePositions');
    Route::get('person/{person}/photo', 'PersonPhotoController@photo');
    Route::post('person/{person}/photo', 'PersonPhotoController@upload');
    Route::patch('person/{person}/password', 'PersonController@password');
    Route::get('person/{person}/roles', 'PersonController@roles');
    Route::post('person/{person}/roles', 'PersonController@updateRoles');

    Route::get('person/{person}/status-history', 'PersonController@statusHistory');
    Route::get('person/{person}/years', 'PersonController@years');

    Route::get('person/{person}/user-info', 'PersonController@userInfo');
    Route::get('person/{person}/unread-message-count', 'PersonController@UnreadMessageCount');
    Route::get('person/{person}/event-info', 'PersonController@eventInfo');

    Route::resource('person', 'PersonController', ['only' => ['index', 'show', 'store', 'update', 'destroy']]);

    Route::resource('person-event', 'PersonEventController');

    Route::get('person-photo/review-config', 'PersonPhotoController@reviewConfig');
    Route::post('person-photo/{person_photo}/replace', 'PersonPhotoController@replace');
    Route::post('person-photo/{person_photo}/activate', 'PersonPhotoController@activate');
    Route::get('person-photo/{person_photo}/reject-preview', 'PersonPhotoController@rejectPreview');
    Route::resource('person-photo', 'PersonPhotoController');

    Route::get('vehicle/paperwork', 'VehicleController@paperwork');
    Route::resource('vehicle', 'VehicleController');

    Route::post('position-credit/copy', 'PositionCreditController@copy');
    Route::resource('position-credit', 'PositionCreditController');

    Route::post('position/bulk-grant-revoke', 'PositionController@bulkGrantRevoke');
    Route::get('position/people-by-position', 'PositionController@peopleByPosition');
    Route::get('position/sandman-qualified', 'PositionController@sandmanQualifiedReport');
    Route::get('position/sanity-checker', 'PositionSanityCheckController@sanityChecker');
    Route::post('position/repair', 'PositionSanityCheckController@repair');

    Route::resource('position', 'PositionController');

    Route::get('rbs/config', 'RbsController@config');
    Route::get('rbs/details', 'RbsController@details');
    Route::get('rbs/receivers', 'RbsController@receivers');
    Route::get('rbs/recipients', 'RbsController@recipients');
    Route::get('rbs/unknown-phones', 'RbsController@unknownPhones');
    Route::get('rbs/stats', 'RbsController@stats');
    Route::get('rbs/unverified-stopped', 'RbsController@unverifiedStopped');
    Route::post('rbs/retry', 'RbsController@retry');
    Route::post('rbs/transmit', 'RbsController@transmit');

    Route::resource('role', 'RoleController');

    Route::get('salesforce/config', 'SalesforceController@config');
    Route::get('salesforce/import', 'SalesforceController@import');

    Route::resource('setting', 'SettingController');

    Route::get('sms', 'SmsController@getNumbers');
    Route::post('sms', 'SmsController@updateNumbers');
    Route::post('sms/send-code', 'SmsController@sendNewCode');
    Route::post('sms/confirm-code', 'SmsController@confirmCode');

    Route::patch('slot/bulkupdate', 'SlotController@bulkUpdate');
    Route::post('slot/copy', 'SlotController@copy');
    Route::get('slot/dirt-shift-times', 'SlotController@dirtShiftTimes');
    Route::get('slot/flakes', 'SlotController@flakeReport');
    Route::get('slot/hq-forecast-report', 'SlotController@hqForecastReport');
    Route::get('slot/shift-coverage-report', 'SlotController@shiftCoverageReport');
    Route::get('slot/shift-lead-report', 'SlotController@shiftLeadReport');
    Route::get('slot/shift-signups-report', 'SlotController@shiftSignUpsReport');
    Route::get('slot/position-schedule-report', 'SlotController@positionScheduleReport');
    Route::get('slot/callsign-schedule-report', 'SlotController@callsignScheduleReport');
    Route::get('slot/years', 'SlotController@years');

    Route::get('slot/{slot}/people', 'SlotController@people');
    Route::resource('slot', 'SlotController');


    Route::get('survey/questionnaire', 'SurveyController@questionnaire');
    Route::post('survey/submit', 'SurveyController@submit');
    Route::get('survey/trainer-surveys', 'SurveyController@trainerSurveys');
    Route::get('survey/trainer-report', 'SurveyController@trainerReport');
    Route::get('survey/{survey}/all-trainers-report', 'SurveyController@allTrainersReport');
    Route::post('survey/{survey}/duplicate', 'SurveyController@duplicate');
    Route::get('survey/{survey}/report', 'SurveyController@report');
    Route::resource('survey', 'SurveyController');

    Route::resource('survey-group', 'SurveyGroupController');
    Route::resource('survey-question', 'SurveyQuestionController');


    Route::get('training-session/sessions', 'TrainingSessionController@sessions');
    Route::post('training-session/{training_session}/score-student', 'TrainingSessionController@scoreStudent');
    Route::post('training-session/{training_session}/trainer-status', 'TrainingSessionController@trainerStatus');
    Route::get('training-session/{training_session}/trainers', 'TrainingSessionController@trainers');
    Route::post('training-session/{trainee_note}/update-note', 'TrainingSessionController@updateNote');
    Route::delete('training-session/{trainee_note}/delete-note', 'TrainingSessionController@deleteNote');
    Route::get('training-session/{training_session}', 'TrainingSessionController@show');

    Route::get('training/{id}/multiple-enrollments', 'TrainingController@multipleEnrollmentsReport');
    Route::get('training/{id}/capacity', 'TrainingController@capacityReport');
    Route::get('training/{id}/people-training-completed', 'TrainingController@peopleTrainingCompleted');
    Route::get('training/{id}/trainer-attendance', 'TrainingController@trainerAttendanceReport');
    Route::get('training/{id}/untrained-people', 'TrainingController@untrainedPeopleReport');
    Route::get('training/{id}', 'TrainingController@show');

    Route::get('ticketing/info', 'TicketingController@ticketingInfo');
    Route::get('ticketing/thresholds', 'TicketingController@thresholds');
    Route::get('ticketing/{person}/package', 'TicketingController@package');
    Route::post('ticketing/{person}/delivery', 'TicketingController@delivery');
    Route::patch('ticketing/{person}/wapso', 'TicketingController@storeWAPSO');

    Route::post('timesheet/bulk-sign-in-out', 'TimesheetController@bulkSignInOut');
    Route::get('timesheet/correction-requests', 'TimesheetController@correctionRequests');
    Route::post('timesheet/confirm', 'TimesheetController@confirm');
    Route::get('timesheet/freaking-years', 'TimesheetController@freakingYearsReport');
    Route::get('timesheet/hours-credits', 'TimesheetController@hoursCreditsReport');
    Route::get('timesheet/info', 'TimesheetController@info');
    Route::get('timesheet/log', 'TimesheetController@showLog');
    Route::get('timesheet/on-duty-shift-lead-report', 'TimesheetController@onDutyShiftLeadReport');
    Route::post('timesheet/signin', 'TimesheetController@signin');
    Route::get('timesheet/radio-eligibility', 'TimesheetController@radioEligibilityReport');
    Route::get('timesheet/potential-shirts-earned', 'TimesheetController@potentialShirtsEarnedReport');
    Route::get('timesheet/sanity-checker', 'TimesheetController@sanityChecker');
    Route::get('timesheet/by-callsign', 'TimesheetController@timesheetByCallsign');
    Route::get('timesheet/by-position', 'TimesheetController@timesheetByPosition');
    Route::get('timesheet/thank-you', 'TimesheetController@thankYou');
    Route::get('timesheet/totals', 'TimesheetController@timesheetTotals');
    Route::get('timesheet/unconfirmed-people', 'TimesheetController@unconfirmedPeople');
    Route::match(['GET', 'POST'], 'timesheet/special-teams', 'TimesheetController@specialTeamsReport');
    Route::post('timesheet/{timesheet}/resignin', 'TimesheetController@resignin');
    Route::post('timesheet/{timesheet}/signoff', 'TimesheetController@signoff');
    Route::patch('timesheet/{timesheet}/update-position', 'TimesheetController@updatePosition');
    Route::resource('timesheet', 'TimesheetController');

    Route::resource('timesheet-missing', 'TimesheetMissingController');

    Route::resource('help', 'HelpController');
});
