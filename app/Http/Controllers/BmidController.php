<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Bmid;
use App\Lib\LambaseBMID;

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
            'year'  => 'required|integer'
        ]);

        return response()->json([ 'bmids' => Bmid::findForQuery($params)]);
    }

    /*
     * Manage a potential list of BMIDs
     */

    public function manage()
    {
        $this->authorize('index', Bmid::class);

        $params = request()->validate([
            'year'  => 'required|integer',
            'filter'   => [
                'required',
                'string',
                Rule::in([ 'special', 'alpha', 'signedup', 'submitted', 'printed', 'nonprint' ])
            ]
        ]);

        return response()->json([ 'bmids' => Bmid::findForManage($params['year'], $params['filter']) ]);
    }

    /*
     * Manage for a single person
     */

    public function managePerson()
    {
        $this->authorize('index', Bmid::class);

        $params = request()->validate([
            'person_id' => 'required|integer|exists:person,id',
            'year'      => 'required|integer',
        ]);

        return response()->json([ 'bmid' => Bmid::findForPersonManage($params['person_id'], $params['year'])]);
    }

    /*
     * Send BMIDs to Lambase
     */

    public function lambase()
    {
        $this->authorize('lambase', Bmid::class);

        $params = request()->validate([
            'year'         => 'required|integer',
            'person_ids'   => 'required|array',
            'person_ids.*' => 'required|integer',
            'batch_info'   => 'sometimes|string',
        ]);

        $bmids = Bmid::findForPersonIds($params['year'], $params['person_ids']);

        $user = $this->user->callsign;
        $uploadDate = date('n/j/y G:i:s');
        $batchInfo = $params['batch_info'] ?? '';
        $batchInfo = $batchInfo . " submitted $uploadDate by $user";

        // Filter out the IDS.
        $filterBmids = $bmids->filter(function ($row) use ($batchInfo) {
            if ($row->isPrintable()) {
                $row->batch = $batchInfo;
                return true;
            }
            return false;
        });

        try {
            if (!empty($filterBmids)) {
                LambaseBMID::upload($filterBmids);
            }
        } catch (LambaseBMIDException $e) {
            $message = $e->getMessage();
            ErrorLog::record(
                request(),
                'lambase-exception',
                [
                    'message' => $message,
                    'data'    => $e->lambaseResult
                ]
            );
            return RestApi::error(response(), 500, "Lambase upload failed: {$message}");
        }

        $results = [];
        foreach ($bmids as $bmid) {
            if (!$bmid->uploadedToLambase) {
                $results[] = [
                    'person_id' => $bmid->person_id,
                    'status'    => 'failed'
                ];
                continue;
            }
            $bmid->status = 'submitted';
            $bmid->notes = "$uploadDate $user: Uploaded to Lambase\n$bmid->notes";
            //$bmid->save();

            $results[] = [
                'person_id' => $bmid->person_id,
                'status'    => 'submitted'
            ];
        }

        return response()->json([ 'bmids' => $results ]);
    }
    /*
     * Sanity Check the BMIDs
     */
    public function sanityCheck()
    {
        $this->authorize('index', Bmid::class);

        $params = request()->validate([
            'year'  => 'required|integer'
        ]);

        return response()->json(Bmid::sanityCheckForYear($params['year']));
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
            'bmid.year'      => 'required|integer',
        ]);
        $params = $params['bmid'];

        // findForPersonManage will construct a potential record
        $bmid = Bmid::findForPersonManage($params['person_id'], $params['year']);
        $this->fromRest($bmid);

        if (!$bmid->save()) {
            return $this->restError($bmid);
        }

        return $this->success($bmid);
    }

    /**
     * Show a single BMID.
     *
     */
    public function show(Bmid $bmid)
    {
        $this->authorize('show', $bmid);

        return $this->success($bmid);
    }

    /**
     * Update a BMID
     *
     */
    public function update(Bmid $bmid)
    {
        $this->authorize('update', $bmid);

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
}
