<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;
use App\Models\Person;
use App\Models\Role;

class CallsignsController extends ApiController
{
    public function index() {
        $params = request()->validate([
            'query'    => 'required|string',
            'type' => 'required|string',
            'limit' => 'required|integer'
        ]);

        $type = $params['type'];

        if (!in_array($type, [ 'message', 'contact' ])) {
            throw new \InvalidArgumentException('type parameter is invalid');
        }

        return response()->json([ 'callsigns' => Person::searchCallsigns($params['query'], $type, $params['limit']) ]);
    }
}
