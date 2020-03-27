<?php

namespace app\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use \Illuminate\Support\Facades\DB;

use Illuminate\Database\Eloquent\Collection;

use App\Http\Controllers\Controller;

use App\Models\ActionLog;
use App\Models\Person;
use App\Models\Role;

use App\Http\RestApi\SerializeRecord;
use App\Http\RestApi\DeserializeRecord;

class ApiController extends Controller
{
    protected $user;

    public function __construct()
    {
        if (Auth::check()) {
            $this->user = Auth::user();
            if (!$this->user->user_authorized || $this->user->status == Person::SUSPENDED) {
                // A user should not be able to login when not authorized.
                // However, a user could be logged in when their account is disabled.
                throw new \Illuminate\Auth\Access\AuthorizationException('Account is disabled.');
            }

            $this->user->retrieveRoles();
            DB::select("UPDATE person SET last_seen_at=NOW() WHERE id=?", [ $this->user->id ]);
        }
    }

    public function isUser($person): bool
    {
        if (!$this->user)
            return false;

        return is_numeric($person) ? $this->user->id == $person : $this->user->id == $person->id;
    }

    public function findPerson($id)
    {
        if ($this->isUser($id)) {
            return $this->user;
        }

        return Person::findOrFail($id);
    }

    public function getYear():int
    {
        $query = request()->validate([ 'year' => 'required|digits:4']);
        return intval($query['year']);
    }

    public function userHasRole($roles) : bool
    {
        if (!$this->user) {
            return false;
        }

        return $this->user->hasRole($roles);
    }

    public function fromRestFiltered($record)
    {
        return DeserializeRecord::fromRest(request(), $record, $this->user);
    }

    public function fromRest($record) {
        $resourceName = $record->getResourceSingle();
        $fields = request()->input($resourceName);

        if (!is_null($fields) && !empty($fields)) {
            $record->fill($fields);
        }
    }

    /*
     * Filter an Eloquent row or collection to send back.
     *
     * $table should be provided for a collection in case the set is empty
     *
     * @param Collection|ApiModel $resource a row or collection to filter
     * @param array $meta Meta information to return
     * @param string $table name of the table or model.
     * @return array associative array built from $resource & $meat
     */


    public function toRestFiltered($resource, $meta = null, $resourceName = null)
    {
        $user = $this->user;
        if ($resource instanceof \Illuminate\Database\Eloquent\Collection) {
            if ($resource->isEmpty()) {
                $results = [];
            } else {
                $results = [];
                foreach ($resource as $row) {
                    $results[] = (new SerializeRecord($row))->toRest($user);
                }
                $resourceName = $resource->first()->getResourceCollection();
            }
        } else {
            $results = (new SerializeRecord($resource))->toRest($user);
            $resourceName = $resource->getResourceSingle();
        }

        $json = [ $resourceName => $results ];
        if ($meta) {
            $json['meta'] = $meta;
        }

        return response()->json($json);
    }

    /**
     * Return either a JSON success if all arguments are null or
     * a REST json success response
     *
     * @param mixed $resource an array, eloquent collection, or similar to return
     * @param mixed $meta any meta information
     * @param mixed $resourceName name of the resource, used when the collection is empty.
     * @return \Illuminate\Http\JsonResponse
     */

    public function success($resource=null, $meta=null, $resourceName = null)
    {
        if ($resource === null) {
            return response()->json([ 'status' => 'success' ]);
        }

        if (is_iterable($resource)) {
            if ($resourceName == '') {
                $resourceName = $resource->first()->getResourceCollection();
            }
            $rows = [];
            foreach ($resource as $row) {
                $rows[] = $row->toArray();
            }

            $result = [ $resourceName => $rows ];
        } else {
            if ($resourceName == '') {
                $resourceName = $resource->getResourceSingle();
            }
            $result = [ $resourceName => $resource ];
        }

        if ($meta) {
            $result['meta'] = $meta;
        }

        return response()->json($result);

    }

    public function error($message, $status = 400)
    {
        return response()->json([ 'error' => $message ], $status);
    }

    public function restDeleteSuccess()
    {
        return response()->json([], 204);
    }

    public function restError($item, $status=422)
    {
        if (gettype($item) == 'string') {
            $payload = [ [ 'title' => $item ] ];
        } else {
            $payload = [];
            foreach ($item->getErrors() as $column => $messages) {
                foreach ($messages as $message) {
                    $payload[] = [
                        'title'   => $message,
                        'source'  => [
                            'pointer' => "/data/attributes/${column}",
                        ],
                        'status'  => $status,
                    ];
                }
            }
        }
        return response()->json([ 'errors' => $payload ], $status);
    }

    public function userCanViewEmail() {
        return $this->userHasRole([ Role::ADMIN, Role::VIEW_PII, Role::VIEW_EMAIL, Role::VC ]);
    }

    public function log($event, $message, $data=null, $targetPersonId=null) {
        ActionLog::record(
            $this->user,
            $event,
            $message,
            $data,
            $targetPersonId
        );
    }

    /**
     * Tells the user (via Handler::render) the operation is not authorized
     *
     * @param $message string to send back
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */

    public function notPermitted($message)
    {
        throw new \Illuminate\Auth\Access\AuthorizationException($message);
    }
}
