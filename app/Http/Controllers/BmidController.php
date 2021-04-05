<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

use App\Models\Bmid;
use App\Models\BmidExport;
use App\Models\PersonPosition;
use App\Models\Position;

use App\Lib\BMIDManagement;
use App\Lib\MarcatoExport;

use InvalidArgumentException;

class BmidController extends ApiController
{
    /**
     * Display a list of BMIDs.
     *
     */
    public function index()
    {
        $this->authorize('index', Bmid::class);

        $params = request()->validate([
            'year' => 'required|integer'
        ]);

        return response()->json(['bmids' => Bmid::findForQuery($params)]);
    }

    /*
     * Manage a potential list of BMIDs
     */

    public function manage()
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

    /*
     * Manage for a single person
     */

    public function managePerson()
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

    public function export()
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

        $batchInfo = $params['batch_info'] ?? '';
        $exportUrl = MarcatoExport::export($filterBmids, $batchInfo);

        return response()->json([ 'export_url' => $exportUrl, 'bmids' => $filterBmids ]);
    }

    /**
     * Retrieve all exports for a given year
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function exportList()
    {
        $this->authorize('export', Bmid::class);
        $year = $this->getYear();

        return response()->json([ 'exports' => BmidExport::findAllForYear($year)]);
    }


    /**
     * Sanity Check the BMIDs
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function sanityCheck()
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
     */
    public function store()
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
     */
    public function show(Bmid $bmid)
    {
        $this->authorize('show', $bmid);

        Bmid::bulkLoadRelationships(new EloquentCollection([$bmid]), [$bmid->person_id]);

        return $this->success($bmid);
    }

    /**
     * Update a BMID
     *
     */
    public function update(Bmid $bmid)
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
     */
    public function destroy(Bmid $bmid)
    {
        $this->authorize('delete', $bmid);
        $bmid->delete();
        return $this->restDeleteSuccess();
    }

    /*
     * Set BMID titles based on positions held in Clubhouse.
     */

    public function setBMIDTitles()
    {
        $this->authorize('setBMIDTitles', Bmid::class);

        $titles = [
            // Title 1
            Position::RSC_SHIFT_LEAD => ['title1', 'Shift Lead'],
            Position::DEPARTMENT_MANAGER => ['title1', 'Department Manager'],
            Position::OPERATIONS_MANAGER => ['title1', 'Operations Manager'],
            Position::OOD => ['title1', 'Officer of the Day'],
            // Title 2
            Position::LEAL => ['title2', 'LEAL'],
            // Title 3
            Position::DOUBLE_OH_7 => ['title3', '007']
        ];

        $year = current_year();

        $bmidTitles = [];
        $bmids = [];

        foreach ($titles as $positionId => $title) {
            // Find folks who have the position
            $people = PersonPosition::where('position_id', $positionId)->pluck('person_id');

            foreach ($people as $personId) {
                $bmid = $bmids[$personId] ?? null;
                if ($bmid == null) {
                    $bmid = Bmid::findForPersonManage($personId, $year);
                    // cache the BMID record - multiple titles might be set
                    $bmids[$personId] = $bmid;
                }

                $bmid->{$title[0]} = $title[1];

                if (!isset($bmids[$personId])) {
                    $bmidTitles[$personId] = [];
                }
                $bmidTitles[$personId][$title[0]] = $title[1];
            }
        }

        $badges = [];

        foreach ($bmids as $bmid) {
            $bmid->auditReason = 'maintenance - set BMID titles';
            $bmid->saveWithoutValidation();

            $person = $bmid->person;
            $title = $bmidTitles[$bmid->person_id];
            $badges[] = [
                'id' => $personId,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'title1' => $title['title1'] ?? null,
                'title2' => $title['title2'] ?? null,
                'title3' => $title['title3'] ?? null,
            ];
        }

        usort($badges, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));

        return response()->json(['bmids' => $badges]);
    }
}
