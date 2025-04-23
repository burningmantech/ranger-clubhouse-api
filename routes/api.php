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

use App\Http\Controllers\AccessDocumentController;
use App\Http\Controllers\ActionLogController;
use App\Http\Controllers\AgreementsController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AlertPersonController;
use App\Http\Controllers\AssetAttachmentController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetPersonController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AwardController;
use App\Http\Controllers\BmidController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\BulkUploadController;
use App\Http\Controllers\CallsignsController;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\Clubhouse1LogController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DebugController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmailHistoryController;
use App\Http\Controllers\ErrorLogController;
use App\Http\Controllers\EventDatesController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\HandleReservationController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\MailLogController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MentorController;
use App\Http\Controllers\MotdController;
use App\Http\Controllers\OAuth2Controller;
use App\Http\Controllers\OauthClientController;
use App\Http\Controllers\OnlineCourseController;
use App\Http\Controllers\PersonAwardController;
use App\Http\Controllers\PersonCertificationController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonEventController;
use App\Http\Controllers\PersonFkaController;
use App\Http\Controllers\PersonLanguageController;
use App\Http\Controllers\PersonMessageController;
use App\Http\Controllers\PersonOnlineCourseController;
use App\Http\Controllers\PersonPhotoController;
use App\Http\Controllers\PersonPogController;
use App\Http\Controllers\PersonPositionLogController;
use App\Http\Controllers\PersonScheduleController;
use App\Http\Controllers\PersonSwagController;
use App\Http\Controllers\PersonTeamLogController;
use App\Http\Controllers\PodController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\PositionCreditController;
use App\Http\Controllers\PositionLineupController;
use App\Http\Controllers\PositionSanityCheckController;
use App\Http\Controllers\ProspectiveApplicationController;
use App\Http\Controllers\ProvisionController;
use App\Http\Controllers\RbsController;
use App\Http\Controllers\RequestLogController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesforceController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\SurveyGroupController;
use App\Http\Controllers\SurveyQuestionController;
use App\Http\Controllers\SwagController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TicketingController;
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\TimesheetMissingController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\TrainingSessionController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

/*
 * APIs which do not require an authorized user
 */

Route::middleware('api')->group(function () {
    Route::get('config/dashboard-period', [ConfigController::class, 'dashboardPeriod']);
    Route::get('config', [ConfigController::class, 'show']);

    Route::post('auth/login', [AuthController::class, 'jwtLogin']);
    Route::post('auth/oauth2/temp-token', [OAuth2Controller::class, 'tempToken']);
    Route::match(['GET', 'POST'], 'auth/oauth2/token', [OAuth2Controller::class, 'grantOAuthToken']);
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);

    Route::post('person/register', [PersonController::class, 'register']);

    Route::post('error-log/record', [ErrorLogController::class, 'record']);
    Route::post('action-log/record', [ActionLogController::class, 'record']);

    Route::match(['GET', 'POST'], 'sms/inbound', [SmsController::class, 'inbound']);

    Route::post('mail-log/sns', [MailLogController::class, 'snsNotification']);

    Route::get('bookmark/{id}', [DocumentController::class, 'bookmark']);

    // Only used for development.
    if (app()->isLocal()) {
        // Serve up files in exports, photos, and staging
        Route::get('{file}', [FileController::class, 'serve'])->where('file', '(exports|photos|staging)/.*');
    }

    Route::get('.well-known/openid-configuration', [OAuth2Controller::class, 'openIdDiscovery']);
});

/*
 * API which require an authorized user
 */

Route::middleware('api')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('auth/oauth2/grant-code', [OAuth2Controller::class, 'grantOAuthCode']);
    Route::get('auth/oauth2/userinfo', [OAuth2Controller::class, 'oauthUserInfo']);

    Route::post('access-document/bank-access-documents', [AccessDocumentController::class, 'bankAccessDocuments']);
    Route::post('access-document/bulk-comment', [AccessDocumentController::class, 'bulkComment']);
    Route::post('access-document/bump-expiration', [AccessDocumentController::class, 'bumpExpiration']);
    Route::get('access-document/claimed-tickets-with-no-signups', [AccessDocumentController::class, 'claimedTicketsWithNoSignups']);
    Route::post('access-document/clean-access-documents', [AccessDocumentController::class, 'cleanAccessDocsFromPriorEvent']);
    Route::get('access-document/current', [AccessDocumentController::class, 'current']);
    Route::get('access-document/early-arrival', [AccessDocumentController::class, 'earlyArrivalReport']);
    Route::post('access-document/expire-access-documents', [AccessDocumentController::class, 'expireAccessDocuments']);
    Route::get('access-document/expiring', [AccessDocumentController::class, 'expiring']);
    Route::post('access-document/grant-alpha-waps', [AccessDocumentController::class, 'grantAlphaWAPs']);
    Route::post('access-document/grant-vps', [AccessDocumentController::class, 'grantVehiclePasses']);
    Route::post('access-document/grant-waps', [AccessDocumentController::class, 'grantWAPs']);
    Route::post('access-document/mark-submitted', [AccessDocumentController::class, 'markSubmitted']);
    Route::post('access-document/set-staff-credentials-access-date', [AccessDocumentController::class, 'setStaffCredentialsAccessDate']);
    Route::get('access-document/special-tickets', [AccessDocumentController::class, 'specialTicketsReport']);
    Route::patch('access-document/{access_document}/status', [AccessDocumentController::class, 'updateStatus']);
    Route::post('access-document/unbank-access-documents', [AccessDocumentController::class, 'unbankAccessDocuments']);
    Route::get('access-document/unclaimed-tickets-with-signups', [AccessDocumentController::class, 'unclaimedTicketsWithSignups']);
    Route::get('access-document/wap-candidates', [AccessDocumentController::class, 'wapCandidates']);
    Route::get('access-document/{access_document}/changes', [AccessDocumentController::class, 'changes']);
    Route::resource('access-document', AccessDocumentController::class);

    Route::resource('action-log', ActionLogController::class)->only('index');

    Route::resource('alert', AlertController::class);

    Route::post('award/bulk-grant-award', [AwardController::class, 'bulkGrantAward']);
    Route::post('award/bulk-grant-service-years-award', [AwardController::class, 'bulkGrantServiceYearsAward']);
    Route::resource('award', AwardController::class);

    Route::resource('clubhouse1-log', Clubhouse1LogController::class)->only('index');

    Route::post('asset/checkout', [AssetController::class, 'checkout']);
    Route::get('asset/{asset}/history', [AssetController::class, 'history']);
    Route::post('asset/{asset}/checkin', [AssetController::class, 'checkin']);
    Route::resource('asset', AssetController::class);
    Route::resource('asset-attachment', AssetAttachmentController::class);

    Route::get('asset-person/radio-checkout-report', [AssetPersonController::class, 'radioCheckoutReport']);
    Route::post('asset-person/checkout', [AssetPersonController::class, 'checkout']);
    Route::post('asset-person/{asset_person}/checkin', [AssetPersonController::class, 'checkin']);
    Route::resource('asset-person', AssetPersonController::class);

    Route::post('agreements/{person}/{document}/sign', [AgreementsController::class, 'sign']);
    Route::get('agreements/{person}/{document}', [AgreementsController::class, 'show']);
    Route::get('agreements/{person}', [AgreementsController::class, 'index']);

    Route::post('bmid/export', [BmidController::class, 'export']);
    Route::get('bmid/exports', [BmidController::class, 'exportList']);
    Route::get('bmid/manage', [BmidController::class, 'manage']);
    Route::get('bmid/manage-person', [BmidController::class, 'managePerson']);
    Route::get('bmid/sanity-check', [BmidController::class, 'sanityCheck']);
    Route::post('bmid/set-bmid-titles', [BmidController::class, 'setBMIDTitles']);
    Route::resource('bmid', BmidController::class);

    Route::get('broadcast', [BroadcastController::class, 'index']);
    Route::get('broadcast/messages', [BroadcastController::class, 'messages']);

    Route::get('bulk-upload/actions', [BulkUploadController::class, 'actions']);
    Route::post('bulk-upload', [BulkUploadController::class, 'process']);

    Route::resource('document', DocumentController::class);

    Route::get('callsigns', [CallsignsController::class, 'index']);

    Route::post('certification/people', [CertificationController::class, 'peopleReport']);
    Route::resource('certification', CertificationController::class);

    Route::post('contact/send', [ContactController::class, 'send']);
    Route::post('contact/{person}/update-mailing-lists', [ContactController::class, 'updateMailingLists']);

    Route::get('debug/sleep-test', [DebugController::class, 'sleepTest']);
    Route::get('debug/db-test', [DebugController::class, 'dbTest']);
    Route::get('debug/phpinfo', [DebugController::class, 'phpInfo']);
    Route::get('debug/cpuinfo', [DebugController::class, 'cpuInfo']);

    Route::resource('person-fka', PersonFkaController::class);

    Route::get('person-language/common-languages', [PersonLanguageController::class, 'commonLanguages']);
    Route::get('person-language/search', [PersonLanguageController::class, 'search']);
    Route::get('person-language/on-site-report', [PersonLanguageController::class, 'onSiteReport']);
    Route::resource('person-language', PersonLanguageController::class);

    Route::get('email-history', [EmailHistoryController::class, 'index']);
    Route::delete('error-log/purge', [ErrorLogController::class, 'purge']);
    Route::resource('error-log', ErrorLogController::class)->only('index');

    Route::get('event-dates/period', [EventDatesController::class, 'period']);
    Route::get('event-dates/year', [EventDatesController::class, 'showYear']);
    Route::resource('event-dates', EventDatesController::class);

    Route::post('handle-reservation/expire', [HandleReservationController::class, 'expire']);
    Route::get('handle-reservation/handles', [HandleReservationController::class, 'handles']);
    Route::post('handle-reservation/upload', [HandleReservationController::class, 'upload']);
    Route::resource('handle-reservation', HandleReservationController::class);

    Route::get('intake', [IntakeController::class, 'index']);
    Route::get('intake/spigot', [IntakeController::class, 'spigot']);
    Route::get('intake/shiny-penny-report', [IntakeController::class, 'shinyPennyReport']);
    Route::get('intake/{person}/history', [IntakeController::class, 'history']);
    Route::post('intake/{person}/note', [IntakeController::class, 'appendNote']);
    Route::post('intake/{person_intake_note}/update-note', [IntakeController::class, 'updateNote']);
    Route::delete('intake/{person_intake_note}/delete-note', [IntakeController::class, 'deleteNote']);
    Route::post('intake/{person}/send-welcome-email', [IntakeController::class, 'sendWelcomeEmail']);

    Route::post('maintenance/mark-off-site', [MaintenanceController::class, 'markOffSite']);
    Route::post('maintenance/deauthorize-assets', [MaintenanceController::class, 'deauthorizeAssets']);
    Route::post('maintenance/reset-pnvs', [MaintenanceController::class, 'resetPNVs']);
    Route::post('maintenance/reset-past-prospectives', [MaintenanceController::class, 'resetPassProspectives']);
    Route::post('maintenance/archive-messages', [MaintenanceController::class, 'archiveMessages']);

    Route::get('online-course/courses', [OnlineCourseController::class, 'courses']);
    Route::get('online-course/progress', [OnlineCourseController::class, 'progressReport']);
    Route::get('online-course/{online_course}/enrollment', [OnlineCourseController::class, 'enrollment']);
    Route::post('online-course/{online_course}/set-name', [OnlineCourseController::class, 'setName']);
    Route::resource('online-course', OnlineCourseController::class);

    Route::post('person-online-course/{person}/change', [PersonOnlineCourseController::class, 'change']);
    Route::get('person-online-course/{person}/course-info', [PersonOnlineCourseController::class, 'courseInfo']);
    Route::get('person-online-course/{person}/info', [PersonOnlineCourseController::class, 'getInfo']);
    Route::post('person-online-course/{person}/mark-completed', [PersonOnlineCourseController::class, 'markCompleted']);
    Route::post('person-online-course/{person}/reset-password', [PersonOnlineCourseController::class, 'resetPassword']);
    Route::post('person-online-course/{person}/setup', [PersonOnlineCourseController::class, 'setupPerson']);
    Route::post('person-online-course/{person}/sync-info', [PersonOnlineCourseController::class, 'syncInfo']);

    Route::get('mail-log/stats', [MailLogController::class, 'stats']);
    Route::get('mail-log', [MailLogController::class, 'index']);

    Route::patch('messages/{person_message}/markread', [PersonMessageController::class, 'markread']);
    Route::resource('messages', PersonMessageController::class)->only('index', 'store', 'destroy');

    Route::get('mentor/alphas', [MentorController::class, 'alphas']);
    Route::get('mentor/alpha-schedule', [MentorController::class, 'alphaSchedule']);
    Route::get('mentor/eligible-alphas', [MentorController::class, 'eligibleAlphas']);
    Route::get('mentor/mittens', [MentorController::class, 'mittens']);
    Route::get('mentor/mentees', [MentorController::class, 'mentees']);
    Route::get('mentor/mentors', [MentorController::class, 'mentors']);
    Route::post('mentor/mentor-assignment', [MentorController::class, 'mentorAssignment']);
    Route::post('mentor/convert-alphas', [MentorController::class, 'convertAlphas']);
    Route::post('mentor/convert-prospectives', [MentorController::class, 'convertProspectives']);
    Route::post('mentor/setup-training-data', [MentorController::class, 'setupTrainingData']);
    Route::get('mentor/verdicts', [MentorController::class, 'verdicts']);

    Route::get('motd/bulletin', [MotdController::class, 'bulletin']);
    Route::post('motd/{motd}/markread', [MotdController::class, 'markRead']);
    Route::resource('motd', MotdController::class);

    Route::resource('oauth-client', OauthClientController::class);

    Route::get('person/alpha-shirts', [PersonController::class, 'alphaShirts']);
    Route::get('person/by-location', [PersonController::class, 'peopleByLocation']);
    Route::get('person/by-status', [PersonController::class, 'peopleByStatus']);
    Route::get('person/by-status-change', [PersonController::class, 'peopleByStatusChange']);
    Route::post('person/bulk-lookup', [PersonController::class, 'bulkLookup']);
    Route::get('person/search', [PersonController::class, 'search']);
    Route::get('person/advanced-search', [PersonController::class, 'advancedSearch']);

    Route::get('person/{person}/alerts', [AlertPersonController::class, 'index']);
    Route::patch('person/{person}/alerts', [AlertPersonController::class, 'update']);

    Route::get('person/{person}/tickets-provisions-progress', [PersonController::class, 'ticketsProvisionsProgress']);
    Route::get('person/{person}/credits', [PersonController::class, 'credits']);
    Route::get('person/{person}/membership', [PersonController::class, 'membership']);
    Route::get('person/{person}/mentees', [PersonController::class, 'mentees']);
    Route::get('person/{person}/mentors', [PersonController::class, 'mentors']);
    Route::get('person/{person}/milestones', [PersonController::class, 'milestones']);
    Route::get('person/{person}/onduty', [PersonController::class, 'onDuty']);
    Route::get('person/{person}/timesheet-summary', [PersonController::class, 'timesheetSummary']);
    Route::get('person/{person}/schedule/permission', [PersonScheduleController::class, 'permission']);
    Route::get('person/{person}/schedule/recommendations', [PersonScheduleController::class, 'recommendations']);
    Route::get('person/{person}/schedule/upcoming', [PersonScheduleController::class, 'upcoming']);
    Route::get('person/{person}/schedule/expected', [PersonScheduleController::class, 'expected']);
    Route::get('person/{person}/schedule/summary', [PersonScheduleController::class, 'scheduleSummary']);
    Route::get('person/{person}/schedule/log', [PersonScheduleController::class, 'scheduleLog']);

    Route::patch('person/{person}/password', [PersonController::class, 'password']);
    Route::get('person/{person}/photo', [PersonPhotoController::class, 'photo']);
    Route::post('person/{person}/photo', [PersonPhotoController::class, 'upload']);
    Route::get('person/{person}/positions', [PersonController::class, 'positions']);
    Route::post('person/{person}/positions', [PersonController::class, 'updatePositions']);
    Route::post('person/{person}/release-callsign', [PersonController::class, 'releaseCallsign']);
    Route::get('person/{person}/roles', [PersonController::class, 'roles']);
    Route::post('person/{person}/roles', [PersonController::class, 'updateRoles']);
    Route::resource('person/{person}/schedule', PersonScheduleController::class)->only('index', 'store', 'destroy');
    Route::get('person/{person}/teams', [PersonController::class, 'teams']);
    Route::post('person/{person}/teams', [PersonController::class, 'updateTeams']);

    Route::get('person/{person}/tokens', [OAuth2Controller::class, 'tokens']);
    Route::delete('person/{person}/revoke-token', [OAuth2Controller::class, 'revokeToken']);

    Route::get('person/{person}/status-history', [PersonController::class, 'statusHistory']);

    Route::get('person/{person}/user-info', [PersonController::class, 'userInfo']);
    Route::get('person/{person}/unread-message-count', [PersonController::class, 'UnreadMessageCount']);
    Route::get('person/{person}/event-info', [PersonController::class, 'eventInfo']);

    Route::resource('person', PersonController::class)->only('index', 'show', 'store', 'update', 'destroy');

    Route::get('person-award/person/{person}/awards', [PersonAwardController::class, 'awardsForPerson']);
    Route::post('person-award/person/{person}/rebuild', [PersonAwardController::class, 'rebuildPerson']);
    Route::post('person-award/bulk-grant', [PersonAwardController::class, 'bulkGrant']);
    Route::post('person-award/rebuild-all-awards', [PersonAwardController::class, 'rebuildAllAwards']);
    Route::resource('person-award', PersonAwardController::class);

    Route::resource('person-certification', PersonCertificationController::class);

    Route::post('person-event/{person}/progress', [PersonEventController::class, 'updateProgress']);
    Route::resource('person-event', PersonEventController::class);

    Route::get('person-photo/review-config', [PersonPhotoController::class, 'reviewConfig']);
    Route::post('person-photo/{person_photo}/replace', [PersonPhotoController::class, 'replace']);
    Route::post('person-photo/{person_photo}/activate', [PersonPhotoController::class, 'activate']);
    Route::get('person-photo/{person_photo}/reject-preview', [PersonPhotoController::class, 'rejectPreview']);
    Route::post('person-photo/convert', [PersonPhotoController::class, 'convertPhoto']);
    Route::resource('person-photo', PersonPhotoController::class);

    Route::get('person-pog/config', [PersonPogController::class, 'config']);
    Route::resource('person-pog', PersonPogController::class);

    Route::resource('person-position-log', PersonPositionLogController::class);

    Route::get('person-swag/distribution', [PersonSwagController::class, 'distribution']);
    Route::resource('person-swag', PersonSwagController::class);

    Route::resource('person-team-log', PersonTeamLogController::class);

    Route::post('position-credit/copy', [PositionCreditController::class, 'copy']);
    Route::resource('position-credit', PositionCreditController::class);

    Route::post('pod/create-alpha-set', [PodController::class, 'createAlphaSet']);
    Route::post('pod/{pod}/person', [PodController::class, 'addPerson']);
    Route::delete('pod/{pod}/person', [PodController::class, 'removePerson']);
    Route::patch('pod/{pod}/person', [PodController::class, 'updatePerson']);
    Route::patch('pod/{oldPod}/move/{person}/{newPod}', [PodController::class, 'movePerson']);
    Route::resource('pod', PodController::class);

    Route::post('position/{position}/bulk-grant-revoke', [PositionController::class, 'bulkGrantRevoke']);
    Route::get('position/people-by-position', [PositionController::class, 'peopleByPosition']);
    Route::get('position/people-by-teams', [PositionController::class, 'peopleByTeamsReport']);
    Route::get('position/sandman-qualified', [PositionController::class, 'sandmanQualifiedReport']);
    Route::get('position/sanity-checker', [PositionSanityCheckController::class, 'sanityChecker']);
    Route::post('position/repair', [PositionSanityCheckController::class, 'repair']);

    Route::get('position/{position}/grants', [PositionController::class, 'grants']);
    Route::resource('position', PositionController::class);

    Route::patch('position-lineup/{position_lineup}/positions', [PositionLineupController::class, 'updatePositions']);
    Route::resource('position-lineup', PositionLineupController::class);

    Route::post('prospective-application/import', [ProspectiveApplicationController::class, 'import']);
    Route::post('prospective-application/create-prospectives', [ProspectiveApplicationController::class, 'createProspectives']);
    Route::get('prospective-application/handles-extract', [ProspectiveApplicationController::class, 'handlesExtract']);
    Route::get('prospective-application/search', [ProspectiveApplicationController::class, 'search']);
    Route::get('prospective-application/{prospective_application}/email-logs', [ProspectiveApplicationController::class, 'emailLogs']);
    Route::get('prospective-application/{prospective_application}/preview-email', [ProspectiveApplicationController::class, 'previewEmail']);
    Route::get('prospective-application/{prospective_application}/related', [ProspectiveApplicationController::class, 'relatedApplications']);
    Route::post('prospective-application/{prospective_application}/status', [ProspectiveApplicationController::class, 'updateStatus']);
    Route::post('prospective-application/{prospective_application}/note', [ProspectiveApplicationController::class, 'addNote']);
    Route::patch('prospective-application/{prospective_application}/note', [ProspectiveApplicationController::class, 'updateNote']);
    Route::delete('prospective-application/{prospective_application}/note', [ProspectiveApplicationController::class, 'deleteNote']);
    Route::post('prospective-application/{prospective_application}/send-email', [ProspectiveApplicationController::class, 'sendEmail']);
    Route::resource('prospective-application', ProspectiveApplicationController::class);

    Route::post('provision/bank-provisions', [ProvisionController::class, 'bankProvisions']);
    Route::post('provision/bulk-comment', [ProvisionController::class, 'bulkComment']);
    Route::post('provision/clean-provisions', [ProvisionController::class, 'cleanProvisionsFromPriorEvent']);
    Route::post('provision/expire-provisions', [ProvisionController::class, 'expireProvisions']);
    Route::patch('provision/{person}/statuses', [ProvisionController::class, 'statuses']);
    Route::get('provision/{person}/package', [ProvisionController::class, 'package']);
    Route::post('provision/unbank-provisions', [ProvisionController::class, 'unbankProvisions']);
    Route::get('provision/unsubmit-recommendations', [ProvisionController::class, 'unsubmitRecommendations']);
    Route::post('provision/unsubmit-provisions', [ProvisionController::class, 'unsubmitProvisions']);
    Route::resource('provision', ProvisionController::class);

    Route::get('rbs/config', [RbsController::class, 'config']);
    Route::get('rbs/details', [RbsController::class, 'details']);
    Route::get('rbs/receivers', [RbsController::class, 'receivers']);
    Route::get('rbs/recipients', [RbsController::class, 'recipients']);
    Route::get('rbs/unknown-phones', [RbsController::class, 'unknownPhones']);
    Route::get('rbs/stats', [RbsController::class, 'stats']);
    Route::get('rbs/unverified-stopped', [RbsController::class, 'unverifiedStopped']);
    Route::post('rbs/retry', [RbsController::class, 'retry']);
    Route::post('rbs/transmit', [RbsController::class, 'transmit']);

    Route::get('request-log', [ RequestLogController::class, 'index']);
    Route::delete('request-log/expire', [ RequestLogController::class, 'expire']);

    Route::get('role/people-by-role', [RoleController::class, 'peopleByRole']);
    Route::get('role/inspect-cache', [RoleController::class, 'inspectCache']);
    Route::post('role/clear-cache', [RoleController::class, 'clearCache']);
    Route::post('role/create-art-roles', [RoleController::class, 'createARTRoles']);
    Route::resource('role', RoleController::class);

    Route::get('salesforce/config', [SalesforceController::class, 'config']);
    Route::get('salesforce/import', [SalesforceController::class, 'import']);

    Route::resource('setting', SettingController::class);

    Route::get('sms', [SmsController::class, 'getNumbers']);
    Route::post('sms', [SmsController::class, 'updateNumbers']);
    Route::post('sms/send-code', [SmsController::class, 'sendNewCode']);
    Route::post('sms/confirm-code', [SmsController::class, 'confirmCode']);

    Route::patch('slot/bulkupdate', [SlotController::class, 'bulkUpdate']);
    Route::get('slot/check-datetime', [SlotController::class, 'checkDateTime']);
    Route::post('slot/copy', [SlotController::class, 'copy']);
    Route::get('slot/dirt-shift-times', [SlotController::class, 'dirtShiftTimes']);
    Route::get('slot/flakes', [SlotController::class, 'flakeReport']);
    Route::get('slot/hq-forecast-report', [SlotController::class, 'hqForecastReport']);
    Route::post('slot/link-slots', [SlotController::class, 'linkSlots']);
    Route::get('slot/shift-coverage-report', [SlotController::class, 'shiftCoverageReport']);
    Route::get('slot/shift-lead-report', [SlotController::class, 'shiftLeadReport']);
    Route::get('slot/shift-signups-report', [SlotController::class, 'shiftSignUpsReport']);
    Route::get('slot/people-in-period', [SlotController::class, 'peopleInPeriod']);
    Route::get('slot/position-schedule-report', [SlotController::class, 'positionScheduleReport']);
    Route::get('slot/callsign-schedule-report', [SlotController::class, 'callsignScheduleReport']);
    Route::get('slot/years', [SlotController::class, 'years']);

    Route::get('slot/{slot}/people', [SlotController::class, 'people']);
    Route::resource('slot', SlotController::class);

    Route::get('survey/positions', [SurveyController::class, 'positions']);
    Route::get('survey/questionnaire', [SurveyController::class, 'questionnaire']);
    Route::post('survey/submit', [SurveyController::class, 'submit']);
    Route::get('survey/trainer-surveys', [SurveyController::class, 'trainerSurveys']);
    Route::get('survey/trainer-report', [SurveyController::class, 'trainerReport']);
    Route::get('survey/{survey}/all-trainers-report', [SurveyController::class, 'allTrainersReport']);
    Route::post('survey/{survey}/duplicate', [SurveyController::class, 'duplicate']);
    Route::get('survey/{survey}/report', [SurveyController::class, 'report']);
    Route::resource('survey', SurveyController::class);

    Route::resource('survey-group', SurveyGroupController::class);

    Route::resource('survey-question', SurveyQuestionController::class);

    Route::post('swag/bulk-grant-swag', [SwagController::class, 'bulkGrantSwag']);
    Route::get('swag/potential-swag', [SwagController::class, 'potentialSwagReport']);
    Route::get('swag/shirts', [SwagController::class, 'shirts']);
    Route::get('swag/potential-shirts-earned', [SwagController::class, 'potentialShirtsEarnedReport']);
    Route::resource('swag', SwagController::class);

    Route::get('team/directory', [TeamController::class, 'directory']);
    Route::get('team/people-by-teams', [TeamController::class, 'peopleByTeamsReport']);
    Route::post('team/{team}/bulk-grant-revoke', [TeamController::class, 'bulkGrantRevoke']);
    Route::get('team/{team}/membership', [TeamController::class, 'membership']);
    Route::resource('team', TeamController::class);

    Route::get('training-session/sessions', [TrainingSessionController::class, 'sessions']);

    Route::post('training-session/{training_session}/graduate-candidates', [TrainingSessionController::class, 'graduateCandidates']);
    Route::get('training-session/{training_session}/graduation-candidates', [TrainingSessionController::class, 'graduationCandidates']);
    Route::post('training-session/{training_session}/score-student', [TrainingSessionController::class, 'scoreStudent']);
    Route::post('training-session/{training_session}/trainer-status', [TrainingSessionController::class, 'trainerStatus']);
    Route::get('training-session/{training_session}/trainers', [TrainingSessionController::class, 'trainers']);
    Route::post('training-session/{trainee_note}/update-note', [TrainingSessionController::class, 'updateNote']);
    Route::delete('training-session/{trainee_note}/delete-note', [TrainingSessionController::class, 'deleteNote']);
    Route::get('training-session/{training_session}', [TrainingSessionController::class, 'show']);

    Route::get('training/{id}/multiple-enrollments', [TrainingController::class, 'multipleEnrollmentsReport']);
    Route::get('training/{id}/capacity', [TrainingController::class, 'capacityReport']);
    Route::get('training/{id}/people-training-completed', [TrainingController::class, 'peopleTrainingCompleted']);
    Route::get('training/{id}/trainer-attendance', [TrainingController::class, 'trainerAttendanceReport']);
    Route::get('training/{id}/untrained-people', [TrainingController::class, 'untrainedPeopleReport']);
    Route::get('training/{id}', [TrainingController::class, 'show']);

    Route::get('ticketing/info', [TicketingController::class, 'ticketingInfo']);
    Route::get('ticketing/thresholds', [TicketingController::class, 'thresholds']);
    Route::get('ticketing/statistics', [TicketingController::class, 'statistics']);
    Route::post('ticketing/{person}/delivery', [TicketingController::class, 'delivery']);
    Route::get('ticketing/{person}/package', [TicketingController::class, 'package']);
    Route::patch('ticketing/{person}/wapso', [TicketingController::class, 'storeWAPSO']);

    Route::post('timesheet/bulk-sign-in-out', [TimesheetController::class, 'bulkSignInOut']);
    Route::get('timesheet/check-overlap', [TimesheetController::class, 'checkForOverlaps']);
    Route::get('timesheet/correction-requests', [TimesheetController::class, 'correctionRequests']);
    Route::get('timesheet/correction-statistics', [TimesheetController::class, 'correctionStatistics']);
    Route::post('timesheet/confirm', [TimesheetController::class, 'confirm']);
    Route::get('timesheet/event-stats', [TimesheetController::class, 'eventStatsReport']);
    Route::get('timesheet/freaking-years', [TimesheetController::class, 'freakingYearsReport']);
    Route::get('timesheet/forced-signins-report', [TimesheetController::class, 'forcedSigninsReport']);
    Route::get('timesheet/hours-credits', [TimesheetController::class, 'hoursCreditsReport']);
    Route::get('timesheet/info', [TimesheetController::class, 'info']);
    Route::get('timesheet/log', [TimesheetController::class, 'showLog']);
    Route::get('timesheet/no-shows-report', [TimesheetController::class, 'noShowsReport']);
    Route::get('timesheet/on-duty-shift-lead-report', [TimesheetController::class, 'onDutyShiftLeadReport']);
    Route::get('timesheet/on-duty-report', [TimesheetController::class, 'OnDutyReport']);
    Route::get('timesheet/payroll', [TimesheetController::class, 'payrollReport']);
    Route::get('timesheet/radio-eligibility', [TimesheetController::class, 'radioEligibilityReport']);
    Route::get('timesheet/sanity-checker', [TimesheetController::class, 'sanityChecker']);
    Route::post('timesheet/signin', [TimesheetController::class, 'signin']);
    Route::get('timesheet/shift-drop-report', [TimesheetController::class, 'shiftDropReport']);
    Route::get('timesheet/top-hour-earners', [TimesheetController::class, 'topHourEarnersReport']);
    Route::get('timesheet/by-callsign', [TimesheetController::class, 'timesheetByCallsign']);
    Route::get('timesheet/by-position', [TimesheetController::class, 'timesheetByPosition']);
    Route::get('timesheet/retention-report', [TimesheetController::class, 'retentionReport']);
    Route::post('timesheet/repair-slot-assoc', [TimesheetController::class, 'repairSlotAssociations']);
    Route::get('timesheet/thank-you', [TimesheetController::class, 'thankYou']);
    Route::get('timesheet/totals', [TimesheetController::class, 'timesheetTotals']);
    Route::get('timesheet/unconfirmed-people', [TimesheetController::class, 'unconfirmedPeople']);
    Route::match(['GET', 'POST'], 'timesheet/special-teams', [TimesheetController::class, 'specialTeamsReport']);
    Route::delete('timesheet/{timesheet}/delete-mistake', [TimesheetController::class, 'deleteMistake']);
    Route::post('timesheet/{timesheet}/resignin', [TimesheetController::class, 'resignin']);
    Route::post('timesheet/{timesheet}/signoff', [TimesheetController::class, 'signoff']);
    Route::patch('timesheet/{timesheet}/update-position', [TimesheetController::class, 'updatePosition']);
    Route::resource('timesheet', TimesheetController::class);

    Route::resource('timesheet-missing', TimesheetMissingController::class);

    Route::resource('help', HelpController::class);

    Route::get('vehicle/info/{person}', [VehicleController::class, 'info']);
    Route::get('vehicle/paperwork', [VehicleController::class, 'paperwork']);
    Route::resource('vehicle', VehicleController::class);
});
