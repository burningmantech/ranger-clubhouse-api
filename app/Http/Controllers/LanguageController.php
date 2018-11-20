<?php

namespace App\Http\Controllers;

use App\Models\PersonLanguage;
use App\Http\Controllers\ApiController;

use Illuminate\Http\Request;

class LanguageController extends ApiController
{
    public function speakers()
    {
        $query = request()->validate([
            'language'  => 'required|string',
            'off_site'  => 'sometimes|boolean'
        ]);

        $language = $query['language'];
        $includeOffSite = isset($query['off_site']);

        $result = [
            'on_duty'   => PersonLanguage::findSpeakers($language, $includeOffSite, PersonLanguage::ON_DUTY),
            'off_duty'  => PersonLanguage::findSpeakers($language, $includeOffSite, PersonLanguage::OFF_DUTY),
            'has_radio' => PersonLanguage::findSpeakers($language, $includeOffSite, PersonLanguage::HAS_RADIO),
        ];

        return response()->json($result);
    }
}
