<?php

use Illuminate\Http\Request;

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

Route::get('config', 'ConfigController@show');

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function ($router) {
    Route::post('login', 'AuthController@login');
    Route::post('logout', 'AuthController@logout');
    Route::post('refresh', 'AuthController@refresh');
    Route::post('reset-password', 'AuthController@resetPassword');
});

Route::group([
    'middleware' => 'api',
], function ($router) {

    Route::resource('alert', 'AlertController');

    Route::get('access-document/ticketing-info', 'AccessDocumentController@ticketingInfo');
    Route::get('access-document/sowap', 'AccessDocumentController@retrieveSOWAP');
    Route::patch('access-document/sowap', 'AccessDocumentController@storeSOWAP');

    Route::patch('access-document/{access_document}/status', 'AccessDocumentController@status');
    Route::resource('access-document', 'AccessDocumentController');

    Route::resource('access-document-delivery', 'AccessDocumentDeliveryController');

    Route::resource('asset', 'AssetController');

    Route::get('callsigns', 'CallsignsController@index');
    Route::get('handles', 'HandleController@index');

    Route::post('contact/send', 'ContactController@send');

    Route::get('language/speakers', 'LanguageController@speakers');
    Route::resource('language', 'LanguageController');

    Route::resource('manual-review', 'ManualReviewController@passed');

    Route::patch('messages/{person_message}/markread', 'PersonMessageController@markread');
    Route::resource('messages', 'PersonMessageController', [ 'only' => [ 'index', 'store', 'destroy' ]]);

    Route::get('person/{person}/alerts', 'AlertPersonController@index');
    Route::patch('person/{person}/alerts', 'AlertPersonController@update');
    Route::get('person/{person}/mentees', 'PersonController@mentees');
    Route::get('person/{person}/credits', 'PersonController@credits');
    Route::get('person/{person}/schedule/permission', 'PersonScheduleController@permission');
    Route::resource('person/{person}/schedule', 'PersonScheduleController', [ 'only' => [ 'index', 'store', 'destroy' ]]);

    Route::get('person/{person}/positions', 'PersonController@positions');
    Route::post('person/{person}/positions', 'PersonController@updatePositions');
    Route::get('person/{person}/photo', 'PersonController@photo');
    Route::patch('person/{person}/password', 'PersonController@password');
    Route::get('person/{person}/roles', 'PersonController@roles');
    Route::post('person/{person}/roles', 'PersonController@updateRoles');

    Route::get('person/{person}/teacher', 'PersonController@teacher');
    Route::get('person/{person}/unread-message-count', 'PersonController@UnreadMessageCount');
    Route::get('person/{person}/yearinfo', 'PersonController@yearInfo');
    Route::get('person/{person}/years', 'PersonController@years');

    Route::resource('person', 'PersonController', [ 'only' => [ 'index','show','store','update','destroy' ]]);

    Route::resource('position', 'PositionController');

    Route::resource('role', 'RoleController');

    Route::get('slot/years', 'SlotController@years');
    Route::get('slot/{slot}/people', 'SlotController@people');
    Route::patch('slot/bulkupdate', 'SlotController@bulkUpdate');
    Route::resource('slot', 'SlotController');

    Route::get('sms', 'SmsController@getNumbers');
    Route::post('sms', 'SmsController@updateNumbers');
    Route::post('sms/send-code', 'SmsController@sendNewCode');
    Route::post('sms/confirm-code', 'SmsController@confirmCode');

    Route::get('training/{id}/multiple-enrollments', 'TrainingController@multipleEnrollmentsReport');
    Route::get('training/{id}/capacity', 'TrainingController@capacityReport');
    Route::get('training/{id}', 'TrainingController@show');

    Route::get('timesheet/info', 'TimesheetController@info');
    Route::resource('timesheet', 'TimesheetController');

    Route::resource('timesheet-missing', 'TimesheetMissingController');
});
