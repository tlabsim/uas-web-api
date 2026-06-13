<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EntityPageCategory;
use App\Models\EntityPageSubcategory;
use Illuminate\Support\Facades\DB;


class MenuController extends Controller
{
    /**
     * Get all categories with their subcategories for current entity.
     */
    public function indexCategories(Request $request)
    {
        $scopeEntityId = $request->attributes->get('current_role_scope');
        $requestEntityId = $request->input('entity_id');
        
        if(!$scopeEntityId) {
            return response()->json(['status' => 'error', 'message' => 'The request is possibly made without proper privilege'], 400);
        }   
        
        if(!$requestEntityId) {
            return response()->json(['status' => 'error', 'message' => 'Missing entity_id parameter'], 400);
        }

        if($requestEntityId != $scopeEntityId) {
            return response()->json(['status' => 'error', 'message' => 'You do not have permission to access this entity'], 403);
        }

        $entityId = $requestEntityId;        

        $categories = EntityPageCategory::with('subcategories')
            ->where('entity_id', $entityId)
            ->orderBy('menu_order')
            ->get();

        return response()->json(['status' => 'success', 'data' => $categories]);
    }

    public function indexSubcategories(Request $request)
    {
        $scopeEntityId = $request->attributes->get('current_role_scope');
        $categoryId = $request->input('category_id');

        if(!$scopeEntityId) {
            return response()->json(['status' => 'error', 'message' => 'The request is possibly made without proper privilege'], 400);
        }   
        if(!$categoryId) {
            return response()->json(['status' => 'error', 'message' => 'Missing category_id parameter'], 400);
        }

        $category = EntityPageCategory::where('entity_id', $scopeEntityId)->find($categoryId);

        if (!$category) {
            return response()->json(['status' => 'error', 'message' => 'You do not have permission to access this category'], 403);
        }

        $subcategories = EntityPageSubcategory::where('category_id', $category->id)
            ->orderBy('menu_order')
            ->get();

        return response()->json(['status' => 'success', 'data' => $subcategories]);
    }

    /**
     * Create a new category.
     */
    public function createCategory(Request $request)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'The request is possibly made without proper privilege'], 400);
        }

        $request->validate([
            'category_name' => 'required|string|max:240',
            'category_slug' => 'nullable|string|max:240',
            'is_menu' => 'nullable|boolean',
            'menu_text' => 'nullable|string|max:240',
            'link_url' => 'nullable|string',
            'menu_order' => 'nullable|integer',
        ]);

        $category = EntityPageCategory::create([
            'entity_id' => $entityId,
            'category_name' => $request->input('category_name'),
            'category_slug' => $request->input('category_slug') ?: \Illuminate\Support\Str::slug($request->input('category_name')),
            'is_menu' => (bool) $request->boolean('is_menu'),
            'menu_text' => $request->boolean('is_menu') ? $request->input('menu_text') : null,
            'link_url' => $request->boolean('is_menu') ? $request->input('link_url') : null,
            'menu_order' => $request->menu_order ?? 0,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Category created', 'data' => $category]);
    }

    /**
     * Create a new subcategory.
     */
    public function createSubcategory(Request $request)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'The request is possibly made without proper privilege'], 400);
        }

        $request->validate([
            'category_id' => 'required|exists:entity_page_categories,id',
            'subcategory_name' => 'required|string|max:240',
            'subcategory_slug' => 'nullable|string|max:240',
            'is_menu' => 'nullable|boolean',
            'menu_text' => 'nullable|string|max:240',
            'link_url' => 'nullable|string',
            'menu_order' => 'nullable|integer',
        ]);

        $category = EntityPageCategory::where('entity_id', $entityId)->findOrFail($request->category_id);

        $subcategory = EntityPageSubcategory::create([
            'category_id' => $category->id,
            'subcategory_name' => $request->input('subcategory_name'),
            'subcategory_slug' => $request->input('subcategory_slug') ?: \Illuminate\Support\Str::slug($request->input('subcategory_name')),
            'is_menu' => (bool) $request->boolean('is_menu'),
            'menu_text' => $request->boolean('is_menu') ? $request->input('menu_text') : null,
            'link_url' => $request->boolean('is_menu') ? $request->input('link_url') : null,
            'menu_order' => $request->menu_order ?? 0,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Subcategory created', 'data' => $subcategory]);
    }


    public function destroyCategory(Request $request, $id)
    {
        $entityId = $request->attributes->get('current_role_scope');

        $category = EntityPageCategory::where('entity_id', $entityId)->findOrFail($id);

        // Optional: also delete its subcategories
        $category->subcategories()->delete();
        $category->delete();

        return response()->json(['status' => 'success', 'message' => 'Category deleted']);
    }

    public function destroySubcategory(Request $request, $id)
    {
        $entityId = $request->attributes->get('current_role_scope');

        $subcategory = EntityPageSubcategory::where('entity_id', $entityId)->findOrFail($id);
        $subcategory->delete();

        return response()->json(['status' => 'success', 'message' => 'Subcategory deleted']);
    }

    /**
     * Update all categories and subcategories for an entity.
     * This will replace existing categories and subcategories.
     */
    public function updateCategoriesAll(Request $request)
    {
        $scopeEntityId = $request->attributes->get('current_role_scope');

        \Log::info('Updating categories and subcategories', ['entity_id' => $request->input('entity_id')]);

        if (!$scopeEntityId) {
            return response()->json(['status' => 'error', 'message' => 'The request is possibly made without proper privilege'], 400);
        }

        $request->validate([
            'entity_id' => 'required|integer',
            'categories' => 'present|nullable|array'
        ]);

        $entityId = $request->input('entity_id');
        $categories = $request->input('categories') ?? [];

        if ((int) $entityId !== (int) $scopeEntityId) {
            return response()->json(['status' => 'error', 'message' => 'You do not have permission to update this entity'], 403);
        }

        $updated = [];

        try {
            DB::transaction(function () use ($entityId, $categories, &$updated) {
                $existingCategoryIds = [];

                foreach ($categories as $catIndex => $catData) {
                    $categoryId = (int) ($catData['id'] ?? 0);
                    $categorySlug = (string) ($catData['category_slug'] ?? '');

                    $category = null;

                    if ($categoryId > 0) {
                        $category = EntityPageCategory::where('entity_id', $entityId)->find($categoryId);
                    }

                    if (!$category && $categorySlug !== '') {
                        $category = EntityPageCategory::where('entity_id', $entityId)
                            ->where('category_slug', $categorySlug)
                            ->first();
                    }

                    if (!$category) {
                        $category = new EntityPageCategory();
                        $category->entity_id = $entityId;
                    }

                    $category->category_name = $catData['category_name'];
                    $category->category_slug = $categorySlug;
                    $category->is_menu = (bool) ($catData['is_menu'] ?? false);
                    $category->menu_text = $category->is_menu ? ($catData['menu_text'] ?? null) : null;
                    $category->link_url = $category->is_menu ? ($catData['link_url'] ?? null) : null;
                    $category->menu_order = $catIndex;
                    $category->save();

                    $catData['id'] = $category->id;
                    $existingCategoryIds[] = $category->id;

                    $subcategories = $catData['subcategories'] ?? [];
                    $submittedSubcategoryIds = [];

                    foreach ($subcategories as $subIndex => $subData) {
                        $subcategoryId = (int) ($subData['id'] ?? 0);
                        $subcategorySlug = (string) ($subData['subcategory_slug'] ?? '');

                        $subcategory = null;

                        if ($subcategoryId > 0) {
                            $subcategory = EntityPageSubcategory::where('category_id', $category->id)->find($subcategoryId);
                        }

                        if (!$subcategory && $subcategorySlug !== '') {
                            $subcategory = EntityPageSubcategory::where('category_id', $category->id)
                                ->where('subcategory_slug', $subcategorySlug)
                                ->first();
                        }

                        if (!$subcategory) {
                            $subcategory = new EntityPageSubcategory();
                            $subcategory->category_id = $category->id;
                        }

                        $subcategory->subcategory_name = $subData['subcategory_name'];
                        $subcategory->subcategory_slug = $subcategorySlug;
                        $subcategory->is_menu = (bool) ($subData['is_menu'] ?? false);
                        $subcategory->menu_text = $subcategory->is_menu ? ($subData['menu_text'] ?? null) : null;
                        $subcategory->link_url = $subcategory->is_menu ? ($subData['link_url'] ?? null) : null;
                        $subcategory->menu_order = $subIndex;
                        $subcategory->save();

                        $subData['id'] = $subcategory->id;
                        $catData['subcategories'][$subIndex] = $subData;

                        $submittedSubcategoryIds[] = $subcategory->id;
                    }

                    // Delete removed subcategories
                    $subcategoryDeleteQuery = EntityPageSubcategory::where('category_id', $category->id);
                    if (!empty($submittedSubcategoryIds)) {
                        $subcategoryDeleteQuery->whereNotIn('id', $submittedSubcategoryIds);
                    }
                    $subcategoryDeleteQuery->delete();

                    $updated[] = [
                        'id' => $category->id,
                        'category_name' => $category->category_name,
                        'category_slug' => $category->category_slug,
                        'is_menu' => $category->is_menu,
                        'menu_text' => $category->menu_text,
                        'link_url' => $category->link_url,
                        'menu_order' => $category->menu_order,
                        'subcategories' => EntityPageSubcategory::where('category_id', $category->id)
                            ->orderBy('menu_order')
                            ->get([
                                'id',
                                'subcategory_name',
                                'subcategory_slug',
                                'is_menu',
                                'menu_text',
                                'link_url',
                                'menu_order',
                            ])
                            ->toArray(),
                    ];
                }

                // Delete subcategories that belong to categories removed from this entity
                $removedCategoryIdsQuery = EntityPageCategory::where('entity_id', $entityId);
                if (!empty($existingCategoryIds)) {
                    $removedCategoryIdsQuery->whereNotIn('id', $existingCategoryIds);
                }
                $removedCategoryIds = $removedCategoryIdsQuery->pluck('id');

                if ($removedCategoryIds->isNotEmpty()) {
                    EntityPageSubcategory::whereIn('category_id', $removedCategoryIds)->delete();
                }

                // Delete removed categories
                $categoryDeleteQuery = EntityPageCategory::where('entity_id', $entityId);
                if (!empty($existingCategoryIds)) {
                    $categoryDeleteQuery->whereNotIn('id', $existingCategoryIds);
                }
                $categoryDeleteQuery->delete();
            });
        }
        catch (\Exception $e) {
            \Log::error('Failed to update categories and subcategories', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save menu: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Menu saved successfully.',
            'data' => $updated,
        ]);
    }


}
