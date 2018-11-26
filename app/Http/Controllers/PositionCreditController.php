<?php

namespace App\Http\Controllers;

use App\Models\PositionCredit;
use App\Http\Controllers\ApiController;

use Illuminate\Http\Request;

class PositionCreditController extends ApiController
{
    /*
     * Show all the position credits for a year
     */
    public function index()
    {
        $params = request()->validate([
            'year'  => 'required|integer',
        ]);

        $this->authorize('view', PositionCredit::class);

        return $this->success(PositionCredit::findForYear($params['year']), null, 'position_credit');
    }

    /*
     * Create a new position credit
     *
     */
    public function store(Request $request)
    {
        $this->authorize('store', PositionCredit::class);

        $position_credit = new \App\Models\PositionCredit;
        $this->fromRest($position_credit);

        if ($position_credit->save()) {
            $position_credit->loadRelations();
            return $this->success($position_credit);
        }

        return $this->restError($position_credit);
    }

    /**
     * Show a single position credit
     */
    public function show(PositionCredit $position_credit)
    {
        $this->authorize('view', Position::class);
        $position_credit->loadRelations();
        return $this->success($position_credit);
    }

    /**
     * Update a position credit
     */
    public function update(Request $request, PositionCredit $position_credit)
    {
        $this->authorize('update', PositionCredit::class);
        $this->fromRest($position_credit);

        if ($position_credit->save()) {
            $position_credit->loadRelations();
            return $this->success($position_credit);
        }

        return $this->restError($position_credit);
    }

    /**
     * Remove a position credit
     *
     */
    public function destroy(PositionCredit $position_credit)
    {
        $this->authorize('delete', PositionCredit::class);
        $position_credit->delete();
        return $this->restDeleteSuccess();
    }
}
