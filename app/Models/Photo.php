<?php

namespace App\Models;

use App\Models\Lambase;
use Illuminate\Support\Facades\Auth;

class Photo
{
    const MUGSHOT_PATH = 'mugshots/id-%05u.jpg';
    const MUGSHOT_URL = '/mugshots/id-%05u.jpg';

    public static function imageUrlForPerson($personId)
    {
        $path = self::localPathForPerson($personId);
        if (file_exists($path)) {
            return sprintf(self::MUGSHOT_URL, $personId);
        } else {
            return null;
        }
    }

    public static function localPathForPerson($personId)
    {
        return sprintf(storage_path(self::MUGSHOT_PATH), $personId);
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

    const MISS_PIGGY_ID = 1196;
    public static function retrieveInfo($person, $sync = false)
    {
        $source = setting('PhotoSource');

        if ($source == 'Lambase') {
            $storeLocal = setting('PhotoStoreLocally') == true;
            $lambase = new LambasePhoto($person);

            $user = Auth::user();

            // Only return a upload url if uploads are enabled, and the user is person requested,
            // or the user is an admin.

            // TODO : GET LAMBASE FIXED AND REMOVE THE EDGE CASE FOR MISS PIGGY!
            if ($user && ($user->id == $person->id || $user->isAdmin()) && (setting('PhotoUploadEnable') || $user->id == self::MISS_PIGGY_ID)) {
                $uploadUrl = $lambase->getUploadUrl();
            } else {
                $uploadUrl = null;
            }

            $personPhoto = PersonPhoto::find($person->id);
            if (!$sync && $personPhoto && $personPhoto->isApproved()) {
                $photoUrl = null;
                if ($storeLocal) {
                    $photoUrl = Photo::imageUrlForPerson($person->id);
                }

                if (!$photoUrl) {
                    $photoUrl = $lambase->getImageUrl($personPhoto->lambase_image);
                }

                return [
                   'photo_url'    => $photoUrl,
                   'photo_status' => 'approved',
                   'upload_url'   => $uploadUrl,
                   'source'       => 'lambase',
                   'message'      => '',
                ];
            }

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
