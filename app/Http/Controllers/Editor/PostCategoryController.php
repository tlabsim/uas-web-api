<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PostCategory;
use Illuminate\Support\Str;

class PostCategoryController extends Controller
{
    /**
     * List all post categories
     */
    public function index(Request $request)
    {
        $query = PostCategory::query();

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by system/custom
        if ($request->filled('is_system')) {
            $query->where('is_system', filter_var($request->is_system, FILTER_VALIDATE_BOOLEAN));
        }

        // Ordered by sort_order
        $query->ordered();

        $categories = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $categories
        ]);
    }

    /**
     * Get a single category with full schema
     */
    public function show($id)
    {
        $category = PostCategory::find($id);

        if (!$category) {
            return response()->json([
                'status' => 'error',
                'message' => 'Category not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $category
        ]);
    }

    /**
     * Create a new custom category
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:post_categories,slug',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'meta_schema' => 'nullable|array',
            'attachment_config' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['is_system'] = false; // Custom categories are not system categories
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? PostCategory::max('sort_order') + 1;

        $category = PostCategory::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    /**
     * Update a category (can only update custom categories)
     */
    public function update(Request $request, $id)
    {
        $category = PostCategory::findOrFail($id);

        // Prevent editing system categories
        if ($category->is_system) {
            return response()->json([
                'status' => 'error',
                'message' => 'System categories cannot be modified'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:post_categories,slug,' . $id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'meta_schema' => 'nullable|array',
            'attachment_config' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $category->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    /**
     * Delete a category (can only delete custom categories with no posts)
     */
    public function destroy($id)
    {
        $category = PostCategory::findOrFail($id);

        // Prevent deleting system categories
        if ($category->is_system) {
            return response()->json([
                'status' => 'error',
                'message' => 'System categories cannot be deleted'
            ], 403);
        }

        // Check if category has posts
        if ($category->posts()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete category with existing posts. Please reassign or delete the posts first.'
            ], 400);
        }

        $category->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Category deleted successfully'
        ]);
    }

    /**
     * Toggle active status
     */
    public function toggleActive($id)
    {
        $category = PostCategory::findOrFail($id);
        
        $category->is_active = !$category->is_active;
        $category->save();

        return response()->json([
            'status' => 'success',
            'message' => $category->is_active ? 'Category activated' : 'Category deactivated',
            'data' => ['is_active' => $category->is_active]
        ]);
    }

    /**
     * Reorder categories
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:post_categories,id',
        ]);

        foreach ($validated['order'] as $sortOrder => $categoryId) {
            PostCategory::where('id', $categoryId)->update(['sort_order' => $sortOrder]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Categories reordered successfully'
        ]);
    }
}
