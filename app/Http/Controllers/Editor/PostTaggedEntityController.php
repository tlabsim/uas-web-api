<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Models\PostTaggedEntity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PostTaggedEntityController extends Controller
{
    /**
     * Display a listing of tagged entities.
     * Filters by entity_id and optionally by status
     */
    public function index(Request $request)
    {
        $query = PostTaggedEntity::with([
            'entity.cachedData',
            'post.postCategory',
            'post.attachments',
            'post.entity.cachedData',
        ]);

        // Filter by entity_id (required)
        if ($request->has('entity_id')) {
            $query->where('entity_id', $request->input('entity_id'));
        }

        // Filter by status (optional)
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by post_id (optional)
        if ($request->has('post_id')) {
            $query->where('post_id', $request->input('post_id'));
        }

        $taggedEntities = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $taggedEntities,
        ]);
    }

    /**
     * Store a newly created tagged entity.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'post_id' => 'required|integer',
            'entity_id' => 'required|integer',
            'status' => 'nullable|in:Pending,Approved,Denied,Withdrawn',
            'approved_by' => 'nullable|string|max:240',
            'is_featured_override' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if this combination already exists
        $existing = PostTaggedEntity::where('post_id', $request->post_id)
            ->where('entity_id', $request->entity_id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'This entity is already tagged for this post.',
            ], 409);
        }

        $taggedEntity = PostTaggedEntity::create([
            'post_id' => $request->post_id,
            'entity_id' => $request->entity_id,
            'status' => $request->input('status', 'Pending'),
            'approved_by' => $request->approved_by,
            'is_featured_override' => $request->input('is_featured_override'),
        ]);

        return response()->json([
            'success' => true,
            'data' => $taggedEntity,
            'message' => 'Entity tagged successfully.',
        ], 201);
    }

    /**
     * Display the specified tagged entity.
     */
    public function show($id)
    {
        $taggedEntity = PostTaggedEntity::with([
            'entity.cachedData',
            'post.postCategory',
            'post.attachments',
            'post.entity.cachedData',
        ])->find($id);

        if (!$taggedEntity) {
            return response()->json([
                'success' => false,
                'message' => 'Tagged entity not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $taggedEntity,
        ]);
    }

    /**
     * Update the specified tagged entity (mainly for status changes).
     */
    public function update(Request $request, $id)
    {
        $taggedEntity = PostTaggedEntity::find($id);

        if (!$taggedEntity) {
            return response()->json([
                'success' => false,
                'message' => 'Tagged entity not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:Pending,Approved,Denied,Withdrawn',
            'approved_by' => 'nullable|string|max:240',
            'is_featured_override' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $taggedEntity->update($request->only(['status', 'approved_by', 'is_featured_override']));

        return response()->json([
            'success' => true,
            'data' => $taggedEntity,
            'message' => 'Tagged entity updated successfully.',
        ]);
    }

    /**
     * Remove the specified tagged entity.
     */
    public function destroy($id)
    {
        $taggedEntity = PostTaggedEntity::find($id);

        if (!$taggedEntity) {
            return response()->json([
                'success' => false,
                'message' => 'Tagged entity not found.',
            ], 404);
        }

        $taggedEntity->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tagged entity removed successfully.',
        ]);
    }
}
