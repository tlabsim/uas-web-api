<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Models\MediaItem;
use App\Services\MediaLibraryService;
use Illuminate\Http\Request;

class MediaItemController extends Controller
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

        $query = MediaItem::with(['folder'])
            ->withCount('galleryItems')
            ->where('owner_entity_id', $entityId);

        if ($request->filled('folder_id')) {
            $query->where('folder_id', $request->integer('folder_id'));
        }

        if ($request->boolean('root_only')) {
            $query->whereNull('folder_id');
        }

        if ($request->filled('media_type')) {
            $query->where('media_type', $request->input('media_type'));
        }

        if ($request->boolean('unused_only')) {
            $query->doesntHave('galleryItems');
        }

        if ($request->boolean('used_in_galleries_only')) {
            $query->has('galleryItems');
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('original_name', 'like', "%{$search}%")
                    ->orWhere('caption', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $query->orderByDesc('created_at');

        if ($request->boolean('fetch_all')) {
            return response()->json([
                'status' => 'success',
                'data' => $query->get(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate($request->input('per_page', 24)),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $mediaItem = MediaItem::with(['folder', 'galleryItems.gallery'])
            ->where('owner_entity_id', $entityId)
            ->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $mediaItem]);
    }

    public function upload(Request $request)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $validated = $request->validate([
            'file' => 'required|file|max:20480',
            'folder_id' => 'nullable|integer',
            'storage_context' => 'nullable|string|max:80',
            'title' => 'nullable|string|max:255',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
            'description' => 'nullable|string',
        ]);

        $folder = $this->mediaLibraryService->resolveFolderForEntity((int) $entityId, $validated['folder_id'] ?? null);

        if (($validated['folder_id'] ?? null) && !$folder) {
            return response()->json([
                'status' => 'error',
                'message' => 'Selected folder does not belong to the current entity.',
            ], 422);
        }

        $mediaItem = $this->mediaLibraryService->upload(
            $request->file('file'),
            (int) $entityId,
            [
                'folder' => $folder,
                'storage_context' => $validated['storage_context'] ?? 'gallery',
                'title' => $validated['title'] ?? null,
                'alt_text' => $validated['alt_text'] ?? null,
                'caption' => $validated['caption'] ?? null,
                'description' => $validated['description'] ?? null,
            ],
            $request
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Media uploaded successfully',
            'data' => $mediaItem->load('folder'),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $mediaItem = MediaItem::where('owner_entity_id', $entityId)->findOrFail($id);

        $validated = $request->validate([
            'folder_id' => 'nullable|integer',
            'title' => 'nullable|string|max:255',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
            'description' => 'nullable|string',
        ]);

        $folder = $this->mediaLibraryService->resolveFolderForEntity((int) $entityId, $validated['folder_id'] ?? null);

        if (($validated['folder_id'] ?? null) && !$folder) {
            return response()->json([
                'status' => 'error',
                'message' => 'Selected folder does not belong to the current entity.',
            ], 422);
        }

        $updateData = [];

        if (array_key_exists('folder_id', $validated)) {
            $updateData['folder_id'] = $folder?->id;
        }

        foreach (['title', 'alt_text', 'caption', 'description'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updateData[$field] = $validated[$field];
            }
        }

        $mediaItem->update($updateData);

        return response()->json([
            'status' => 'success',
            'message' => 'Media updated successfully',
            'data' => $mediaItem->fresh(['folder']),
        ]);
    }

    public function move(Request $request, int $id)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $mediaItem = MediaItem::where('owner_entity_id', $entityId)->findOrFail($id);

        $validated = $request->validate([
            'folder_id' => 'nullable|integer',
        ]);

        $folder = $this->mediaLibraryService->resolveFolderForEntity((int) $entityId, $validated['folder_id'] ?? null);

        if (($validated['folder_id'] ?? null) && !$folder) {
            return response()->json([
                'status' => 'error',
                'message' => 'Selected folder does not belong to the current entity.',
            ], 422);
        }

        $mediaItem->update(['folder_id' => $folder?->id]);

        return response()->json([
            'status' => 'success',
            'message' => 'Media moved successfully',
            'data' => $mediaItem->fresh(['folder']),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $mediaItem = MediaItem::where('owner_entity_id', $entityId)->findOrFail($id);

        $this->mediaLibraryService->delete($mediaItem);

        return response()->json([
            'status' => 'success',
            'message' => 'Media deleted successfully',
        ]);
    }
}
