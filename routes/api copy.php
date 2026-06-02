<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\EntityController;
use App\Http\Controllers\V1\PageController;
use App\Http\Controllers\V1\PostController;
use App\Http\Controllers\V1\PersonnelController;
use App\Http\Controllers\V1\PublicationController;
use App\Http\Controllers\V1\ResearchController;
use App\Http\Controllers\V1\SnippetController;

use App\Http\Controllers\V1\Editor\PageController as EditorPageController;
use App\Http\Controllers\V1\Editor\PostController as EditorPostController;
use App\Http\Controllers\V1\Editor\MenuController as EditorMenuController;
use App\Http\Controllers\V1\Editor\SnippetController as EditorSnippetController;

Route::get('/test', function () {
    return response()->json(['message' => 'This is a test route']);
});

Route::middleware([])->prefix('v1')->group(function () {
    // 🔹 Entities

    Route::get('/', function () {
        return response()->json(['message' => 'Welcome to the NSTU Web API v1']);
    });

    Route::get('/entites', [EntityController::class, 'index']);        // Optional filter: ?type=Department
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
});

// Only for web curators
// This route group is protected by the 'ims.logged_in_and_role_selected:web_curator'
// The middleware will inject the entity scope based on the selected role
Route::middleware('ims.logged_in_and_role_selected:web_curator')->prefix('v1')->group(function () {
    // Pages
    Route::post('pages', [EditorPageController::class, 'store']);
    Route::put('pages/{id}', [EditorPageController::class, 'update']);
    Route::delete('pages/{id}', [EditorPageController::class, 'destroy']);

    // Posts
    Route::post('posts', [EditorPostController::class, 'store']);
    Route::put('posts/{id}', [EditorPostController::class, 'update']);
    Route::delete('posts/{id}', [EditorPostController::class, 'destroy']);

    // Menus
    Route::get('menus', [EditorMenuController::class, 'index']); // Gets categories + subcategories
    Route::post('categories', [EditorMenuController::class, 'createCategory']);
    Route::post('subcategories', [EditorMenuController::class, 'createSubcategory']);
    Route::delete('categories/{id}', [EditorMenuController::class, 'destroyCategory']);
    Route::delete('subcategories/{id}', [EditorMenuController::class, 'destroySubcategory']);

    // Snippets
    Route::get('snippets', [EditorSnippetController::class, 'index']);
    Route::post('snippets', [EditorSnippetController::class, 'store']);
    Route::put('snippets/{id}', [EditorSnippetController::class, 'update']);
    Route::delete('snippets/{id}', [EditorSnippetController::class, 'destroy']);
});