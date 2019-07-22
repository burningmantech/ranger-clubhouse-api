<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

use App\Models\Bmid;
use App\Models\Person;
use App\Models\Photo;
use App\Models\LambasePhoto;

use App\Lib\LambaseBMID;

use GuzzleHttp;

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
                Rule::in([ 'special', 'alpha', 'signedup', 'submitted', 'printed', 'nonprint', 'no-shifts' ])
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
            ErrorLog::recordException($e, 'lambase-bmid-exception', [
                    'lambase_result'    => $e->lambaseResult
            ]);
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
            $bmid->save();

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

        $this->log('bmid-create', 'bmid create', $bmid->getAttributes(), $bmid->person_id);

        Bmid::bulkLoadRelationships(new EloquentCollection([ $bmid ]), [ $bmid->person_id ]);
        return $this->success($bmid);
    }

    /**
     * Show a single BMID.
     *
     */
    public function show(Bmid $bmid)
    {
        $this->authorize('show', $bmid);

        Bmid::bulkLoadRelationships(new EloquentCollection([ $bmid ]), [ $bmid->person_id ]);

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
        Bmid::bulkLoadRelationships(new EloquentCollection([ $bmid ]), [ $bmid->person_id ]);
        $this->fromRest($bmid);

        $changes = $bmid->getChangedValues();
        if (!$bmid->save()) {
            return $this->restError($bmid);
        }

        if (!empty($changes)) {
            $changes['id'] = $bmid->id;
            $this->log('bmid-update', 'bmid update', $changes, $bmid->person_id);
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
        $this->log('bmid-delete', 'bmid delete', $bmid, $bmid->person_id);
        return $this->restDeleteSuccess();
    }

    /**
     * Test BMID submission
     */

    const ROSCOE_TEST_ID = 1293;

    public function testUpload()
    {
        $this->checkTestToken();

        $person = Person::findOrFail(self::ROSCOE_TEST_ID);

        $bmid = Bmid::firstOrNewForPersonYear(self::ROSCOE_TEST_ID, current_year());

        $bmid->meals = 'all';
        $bmid->showers = 1;
        $bmid->org_vehicle_insurance = 1;
        $bmid->title1 = "Title 1";
        $bmid->title2 = "Title 2";
        $bmid->title3 = "Title 3";
        $bmid->batch = date('n/j/y G:i:s')." test upload";

        $exchange = LambaseBMID::upload([ $bmid ], true);
        if ($bmid->uploadedToLambase) {
            $response = "BMID was successfully uploaded.";
        } else {
            $response = "BMID upload failed - Lambase did include BMID in response.";
        }
        return <<<EOM
Result:
{$response}

Lambase upload URL used:
{$exchange['url']}

Clubhouse to Lambase JSON:
{$exchange['json']}

Lambase response:
{$exchange['result']}


EOM;

    }

    public function testPhoto()
    {
        $this->checkTestToken();

        $person = Person::findOrFail(self::ROSCOE_TEST_ID);

        $lambase = new LambasePhoto($person);
        $url = $lambase->getUploadUrl();
        error_log("PHOTO $url");

        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', $url);
        $status = $res->getStatusCode();
        return <<<EOM
Touched Lambase URL:
$url

Status code:
$status

EOM;
    }


    private function checkTestToken()
    {
        $token = setting('BmidTestToken');

        if (empty($token)) {
            throw new \RuntimeException("BMID test token not set -- cannot authorzie request");
        }

        $ourToken = request()->input('token');

        if ($token != $ourToken) {
            $this->notPermitted("Token mismatch -- not authorized.");
        }
    }

}
