<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\PostCategory;
use App\Services\PostMetaService;
use App\Services\PostAttachmentService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    protected $metaService;
    protected $attachmentService;

    public function __construct(PostMetaService $metaService, PostAttachmentService $attachmentService)
    {
        $this->metaService = $metaService;
        $this->attachmentService = $attachmentService;
    }

    /**
     * List posts for the current entity with filters.
     */
    public function index(Request $request)
    {
        $entityId = $request->input('entity_id');
        
        if (!$entityId && $request->user() && $request->user()->current_db_role) {
            $entityId = $request->user()->current_db_role->entity_id;
        }

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity ID is required'], 400);
        }

        $query = Post::with(['postCategory', 'metadata', 'attachments', 'taggedEntities.entity.cachedData'])
            ->where('owner_entity_id', $entityId);

        // Filter by category_id
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by category slug
        if ($request->filled('category_slug')) {
            $query->byCategorySlug($request->category_slug);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('post_status', $request->status);
        }

        // Filter by featured
        if ($request->filled('is_featured')) {
            $query->where('is_featured', filter_var($request->is_featured, FILTER_VALIDATE_BOOLEAN));
        }

        // Search in title, excerpt, content
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('post_title', 'like', "%{$searchTerm}%")
                  ->orWhere('post_excerpt', 'like', "%{$searchTerm}%")
                  ->orWhere('post_content', 'like', "%{$searchTerm}%");
            });
        }

        // Search in metadata
        if ($request->filled('search_meta')) {
            $query->searchMeta($request->search_meta);
        }

        // Filter by metadata key-value
        if ($request->filled('meta_key') && $request->filled('meta_value')) {
            $operator = $request->input('meta_operator', '=');
            $query->withMeta($request->meta_key, $operator, $request->meta_value);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('published_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('published_at', '<=', $request->date_to . ' 23:59:59');
        }

        // Order - Support both 'order_by'/'order_dir' and 'sort'/'direction' parameters
        // Default to updated_at DESC (falls back to created_at) to show recently modified posts first
        $orderBy = $request->input('sort', $request->input('order_by', 'updated_at'));
        $orderDir = $request->input('direction', $request->input('order_dir', 'desc'));
        
        // Validate sort field to prevent SQL injection
        $allowedSortFields = ['post_title', 'published_at', 'updated_at', 'created_at', 'post_status', 'is_featured'];
        if (!in_array($orderBy, $allowedSortFields)) {
            $orderBy = 'updated_at';
        }
        
        // Validate direction
        $orderDir = strtolower($orderDir) === 'asc' ? 'asc' : 'desc';
        
        // Use COALESCE to fall back to created_at if updated_at is null or same as created_at
        if ($orderBy === 'updated_at') {
            $query->orderByRaw("COALESCE(NULLIF(updated_at, created_at), created_at) {$orderDir}");
        } else {
            $query->orderBy($orderBy, $orderDir);
        }

        $posts = $query->paginate($request->input('per_page', 15));
        
        // Add organized metadata to each post
        $posts->getCollection()->transform(function ($post) {
            $organizedMeta = $this->metaService->getOrganizedMetadata($post);
            $post->organized_metadata = $organizedMeta;
            return $post;
        });

        return response()->json(['status' => 'success', 'data' => $posts]);
    }

    /**
     * Get a single post by ID with all relations.
     */
    public function show($id)
    {
        $post = Post::with(['postCategory', 'metadata', 'attachments', 'taggedEntities.entity.cachedData'])
            ->find($id);

        if (!$post) {
            return response()->json(['status' => 'error', 'message' => 'Post not found'], 404);
        }

        // Organize metadata
        $organizedMeta = $this->metaService->getOrganizedMetadata($post);

        $data = $post->toArray();
        $data['metadata_organized'] = $organizedMeta;

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    /**
     * Store a new post with metadata and attachments.
     */
    public function store(Request $request)
    {
        $entityId = $request->input('owner_entity_id');
        
        if (!$entityId && $request->user() && $request->user()->current_db_role) {
            $entityId = $request->user()->current_db_role->entity_id;
        }

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity ID is required'], 400);
        }

        $validated = $request->validate([
            'post_title' => 'required|string|max:255',
            'post_excerpt' => 'nullable|string|max:500',
            'post_content' => 'required|string',
            'category_id' => 'required|exists:post_categories,id',
            'featured_image_uri' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'author' => 'nullable|string|max:240',
            'tags' => 'nullable|string|max:1024',
            'post_status' => 'required|in:Draft,Published,Withdrawn',
            'published_at' => 'nullable|date',
            'meta' => 'nullable|array',
            'attachments.*' => 'nullable|file|max:10240', // 10MB max per file
        ]);

        DB::beginTransaction();
        try {
            // Get category
            $category = PostCategory::findOrFail($validated['category_id']);

            // Validate metadata against schema
            if (isset($validated['meta']) && is_array($validated['meta'])) {
                $validated['meta'] = $this->metaService->validateMetadata($category, $validated['meta']);
            }

            // Validate attachments
            if ($request->hasFile('attachments')) {
                $this->attachmentService->validateAttachments($category, $request->file('attachments'));
            }

            // Create post
            $post = Post::create([
                'owner_entity_id' => $entityId,
                'category_id' => $validated['category_id'],
                'category' => $category->slug, // Legacy field for backward compatibility
                'post_title' => $validated['post_title'],
                'post_excerpt' => $validated['post_excerpt'] ?? null,
                'post_content' => $validated['post_content'],
                'featured_image_uri' => $validated['featured_image_uri'] ?? null,
                'is_featured' => $validated['is_featured'] ?? false,
                'author' => $validated['author'] ?? null,
                'tags' => $validated['tags'] ?? null,
                'post_status' => $validated['post_status'],
                'published_at' => $validated['published_at'] ?? ($validated['post_status'] === 'Published' ? now() : null),
                'content_last_edited_at' => now(),
            ]);

            // Save metadata
            if (isset($validated['meta']) && is_array($validated['meta'])) {
                $this->metaService->saveMetadata($post, $validated['meta'], $category);
            }

            // Handle file uploads
            if ($request->hasFile('attachments')) {
                $this->attachmentService->attachFiles($post, $request->file('attachments'));
            }

            DB::commit();

            $post->load(['postCategory', 'metadata', 'attachments']);

            return response()->json([
                'status' => 'success',
                'message' => 'Post created successfully',
                'data' => $post
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating post', ['exception' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create post: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing post with metadata and attachments.
     */
    public function update(Request $request, $id)
    {
        $entityId = $request->input('owner_entity_id');
        
        if (!$entityId && $request->user() && $request->user()->current_db_role) {
            $entityId = $request->user()->current_db_role->entity_id;
        }

        $post = Post::with(['metadata', 'attachments'])->findOrFail($id);
        
        // Security check
        if ($entityId && $post->owner_entity_id != $entityId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'post_title' => 'required|string|max:255',
            'post_excerpt' => 'nullable|string|max:500',
            'post_content' => 'required|string',
            'category_id' => 'required|exists:post_categories,id',
            'featured_image_uri' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'author' => 'nullable|string|max:240',
            'tags' => 'nullable|string|max:1024',
            'post_status' => 'required|in:Draft,Published,Withdrawn',
            'published_at' => 'nullable|date',
            'meta' => 'nullable|array',
            'attachments.*' => 'nullable|file|max:10240',
            'delete_attachments' => 'nullable|array',
            'delete_attachments.*' => 'integer',
        ]);

        DB::beginTransaction();
        try {
            // Get category
            $category = PostCategory::findOrFail($validated['category_id']);

            // Validate metadata
            if (isset($validated['meta']) && is_array($validated['meta'])) {
                $validated['meta'] = $this->metaService->validateMetadata($category, $validated['meta']);
            }

            // Validate new attachments
            if ($request->hasFile('attachments')) {
                $this->attachmentService->validateAttachments($category, $request->file('attachments'));
            }

            // Update post
            $post->update([
                'category_id' => $validated['category_id'],
                'category' => $category->slug,
                'post_title' => $validated['post_title'],
                'post_excerpt' => $validated['post_excerpt'] ?? null,
                'post_content' => $validated['post_content'],
                'featured_image_uri' => $validated['featured_image_uri'] ?? $post->featured_image_uri,
                'is_featured' => $validated['is_featured'] ?? $post->is_featured,
                'author' => $validated['author'] ?? $post->author,
                'tags' => $validated['tags'] ?? $post->tags,
                'post_status' => $validated['post_status'],
                'published_at' => $validated['published_at'] ?? $post->published_at,
                'content_last_edited_at' => now(),
            ]);

            // Update metadata (use updateOrCreate instead of delete+create to avoid bloat)
            if (isset($validated['meta']) && is_array($validated['meta'])) {
                $this->metaService->saveMetadata($post, $validated['meta'], $category);
                
                // Optionally, remove metadata keys that are no longer in the schema
                $allSchemaKeys = collect($category->all_fields)->pluck('key')->toArray();
                $submittedKeys = array_keys($validated['meta']);
                $keysToKeep = array_intersect($allSchemaKeys, $submittedKeys);
                
                // Delete metadata that's not in the schema and not submitted
                $post->metadata()
                    ->whereNotIn('meta_key', $keysToKeep)
                    ->delete();
            }

            // Delete requested attachments
            if (isset($validated['delete_attachments']) && is_array($validated['delete_attachments'])) {
                $this->attachmentService->deleteAttachments($validated['delete_attachments']);
            }

            // Handle new file uploads
            if ($request->hasFile('attachments')) {
                $this->attachmentService->attachFiles($post, $request->file('attachments'));
            }

            DB::commit();

            $post->load(['postCategory', 'metadata', 'attachments']);

            return response()->json([
                'status' => 'success',
                'message' => 'Post updated successfully',
                'data' => $post
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating post', ['exception' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update post: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a post.
     */
    public function destroy(Request $request, $id)
    {
        $entityId = $request->input('entity_id');
        
        if (!$entityId && $request->user() && $request->user()->current_db_role) {
            $entityId = $request->user()->current_db_role->entity_id;
        }

        $post = Post::findOrFail($id);
        
        // Security check
        if ($entityId && $post->owner_entity_id != $entityId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        try {
            $post->delete(); // Soft delete, cascades to metadata and attachments

            return response()->json([
                'status' => 'success',
                'message' => 'Post deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting post', ['exception' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete post'
            ], 500);
        }
    }

    /**
     * Toggle featured status
     */
    public function toggleFeatured(Request $request, $id)
    {
        $post = Post::findOrFail($id);
        
        $post->is_featured = !$post->is_featured;
        $post->save();

        return response()->json([
            'status' => 'success',
            'message' => $post->is_featured ? 'Post marked as featured' : 'Post removed from featured',
            'data' => ['is_featured' => $post->is_featured]
        ]);
    }

    /**
     * Update post status (simple endpoint for status changes)
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'post_status' => 'required|in:Draft,Published,Withdrawn',
        ]);

        $post = Post::findOrFail($id);
        
        // Security check - ensure user owns this post
        $entityId = null;
        if ($request->user() && $request->user()->current_db_role) {
            $entityId = $request->user()->current_db_role->entity_id;
        }
        
        if ($entityId && $post->owner_entity_id != $entityId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $oldStatus = $post->post_status;
        $post->post_status = $validated['post_status'];
        
        // Set published_at when changing to Published status
        if ($validated['post_status'] === 'Published' && !$post->published_at) {
            $post->published_at = now();
        }
        
        $post->save();

        return response()->json([
            'status' => 'success',
            'message' => "Post status changed from {$oldStatus} to {$validated['post_status']}",
            'data' => [
                'post_status' => $post->post_status,
                'published_at' => $post->published_at
            ]
        ]);
    }
}
