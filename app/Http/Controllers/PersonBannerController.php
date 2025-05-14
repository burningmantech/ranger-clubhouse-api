<?php

namespace App\Http\Controllers;

use App\Models\PersonBanner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class PersonBannerController extends ApiController
{
    /**
     * Display a listing of the person banners.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'active' => 'sometimes|bool',
            'include_person' => 'sometimes|bool',
            'is_permanent' => 'sometimes|bool',
            'person_id' => 'sometimes|int|exists:person,id',
            'year' => 'sometimes|int',
        ]);

        $this->authorize(isset($params['person_id']) ? 'indexForPerson' : 'index', PersonBanner::class);

        return $this->success(PersonBanner::findForQuery($params), null, 'person_banner');
    }

    /**
     * Create a person_banner record
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(): JsonResponse
    {
        $person_banner = new PersonBanner;
        $this->fromRest($person_banner);
        $this->authorize('store', $person_banner);

        if ($person_banner->save()) {
            return $this->success($person_banner);
        }

        return $this->restError($person_banner);
    }

    /**
     * Display the specified person banner.
     *
     * @param PersonBanner $person_banner
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(PersonBanner $person_banner): JsonResponse
    {
        $this->authorize('show', PersonBanner::class);
        return $this->success($person_banner);
    }

    /**
     * Update the specified person_banner
     *
     * @param PersonBanner $person_banner
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(PersonBanner $person_banner): JsonResponse
    {
        $this->authorize('update', $person_banner);
        $this->fromRest($person_banner);

        if ($person_banner->save()) {
            return $this->success($person_banner);
        }

        return $this->restError($person_banner);
    }

    /**
     * Delete a person banner
     *
     * @param PersonBanner $person_banner
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(PersonBanner $person_banner): JsonResponse
    {
        $this->authorize('destroy', $person_banner);
        $person_banner->delete();
        return $this->restDeleteSuccess();
    }
}
