<?php

namespace App\Models;

use App\Models\Lambase;
use Illuminate\Support\Facades\Auth;

class Photo
{
    const MUGSHOT_PATH = 'images/mugshots/id-%05u.jpg';
    const THUMBNAIL_PATH = 'images/mugshots/thumbs/thumb-%05u.jpg';
    const ASSET_DIR = 'public';

    public static function imageUrlForPerson($personId)
    {
        $path = self::localPathForPerson($personId);
        if (file_exists($path)) {
            return asset(sprintf(self::MUGSHOT_PATH, $personId));
        } else {
            return null;
        }
    }

    public static function localPathForPerson($personId)
    {
        $mugshotPath = sprintf(self::MUGSHOT_PATH, $personId);
        return base_path().'/'.self::ASSET_DIR.'/'.$mugshotPath;
    }

    /*
     * Retrieve photo information, and optionally download the photo from Lambase
     *
     * status codes are:
     * 'approved' - photo submitted and has been approved.
     * 'missing' - a photo is not submitted
     * 'rejected' - not approved
     * 'submitted' - user submitted, not reviewed yet
     * 'error' - unknown error, usually Lambase communication failure
     */

    public static function retrieveInfo($person, $sync = false)
    {
        $source = setting('PhotoSource');
        if ($source == 'Lambase') {
            $lambase = new LambasePhoto($person);

            $user = Auth::user();

            // Only return a upload url if uploads are enabled, and the user is person requested,
            // or the user is an admin.
            if ($user && ($user->id == $person->id || $user->isAdmin()) && setting('PhotoUploadEnable')) {
                $uploadUrl = $lambase->getUploadUrl();
            } else {
                $uploadUrl = null;
            }

            $personPhoto = PersonPhoto::find($person->id);
            if (!$sync && $personPhoto && $personPhoto->isApproved()) {
                return [
                   'photo_url'    => $lambase->getImageUrl($personPhoto->lambase_image),
                   'photo_status' => 'approved',
                   'upload_url'   => $uploadUrl,
                   'source'       => 'lambase',
                   'message'      => '',
                ];
            }
            $storeLocal = setting('PhotoStoreLocally') == true;

            $status = $lambase->getStatus();

            $errorMessage = null;
            $imageUrl = null;

            if (!$status['error']) {
                $imageStatus = LambasePhoto::statusToCode($status['status'], $status['data']);

                if ($storeLocal) {
                    if ($status['data']) {
                        // should the photo be downloaded?
                        if ($lambase->downloadNeeded($status['image_hash'])) {
                            if (!$lambase->downloadImage($status['image'])) {
                                $imageStatus = 'error';
                                $imageUrl = null;
                                $errorMessage = 'Failed to download image';
                            } else {
                                $imageUrl = Photo::imageUrlForPerson($person->id);
                            }
                        } else {
                            $imageUrl = Photo::imageUrlForPerson($person->id);
                        }
                    } else {
                        // Missing file, delete local copy
                        $lambase->deleteLocal();
                    }
                }

                if (!$personPhoto) {
                    $personPhoto = new PersonPhoto([ 'person_id' => $person->id ]);
                }

                $personPhoto->lambase_date = !empty($status['date']) ? $status['date'] : null;
                if ($status['data']) {
                    $personPhoto->lambase_image = $status['image'];
                }
                $personPhoto->status = $imageStatus;
                $personPhoto->save();
            } else {
                // Something went horribly wrong.
                $imageStatus = 'error';
                $errorMessage = $status['message'];
            }

            if ($imageStatus != 'error' && $imageStatus != 'missing' && $imageUrl == null) {
                $imageUrl = $lambase->getImageUrl($status['image']);
            }

            return [
               'photo_url'    => $imageUrl,
               'photo_status' => $imageStatus,
               'upload_url'   => $uploadUrl,
               'source'       => 'lambase',
               'message'      => $errorMessage,
            ];
        } elseif ($source == 'test') {
            return [
                'source'       => 'local',
                'photo_status' => 'approved',
                'photo_url'    => 'images/test-mugshot.jpg',
                'upload_url'   => null,
            ];
        } else {
            // Local photo source
            $imageUrl = Photo::imageUrlForPerson($person->id);
            return [
                'source'       => 'local',
                'photo_status' => ($imageUrl != '' ? 'approved' : 'missing'),
                'photo_url'    => $imageUrl,
                'upload_url'   => null,
            ];
        }
    }
}
