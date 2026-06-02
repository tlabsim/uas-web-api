<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Publication;

class PublicationController extends Controller
{
    /**
     * Show a single publication by ID.
     * Input: ?id
     * Example: GET /api/v1/publication?id=123
     */
    public function show(Request $request)
    {
        if (!$request->filled('id')) {
            return response()->json(['status' => 'error', 'message' => 'Missing publication ID'], 400);
        }

        $publication = Publication::with(['authors', 'meta'])
            ->find($request->id);

        if (!$publication) {
            return response()->json(['status' => 'error', 'message' => 'Publication not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $publication]);
    }
}
