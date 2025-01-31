<?php

namespace App\Http\Controllers;

use App\Http\RestApi\DeserializeRecord;
use App\Http\RestApi\SerializeRecord;
use App\Models\ActionLog;
use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ApiController extends Controller
{
    /**
     * @var ?Person
     */
    protected ?Person $user = null;

    /**
     * Set up the user if logged in
     */

    public function __construct()
    {
        $this->user = Auth::user();
    }

    /**
     * Is the given person the current user?
     *
     * @param $person
     * @return bool
     */

    public function isUser($person): bool
    {
        if (!$this->user)
            return false;

        return is_numeric($person) ? $this->user->id == $person : $this->user->id == $person->id;
    }

    /**
     * Retrieve a specific person, using the authorized user if the ids match.
     *
     * @param $id
     * @return ?Person
     */

    public function findPerson($id): ?Person
    {
        if ($this->isUser($id)) {
            return $this->user;
        }

        return Person::findOrFail($id);
    }

    /**
     * Convenience helper to get the year parameter.
     *
     * @return int
     */

    public function getYear(): int
    {
        $query = request()->validate(['year' => 'required|digits:4']);
        return intval($query['year']);
    }

    /**
     * Does the user hold the effective role or roles?
     *
     * @param $roles
     * @return bool
     */

    public function userHasRole($roles): bool
    {
        if (!$this->user) {
            return false;
        }

        return $this->user->hasRole($roles);
    }

    /**
     * Does the user hold the true role or roles?
     *
     * @param $roles
     * @return bool
     */

    public function userHasTrueRole($roles): bool
    {
        if (!$this->user) {
            return false;
        }

        return $this->user->hasTrueRole($roles);
    }


    /**
     * Load a model record using a filter
     *
     * @param $record
     * @return mixed
     */

    public function fromRestFiltered($record): mixed
    {
        return DeserializeRecord::fromRest(request(), $record, $this->user);
    }

    public function fromRest($record): void
    {
        $resourceName = $record->getResourceSingle();
        $fields = request()->input($resourceName);

        if (!empty($fields)) {
            $record->fill($fields);
        }
    }

    /*
     * Filter an Eloquent row or collection to send back.
     *
     * $table should be provided for a collection in case the set is empty
     *
     * @param mixed $resource a row or collection to filter
     * @param array $meta Meta information to return
     * @param string $table name of the table or model.
     * @return array associative array built from $resource & $meat
     */


    public function toRestFiltered(mixed $resource, $meta = null, $resourceName = null): JsonResponse
    {
        $user = $this->user;
        if ($resource instanceof Collection) {
            $results = [];
            if (!$resource->isEmpty()) {
                foreach ($resource as $row) {
                    $results[] = (new SerializeRecord($row))->toRest($user);
                }
                $resourceName = $resource->first()->getResourceCollection();
            }
        } else {
            $results = (new SerializeRecord($resource))->toRest($user);
            $resourceName = $resource->getResourceSingle();
        }

        $json = [$resourceName => $results];
        if ($meta) {
            $json['meta'] = $meta;
        }

        return response()->json($json);
    }

    /**
     * Return either a JSON success if all arguments are null or
     * a REST json success response
     *
     * @param mixed|null $resource an array, eloquent collection, or similar to return
     * @param mixed|null $meta any meta information
     * @param mixed|null $resourceName name of the resource, used when the collection is empty.
     * @return JsonResponse
     */

    public function success(mixed $resource = null, mixed $meta = null, mixed $resourceName = null): JsonResponse
    {
        if ($resource === null) {
            return response()->json(['status' => 'success']);
        }

        if (is_iterable($resource)) {
            if ($resourceName == '') {
                $resourceName = $resource->first()->getResourceCollection();
            }
            $rows = [];
            foreach ($resource as $row) {
                $rows[] = is_array($row) ? $row : $row->toArray();
            }

            $result = [$resourceName => $rows];
        } else {
            if ($resourceName == '') {
                $resourceName = $resource->getResourceSingle();
            }
            $result = [$resourceName => $resource];
        }

        if ($meta) {
            $result['meta'] = $meta;
        }

        return response()->json($result);
    }

    /**
     * Send back a single error response
     *
     * @param $message
     * @param int $status
     * @return JsonResponse
     */

    public function error($message, int $status = 400): JsonResponse
    {
        return response()->json(['error' => $message], $status);
    }

    /**
     * Respond with a successful deletion status
     *
     * @return JsonResponse
     */

    public function restDeleteSuccess(): JsonResponse
    {
        return response()->json([], 204);
    }

    /**
     * Respond a REST api error.
     *
     * @param $item
     * @param int $status
     * @return JsonResponse
     */

    public function restError($item, int $status = 422): JsonResponse
    {
        if (gettype($item) == 'string') {
            $payload = [['title' => $item]];
        } else {
            $payload = [];
            foreach ($item->getErrors() as $column => $messages) {
                foreach ($messages as $message) {
                    $payload[] = [
                        'title' => $message,
                        'source' => [
                            'pointer' => "/data/attributes/{$column}",
                        ],
                        'status' => $status,
                    ];
                }
            }
        }
        return response()->json(['errors' => $payload], $status);
    }

    /**
     * Can the user view another person's email address?
     *
     * @return bool
     */

    public function userCanViewEmail(): bool
    {
        return $this->userHasRole([Role::ADMIN, Role::VIEW_PII, Role::VIEW_EMAIL, Role::VC]);
    }

    /**
     * Record an action using the current user as the actor.
     *
     * @param $event
     * @param $message
     * @param $data
     * @param $targetPersonId
     * @return void
     */

    public function log($event, $message, $data = null, $targetPersonId = null): void
    {
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
     * @param string $message to send back
     * @throws AuthorizationException
     */

    public function notPermitted(string $message)
    {
        throw new AuthorizationException($message);
    }
}
