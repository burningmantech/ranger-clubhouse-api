<?php

namespace App\Models;

use App\Models\ApiModel;

use App\Models\Person;
use App\Models\ErrorLog;


use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


use Aws\Rekognition\RekognitionClient;
use Aws\Credentials\Credentials;
use RuntimeException;

/*
 * person_photo represents the most recent photo submission, rejection, or approval
 * for a person
 */


class PersonPhoto extends ApiModel
{
    protected $table = 'person_photo';
    public $timestamps = true;
    protected $auditModel = true;

    const STORAGE_DIR = 'photos/';
    const STORAGE_STAGING_DIR = 'staging/';

    protected $guarded = [
        'person_id',
        'edit_person_id',
        'upload_person_id',
        'review_person_id'
    ];

    protected $dates = [
        'uploaded_at',
        'reviewed_at',
        'uploaded_at',
        'edited_at'
    ];

    protected $appends = [
        'image_url',
        'orig_url',
        'reject_labels',
        'analysis_details'
    ];

    const APPROVED = 'approved';
    const REJECTED = 'rejected';
    const SUBMITTED = 'submitted';
    const MISSING = 'missing';

    const NOT_REQUIRED = 'not-required'; // Not stored within the database, used by scheduling gate keeping logic

    // Image ratio is 7 by 9 for 350px by 450px
    const BMID_WIDTH = 350;
    const BMID_HEIGHT = 450;

    const ORIG_MAX_WIDTH = 1050;
    const ORIG_MAX_HEIGHT = 1350;

    const PERSON_TABLES = [
        'person:id,callsign,status,first_name,last_name',
        'review_person:id,callsign',
        'upload_person:id,callsign',
        'edit_person:id,callsign'
    ];

    protected $rules = [
        'status' => 'required|string',
    ];

    const REJECTIONS = [
        'underexposed' => [
            'label' => 'Underexposed',
            'message' => 'The photo is not bright enough. Your face should be evenly lit and clearly visible. Try taking the photo outside, or turning on more lights within the room'
        ],

        'overexposed' => [
            'label' => 'Overexposed',
            'message' => 'The photo is too bright. Try turning down the lighting in the room, or move out of direct sunlight',
        ],

        'blurry' => [
            'label' => 'Too blurry',
            'message' => "The photo is blurry. Retake the photo and make sure things are in focus. Don't forget to clean the lens!",
        ],

        'grainy' => [
            'label' => 'Too grainy',
            'message' => "The photo is too grainy. Move closer to the camera AND not crop the image OR the photo file is too small.",
        ],

        'altered' => [
            'label' => 'Altered / Photoshopped',
            'message' => 'The photo has been altered. Do not use beauty filters, emojis, copy-n-paste, etc.'
        ],

        'other-people' => [
            'label' => 'Other people present',
            'message' => 'This might not be a photo of just you. Submit a new photo with only you in it.'
        ],

        'scanned-photo' => [
            'label' => 'Scanned photo',
            'message' => 'A scanned photo or a photo of a photo cannot be used. Try using a smartphone or tablet to take a selfie.'
        ],

        'is-photo-of-id' => [
            'label' => "Driver's License / Photo",
            'message' => "Pictures of your driver’s license, passport, or similar id is not allowed. Submit an original photo."
        ],

        'not-color' => [
            'label' => 'Not color image',
            'message' => 'A black and white photo cannot be used. Submit a color photo.',
        ],

        'dark-background' => [
            'label' => 'Dark background',
            'message' => 'The background is poorly lit. Retake the photo against a lighter background or one with better lighting.'
        ],

        'no-profile-background-contrast' => [
            'label' => 'No profile/background contrast',
            'message' => 'There is not enough contrast between the background and your profile. Retake the photo with a different background.'
        ],

        'no-hair-background-contrast' => [
            'label' => 'No hair/background contrast',
            'message' => 'There is not enough contrast between your hair and the background. Retake the photo with a different background.'
        ],

        'shadows' => [
            'label' => 'Shadows on face',
            'message' => 'You have one or more shadows on your face. Your face should be evenly lit and clearly visible.'
        ],

        'wearing-hat' => [
            'label' => 'No hats/headbands',
            'message' => 'Remove anything on your head such as hats, headbands, wreaths, horns, flowers, etc. Woven braids and religious attire is okay.',
        ],

        'wearing-goggles' => [
            'label' => 'Wearing sunglasses / goggles',
            'message' => 'Sunglasses and goggles are not allowed. Your entire face and eyes must be clearly visible. Clear prescription glasses are okay as long as we can see your eyes.'
        ],

        'face-covered' => [
            'label' => 'Face covered (masks, paint, etc)',
            'message' => 'Your face is covered. No sunglasses, dust masks, face paint, etc. Your entire face must be clearly visible.'
        ],

        'not-facing' => [
            'label' => 'Not facing camera',
            'message' => 'You must be facing the camera. Side profile shots are not allowed. Turn and face the camera dead on.'
        ],

        'too-close' => [
            'label' => 'Face too close',
            'message' => 'Your face is too close to the camera. Back up a little. Your shoulders should be in the photo.'
        ],

        'too-far' => [
            'label' => 'Too far away',
            'message' => 'Your face is not close enough to the camera. Move in a bit. Your face and shoulders should take up most of the frame.'
        ],

        'not-centered' => [
            'label' => 'Face not centered',
            'message' => 'Your face needs to be front and centered. The entire head needs to be visible, with space between the top of your head and the frame.'
        ],

        'head-cropped' => [
            'label' => 'Head cropped',
            'message' => 'Portions of your head is cropped. Your entire head needs to be visible, with space between the top of your head, sides, chin, and the photo edge.'
        ],

        'no-margin' => [
            'label' => 'No margins',
            'message' => 'You do not have enough space around your face and the edge of the frame. Zoom out or move away from the camera.',
        ],

    ];

    public $reject_history;

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function review_person()
    {
        return $this->belongsTo(Person::class);
    }

    public function upload_person()
    {
        return $this->belongsTo(Person::class);
    }

    public function edit_person()
    {
        return $this->belongsTo(Person::class);
    }

    public static function findForQuery($params)
    {
        $photoStatus = $params['status'] ?? null;
        $personStatus = $params['person_status'] ?? null;
        $personId = $params['person_id'] ?? null;
        $includeRejects = $params['include_rejects'] ?? null;
        $sort = $params['sort'] ?? '';

        $page = $params['page'] ?? 1;
        $pageSize = $params['page_size'] ?? 100;

        $sql = self::select('person_photo.*', DB::raw("(SELECT 1 FROM person WHERE person.id=person_photo.person_id AND person.person_photo_id=person_photo.id LIMIT 1) AS is_active"))
            ->join('person', 'person.id', 'person_photo.person_id')
            ->with(self::PERSON_TABLES);

        if ($personId) {
            $sql->where('person_id', $personId);
        }

        if ($photoStatus) {
            $sql->where('person_photo.status', $photoStatus);
        }

        if ($personStatus) {
            $sql->where('person.status', $personStatus);
        }


        // How many total for the query
        $total = $sql->count();

        if (!$total) {
            // Nada.. don't bother
            return [
                'person_photo' => [],
                'meta' => [
                    'page' => $page,
                    'total' => 0,
                    'total_pages' => 0
                ]
            ];
        }

        // Figure out pagination
        $page = $page - 1;
        if ($page < 0) {
            $page = 0;
        }


        if ($personId) {
            $sql->orderBy('is_active', 'desc')
                ->orderBy('person_photo.created_at', 'desc');
        } else {
            if ($sort == 'callsign') {
                $sql->orderBy('person.callsign', 'asc');
            }

            $sql->orderBy('person_photo.created_at', 'desc');
        }

        $rows = $sql->offset($page * $pageSize)
            ->limit($pageSize)
            ->get();

        if ($includeRejects) {
            foreach ($rows as $row) {
                $row->appends[] = 'reject_history';
                $row->reject_history = self::where('person_id', $row->person_id)
                    ->where('id', '!=', $row->id)
                    ->where('status', self::REJECTED)
                    ->with(self::PERSON_TABLES)
                    ->orderBy('created_at')
                    ->get();
            }
        }

        return [
            'person_photo' => $rows,
            'meta' => [
                'total' => $total,
                'total_pages' => (int)(($total + ($pageSize - 1)) / $pageSize),
                'page_size' => $pageSize,
                'page' => $page + 1,
            ]
        ];
    }

    /**
     * Retrieve the (approved) image url for the given person
     *
     * @param $personId
     * @return string
     */

    public static function retrieveImageUrlForPerson(int $personId) : string
    {
        $photo = self::join('person', function ($j) use ($personId) {
            $j->on('person.id', 'person_photo.person_id');
            $j->where('person.id', $personId);
        })->where('person_photo.status', self::APPROVED)->first();

        return $photo ? $photo->image_url : '';
    }

    /**
     * Find all photos queued up for review.
     *
     * @return Collection
     */

    public static function findAllPending()
    {
        return self::where('status', self::SUBMITTED)
            ->orderBy('created_at')
            ->with('person:id,callsign,status')
            ->get();
    }

    /**
     * Delete all the photos on file for the given person
     *
     * @param $personId
     */

    public static function deleteAllForPerson($personId)
    {
        $userId = Auth::id();

        $rows = self::where('person_id', $personId)->get();

        foreach ($rows as $row) {
            try {
                // Remove the files.
                $row->deleteImage();
                $row->deleteOrigImage();
            } catch (Exception $e) {
                ErrorLog::record('person-photo-delete-exception', [
                    'person_id' => $userId,
                    'target_person_id' => $personId,
                    'person_photo_id' => $row->id,
                    'image_filename' => $row->image_filename,
                    'orig_filename' => $row->orig_filename
                ]);
            }

            $row->delete();
        }
    }

    public function setUploadedAtToNow()
    {
        $this->uploaded_at = now();
    }

    public function getImageUrlAttribute() : string
    {
        return self::storage()->url(self::storagePath($this->image_filename));
    }

    public function getOrigUrlAttribute() : string
    {
        return self::storage()->url(self::storagePath($this->orig_filename));
    }

    /**
     * Retrieve the photo status for the given person
     *
     * @param Person $person
     * @return string
     */

    public static function retrieveStatus(Person $person)
    {
        $photo = $person->person_photo;

        return $photo ? $photo->status : self::MISSING;
    }

    /**
     * Retrieve the person's photo, status, and any rejection messages (if rejected)
     *
     * @param Person $person
     * @return array
     */

    public static function retrieveInfo(Person $person) : array
    {
        $photo = $person->person_photo;

        if ($photo) {
            $status = $photo->status;
            $url = $photo->image_url;
        } else {
            $status = self::MISSING;
            $url = null;
        }

        $info = [
            'photo_status' => $status,
            'photo_url' => $url,
            'upload_enabled' => setting('PhotoUploadEnable')
        ];

        if ($status == self::REJECTED) {
            // Grab the rejection reasons.

            $reasons = $photo->reject_reasons;

            $rejections = [];
            if (is_array($reasons)) {
                foreach ($reasons as $reason) {
                    $message = self::REJECTIONS[$reason] ?? null;
                    $rejections[] = $message['message'] ?? "Reason $reason";
                }
            }

            $info['rejections'] = $rejections;
            if (!empty($photo->reject_message)) {
                $info['reject_message'] = $photo->reject_message;
            }
        }

        return $info;
    }

    /**
     * Obtain the photo storage object
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */

    public static function storage()
    {
        $storage = config('clubhouse.PhotoStorage');
        if (!$storage) {
            throw new RuntimeException('PhotoStorage setting is not configured');
        }
        return Storage::disk($storage);
    }

    /**
     * Store/upload the photo image to photo storage
     *
     * @param $contents - raw image
     * @param $timestamp - timestamp image was uploaded
     * @param $isOrig - is this the original image or one that was resized
     * @return bool - true if succesful
     */

    public function storeImage($contents, $timestamp, $isOrig)
    {
        if ($isOrig) {
            $file = "photo-{$this->person_id}-{$timestamp}-orig.jpg";
            $this->orig_filename = $file;
        } else {
            $file = "photo-{$this->person_id}-{$timestamp}.jpg";
            $this->image_filename = $file;
        }

        return self::storage()->put(self::storagePath($file), $contents);
    }

    /**
     * Does the image file actually exist within the storage?
     *
     * @return bool
     */
    public function imageExists() : bool
    {
        return self::storage()->exists(self::storagePath($this->image_filename));
    }
    /**
     * Return the contents of the image.
     *
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */

    public function readImage() : string
    {
        return self::storage()->get(self::storagePath($this->image_filename));
    }

    /**
     * Delete the photo from storage
     */

    public function deleteImage()
    {
        self::storage()->delete(self::storagePath($this->image_filename));
    }

    /**
     * Delete the original photo from storage
     */

    public function deleteOrigImage()
    {
        self::storage()->delete(self::storagePath($this->orig_filename));
    }


    public function setRejectReasonsAttribute($value)
    {
        $this->attributes['reject_reasons'] = empty($value) ? null : json_encode($value);
    }

    public function getRejectReasonsAttribute()
    {
        if (!empty($this->attributes['reject_reasons'])) {
            return json_decode($this->attributes['reject_reasons']);
        }

        return [];
    }

    public function getRejectLabelsAttribute()
    {
        $reasons = $this->reject_reasons;
        if (empty($reasons)) {
            return null;
        }

        return array_map(function ($r) {
            if (isset(self::REJECTIONS[$r])) {
                return self::REJECTIONS[$r]['label'];
            }
            return "Reason $r";
        }, $this->reject_reasons);
    }

    public function getRejectHistoryAttribute()
    {
        return $this->reject_history;
    }

    /**
     * Get the AWS Rekognition analysis details
     *
     * @return array|string[]
     */

    public function getAnalysisDetailsAttribute()
    {
        $status = $this->analysis_status;
        if ($status == 'failed') {
            return ['status' => 'failed'];
        }

        if ($status == 'none' || empty($this->analysis_info)) {
            return ['status' => 'no-data'];
        }

        $data = json_decode($this->analysis_info);

        if ($data == null) {
            return ['status' => 'no-data'];
        }

        if (empty($data->FaceDetails)) {
            // No face was detected
            return ['status' => 'success', 'issues' => ['no-face'], 'sharpness' => 0];
        }

        $issues = [];

        if (count($data->FaceDetails) > 1) {
            // Multiple people are in the image
            $issues = ['multiple-people'];
        }

        $face = $data->FaceDetails[0];
        if ($face->Sunglasses->Value && $face->Sunglasses->Confidence >= 0.9) {
            // Wearing sunglasses.
            $issues[] = 'sunglasses';
        }

        if (!$face->EyesOpen->Value || $face->EyesOpen->Confidence < 0.9) {
            // Their eyes are closed
            $issues[] = 'eyes-closed';
        }

        $box = $face->BoundingBox;

        return [
            'status' => 'success',
            'issues' => $issues,
            'sharpness' => (int)$face->Quality->Sharpness,
            'bounding' => [
                // The face's location within the image
                'height' => $box->Height,
                'left' => $box->Left,
                'top' => $box->Top,
                'width' => $box->Width
            ]
        ];
    }

    /**
     * Take an image, and run it through AWS' Rekognition service.
     *
     * @param $contents
     */

    public function analyzeImage($contents)
    {
        if (!setting('PhotoAnalysisEnabled')) {
            return;
        }

        try {
            $rekognition = new RekognitionClient([
                'region' => 'us-west-2',
                'version' => 'latest',
                'credentials' => new Credentials(setting('PhotoRekognitionAccessKey'), setting('PhotoRekognitionAccessSecret'))
            ]);

            $result = $rekognition->DetectFaces([
                'Image' => ['Bytes' => $contents],
                'Attributes' => ['ALL']
            ]);

            $this->analysis_info = json_encode($result->toArray());
            $this->analysis_status = 'success';
        } catch (Exception $e) {
            ErrorLog::recordException($e, 'photo-analyze-exception', [
                'person_id' => Auth::id(),
                'target_person_id' => $this->person_id
            ]);

            $this->analysis_status = 'failed';
        }
    }

    /**
     * Create a (local filesystem not url) path to where the image is stored
     *
     * @param string $filename
     * @return string
     */

    public static function storagePath(string $filename): string
    {
        $path = (!app()->isLocal() && config('clubhouse.DeploymentEnvironment') == 'Staging') ? self::STORAGE_STAGING_DIR : self::STORAGE_DIR;
        return $path . $filename;
    }
}
