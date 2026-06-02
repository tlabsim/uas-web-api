<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Snippet;

class SnippetController extends Controller
{
    /**
     * List published snippets for a given entity.
     * Accepts either entity_id or slug (slug resolution is optional).
     * Example: GET /api/v1/snippets?entity_id=10
     */
    public function index(Request $request)
    {
        if (!$request->filled('entity_id')) {
            return response()->json(['status' => 'error', 'message' => 'Missing entity_id'], 400);
        }

        $snippets = Snippet::with('meta')
            ->where('entity_id', $request->entity_id)
            ->where('status', 'Published')
            ->orderBy('id')
            ->get();

        return response()->json(['status' => 'success', 'data' => $snippets]);
    }
}
