<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Models\MediaFolder;
use App\Services\MediaLibraryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MediaFolderController extends Controller
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

        return response()->json([
            'status' => 'success',
            'data' => $this->mediaLibraryService->buildFolderTree((int) $entityId),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $entityId = $request->attributes->get('current_role_scope');
        $folder = MediaFolder::where('owner_entity_id', $entityId)
            ->withCount(['children', 'mediaItems'])
            ->with('parent')
            ->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $folder]);
    }

    public function store(Request $request)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $validated = $request->validate([
            'folder_name' => 'required|string|max:150',
            'parent_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'slug' => 'nullable|string|max:180',
        ]);

        $parent = $this->resolveParentFolder((int) $entityId, $validated['parent_id'] ?? null);
        $slug = $this->mediaLibraryService->generateUniqueFolderSlug(
            (int) $entityId,
            $validated['slug'] ?? $validated['folder_name'],
            $parent?->id
        );

        $folder = MediaFolder::create([
            'owner_entity_id' => $entityId,
            'parent_id' => $parent?->id,
            'folder_name' => $validated['folder_name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Folder created successfully',
            'data' => $folder,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $entityId = $request->attributes->get('current_role_scope');
        $folder = MediaFolder::where('owner_entity_id', $entityId)->findOrFail($id);

        $validated = $request->validate([
            'folder_name' => 'required|string|max:150',
            'parent_id' => [
                'nullable',
                'integer',
                Rule::notIn([$folder->id]),
            ],
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'slug' => 'nullable|string|max:180',
        ]);

        $parent = $this->resolveParentFolder((int) $entityId, $validated['parent_id'] ?? null, $folder->id);
        $slug = $this->mediaLibraryService->generateUniqueFolderSlug(
            (int) $entityId,
            $validated['slug'] ?? $validated['folder_name'],
            $parent?->id,
            $folder->id
        );

        $folder->update([
            'parent_id' => $parent?->id,
            'folder_name' => $validated['folder_name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $folder->sort_order,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Folder updated successfully',
            'data' => $folder->fresh(['parent']),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $entityId = $request->attributes->get('current_role_scope');
        $folder = MediaFolder::where('owner_entity_id', $entityId)->findOrFail($id);

        $validated = $request->validate([
            'content_strategy' => 'nullable|in:keep,delete',
        ]);

        $strategy = $validated['content_strategy'] ?? 'keep';

        DB::beginTransaction();

        try {
            if ($strategy === 'delete') {
                $this->mediaLibraryService->deleteFolderContents($folder);
            } else {
                $folder->children()->update(['parent_id' => null]);
                $folder->mediaItems()->update(['folder_id' => null]);
            }

            $folder->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Folder deleted successfully',
        ]);
    }

    public function reorder(Request $request)
    {
        $entityId = $request->attributes->get('current_role_scope');

        if (!$entityId) {
            return response()->json(['status' => 'error', 'message' => 'Entity scope not found'], 403);
        }

        $validated = $request->validate([
            'folders' => 'required|array|min:1',
            'folders.*.id' => 'required|integer',
            'folders.*.parent_id' => 'nullable|integer',
            'folders.*.sort_order' => 'required|integer|min:0',
        ]);

        $folderMap = MediaFolder::where('owner_entity_id', $entityId)
            ->whereIn('id', collect($validated['folders'])->pluck('id'))
            ->get()
            ->keyBy('id');

        if ($folderMap->count() !== count($validated['folders'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'One or more folders do not belong to the current entity.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            foreach ($validated['folders'] as $payload) {
                /** @var MediaFolder $folder */
                $folder = $folderMap->get($payload['id']);
                $parent = $this->resolveParentFolder((int) $entityId, $payload['parent_id'] ?? null, $folder->id);

                $folder->update([
                    'parent_id' => $parent?->id,
                    'sort_order' => $payload['sort_order'],
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Folders reordered successfully',
            'data' => $this->mediaLibraryService->buildFolderTree((int) $entityId),
        ]);
    }

    private function resolveParentFolder(int $entityId, ?int $parentId, ?int $currentFolderId = null): ?MediaFolder
    {
        if (!$parentId) {
            return null;
        }

        $parent = MediaFolder::where('owner_entity_id', $entityId)->find($parentId);

        if (!$parent) {
            throw ValidationException::withMessages([
                'parent_id' => ['Selected parent folder does not belong to the current entity.'],
            ]);
        }

        if ($currentFolderId && $this->isDescendantOf($parent, $currentFolderId)) {
            throw ValidationException::withMessages([
                'parent_id' => ['A folder cannot be moved under one of its descendants.'],
            ]);
        }

        return $parent;
    }

    private function isDescendantOf(MediaFolder $folder, int $targetFolderId): bool
    {
        $current = $folder;

        while ($current) {
            if ($current->id === $targetFolderId) {
                return true;
            }

            $current = $current->parent;
        }

        return false;
    }
}
