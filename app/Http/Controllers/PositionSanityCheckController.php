<?php

namespace App\Http\Controllers;

use App\Models\Position;

use App\Lib\PositionSanityCheck;

use Illuminate\Http\Request;

class PositionSanityCheckController extends Controller
{
    /**
     * Return a Rangers with position issues
     *
     * @return  \Illuminate\Http\JsonResponse
     */
    public function sanityChecker()
    {
        $this->authorize('sanityChecker', [ Position::class ]);
        return response()->json(PositionSanityCheck::issues());
    }

    /**
     * Repair position issues
     *
     * @return  \Illuminate\Http\JsonResponse
     */

    public function repair()
    {
        $this->authorize('repair', [ Position::class ]);

        $params = request()->validate([
            'repair'       => 'required|string',
            'people_ids'   => 'required|array',
            'people_ids.*' => 'required|integer|exists:person,id',
            'repair_params' => 'nullable'
        ]);

        if (!array_key_exists('repair_params', $params)) {
            $params['repair_params'] = [];
        }

        return response()->json(PositionSanityCheck::repair(
            $params['repair'],
            $params['people_ids'],
            $params['repair_params']));
    }
}
