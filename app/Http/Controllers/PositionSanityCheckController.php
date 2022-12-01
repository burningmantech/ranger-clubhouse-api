<?php

namespace App\Http\Controllers;

use App\Lib\PositionSanityCheck;
use App\Models\Position;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class PositionSanityCheckController extends ApiController
{
    /**
     * Return a Rangers with position issues
     *
     * @return  JsonResponse
     * @throws AuthorizationException
     */

    public function sanityChecker(): JsonResponse
    {
        $this->authorize('sanityChecker', Position::class);
        return response()->json(PositionSanityCheck::issues());
    }

    /**
     * Repair position issues
     *
     * @return  JsonResponse
     * @throws AuthorizationException
     */

    public function repair(): JsonResponse
    {
        $this->authorize('repair', Position::class);

        $params = request()->validate([
            'repair' => 'required|string',
            'people_ids' => 'required|array',
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
