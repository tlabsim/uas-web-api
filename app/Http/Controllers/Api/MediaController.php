<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaItem;
use App\Services\MediaLibraryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    private MediaLibraryService $mediaLibraryService;

    public function __construct(MediaLibraryService $mediaLibraryService)
    {
        $this->mediaLibraryService = $mediaLibraryService;
    }

    /**
     * Upload an image and return the public URL
     */
    public function uploadImage(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp,svg|max:5120', // 5MB max
            'folder' => 'string|nullable', // Optional: pages, posts, profiles, etc.
            'folder_id' => 'nullable|integer',
            'title' => 'nullable|string|max:255',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
            'description' => 'nullable|string',
        ]);

        try {
            $entityId = $request->attributes->get('current_role_scope') ?? $request->input('entity_id');

            if (!$entityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Entity scope not found for media upload',
                ], 403);
            }

            $folderModel = $this->mediaLibraryService->resolveFolderForEntity((int) $entityId, $validated['folder_id'] ?? null);

            if (($validated['folder_id'] ?? null) && !$folderModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected folder does not belong to the current entity',
                ], 422);
            }

            $mediaItem = $this->mediaLibraryService->upload(
                $request->file('image'),
                (int) $entityId,
                [
                    'folder' => $folderModel,
                    'storage_context' => $validated['folder'] ?? 'uploads',
                    'title' => $validated['title'] ?? null,
                    'alt_text' => $validated['alt_text'] ?? null,
                    'caption' => $validated['caption'] ?? null,
                    'description' => $validated['description'] ?? null,
                ],
                $request
            );

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'id' => $mediaItem->id,
                    'filename' => $mediaItem->file_name,
                    'path' => $mediaItem->storage_path,
                    'url' => $mediaItem->public_url,
                    'full_url' => $mediaItem->full_url,
                    'thumbnail_url' => $mediaItem->thumbnail_url,
                    'thumbnail_full_url' => $mediaItem->thumbnail_full_url,
                    'size' => $mediaItem->file_size,
                    'mime_type' => $mediaItem->mime_type,
                    'media_item' => $mediaItem->load('folder'),
                ]
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an uploaded image
     */
    public function deleteImage(Request $request)
    {
        $validated = $request->validate([
            'path' => 'nullable|string',
            'media_item_id' => 'nullable|integer',
        ]);

        try {
            $entityId = $request->attributes->get('current_role_scope') ?? $request->input('entity_id');

            $mediaItem = null;
            if (!empty($validated['media_item_id']) && $entityId) {
                $mediaItem = MediaItem::where('owner_entity_id', $entityId)->find($validated['media_item_id']);
            } elseif (!empty($validated['path']) && $entityId) {
                $mediaItem = MediaItem::where('owner_entity_id', $entityId)
                    ->where('storage_path', $validated['path'])
                    ->first();
            }

            if ($mediaItem) {
                $this->mediaLibraryService->delete($mediaItem);
                return response()->json([
                    'success' => true,
                    'message' => 'Image deleted successfully'
                ]);
            }

            $path = $validated['path'] ?? null;

            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);

                return response()->json([
                    'success' => true,
                    'message' => 'Image deleted successfully'
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Image not found'
            ], 404);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
