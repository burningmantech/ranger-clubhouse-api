<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;
use App\Models\PersonMentor;
use App\Policies\PersonMentorPolicy;

class MentorController extends ApiController
{
    public function mentees()
    {
        $this->authorize('mentees', [ PersonMentor::class ]);

        $params = request()->validate([
            'year'  => 'required|integer',
        ]);

        return response()->json([ 'mentees' => PersonMentor::findMenteesForYear($params['year']) ]);
    }
}
