<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Research;

class ResearchController extends Controller
{
    /**
     * Show a single research project.
     * Input: ?id
     * Example: GET /api/v1/research?id=42
     */
    public function show(Request $request)
    {
        if (!$request->filled('id')) {
            return response()->json(['status' => 'error', 'message' => 'Missing research ID'], 400);
        }

        $research = Research::with(['people', 'publications'])->find($request->id);

        if (!$research) {
            return response()->json(['status' => 'error', 'message' => 'Research not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $research]);
    }
}
