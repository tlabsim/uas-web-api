<?php

namespace App\Http\Controllers;

use App\Services\EntityNavigationService;
use Illuminate\Http\Request;

class EntityNavigationController extends Controller
{
    public function __construct(
        protected EntityNavigationService $navigationService
    ) {
    }

    public function show(Request $request)
    {
        $entityId = $request->integer('entity_id');

        if (!$entityId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing entity_id',
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->navigationService->buildForEntity($entityId),
        ]);
    }
}
