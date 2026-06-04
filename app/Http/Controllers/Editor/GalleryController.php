<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\MediaItem;
use App\Services\MediaLibraryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GalleryController extends Controller
{
    private MediaLibraryService $mediaLibraryService;

    public function __construct(MediaLibraryService $mediaLibraryService)
    {
        $this->mediaLibraryService = $mediaLibraryService;
    }

    public function index(Request $request)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $query = Gallery::with(['coverMediaItem'])
            ->withCount('items')
            ->where('owner_entity_id', $entityId);

        if ($request->filled('status')) {
            $query->where('gallery_status', $request->input('status'));
        }

        if ($request->filled('is_featured')) {
            $query->where('is_featured', filter_var($request->input('is_featured'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $query->orderByRaw("COALESCE(NULLIF(updated_at, created_at), created_at) DESC");

        if ($request->boolean('fetch_all')) {
            return response()->json(['status' => 'success', 'data' => $query->get()]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate($request->input('per_page', 15)),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $gallery = Gallery::with([
            'coverMediaItem',
            'items.mediaItem',
        ])
            ->where('owner_entity_id', $entityId)
            ->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $gallery]);
    }

    public function store(Request $request)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'cover_media_item_id' => 'nullable|integer',
            'gallery_status' => 'required|in:Draft,Published,Withdrawn',
            'is_featured' => 'nullable|boolean',
            'author' => 'nullable|string|max:240',
            'published_at' => 'nullable|date',
            'items' => 'nullable|array',
            'items.*.media_item_id' => 'required|integer',
            'items.*.caption_override' => 'nullable|string|max:500',
            'items.*.alt_override' => 'nullable|string|max:255',
            'items.*.sort_order' => 'nullable|integer|min:0',
        ]);

        $coverMediaItem = $this->resolveMediaItemForEntity((int) $entityId, $validated['cover_media_item_id'] ?? null);

        if (($validated['cover_media_item_id'] ?? null) && !$coverMediaItem) {
            return response()->json([
                'status' => 'error',
                'message' => 'Selected cover media item does not belong to the current entity.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $gallery = Gallery::create([
                'owner_entity_id' => $entityId,
                'title' => $validated['title'],
                'slug' => $this->mediaLibraryService->generateUniqueGallerySlug((int) $entityId, $validated['slug'] ?? $validated['title']),
                'excerpt' => $validated['excerpt'] ?? null,
                'description' => $validated['description'] ?? null,
                'cover_media_item_id' => $coverMediaItem?->id,
                'gallery_status' => $validated['gallery_status'],
                'is_featured' => $validated['is_featured'] ?? false,
                'author' => $validated['author'] ?? null,
                'published_at' => $validated['published_at'] ?? ($validated['gallery_status'] === 'Published' ? now() : null),
                'content_last_edited_at' => now(),
            ]);

            if (!empty($validated['items'])) {
                $this->attachItems($gallery, $validated['items'], (int) $entityId);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Gallery created successfully',
                'data' => $gallery->load(['coverMediaItem', 'items.mediaItem']),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function update(Request $request, int $id)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $gallery = Gallery::where('owner_entity_id', $entityId)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'cover_media_item_id' => 'nullable|integer',
            'gallery_status' => 'required|in:Draft,Published,Withdrawn',
            'is_featured' => 'nullable|boolean',
            'author' => 'nullable|string|max:240',
            'published_at' => 'nullable|date',
        ]);

        $coverMediaItem = $this->resolveMediaItemForEntity((int) $entityId, $validated['cover_media_item_id'] ?? null);

        if (($validated['cover_media_item_id'] ?? null) && !$coverMediaItem) {
            return response()->json([
                'status' => 'error',
                'message' => 'Selected cover media item does not belong to the current entity.',
            ], 422);
        }

        $updateData = [
            'title' => $validated['title'],
            'slug' => $this->mediaLibraryService->generateUniqueGallerySlug((int) $entityId, $validated['slug'] ?? $validated['title'], $gallery->id),
            'gallery_status' => $validated['gallery_status'],
            'content_last_edited_at' => now(),
        ];

        foreach (['excerpt', 'description', 'author'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updateData[$field] = $validated[$field];
            }
        }

        if (array_key_exists('cover_media_item_id', $validated)) {
            $updateData['cover_media_item_id'] = $coverMediaItem?->id;
        }

        if (array_key_exists('is_featured', $validated)) {
            $updateData['is_featured'] = $validated['is_featured'];
        }

        if (array_key_exists('published_at', $validated)) {
            $updateData['published_at'] = $validated['published_at'];
        } elseif ($validated['gallery_status'] === 'Published') {
            $updateData['published_at'] = $gallery->published_at ?? now();
        } elseif ($validated['gallery_status'] !== 'Published') {
            $updateData['published_at'] = null;
        }

        $gallery->update($updateData);

        return response()->json([
            'status' => 'success',
            'message' => 'Gallery updated successfully',
            'data' => $gallery->fresh(['coverMediaItem', 'items.mediaItem']),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $gallery = Gallery::where('owner_entity_id', $entityId)->findOrFail($id);
        $gallery->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Gallery deleted successfully',
        ]);
    }

    public function addItems(Request $request, int $galleryId)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $gallery = Gallery::where('owner_entity_id', $entityId)->findOrFail($galleryId);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.media_item_id' => 'required|integer',
            'items.*.caption_override' => 'nullable|string|max:500',
            'items.*.alt_override' => 'nullable|string|max:255',
            'items.*.sort_order' => 'nullable|integer|min:0',
        ]);

        DB::beginTransaction();

        try {
            $this->attachItems($gallery, $validated['items'], (int) $entityId);
            $gallery->update(['content_last_edited_at' => now()]);
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Gallery items updated successfully',
                'data' => $gallery->fresh(['coverMediaItem', 'items.mediaItem']),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateItem(Request $request, int $galleryId, int $itemId)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $gallery = Gallery::where('owner_entity_id', $entityId)->findOrFail($galleryId);
        $galleryItem = $gallery->items()->findOrFail($itemId);

        $validated = $request->validate([
            'caption_override' => 'nullable|string|max:500',
            'alt_override' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $galleryItem->update($validated);
        $gallery->update(['content_last_edited_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'Gallery item updated successfully',
            'data' => $galleryItem->fresh(['mediaItem']),
        ]);
    }

    public function reorderItems(Request $request, int $galleryId)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $gallery = Gallery::where('owner_entity_id', $entityId)->findOrFail($galleryId);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            $gallery->items()->where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        $gallery->update(['content_last_edited_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'Gallery items reordered successfully',
        ]);
    }

    public function removeItem(Request $request, int $galleryId, int $itemId)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $gallery = Gallery::where('owner_entity_id', $entityId)->findOrFail($galleryId);
        $galleryItem = $gallery->items()->findOrFail($itemId);

        $galleryItem->delete();
        $gallery->update(['content_last_edited_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'Gallery item removed successfully',
        ]);
    }

    private function attachItems(Gallery $gallery, array $items, int $entityId): void
    {
        $maxSortOrder = (int) ($gallery->items()->max('sort_order') ?? -1);

        foreach ($items as $item) {
            $mediaItem = $this->resolveMediaItemForEntity($entityId, $item['media_item_id']);

            if (!$mediaItem) {
                throw ValidationException::withMessages([
                    'items' => ["Media item {$item['media_item_id']} does not belong to the current entity."],
                ]);
            }

            if (!in_array($mediaItem->media_type, ['image', 'video'], true)) {
                throw ValidationException::withMessages([
                    'items' => ["Only image and video media can be added to galleries. Media item {$item['media_item_id']} is not eligible."],
                ]);
            }

            $sortOrder = array_key_exists('sort_order', $item)
                ? $item['sort_order']
                : ++$maxSortOrder;

            GalleryItem::updateOrCreate(
                [
                    'gallery_id' => $gallery->id,
                    'media_item_id' => $mediaItem->id,
                ],
                [
                    'caption_override' => $item['caption_override'] ?? null,
                    'alt_override' => $item['alt_override'] ?? null,
                    'sort_order' => $sortOrder,
                ]
            );
        }
    }

    private function resolveMediaItemForEntity(int $entityId, ?int $mediaItemId): ?MediaItem
    {
        if (!$mediaItemId) {
            return null;
        }

        return MediaItem::where('owner_entity_id', $entityId)->find($mediaItemId);
    }
}
