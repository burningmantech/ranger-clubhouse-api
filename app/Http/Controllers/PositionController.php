<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Http\Controllers\ApiController;

use Illuminate\Http\Request;

class PositionController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //$this->authorize('view');
        return $this->success(Position::findAll(), null);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('store', Position::class);

        $position = new \App\Models\Position;
        $this->fromRest($position);

        if ($position->save()) {
            return $this->success($position);
        }

        return $this->restError($position);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Position  $position
     * @return \Illuminate\Http\Response
     */
    public function show(Position $position)
    {
        return $this->success($position);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Position  $position
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Position $position)
    {
        $this->authorize('update', Position::class);
        $this->fromRest($position);

        if ($position->save()) {
            return $this->success($position);
        }

        return $this->restError($position);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Position  $position
     * @return \Illuminate\Http\Response
     */
    public function destroy(Position $position)
    {
        $this->authorize('delete', Position::class);
        $position->delete();
        $this->log('position-delete', 'Position Deleted', [ 'id' => $position->id]);
        return $this->restDeleteSuccess();
    }

    /**
     * Sandman Qualification Report
     */

    public function sandmanQualifiedReport()
    {
        $this->authorize('sandmanQualified', Position::class);
        return response()->json( Position::retrieveSandPeopleQualifications());
    }
}
