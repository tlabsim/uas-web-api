<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\EntityController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PersonnelController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\ResearchController;
use App\Http\Controllers\SnippetController;

use App\Http\Controllers\Editor\PageController as EditorPageController;
use App\Http\Controllers\Editor\PostController as EditorPostController;
use App\Http\Controllers\Editor\PostCategoryController;
use App\Http\Controllers\Editor\PostTaggedEntityController;
use App\Http\Controllers\Editor\MenuController as EditorMenuController;
use App\Http\Controllers\Editor\SnippetController as EditorSnippetController;
use App\Http\Controllers\Entity\ProfileController as EntityProfileController;
use App\Http\Controllers\Entity\SettingController as EntitySettingController;


Route::middleware([])->group(function () {
    // 🔹 Entities

    Route::get('/', function () {
        return response()->json(['message' => 'Welcome to the NSTU Web API v1']);
    });

    Route::get('entities', [EntityController::class, 'index']);        // Optional filter: ?type=Department
    Route::get('entity_profiles', [EntityController::class, 'indexProfiles']);        // Optional filter: ?type=Department
    Route::get('entity', [EntityController::class, 'show']);           // Input: ?entity_id or ?slug
    Route::get('entity/menus', [EntityController::class, 'menus']);    // Input: ?entity_id
    Route::get('entity/settings', [EntityController::class, 'settings']); // Input: ?entity_id

    // 🔹 Static Pages
    Route::get('pages', [PageController::class, 'index']);             // Input: ?entity_id, optional: category_id, subcategory_id
    Route::get('page', [PageController::class, 'show']);               // Input: ?entity_id, ?page_slug

    // 🔹 Posts
    Route::get('posts', [PostController::class, 'index']);             // Input: ?entity_id, optional filters
    Route::get('post', [PostController::class, 'show']);               // Input: ?id

    // 🔹 Personnel
    Route::get('personnel', [PersonnelController::class, 'show']);     // Input: ?personnel_id

    // 🔹 Publications
    Route::get('publication', [PublicationController::class, 'show']); // Input: ?id

    // 🔹 Research
    Route::get('research', [ResearchController::class, 'show']);       // Input: ?id

    // 🔹 Snippets
    Route::get('snippets', [SnippetController::class, 'index']);       // Input: ?entity_id or ?slug    

    // 🔹 Post Categories (Public read access)
    Route::get('post-categories', [PostCategoryController::class, 'index']);
    Route::get('post-categories/{id}', [PostCategoryController::class, 'show']);
    
});

// Only for web curators
// This route group is protected by the 'ims.logged_in_and_role_selected:web_curator'
// The middleware will inject the entity scope based on the selected role
// 'ims.logged_in_and_role_selected:web_curator'
Route::middleware(['ims.logged_in_and_role_selected:web_curator'])->group(function () {
    // Pages
    Route::get('pages', [EditorPageController::class, 'index']);       // List all pages for editor
    Route::post('pages', [EditorPageController::class, 'store']);
    Route::put('pages/{id}', [EditorPageController::class, 'update']);
    Route::delete('pages/{id}', [EditorPageController::class, 'destroy']);

    // Posts
    Route::get('posts', [EditorPostController::class, 'index']);
    Route::get('posts/{id}', [EditorPostController::class, 'show']);
    Route::post('posts', [EditorPostController::class, 'store']);
    Route::put('posts/{id}', [EditorPostController::class, 'update']);
    Route::delete('posts/{id}', [EditorPostController::class, 'destroy']);
    Route::post('posts/{id}/toggle-featured', [EditorPostController::class, 'toggleFeatured']);
    Route::post('posts/{id}/update-status', [EditorPostController::class, 'updateStatus']);

    // Post Categories (Write operations - require authentication)
    Route::post('post-categories', [PostCategoryController::class, 'store']);
    Route::put('post-categories/{id}', [PostCategoryController::class, 'update']);
    Route::delete('post-categories/{id}', [PostCategoryController::class, 'destroy']);
    Route::post('post-categories/{id}/toggle-active', [PostCategoryController::class, 'toggleActive']);
    Route::post('post-categories/reorder', [PostCategoryController::class, 'reorder']);

    // Post Tagged Entities
    Route::get('post-tagged-entities', [PostTaggedEntityController::class, 'index']);           // Input: ?entity_id, ?status, ?post_id
    Route::post('post-tagged-entities', [PostTaggedEntityController::class, 'store']);
    Route::get('post-tagged-entities/{id}', [PostTaggedEntityController::class, 'show']);
    Route::put('post-tagged-entities/{id}', [PostTaggedEntityController::class, 'update']);     // Update status, approved_by
    Route::delete('post-tagged-entities/{id}', [PostTaggedEntityController::class, 'destroy']);

    // Menus, categories, subcategories
    Route::get('categories', [EditorMenuController::class, 'indexCategories']);       // Input: ?entity_id
    Route::get('subcategories', [EditorMenuController::class, 'indexSubcategories']); // Input: ?entity_id, ?category_id
    Route::post('categories', [EditorMenuController::class, 'createCategory']);
    Route::post('categories-all', [EditorMenuController::class, 'updateCategoriesAll']); //Web Curator can update all categories for the entity at once
    Route::post('subcategories', [EditorMenuController::class, 'createSubcategory']);
    Route::delete('categories/{id}', [EditorMenuController::class, 'destroyCategory']);
    Route::delete('subcategories/{id}', [EditorMenuController::class, 'destroySubcategory']);

    // Snippets
    Route::get('snippets', [EditorSnippetController::class, 'index']);
    Route::post('snippets', [EditorSnippetController::class, 'store']);
    Route::put('snippets/{id}', [EditorSnippetController::class, 'update']);
    Route::delete('snippets/{id}', [EditorSnippetController::class, 'destroy']);

    // Media uploads
    Route::post('media/upload', [\App\Http\Controllers\Api\MediaController::class, 'uploadImage']);
    Route::delete('media/delete', [\App\Http\Controllers\Api\MediaController::class, 'deleteImage']);

    // Entity Profile
    Route::get('entity/profile', [EntityProfileController::class, 'show']);
    Route::put('entity/profile', [EntityProfileController::class, 'update']);

    // Entity Settings
    Route::get('entity/settings', [EntitySettingController::class, 'index']);
    Route::post('entity/settings/create', [EntitySettingController::class, 'store']);
    Route::put('entity/settings/{id}', [EntitySettingController::class, 'updateSingle']);
    Route::delete('entity/settings/{id}', [EntitySettingController::class, 'destroy']);
    Route::put('entity/settings', [EntitySettingController::class, 'update']);
});