<?php

namespace \App\Models;

class Lam {

    protected $personId;

    public function __construct($personId) {
        $this->personId = $personId;
    }

    public function lambaseStatus() {

    }
    public static function
    $personId = $id;
    if (!isset($personInfo['callsign'])) {
        echo("<br>Warning: no callsign for id $id, skipping\n");
        return -1;
    }

    if ($datef == "") {
        echo("<br>Warning: Lambase date is empty for id $id, skipping\n");
        return -1;
    }

    $callsign = $personInfo['callsign'];

    $file = photo_local_path($id);

    $stats = stat($file);
    $mtime = 0;     // If file not there, set time = 0 to force download
    if ($stats != false) {
        $mtime = $stats['mtime'];
    }

    $date = strtotime($datef);
    $mtimef = date("Y-m-d H:i:s", $mtime);

    // If the file is younger than the lambase copy, download it
    if ($mtime < $date) {
        echo("<br>Downloading $callsign file $file mtime $mtime ($mtimef), updated $datef\n");
        $lamb = lambase_status($personInfo);
        if (!$lamb['error']) {
            if ($lamb['data']) {
                if (!lambase_download($personId, $personInfo, $lamb['image'])) {
                    echo('<p style="color: red;">Lambase download failed!</p>');
                    return -1;
                }
            } else {
                // If we have a photo on file, delete it
                lambase_delete_local($personId, $personInfo);
            }
        }
        return 1;
    }
    return 0;       // skipped

}
