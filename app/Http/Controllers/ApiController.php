<?php

namespace app\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

use App\Models\Person;
use App\Models\ActionLog;

use App\Http\RestApi\SerializeRecord;
use App\Http\RestApi\DeserializeRecord;

class ApiController extends Controller
{
    protected $user;

    public function __construct()
    {
        $this->user = Auth::guard('api')->user();
        if ($this->user) {
            if (!$this->user->user_authorized) {
                // A user should not be able to login when not authorized.
                // However, a user could be logged in when their account is disabled.
                throw new \Illuminate\Auth\Access\AuthorizationException('Account is disabled.');
            }

            $this->user->retrieveRoles();
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
        $table = $record->getTable();
        $fields = request()->input($table);

        if (empty($fields)) {
            throw new \InvalidArgumentException("Missing '$table' root name");
        }

        $record->fill($fields);
    }

    public function toRestFiltered($resource, $meta = null)
    {
        $user = $this->user;
        if (is_iterable($resource)) {
            $results = [];
            foreach ($resource as $row) {
                $results[] = (new SerializeRecord($row))->toRest($user);
            }
            $model = $resource->first()->getTable();
        } else {
            $results = (new SerializeRecord($resource))->toRest($user);
            $model = $resource->getTable();
        }

        $json = [ $model => $results ];
        if ($meta) {
            $json['meta'] = $meta;
        }

        return response()->json($json);
    }

    public function success($resource=null, $meta=null)
    {
        if (!$resource) {
            return response()->json([ 'message' => 'success' ]);
        }

        if (is_iterable($resource)) {
            $table = $resource->first()->getTable();
            $rows = [];
            foreach ($resource as $row) {
                $rows[] = $row->toArray();
            }

            $result = [ $table => $rows ];
        } else {
            $table = $resource->getTable();
            $result = [ $table => $resource ];
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
                            'pointer' => "data/attributes/${column}",
                        ],
                        'status'  => $status,
                    ];
                }
            }
        }
        return response()->json([ 'errors' => $payload ], $status);
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

    public function notPermitted($message)
    {
        throw new \Illuminate\Auth\Access\AuthorizationException($message);
    }
}
