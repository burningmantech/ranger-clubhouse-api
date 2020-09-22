<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;

use App\Models\ActionLog;
use App\Models\ErrorLog;
use App\Models\Person;
use App\Models\PersonPhoto;
use App\Models\Role;

use App\Mail\PhotoApprovedMail;
use App\Mail\PhotoRejectedMail;

use App\Helpers\SqlHelper;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Storage;

use Illuminate\Validation\Rule;
use Intervention\Image\ImageManagerStatic as Image;

/*
 *  Clubhouse photo management is handled through here.
 */

class PersonPhotoController extends ApiController
{
    /**
     * Display a listing of photos based on search criteria
     */

    public function index()
    {
        $this->authorize('index', PersonPhoto::class);

        $params = request()->validate([
            'status'  => 'sometimes|string',
            'person_status' => 'sometimes|string',
            'page'          => 'sometimes|integer',
            'page_size'     => 'sometimes|integer',
            'person_id'     => 'sometimes|integer',
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
     */

    public function reviewConfig()
    {
        $this->authorize('reviewConfig', PersonPhoto::class);

        $rejections = [];
        foreach (PersonPhoto::REJECTIONS as $key => $info) {
            $rejections[] = [
                'key'   => $key,
                'label' => $info['label']
            ];
        };

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
        throw new \RuntimeException("Unimplemented");
    }

    /**
     * Display the record.
     */

    public function show(PersonPhoto $personPhoto)
    {
        $this->authorize('show', $personPhoto);
        $personPhoto->load(PersonPhoto::PERSON_TABLES);
        return $this->success($personPhoto);
    }

    /**
     * Update a record
     */

    public function update(PersonPhoto $personPhoto)
    {
        $this->authorize('update', $personPhoto);
        $person = $personPhoto->person;
        if (!$personPhoto->person) {
            throw new \InvalidArgumentException('Record is not linked to a person.');
        }

        $this->fromRest($personPhoto);

        $reviewed = false;
        if ($personPhoto->isDirty('status')) {
            // Setting the status means the record has been reviewed.
            $personPhoto->reviewed_at = SqlHelper::now();
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
                mail_to($person->email, new PhotoApprovedMail($person), true);
            } elseif ($status == PersonPhoto::REJECTED) {
                mail_to($person->email, new PhotoRejectedMail($person, $personPhoto->reject_reasons, $personPhoto->reject_message), true);
            }
        }

        $personPhoto->load(PersonPhoto::PERSON_TABLES);

        return $this->success($personPhoto);
    }

    /**
     * Replace a photo (aka edit)
     *
     * Ideally the client will take the original photo, edit it, and then send
     * it back to the server.
     */

    public function replace(PersonPhoto $personPhoto)
    {
        $this->authorize('update', $personPhoto);

        $params = request()->validate([ 'image' => 'required' ]);

        $oldFilename = $personPhoto->image_filename;
        list ($image, $width, $height) = $this->processImage($params['image'], $personPhoto->person_id);

        $personPhoto->edit_person_id = $this->user->id;
        $personPhoto->edited_at = SqlHelper::now();
        $personPhoto->width = $width;
        $personPhoto->height = $height;

        try {
            $personPhoto->storeImage($image, $personPhoto->edited_at->timestamp, false);
        } catch (\Exception $e) {
            ErrorLog::recordException($e, 'person-photo-storage-exception', [
                 'target_person_id' => $personPhoto->person_id,
                 'filename'         => $personPhoto->image_filename,
                 'action'           => 'replace'
             ]);

             return response()->json([ 'status' => 'storage-fail' ], 500);
        }

        $personPhoto->auditReason = 'photo replace';
        $personPhoto->saveWithoutValidation();

        // Delete the old (cropped) photo
        try {
            PersonPhoto::storage()->delete(PersonPhoto::storagePath($oldFilename));
        } catch (\Exception $e) {
            ErrorLog::recordException($e, 'person-photo-delete-exception', [
                    'target_person_id' => $personPhoto->person_id,
                    'filename'          => $oldFilename,
                    'action'           => 'replace'
             ]);
             // Allow the request to complete. The photo record and image was updated successfully.
        }

        return $this->success();
    }

    /**
     * Activate a record - i.e. set the photo as the current photo for a person
     */

    public function activate(PersonPhoto $personPhoto) {
        $this->authorize('update', $personPhoto);

        $person = $personPhoto->person;

        if (!$person) {
            throw new \RuntimeException("Photo has no person associated with it");
        }

        $oldPhotoId = $person->person_photo_id;
        $person->person_photo_id = $personPhoto->id;
        $person->auditReason = 'photo activate';
        $person->saveWithoutValidation();

        return $this->success();
    }

    /**
     * Remove a record.
     *
     */

    public function destroy(PersonPhoto $personPhoto)
    {
        $this->authorize('destroy', $personPhoto);

        try {
            // Remove the files.
            $personPhoto->deleteImage();
            $personPhoto->deleteOrigImage();
        } catch (\Exception $e) {
            ErrorLog::record('person-photo-delete-exception', [
                    'person_id'        => $this->user->id,
                    'target_person_id' => $personPhoto->person_id,
                    'person_photo_id'  => $personPhoto->id,
                    'image_filename'   => $personPhoto->image_filename,
                    'orig_filename'    => $personPhoto->orig_filename
             ]);
        }

        $personPhoto->delete();

        $person = $personPhoto->person;
        if ($person && $personPhoto->id == $person->person_photo_id) {
            // Is this record the person's current photo? kill it!
            $person->person_photo_id = null;
            $person->saveWithoutValidation();
        }

        return $this->restDeleteSuccess();
    }

    /*
     * Obtain the photo url and status
     */

    public function photo(Person $person)
    {
        $this->authorize('photo', [ PersonPhoto::class, $person ]);
        return response()->json([ 'photo' => PersonPhoto::retrieveInfo($person) ]);
    }

    /**
     * Upload photo
     */

    public function upload(Person $person)
    {
        $this->authorize('upload', [ PersonPhoto::class, $person ]);

        if (!setting('PhotoUploadEnable')
        && !$this->userHasRole([ Role::ADMIN, Role:: VC ])) {
            throw new \InvalidArgumentException('Photo upload is currently disabled.');
        }

        $params = request()->validate([
            'image'      => 'sometimes', // Cropped image
            'orig_image' => 'required'  // Original image
        ]);

        $personId = $person->id;

        list ($origContents, $origWidth, $origHeight) = $this->processImage($params['orig_image'], $personId);
        list ($imageContents, $imageWidth, $imageHeight) = $this->processImage($params['image'] ?? $params['orig_image'], $personId, true);

        if (!$imageContents || !$origContents) {
            return response()->json([ 'status' => 'conversion-fail' ], 500);
        }

        $photo = new PersonPhoto;
        $photo->person_id = $personId;
        $photo->status = PersonPhoto::SUBMITTED;
        $photo->uploaded_at = SqlHelper::now();
        $photo->upload_person_id = $this->user->id;

        $photo->width = $imageWidth;
        $photo->height = $imageHeight;
        $photo->orig_width = $origWidth;
        $photo->orig_height = $origHeight;

        $timestamp = $photo->uploaded_at->timestamp;

        if ($photo->storeImage($imageContents, $timestamp, false) === false) {
            ErrorLog::record('person-photo-store-error', [
                    'person_id'        => $this->user->id,
                    'target_person_id' => $personId,
             ]);

             return response()->json([ 'status' => 'storage-fail' ], 500);
        }

        if ($photo->storeImage($origContents, $timestamp, true) === false) {
            ErrorLog::record('person-photo-store-error', [
                    'person_id'        => $this->user->id,
                    'target_person_id' => $personId,
             ]);

             // Try cleaning up -- ignore any errors
             try {
                 $photo->deleteImage();
             } catch (\Exception $e) {
                 ;
             }

             return response()->json([ 'status' => 'storage-fail' ], 500);
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
            try {
                $submitted->deleteImage();
                $submitted->deleteOrigImage();
            } catch (\Exception $e) {
                ErrorLog::recordException($e, 'person-photo-delete-exception', [
                    'target_person_id' => $personId,
                    'person_photo_id'  => $submitted->id,
                    'image_filename'   => $submitted->image_filename,
                    'orig_filename'    => $submitted->orig_filename
                ]);
            }

            $submitted->auditReason = 'upload replacement';
            $submitted->delete();
        }

        return $this->success();
    }

    /**
     * Preview a rejection email
     *
     *
     */
    public function rejectPreview(PersonPhoto $personPhoto)
    {
        $this->authorize('rejectPreview', $personPhoto);

        $params = request()->validate([
            'reject_reasons' => 'sometimes|array',
            'reject_message' => 'sometimes|string'
        ]);
        
        $mail = new PhotoRejectedMail($personPhoto->person, $params['reject_reasons'] ?? [], $params['reject_message' ?? '']);
        return response()->json([ 'mail' => $mail->render() ]);
    }

    /*
     * Serve up an image with CORS.
     *
     * Only used for development.
     */

    public function photoImage($filename)
    {
        if (!app()->isLocal()) {
            $this->notPermitted("Unauthorized.");
        }

        $path = PersonPhoto::storage()->path(PersonPhoto::storagePath($filename));

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->file(
            $path,
            [
                'Content-Type' => preg_match('/\.(jpg|jpeg)$/', $filename) ? 'image/jpeg' : 'image/png',
                'Access-Control-Allow-Origin' => '*'
            ]
        );
    }

    private function processImage($imageParam, $personId, $isBmid = false) {
        $filename = $imageParam->getClientOriginalName();

        if ($isBmid) {
            $width = PersonPhoto::BMID_WIDTH;
            $height = PersonPhoto::BMID_WIDTH;
        } else {
            $width = PersonPhoto::ORIG_MAX_WIDTH;
            $height = PersonPhoto::ORIG_MAX_WIDTH;
        }

        try {
            $image = Image::make($imageParam);

            // correct image orientation
            $image->orientate();

            if ($image->width() > $width) {
                $image->resize($width, null, function ($constrain) {
                    $constrain->aspectRatio();
                });
            }

            $contents = $image->stream('jpg', 75)->getContents();
            $width = $image->width();
            $height = $image->height();
            $image->destroy();  // free up memory
            $image = null; // and kill the object
            gc_collect_cycles();     // Images can be huge, garbage collect.

            return [ $contents, $width, $height ];
        } catch (\Exception $e) {
            ErrorLog::recordException($e, 'person-photo-convert-exception', [
                'target_person_id' => $personId,
                'filename'         => $filename
             ]);

             return [ null, 0, 0];
        }
    }
}
