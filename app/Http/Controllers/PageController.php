<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EntityStaticPage;

class PageController extends Controller
{
    /**
     * List all published static pages for an entity.
     * Optional filters: category, subcategory
     * Example: GET /api/v1/pages?entity_id=5&category=2&subcategory=7
     */
    public function index(Request $request)
    {
        if (!$request->filled('entity_id')) {
            return response()->json(['status' => 'error', 'message' => 'Missing entity_id'], 400);
        }

        $query = EntityStaticPage::query()
            ->where('entity_id', $request->entity_id)
            ->where('page_status', 'Published');

        if ($request->filled('category')) {
            $query->where('page_category', $request->category);
        }

        if ($request->filled('subcategory')) {
            $query->where('page_subcategory', $request->subcategory);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->orderBy('page_title')->get()
        ]);
    }

    /**
     * Show a specific published page by slug and entity_id.
     * Example: GET /api/v1/page?entity_id=5&page_slug=about
     */
    public function show(Request $request)
    {
        if (!$request->filled('entity_id') || !$request->filled('page_slug')) {
            return response()->json(['status' => 'error', 'message' => 'Missing entity_id or page_slug'], 400);
        }

        $page = EntityStaticPage::where('entity_id', $request->entity_id)
            ->where('page_slug', $request->page_slug)
            ->where('page_status', 'Published')
            ->first();

        if (!$page) {
            return response()->json(['status' => 'error', 'message' => 'Page not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $page]);
    }
}
