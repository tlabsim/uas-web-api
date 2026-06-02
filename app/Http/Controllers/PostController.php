<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;

class PostController extends Controller
{
    /**
     * List published posts for a given entity.
     * Optional filters: category, tag, limit, search
     * Example: GET /api/v1/posts?entity_id=5&category=Notice
     */
    public function index(Request $request)
    {
        if (!$request->filled('entity_id')) {
            return response()->json(['status' => 'error', 'message' => 'Missing entity_id'], 400);
        }

        $entityId = $request->entity_id;

        $query = Post::where('post_status', 'Published')
            ->where(function ($q) use ($entityId) {
                $q->where('owner_entity_id', $entityId)
                    ->orWhereHas('taggedEntities', function ($taggedQuery) use ($entityId) {
                        $taggedQuery->where('entity_id', $entityId)
                            ->where('status', 'Approved');
                    });
            })
            ->orderByDesc('published_at');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('tag')) {
            $query->where('tags', 'like', '%' . $request->tag . '%');
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('post_title', 'like', '%' . $request->search . '%')
                  ->orWhere('post_excerpt', 'like', '%' . $request->search . '%');
            });
        }

        $limit = $request->input('limit', 10);

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate($limit)
        ]);
    }

    /**
     * Show a single post by ID.
     * Example: GET /api/v1/post?id=123
     */
    public function show(Request $request)
    {
        if (!$request->filled('id')) {
            return response()->json(['status' => 'error', 'message' => 'Missing post id'], 400);
        }

        $post = Post::with(['attachments', 'meta', 'taggedEntities'])
            ->where('id', $request->id)
            ->where('post_status', 'Published')
            ->first();

        if (!$post) {
            return response()->json(['status' => 'error', 'message' => 'Post not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $post]);
    }
}
