<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Http\Controllers\ApiController;

use Illuminate\Http\Request;

class RoleController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //$this->authorize('view');
        return $this->success(Role::findAll(), null);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('store', Role::class);

        $role = new \App\Models\Role;
        $this->fromRest($role);

        if ($role->save()) {
            return $this->success($role);
        }

        return $this->restError($role);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function show(Role $role)
    {
        return $this->success($role);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Role $role)
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
     * @param  \App\Models\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function destroy(Role $role)
    {
        $this->authorize('delete', Role::class);
        $role->delete();
        $this->log('role-delete', 'Role Deleted', [ 'id' => $role->id]);
        return $this->restDeleteSuccess();
    }
}
