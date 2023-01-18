<?php

namespace App\Http\Controllers;

use App\Lib\Reports\PeopleByRoleReport;
use App\Models\PersonRole;
use App\Models\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class RoleController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */

    public function index(): JsonResponse
    {
        return $this->success(Role::findAll(), null);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', Role::class);

        $role = new Role;
        $this->fromRest($role);

        if ($role->save()) {
            return $this->success($role);
        }

        return $this->restError($role);
    }

    /**
     * Display the specified resource.
     *
     * @param Role $role
     * @return JsonResponse
     */

    public function show(Role $role): JsonResponse
    {
        return $this->success($role);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Role $role
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(Role $role): JsonResponse
    {
        $this->authorize('update', Role::class);
        $this->fromRest($role);

        if ($role->save()) {
            return $this->success($role);
        }

        return $this->restError($role);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Role $role
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', Role::class);
        $role->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * People By Role report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function peopleByRole(): JsonResponse
    {
        $this->authorize('peopleByRole', Role::class);

        return response()->json(['roles' => PeopleByRoleReport::execute()]);
    }

    /**
     * Clear the role cache for a person
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function clearCache(): JsonResponse
    {
        $this->authorize('clearCache', Role::class);
        $params = request()->validate([
            'person_id' => 'required|integer|exists:person,id'
        ]);

        PersonRole::clearCache($params['person_id']);

        return $this->success();
    }
}
