<?php

namespace App\Http\Controllers;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\Reports\LanguagesSpokenOnSiteReport;
use App\Models\PersonLanguage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PersonLanguageController extends ApiController
{
    /**
     * Retrieve a list of records based on a criteria
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'required|integer|exists:person,id'
        ]);

        $this->authorize('index', [PersonLanguage::class, $params['person_id']]);

        return $this->success(PersonLanguage::findForQuery($params), null, 'person_language');
    }

    /***
     * Create a PersonLanguage record
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
    {
        $person_language = new PersonLanguage;
        $this->fromRest($person_language);

        $this->authorize('store', $person_language);

        if ($person_language->save()) {
            return $this->success($person_language);
        }

        return $this->restError($person_language);
    }

    /**
     * Update the specified PersonLanguage in storage.
     *
     * @param PersonLanguage $person_language
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function update(PersonLanguage $person_language): JsonResponse
    {
        $this->authorize('update', $person_language);
        $this->fromRest($person_language);

        if ($person_language->save()) {
            return $this->success($person_language);
        }

        return $this->restError($person_language);
    }

    /**
     * Delete a person language record
     *
     * @param PersonLanguage $person_language
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(PersonLanguage $person_language): JsonResponse
    {
        $this->authorize('destroy', $person_language);
        $person_language->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * Return the speakers of a given language
     *
     * @return JsonResponse
     * @throws UnacceptableConditionException
     */

    public function search(): JsonResponse
    {
        $query = request()->validate([
            'language' => 'required|string',
            'off_site' => 'sometimes|boolean'
        ]);

        $language = $query['language'];
        $includeOffSite = $query['off_site'] ?? false;

        $result = [
            'on_duty' => PersonLanguage::findSpeakers($language, $includeOffSite, PersonLanguage::ON_DUTY),
            'off_duty' => PersonLanguage::findSpeakers($language, $includeOffSite, PersonLanguage::OFF_DUTY),
            'has_radio' => PersonLanguage::findSpeakers($language, $includeOffSite, PersonLanguage::HAS_RADIO),
        ];

        return response()->json($result);
    }

    /**
     * Languages Spoken On Site Report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function onSiteReport(): JsonResponse
    {
        $this->authorize('onSiteReport', PersonLanguage::class);

        return response()->json(['languages' => LanguagesSpokenOnSiteReport::execute()]);
    }

    /**
     * Obtain the common languages options.
     *
     * @return JsonResponse
     */

    public function commonLanguages(): JsonResponse
    {
        return response()->json(['languages' => PersonLanguage::COMMON_PLAYA_LANGUAGES]);
    }

}
