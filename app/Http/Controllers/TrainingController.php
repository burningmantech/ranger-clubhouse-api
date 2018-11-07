<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\Training;

class TrainingController extends ApiController
{
    public function show($id)
    {
        $training = Training::findOrFail($id);

        return response()->json($training);
    }

    public function multipleEnrollmentsReport($id)
    {
        $year = request()->validate([
            'year'  => 'required|integer'
        ]);

        $training = Training::findOrFail($id);

        return response()->json([
            'enrollments' => Training::retrieveMultipleEnrollments($training, $year),
            'year'        => $year
        ]);
    }

    public function capacityReport($id)
    {
        $year = request()->validate([
            'year'  => 'required|integer'
        ]);

        $training = Training::findOrFail($id);

        return response()->json([
            'slots' => Training::retrieveSlotsCapacity($training, $year)
        ]);
    }
}
