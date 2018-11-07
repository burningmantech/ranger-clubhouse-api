<?php

namespace App\Models;

use App\Models\Lambase;

class Photo {

    const MUGSHOT_PATH = 'images/mugshots/id-%05u.jpg';
    const THUMBNAIL_PATH = 'images/mugshots/thumbs/thumb-%05u.jpg';
    const ASSET_DIR = 'public';

    public static function imageUrlForPerson($personId) {

        $path = self::localPathForPerson($personId);
        if (file_exists($path)) {
            return asset(sprintf(self::MUGSHOT_PATH, $personId));
        } else {
            return null;
        }
    }

    public static function localPathForPerson($personId) {
        $mugshotPath = sprintf(self::MUGSHOT_PATH, $personId);
        return base_path().'/'.self::ASSET_DIR.'/'.$mugshotPath;
    }

    /*
     * Retrieve the photo status for a person.
     *
     * 'approved' - photo submitted and has been approved.
     * 'missing' - a photo is not submitted
     * 'rejected' - not approved
     * 'submitted' - user submitted, not reviewed yet
     * 'error' - unknown error, usually Lambase communication failure
     *
     * @param int $personId person to look up
     * @return string photo status
     */

    public static function retrieveStatus($person) {
        $isLambase = config('clubhouse.PhotoSource') == 'Lambase';

        if ($isLambase) {
            $lambase = new LambasePhoto($person);
            $status = $lambase->getStatus();
            if (!$status['error']) {
                return LambasePhoto::statusToCode($status['status'], $status['data']);
            } else {
                return 'error';
            }
        } else {
            return 'approved';
        }

    }
}
