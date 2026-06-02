<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EntityStaticPage;
use Illuminate\Support\Str;

class PageController extends Controller
{
    /**
     * List pages for the current entity.
     */
    public function index(Request $request)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $pages = EntityStaticPage::where('entity_id', $entityId)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['status' => 'success', 'data' => $pages]);
    }

    /**
     * Store a new page.
     */
    public function store(Request $request)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $request->validate([
            'page_title' => 'required|string|max:255',
            'page_slug' => [
                'nullable',
                'string',
                'max:240',
                \Illuminate\Validation\Rule::unique('entity_static_pages')
                    ->where('entity_id', $entityId)
            ],
            'page_excerpt' => 'nullable|string',
            'page_content' => 'required|string',
            'custom_css' => 'nullable|string',
            'custom_js' => 'nullable|string',
            'page_status' => 'required|in:Draft,Published,Withdrawn',
            'page_category' => 'nullable|integer',
            'page_subcategory' => 'nullable|integer',
            'is_menu' => 'nullable|boolean',
            'menu_text' => 'nullable|string|max:100',
            'menu_order' => 'nullable|integer|min:0',
            'featured_image_uri' => 'nullable|string',
        ]);

        $page = EntityStaticPage::create([
            'entity_id' => $entityId,
            'page_title' => $request->page_title,
            'page_slug' => $request->page_slug ?? Str::slug($request->page_title),
            'page_excerpt' => $request->page_excerpt,
            'page_content' => $request->page_content,
            'custom_css' => $request->custom_css,
            'custom_js' => $request->custom_js,
            'page_status' => $request->page_status,
            'page_category' => $request->page_category,
            'page_subcategory' => $request->page_subcategory,
            'is_menu' => $request->is_menu ?? false,
            'menu_text' => $request->menu_text,
            'menu_order' => $request->menu_order ?? 999,
            'featured_image_uri' => $request->featured_image_uri,
            'author' => $request->input('author'),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Page created successfully', 'data' => $page], 201);
    }

    /**
     * Update an existing page.
     */
    public function update(Request $request, $id)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $page = EntityStaticPage::where('entity_id', $entityId)->findOrFail($id);

        $request->validate([
            'page_title' => 'required|string|max:255',
            'page_slug' => [
                'nullable',
                'string',
                'max:240',
                \Illuminate\Validation\Rule::unique('entity_static_pages')
                    ->where('entity_id', $entityId)
                    ->ignore($page->id)
            ],
            'page_excerpt' => 'nullable|string',
            'page_content' => 'required|string',
            'custom_css' => 'nullable|string',
            'custom_js' => 'nullable|string',
            'page_status' => 'required|in:Draft,Published,Withdrawn',
            'page_category' => 'nullable|integer',
            'page_subcategory' => 'nullable|integer',
            'is_menu' => 'nullable|boolean',
            'menu_text' => 'nullable|string|max:100',
            'menu_order' => 'nullable|integer|min:0',
            'featured_image_uri' => 'nullable|string',
        ]);

        $page->update([
            'page_title' => $request->page_title,
            'page_slug' => $request->page_slug ?? Str::slug($request->page_title),
            'page_excerpt' => $request->page_excerpt,
            'page_content' => $request->page_content,
            'custom_css' => $request->custom_css,
            'custom_js' => $request->custom_js,
            'page_status' => $request->page_status,
            'is_menu' => $request->is_menu ?? false,
            'menu_order' => $request->menu_order ?? 999,
            'page_category' => $request->page_category,
            'page_subcategory' => $request->page_subcategory,
            'menu_text' => $request->menu_text,
            'featured_image_uri' => $request->featured_image_uri,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Page updated successfully', 'data' => $page]);
    }

    /**
     * Delete a page.
     */
    public function destroy(Request $request, $id)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $page = EntityStaticPage::where('entity_id', $entityId)->findOrFail($id);

        $page->delete();

        return response()->json(['status' => 'success', 'message' => 'Page deleted successfully']);
    }
}
