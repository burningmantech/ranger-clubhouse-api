<?php

namespace App\Http\Controllers;

use App\Lib\Reports\CertificationReport;
use App\Models\Certification;
use App\Models\Person;
use App\Models\PersonCertification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CertificationController extends ApiController
{
    /**
     * Display a listing of the certifications
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('index', Certification::class);
        $params = request()->validate([
            'on_shift_report' => 'sometimes|boolean',
        ]);

        return $this->success(Certification::findForQuery($params), null, 'certification');
    }

    /**
     * Create a certification
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', Certification::class);
        $certification = new Certification();
        $this->fromRest($certification);
        if ($certification->save()) {
            return $this->success($certification);
        }

        return $this->restError($certification);
    }

    /**
     * Show certification
     *
     * @param Certification $certification
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(Certification $certification): JsonResponse
    {
        $this->authorize('show', $certification);
        return $this->success($certification);
    }

    /**
     * Update the specified resource in storage.
     * @param Certification $certification
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(Certification $certification): JsonResponse
    {
        $this->authorize('update', $certification);
        $this->fromRest($certification);

        if ($certification->save()) {
            return $this->success($certification);
        }

        return $this->restError($certification);
    }

    /**
     * Delete a certification
     *
     * @param Certification $certification
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(Certification $certification): JsonResponse
    {
        $this->authorize('delete', $certification);
        $certification->delete();
        DB::table('person_certification')->where('certification_id', $certification->id)->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * Report on certifications held by folks.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function peopleReport(Person $person): JsonResponse
    {
        $this->authorize('peopleReport', PersonCertification::class);

        $params = request()->validate([
            'certification_ids' => 'required|array',
            'certification_ids.*' => 'required|integer|exists:certification,id',
        ]);

        return response()->json(CertificationReport::execute($params['certification_ids']));
    }

}
