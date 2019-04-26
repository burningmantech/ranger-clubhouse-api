<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;

use App\Models\Help;

class HelpController extends ApiController
{
    /**
     * Display a listing of the helps.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return $this->success(Help::findAll(), null, 'help');
    }

    /**
     * Store a newly created help in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('store', [ Help::class ]);
        $help = new Help;
        $this->fromRest($help);

        if ($help->save()) {
            return $this->success($help);
        }

        return $this->restError($help);
    }

    /**
     * Display the specified help.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Help $help)
    {
        return $this->success($help);
    }

    /**
     * Update the specified help in storage.
     *
     */
    public function update(Help $help)
    {
        $this->authorize('update', $help);
        $this->fromRest($help);

        if ($help->save()) {
            return $this->success($help);
        }

        return $this->restError($help);
    }

    /**
     * Remove the specified help from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Help $help)
    {
        $this->authorize('destroy', $help);
        $help->delete();
        return $this->restDeleteSuccess();
    }
}
