<?php

namespace App\Http\Controllers;

use App\Lib\BMIDManagement;
use App\Lib\MarcatoExport;
use App\Models\Bmid;
use App\Models\BmidExport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class BmidController extends ApiController
{
    /**
     * Display a list of BMIDs.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('index', Bmid::class);

        $params = request()->validate([
            'year' => 'required|integer'
        ]);

        return response()->json(['bmids' => Bmid::findForQuery($params)]);
    }

    /**
     * Manage a potential list of BMIDs
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function manage(): JsonResponse
    {
        $this->authorize('index', Bmid::class);

        $params = request()->validate([
            'year' => 'required|integer',
            'filter' => [
                'required',
                'string',
                Rule::in(['special', 'alpha', 'signedup', 'submitted', 'printed', 'nonprint', 'no-shifts'])
            ]
        ]);

        return response()->json(['bmids' => BMIDManagement::retrieveCategoryToManage($params['year'], $params['filter'])]);
    }

    /**
     * Manage for a single person
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function managePerson(): JsonResponse
    {
        $this->authorize('index', Bmid::class);

        $params = request()->validate([
            'person_id' => 'required|integer|exists:person,id',
            'year' => 'required|integer',
        ]);

        return response()->json(['bmid' => Bmid::findForPersonManage($params['person_id'], $params['year'])]);
    }

    /**
     * Export BMIDs to Marcato
     *
     * @returns JsonResponse
     * @throws AuthorizationException
     */

    public function export(): JsonResponse
    {
        $this->authorize('export', Bmid::class);

        $params = request()->validate([
            'year' => 'required|integer',
            'person_ids' => 'required|array',
            'person_ids.*' => 'required|integer',
            'batch_info' => 'sometimes|string',
        ]);

        $bmids = Bmid::findForPersonIds($params['year'], $params['person_ids']);

        if ($bmids->isEmpty()) {
            throw new InvalidArgumentException('No BMIDs were found');
        }

        // Filter out the IDS.
        $filterBmids = $bmids->filter(fn($bmid) => $bmid->isPrintable());

        if ($filterBmids->isEmpty()) {
            throw new InvalidArgumentException("No prep or ready-to-print status BMIDs found. Previously submitted BMIDs are ignored.");
        }

        $batchInfo = $params['batch_info'] ?? '';
        $exportUrl = MarcatoExport::export($filterBmids, $batchInfo);

        return response()->json(['export_url' => $exportUrl, 'bmids' => $filterBmids]);
    }

    /**
     * Retrieve all exports for a given year
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function exportList(): JsonResponse
    {
        $this->authorize('export', Bmid::class);
        $year = $this->getYear();

        return response()->json(['exports' => BmidExport::findAllForYear($year)]);
    }


    /**
     * Sanity Check the BMIDs
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function sanityCheck(): JsonResponse
    {
        $this->authorize('index', Bmid::class);

        $params = request()->validate([
            'year' => 'required|integer'
        ]);

        return response()->json(BMIDManagement::sanityCheckForYear($params['year']));
    }

    /**
     * Store a newly created BMID.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('create', Bmid::class);

        $params = request()->validate([
            'bmid.person_id' => 'required|integer',
            'bmid.year' => 'required|integer',
        ]);
        $params = $params['bmid'];

        // findForPersonManage will construct a potential record
        $bmid = Bmid::findForPersonManage($params['person_id'], $params['year']);
        $this->fromRest($bmid);

        if (!$bmid->save()) {
            return $this->restError($bmid);
        }

        Bmid::bulkLoadRelationships(new EloquentCollection([$bmid]), [$bmid->person_id]);
        return $this->success($bmid);
    }

    /**
     * Show a single BMID.
     *
     * @param Bmid $bmid
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(Bmid $bmid): JsonResponse
    {
        $this->authorize('show', $bmid);

        Bmid::bulkLoadRelationships(new EloquentCollection([$bmid]), [$bmid->person_id]);

        return $this->success($bmid);
    }

    /**
     * Update a BMID
     *
     * @param Bmid $bmid
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(Bmid $bmid): JsonResponse
    {
        $this->authorize('update', $bmid);

        // load up additional info
        Bmid::bulkLoadRelationships(new EloquentCollection([$bmid]), [$bmid->person_id]);
        $this->fromRest($bmid);

        if (!$bmid->save()) {
            return $this->restError($bmid);
        }

        return $this->success($bmid);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Bmid $bmid
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(Bmid $bmid): JsonResponse
    {
        $this->authorize('delete', $bmid);
        $bmid->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * Set BMID titles based on positions held in Clubhouse.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function setBMIDTitles(): JsonResponse
    {
        $this->authorize('setBMIDTitles', Bmid::class);
        return response()->json(['bmids' => BMIDManagement::setBMIDTitles()]);
    }
}
