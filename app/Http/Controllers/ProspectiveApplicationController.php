<?php

namespace App\Http\Controllers;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\AIHandlesExtract;
use App\Lib\ProspectiveApplicationImport;
use App\Lib\ProspectiveApplicationStatusMail;
use App\Lib\ProspectiveClubhouseAccountFromApplication;
use App\Mail\ProspectiveApplication\SendEmail;
use App\Models\MailLog;
use App\Models\ProspectiveApplication;
use App\Models\ProspectiveApplicationNote;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use ReflectionException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ProspectiveApplicationController extends ApiController
{
    /**
     * Retrieve a  set of prospective applications
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('index', ProspectiveApplication::class);
        $params = request()->validate(
            [
                'year' => 'sometimes|integer',
                'status' => 'sometimes|string',
                'contact' => 'sometimes|string',
                'application_id' => 'sometimes|string',
                'person_id' => 'sometimes|string',
            ]);

        $applicationId = $params['application_id'] ?? null;
        if ($applicationId) {
            // Support hack for ember-data queryRecord().
            $record = ProspectiveApplication::findByApplicationIdOrFail($applicationId);
            $record->loadRelationships();
            $record->screenHandles();
            return $this->success($record);
        }

        $rows = ProspectiveApplication::findForQuery($params);

        return $this->success($rows, null, 'prospective_application');
    }

    /**
     * Search for applications
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function search(): JsonResponse
    {
        $this->authorize('search', ProspectiveApplication::class);

        $params = request()->validate([
            'query' => 'required|string'
        ]);

        return response()->json(ProspectiveApplication::searchForApplications($params['query']));
    }

    /**
     * Create a prospective application
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', ProspectiveApplication::class);

        $prospectiveApplication = new ProspectiveApplication();
        $this->fromRest($prospectiveApplication);

        if ($prospectiveApplication->save()) {
            $prospectiveApplication->loadRelationships();
            $prospectiveApplication->screenHandles();
            return $this->success($prospectiveApplication);
        }

        return $this->restError($prospectiveApplication);
    }

    /**
     * Show a prospective application
     *
     * @param ProspectiveApplication $prospectiveApplication
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(ProspectiveApplication $prospectiveApplication): JsonResponse
    {
        $this->authorize('show', $prospectiveApplication);
        $prospectiveApplication->loadRelationships();
        $prospectiveApplication->screenHandles();
        return $this->success($prospectiveApplication);
    }

    /**
     * Update a prospective application
     *
     * @param ProspectiveApplication $prospectiveApplication
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws ValidationException
     */

    public function update(ProspectiveApplication $prospectiveApplication): JsonResponse
    {
        $this->authorize('update', $prospectiveApplication);

        $this->fromRest($prospectiveApplication);
        if ($prospectiveApplication->save()) {
            $prospectiveApplication->loadRelationships();
            $prospectiveApplication->screenHandles();
            return $this->success($prospectiveApplication);
        }

        return $this->restError($prospectiveApplication);
    }

    /**
     * Delete a prospective application
     *
     * @param ProspectiveApplication $prospectiveApplication
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(ProspectiveApplication $prospectiveApplication): JsonResponse
    {
        $this->authorize('destroy', $prospectiveApplication);
        $prospectiveApplication->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * Find past and/or duplications applications related to this one.
     *
     * @param ProspectiveApplication $prospectiveApplication
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function relatedApplications(ProspectiveApplication $prospectiveApplication): JsonResponse
    {
        $this->authorize('show', $prospectiveApplication);

        $rows = DB::table('prospective_application')
            ->select('id', 'salesforce_name', 'year', 'status')
            ->where('id', '!=', $prospectiveApplication->id)
            ->where('bpguid', $prospectiveApplication->bpguid)
            ->get();

        return response()->json(['applications' => $rows]);
    }

    /**
     * Update the application's status and fire off an email (maybe)
     *
     * @throws AuthorizationException|ValidationException|TransportExceptionInterface
     */

    public function updateStatus(ProspectiveApplication $prospectiveApplication): JsonResponse
    {
        $this->authorize('updateStatus', $prospectiveApplication);

        $rules = [
            'status' => 'required|string',
            'message' => 'sometimes|string',
            'approved_handle' => 'sometimes|string',
        ];

        $params = request()->validate($rules);

        $status = $params['status'];
        $message = $params['message'] ?? null;

        $prospectiveApplication->status = $status;
        switch ($status) {
            case ProspectiveApplication::STATUS_MORE_HANDLES:
                $prospectiveApplication->recordRejections($message);
                break;

            case ProspectiveApplication::STATUS_APPROVED:
            case ProspectiveApplication::STATUS_HOLD_PII_ISSUE:
                if (isset($params['approved_handle'])) {
                    $prospectiveApplication->approved_handle = $params['approved_handle'];
                }
                break;
        }
        $prospectiveApplication->saveOrThrow();
        $prospectiveApplication->loadRelationships();

        ProspectiveApplicationStatusMail::execute($prospectiveApplication, $status, $message);
        return $this->success();
    }

    /**
     * Add a note to an application
     *
     * @param ProspectiveApplication $prospectiveApplication
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function addNote(ProspectiveApplication $prospectiveApplication): JsonResponse
    {
        $this->authorize('addNote', $prospectiveApplication);

        $params = request()->validate([
            'note' => 'required|string',
            'type' => 'required|string'
        ]);

        ProspectiveApplicationNote::create([
            'prospective_application_id' => $prospectiveApplication->id,
            'person_id' => $this->user->id,
            'note' => $params['note'],
            'type' => $params['type']
        ]);

        return $this->success();
    }

    /**
     * Edit a note
     *
     * @param ProspectiveApplication $prospectiveApplication
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function updateNote(ProspectiveApplication $prospectiveApplication): JsonResponse
    {
        $this->authorize('updateNote', $prospectiveApplication);

        $params = request()->validate([
            'prospective_application_note_id' => 'required|integer',
            'note' => 'required|string',
        ]);

        $note = ProspectiveApplicationNote::findNoteForApplication($prospectiveApplication->id, $params['prospective_application_note_id']);
        $note->note = $params['note'];
        $note->saveOrThrow();

        return $this->success();
    }

    /**
     * Delete a note
     *
     * @param ProspectiveApplication $prospectiveApplication
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function deleteNote(ProspectiveApplication $prospectiveApplication): JsonResponse
    {
        $this->authorize('deleteNote', $prospectiveApplication);

        $params = request()->validate([
            'prospective_application_note_id' => 'required|integer'
        ]);

        $note = ProspectiveApplicationNote::findNoteForApplication($prospectiveApplication->id, $params['prospective_application_note_id']);
        $note->delete();

        return $this->success();
    }

    /**
     * Email an applicant
     *
     * @param ProspectiveApplication $prospectiveApplication
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function sendEmail(ProspectiveApplication $prospectiveApplication): JsonResponse
    {
        $this->authorize('sendEmail', ProspectiveApplication::class);

        $params = request()->validate([
            'subject' => 'required|string',
            'message' => 'required|string'
        ]);

        mail_send(new SendEmail($prospectiveApplication, $params['subject'], $params['message']));

        return $this->success();
    }

    /**
     * Preview an email based on the proposed status.
     *
     * @param ProspectiveApplication $prospectiveApplication
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws UnacceptableConditionException|ReflectionException
     */

    public function previewEmail(ProspectiveApplication $prospectiveApplication): JsonResponse
    {
        $this->authorize('previewEmail', $prospectiveApplication);
        $params = request()->validate([
            'status' => 'sometimes|string',
            'message' => 'sometimes|string',
            'subject' => 'sometimes|string',
            'is_raw_email' => 'sometimes|boolean',
        ]);

        $message = $params['message'] ?? null;
        $isRawEmail = $params['is_raw_email'] ?? false;

        if ($isRawEmail) {
            $rendered = (new SendEmail($prospectiveApplication, $params['subject'] ?? null, $message))->render();
        } else {
            $status = $params['status'];
            $rendered = ProspectiveApplicationStatusMail::preview($prospectiveApplication, $status, $message);
        }

        return response()->json(['mail' => $rendered]);
    }

    /**
     * Retrieve the email logs for an application
     *
     * @param ProspectiveApplication $prospectiveApplication
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function emailLogs(ProspectiveApplication $prospectiveApplication): JsonResponse
    {
        $this->authorize('emailLogs', ProspectiveApplication::class);

        return response()->json(['email_logs' => MailLog::findForProspectiveApplication($prospectiveApplication->id)]);
    }

    /**
     * Run text through an AI Engine to parse out the actual names, and remove any
     * extraneous bits related to handle preference, and other commentary.
     *
     * @return JsonResponse
     * @throws AuthorizationException|UnacceptableConditionException
     */

    public function handlesExtract(): JsonResponse
    {
        $this->authorize('handlesExtract', ProspectiveApplication::class);
        $params = request()->validate([
            'text' => 'required|string'
        ]);

        return response()->json(['handles' => AIHandlesExtract::execute($params['text'])]);
    }

    /**
     * Import the applications into the Clubhouse
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function import(): JsonResponse
    {
        $this->authorize('import', ProspectiveApplication::class);
        $params = request()->validate([
            'commit' => 'sometimes|boolean',
        ]);

        $import = new ProspectiveApplicationImport();
        if (!$import->auth()) {
            return response()->json([
                'status' => 'auth-fail',
                'message' => $import->sf->errorMessage,
            ]);
        }

        $import->importUnprocessed($params['commit'] ?? false);

        return response()->json([
            'status' => 'success',
            'new' => $import->newApplications,
            'existing' => $import->existingApplications,
            'query_failures' => $import->queryFailures,
            'create_failures' => $import->creationFailures,
            'api_error' => $import->errorMessage,
        ]);
    }

    /**
     * Convert approved applications into Clubhouse accounts.
     *
     * @throws AuthorizationException
     */

    public function createProspectives(): JsonResponse
    {
        $this->authorize('createProspectives', ProspectiveApplication::class);
        $params = request()->validate([
            'commit' => 'sometimes|boolean',
        ]);

        $applications = ProspectiveClubhouseAccountFromApplication::execute($params['commit'] ?? false);
        if ($applications === false) {
            // Salesforce authentication failure.
            return response()->json(['status' => 'auth-failure']);
        }

        return response()->json([
            'status' => 'success',
            'applications' => $applications,
        ]);
    }
}
