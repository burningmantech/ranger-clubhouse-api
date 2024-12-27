<?php

namespace App\Models;

use Aws\Credentials\Credentials;
use Aws\Rekognition\RekognitionClient;
use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Jcupitt\Vips;
use RuntimeException;

/*
 * person_photo represents the most recent photo submission, rejection, or approval
 * for a person
 */


class PersonPhoto extends ApiModel
{
    protected $table = 'person_photo';
    public $timestamps = true;
    protected bool $auditModel = true;

    const string STORAGE_DIR = 'photos/';
    const string STORAGE_STAGING_DIR = 'staging/';

    protected $guarded = [
        'person_id',
        'edit_person_id',
        'upload_person_id',
        'review_person_id'
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'uploaded_at' => 'datetime',
        ];
    }

    protected $appends = [
        'image_url',
        'orig_url',
        'profile_url',
        'reject_labels',
        'analysis_details'
    ];

    // How many months should an archived photo be expired?
    const int EXPIRE_ARCHIVED_AFTER = 6;

    const string APPROVED = 'approved';
    const string REJECTED = 'rejected';
    const string SUBMITTED = 'submitted';
    const string MISSING = 'missing';

    const string NOT_REQUIRED = 'not-required'; // Not stored within the database, used by scheduling gate keeping logic

    const string SIZE_BMID = 'bmid';
    const string SIZE_PROFILE = 'profile';
    const string SIZE_ORIGINAL = 'original';

    // Image ratio is 7 by 9 for 350px by 450px
    const int BMID_WIDTH = 350;
    const int BMID_HEIGHT = 450;

    const int ORIG_MAX_WIDTH = 1050;
    const ORIG_MAX_HEIGHT = 1350;

    const int PROFILE_HEIGHT = 180;
    const int PROFILE_WIDTH = 140;

    const array PERSON_TABLES = [
        'person:id,callsign,status,first_name,preferred_name,last_name',
        'review_person:id,callsign',
        'upload_person:id,callsign',
        'edit_person:id,callsign'
    ];

    protected $rules = [
        'status' => 'required|string',
    ];

    const array REJECTIONS = [
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

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function review_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function upload_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function edit_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }


    public static function boot(): void
    {
        parent::boot();

        self::deleted(function (PersonPhoto $model) {
            $person = $model->person;
            if ($model->id == $person?->person_photo_id) {
                // Is this record the person's current photo? kill it!
                $person->person_photo_id = null;
                $person->auditReason = 'current photo deletion';
                $person->saveWithoutValidation();
            }

            try {
                // Remove the files.
                $model->deleteAllVersions();
            } catch (Exception $e) {
                ErrorLog::record('person-photo-delete-exception', [
                    'person_id' => Auth::id(),
                    'target_person_id' => $model->person_id,
                    'person_photo_id' => $model->id,
                    'image_filename' => $model->image_filename,
                    'orig_filename' => $model->orig_filename
                ]);
            }
        });
    }

    public static function findForQuery($params): array
    {
        $photoStatus = $params['status'] ?? null;
        $personStatus = $params['person_status'] ?? null;
        $personId = $params['person_id'] ?? null;
        $includeRejects = $params['include_rejects'] ?? null;
        $sort = $params['sort'] ?? '';

        $page = (int)($params['page'] ?? 1);
        $pageSize = (int)($params['page_size'] ?? 100);

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
     * Process an image
     */

    public static function processImage(string $imageParam, $personId, string $type = self::SIZE_BMID): array
    {
        switch ($type) {
            case self::SIZE_BMID:
                $width = PersonPhoto::BMID_WIDTH;
                $height = PersonPhoto::BMID_HEIGHT;
                break;

            case self::SIZE_ORIGINAL:
                $width = PersonPhoto::ORIG_MAX_WIDTH;
                $height = PersonPhoto::ORIG_MAX_HEIGHT;
                break;

            case self::SIZE_PROFILE:
                $width = self::PROFILE_WIDTH;
                $height = self::PROFILE_HEIGHT;
                break;

            default:
                throw new RuntimeException("Unknown type [$type]");
        }

        try {
            $image = Vips\Image::thumbnail_buffer($imageParam, $width, ['height' => $height]);
            $width = $image->width;
            $height = $image->height;

            $contents = $image->writeToBuffer('.jpg', ['Q' => 90]);
            $image = null;
            gc_collect_cycles();     // Images can be huge, garbage collect.

            return [$contents, $width, $height];
        } catch (Exception $e) {
            ErrorLog::recordException($e, 'person-photo-convert-exception', [
                'target_person_id' => $personId,
            ]);

            return [null, 0, 0];
        }
    }

    /**
     * Retrieve the (approved) profile url for the given person
     *
     * @param int $personId
     * @return string
     */

    public static function retrieveProfileUrlForPerson(int $personId): string
    {
        $photo = self::join('person', function ($j) use ($personId) {
            $j->on('person.person_photo_id', 'person_photo.id');
            $j->where('person.id', $personId);
        })->where('person_photo.status', self::APPROVED)->first();

        if (!$photo) {
            return '';
        }

        return ($photo->profile_url ?? $photo->image_url) ?? '';
    }

    /**
     * Find all photos queued up for review.
     *
     * @return Collection
     */

    public static function findAllPending(): Collection
    {
        return self::where('status', self::SUBMITTED)
            ->orderBy('created_at')
            ->with('person:id,callsign,status')
            ->get();
    }

    /**
     * Find all expired archived photos for folks who have an approved current photo,
     * or whose account status is deactivated (past prospectives, resigned, bonked, dismissed, etc.)
     * We don't touch any "current" accounts with a rejected photo in case the previous photo is to be reactivated.
     *
     * @return Collection
     */

    public static function retrieveExpiredPhotos(): Collection
    {
        return PersonPhoto::select('person_photo.*')
            ->join('person', 'person.id', 'person_photo.person_id')
            ->whereColumn('person.person_photo_id', '!=', 'person_photo.id')
            ->where(function ($w) {
                $w->whereIn('person.status', Person::DEACTIVATED_STATUSES)
                    ->orWhereExists(function ($sub) {
                        $sub->from('person_photo as current')
                            ->selectRaw(1)
                            ->whereColumn('current.id', 'person.person_photo_id')
                            ->where('current.status', self::APPROVED)
                            ->limit(1);
                    });
            })
            ->where('person_photo.created_at', '<', now()->subMonths(self::EXPIRE_ARCHIVED_AFTER))
            ->with('person:id,callsign,status,person_photo_id')
            ->get();
    }

    /**
     * Delete all the photos on file for the given person
     *
     * @param int $personId
     */

    public static function deleteAllForPerson(int $personId): void
    {
        $rows = self::where('person_id', $personId)->get();

        foreach ($rows as $row) {
            $row->delete();
        }
    }

    public function deleteAllVersions(): void
    {
        $this->deleteImage();
        $this->deleteOrigImage();
        $this->deleteProfileImage();
    }

    public function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if (!empty($attributes['image_filename'])) {
                    return PersonPhoto::storage()->url(PersonPhoto::storagePath($attributes['image_filename']));
                }

                return '';
            }
        );
    }

    public function origUrl(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if (!empty($attributes['orig_filename'])) {
                    return PersonPhoto::storage()->url(PersonPhoto::storagePath($attributes['orig_filename']));
                }

                return '';
            }
        );
    }

    public function profileUrl(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if (!empty($attributes['profile_filename'])) {
                    return PersonPhoto::storage()->url(PersonPhoto::storagePath($attributes['profile_filename']));
                }

                return '';
            }
        );
    }

    /**
     * Retrieve the photo status for the given person
     *
     * @param Person $person
     * @return string
     */

    public static function retrieveStatus(Person $person): string
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

    public static function retrieveInfo(Person $person): array
    {
        $photo = $person->person_photo;

        if ($photo) {
            $status = $photo->status;
            $url = $photo->image_url;
            $profileUrl = $photo->profile_url;
        } else {
            $status = self::MISSING;
            $url = null;
            $profileUrl = null;
        }

        $info = [
            'photo_status' => $status,
            'photo_url' => $url,
            'profile_url' => $profileUrl,
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
     * @return Filesystem
     */

    public static function storage(): Filesystem
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
     * @param $type
     * @return bool - true if succesful
     */

    public function storeImage($contents, $timestamp, $type): bool
    {
        switch ($type) {
            case self::SIZE_ORIGINAL:
                $file = "photo-{$this->person_id}-{$timestamp}-orig.jpg";
                $this->orig_filename = $file;
                break;
            case self::SIZE_BMID:
                $file = "photo-{$this->person_id}-{$timestamp}.jpg";
                $this->image_filename = $file;
                break;

            case self::SIZE_PROFILE:
                $file = "photo-{$this->person_id}-{$timestamp}-profile.jpg";
                $this->profile_filename = $file;
                break;
        }

        return self::storage()->put(self::storagePath($file), $contents);
    }

    /**
     * Does the image file actually exist within the storage?
     *
     * @return bool
     */

    public function imageExists(): bool
    {
        return self::storage()->exists(self::storagePath($this->image_filename));
    }

    /**
     * Return the contents of the image.
     *
     * @return string
     */

    public function readImage(): string
    {
        return self::storage()->get(self::storagePath($this->image_filename));
    }

    /**
     * Delete the photo from storage
     */

    public function deleteImage(): void
    {
        if (!empty($this->image_filename)) {
            self::storage()->delete(self::storagePath($this->image_filename));
        }
    }

    /**
     * Delete the original photo from storage
     */

    public function deleteOrigImage(): void
    {
        if (!empty($this->orig_filename)) {
            self::storage()->delete(self::storagePath($this->orig_filename));
        }
    }

    /**
     * Delete the profile photo from storage
     */

    public function deleteProfileImage(): void
    {
        if (!empty($this->profile_filename)) {
            self::storage()->delete(self::storagePath($this->profile_filename));
        }
    }

    public function rejectReasons(): Attribute
    {
        return Attribute::make(
            get: fn($value) => empty($value) ? [] : json_decode($value),
            set: fn($value) => empty($value) ? null : json_encode($value)
        );
    }

    public function getRejectLabelsAttribute(): ?array
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

    public function getAnalysisDetailsAttribute(): array
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

    public function analyzeImage($contents): void
    {
        if (!setting('PhotoAnalysisEnabled')) {
            return;
        }

        $accessKey = setting('PhotoRekognitionAccessKey');
        $secret = setting('PhotoRekognitionAccessSecret');

        if (empty($accessKey) || empty($secret)) {
            ErrorLog::record('photo-analyze-no-credentials', [
                'message' => 'PhotoRekognitionAccessKey or PhotoRekognitionAccessSecret is empty'
            ]);
            return;
        }

        try {
            $rekognition = new RekognitionClient([
                'region' => 'us-west-2',
                'version' => 'latest',
                'credentials' => new Credentials($accessKey, $secret)
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

    /**
     * Is the photo approved?
     *
     * @return bool
     */

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }
}
