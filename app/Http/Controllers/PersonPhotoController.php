<?php

namespace App\Http\Controllers;

use App\Exceptions\UnacceptableConditionException;
use App\Mail\PhotoApprovedMail;
use App\Mail\PhotoRejectedMail;
use App\Models\ErrorLog;
use App\Models\Person;
use App\Models\PersonPhoto;
use App\Models\Role;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use ReflectionException;
use RuntimeException;

/*
 *  Clubhouse photo management is handled through here.
 */

class PersonPhotoController extends ApiController
{
    /**
     * Return a list of photos based on the given criteria
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('index', PersonPhoto::class);

        $params = request()->validate([
            'status' => 'sometimes|string',
            'person_status' => 'sometimes|string',
            'page' => 'sometimes|integer',
            'page_size' => 'sometimes|integer',
            'person_id' => 'sometimes|integer',
            'include_rejects' => 'sometimes|boolean',
            'sort' => [
                'sometimes',
                Rule::in(['callsign'])
            ],
        ]);

        $result = PersonPhoto::findForQuery($params);
        return $this->success($result['person_photo'], $result['meta'], 'person_photo');
    }

    /**
     * Retrieve the review configuration.
     * @return JsonResponse
     *
     * @throws AuthorizationException
     */

    public function reviewConfig(): JsonResponse
    {
        $this->authorize('reviewConfig', PersonPhoto::class);

        $rejections = [];
        foreach (PersonPhoto::REJECTIONS as $key => $info) {
            $rejections[] = [
                'key' => $key,
                'label' => $info['label']
            ];
        }

        return response()->json([
            'review_config' => [
                'rejections' => $rejections,
                'upload_enable' => setting('PhotoUploadEnable')
            ]
        ]);
    }


    /**
     * Create a new person photo record
     *
     * Not implement -- creation is done thru upload
     */

    public function store(Request $request)
    {
        throw new RuntimeException("Unimplemented");
    }

    /**
     * Return a photo record
     *
     * @param PersonPhoto $personPhoto
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(PersonPhoto $personPhoto): JsonResponse
    {
        $this->authorize('show', $personPhoto);
        $personPhoto->load(PersonPhoto::PERSON_TABLES);
        return $this->success($personPhoto);
    }

    /**
     * Update a record
     *
     * @param PersonPhoto $personPhoto
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function update(PersonPhoto $personPhoto): JsonResponse
    {
        $this->authorize('update', $personPhoto);
        prevent_if_ghd_server('photo update');

        $person = $personPhoto->person;
        if (!$personPhoto->person) {
            throw new UnacceptableConditionException('Record is not linked to a person.');
        }

        $this->fromRest($personPhoto);

        $reviewed = false;
        if ($personPhoto->isDirty('status')) {
            // Setting the status means the record has been reviewed.
            $personPhoto->reviewed_at = now();
            $personPhoto->review_person_id = $this->user->id;
            $reviewed = true;
        }

        if (!$personPhoto->save()) {
            return $this->restError($personPhoto);
        }

        /*
         * Only email an approved or rejection message if the account is not locked
         */

        if ($reviewed && !in_array($person->status, Person::LOCKED_STATUSES)) {
            $status = $personPhoto->status;
            if ($status == PersonPhoto::APPROVED) {
                mail_send(new PhotoApprovedMail($person));
            } elseif ($status == PersonPhoto::REJECTED) {
                mail_send(new PhotoRejectedMail($person, $personPhoto->reject_reasons, $personPhoto->reject_message));
            }
        }

        $personPhoto->load(PersonPhoto::PERSON_TABLES);

        return $this->success($personPhoto);
    }

    /**
     * Replace a photo (aka edit)
     *
     * Ideally the client will take the original photo, edit it, and then send it back to the server.
     *
     * @param PersonPhoto $personPhoto
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function replace(PersonPhoto $personPhoto): JsonResponse
    {
        $this->authorize('update', $personPhoto);

        prevent_if_ghd_server('Photo replacement');

        $params = request()->validate(['image' => 'required']);

        $oldFilename = $personPhoto->image_filename;
        list ($image, $width, $height) = PersonPhoto::processImage($params['image'], $personPhoto->person_id, PersonPhoto::SIZE_ORIGINAL);

        $personPhoto->edit_person_id = $this->user->id;
        $personPhoto->edited_at = now();
        $personPhoto->width = $width;
        $personPhoto->height = $height;

        try {
            $personPhoto->storeImage($image, $personPhoto->edited_at->timestamp, PersonPhoto::SIZE_BMID);
        } catch (Exception $e) {
            ErrorLog::recordException($e, 'person-photo-storage-exception', [
                'target_person_id' => $personPhoto->person_id,
                'filename' => $personPhoto->image_filename,
                'action' => 'replace'
            ]);

            return response()->json(['status' => 'storage-fail'], 500);
        }

        $oldProfileFilename = $personPhoto->profile_filename;
        list ($image, $width, $height) = PersonPhoto::processImage($params['image'], $personPhoto->person_id, PersonPhoto::SIZE_PROFILE);
        $personPhoto->profile_width = $width;
        $personPhoto->profile_height = $height;
        if ($personPhoto->storeImage($image, $personPhoto->edited_at->timestamp, PersonPhoto::SIZE_PROFILE) === false) {
            ErrorLog::record('person-photo-store-error', [
                'person_id' => $this->user->id,
                'target_person_id' => $personPhoto->person_id,
            ]);

            return response()->json(['status' => 'storage-fail'], 500);
        }

        $personPhoto->auditReason = 'photo replace';
        $personPhoto->saveWithoutValidation();

        // Delete the old (cropped) photo
        try {
            PersonPhoto::storage()->delete(PersonPhoto::storagePath($oldFilename));
            if (!empty($oldProfileFilename)) {
                PersonPhoto::storage()->delete(PersonPhoto::storagePath($oldProfileFilename));
            }
        } catch (Exception $e) {
            ErrorLog::recordException($e, 'person-photo-delete-exception', [
                'target_person_id' => $personPhoto->person_id,
                'filename' => $oldFilename,
                'action' => 'replace'
            ]);
            // Allow the request to complete. The photo record and image was updated successfully.
        }

        return $this->success();
    }

    /**
     * Activate a record - i.e. set the photo as the current photo for a person
     *
     * @param PersonPhoto $personPhoto
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function activate(PersonPhoto $personPhoto): JsonResponse
    {
        $this->authorize('update', $personPhoto);

        prevent_if_ghd_server('Photo activation');

        $person = $personPhoto->person;

        if (!$person) {
            throw new RuntimeException("Photo has no person associated with it");
        }

        $person->person_photo_id = $personPhoto->id;
        $person->auditReason = 'photo activate';
        $person->saveWithoutValidation();

        return $this->success();
    }

    /**
     * Remove a photo.
     *
     * @param PersonPhoto $personPhoto
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(PersonPhoto $personPhoto): JsonResponse
    {
        $this->authorize('destroy', $personPhoto);

        prevent_if_ghd_server('Photo deletion');

        $personPhoto->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * Obtain the photo url and status
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function photo(Person $person): JsonResponse
    {
        $this->authorize('photo', [PersonPhoto::class, $person]);
        return response()->json(['photo' => PersonPhoto::retrieveInfo($person)]);
    }

    /**
     * Upload photo
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException|UnacceptableConditionException
     */

    public function upload(Person $person): JsonResponse
    {
        $this->authorize('upload', [PersonPhoto::class, $person]);

        prevent_if_ghd_server('Photo Uploading');

        if (!setting('PhotoUploadEnable')
            && !$this->userHasRole([Role::ADMIN, Role:: VC])) {
            throw new UnacceptableConditionException('Photo uploading is currently disabled.');
        }

        $params = request()->validate([
            'image' => 'sometimes', // Cropped image
            'orig_image' => 'required'  // Original image
        ]);

        $personId = $person->id;

        list ($origContents, $origWidth, $origHeight) = PersonPhoto::processImage($params['orig_image'], $personId, PersonPhoto::SIZE_ORIGINAL);
        list ($imageContents, $imageWidth, $imageHeight) = PersonPhoto::processImage($params['image'] ?? $params['orig_image'], $personId, PersonPhoto::SIZE_BMID);
        list ($profileContents, $profileWidth, $profileHeight) = PersonPhoto::processImage($params['image'] ?? $params['orig_image'], $personId, PersonPhoto::SIZE_PROFILE);

        if (!$imageContents || !$origContents) {
            return response()->json(['status' => 'conversion-fail'], 500);
        }

        $photo = new PersonPhoto;
        $photo->person_id = $personId;
        $photo->status = PersonPhoto::SUBMITTED;
        $photo->uploaded_at = now();
        $photo->upload_person_id = $this->user->id;

        $photo->width = $imageWidth;
        $photo->height = $imageHeight;
        $photo->orig_width = $origWidth;
        $photo->orig_height = $origHeight;
        $photo->profile_height = $profileHeight;
        $photo->profile_width = $profileWidth;

        $timestamp = $photo->uploaded_at->timestamp;

        if ($photo->storeImage($imageContents, $timestamp, PersonPhoto::SIZE_BMID) === false) {
            ErrorLog::record('person-photo-store-error', [
                'person_id' => $this->user->id,
                'target_person_id' => $personId,
            ]);

            return response()->json(['status' => 'storage-fail'], 500);
        }

        if ($photo->storeImage($origContents, $timestamp, PersonPhoto::SIZE_ORIGINAL) === false) {
            ErrorLog::record('person-photo-store-error', [
                'person_id' => $this->user->id,
                'target_person_id' => $personId,
            ]);

            // Try cleaning up -- ignore any errors
            try {
                $photo->deleteAllVersions();
            } catch (Exception $e) {
            }

            return response()->json(['status' => 'storage-fail'], 500);
        }

        if ($photo->storeImage($profileContents, $timestamp, PersonPhoto::SIZE_PROFILE) === false) {
            ErrorLog::record('person-photo-store-error', [
                'person_id' => $this->user->id,
                'target_person_id' => $personId,
            ]);

            // Try cleaning up -- ignore any errors
            try {
                $photo->deleteAllVersions();
            } catch (Exception $e) {
            }

            return response()->json(['status' => 'storage-fail'], 500);
        }

        $photo->analyzeImage($imageContents);
        $photo->saveWithoutValidation();

        $person->person_photo_id = $photo->id;
        $person->saveWithoutValidation();

        /*
         * Remove any previously submitted un-reviewed photos
         */

        $prevSubmitted = PersonPhoto::where('person_id', $person->id)
            ->where('id', '!=', $photo->id)
            ->where('status', PersonPhoto::SUBMITTED)
            ->get();

        foreach ($prevSubmitted as $submitted) {
            $submitted->auditReason = 'upload replacement';
            $submitted->delete();
        }

        return $this->success();
    }

    /**
     * Preview a rejection email
     *
     * @param PersonPhoto $personPhoto
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws ReflectionException
     */

    public function rejectPreview(PersonPhoto $personPhoto): JsonResponse
    {
        $this->authorize('rejectPreview', $personPhoto);

        prevent_if_ghd_server('Photo rejection');

        $params = request()->validate([
            'reject_reasons' => 'sometimes|array',
            'reject_message' => 'sometimes|string|nullable'
        ]);

        $mail = new PhotoRejectedMail($personPhoto->person, $params['reject_reasons'] ?? [], $params['reject_message'] ?? '');
        return response()->json(['mail' => $mail->render()]);
    }

    /**
     * Convert a photo to jpg, and send it back. Used to handle HEIC (Apple/iPhone format) for editing purposes.
     *
     * @throws AuthorizationException|ValidationException
     */

    public function convertPhoto(): JsonResponse
    {
        prevent_if_ghd_server('Photo uploading');
        $this->authorize('convertPhoto', PersonPhoto::class);
        $params = request()->validate([
            'image' => [
                'required',
            ]
        ]);

        $converted = PersonPhoto::convertToJpeg($params['image']);
        if (!$converted) {
            throw  ValidationException::withMessages([
                'image' => 'Cannot convert the image'
            ]);
        }

        return response()->json(['image' => base64_encode($converted)]);
    }
}
