<?php

namespace App\Services;

use App\Models\MediaFolder;
use App\Models\MediaItem;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaLibraryService
{
    public function upload(
        UploadedFile $file,
        int $entityId,
        array $options = [],
        ?Request $request = null
    ): MediaItem {
        $disk = $options['disk'] ?? 'public';
        $folder = $options['folder'] ?? null;
        $storageContext = $this->sanitizeStorageContext($options['storage_context'] ?? 'uploads');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $storedName = Str::slug($baseName) . '_' . Str::lower(Str::random(12));

        if ($extension) {
            $storedName .= '.' . Str::lower($extension);
        }

        $storagePath = $this->buildStoragePath($entityId, $storageContext, $storedName);
        Storage::disk($disk)->putFileAs(
            dirname($storagePath),
            $file,
            basename($storagePath)
        );

        [$width, $height] = $this->extractDimensions($file);
        $mimeType = $file->getMimeType();
        $publicUrl = Storage::disk($disk)->url($storagePath);
        $actor = $this->resolveActor($request);

        return MediaItem::create([
            'owner_entity_id' => $entityId,
            'folder_id' => $folder?->id,
            'storage_disk' => $disk,
            'storage_path' => $storagePath,
            'storage_context' => $storageContext,
            'file_name' => basename($storagePath),
            'original_name' => $originalName,
            'public_url' => $publicUrl,
            'media_type' => $this->determineMediaType($mimeType),
            'mime_type' => $mimeType,
            'file_size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'title' => $options['title'] ?? $baseName,
            'alt_text' => $options['alt_text'] ?? null,
            'caption' => $options['caption'] ?? null,
            'description' => $options['description'] ?? null,
            'uploaded_by_ims_user_id' => $actor['id'],
            'uploaded_by_name' => $actor['name'],
        ]);
    }

    public function delete(MediaItem $mediaItem): void
    {
        if ($mediaItem->storage_path && Storage::disk($mediaItem->storage_disk)->exists($mediaItem->storage_path)) {
            Storage::disk($mediaItem->storage_disk)->delete($mediaItem->storage_path);
        }

        $mediaItem->delete();
    }

    public function resolveFolderForEntity(int $entityId, ?int $folderId = null): ?MediaFolder
    {
        if (!$folderId) {
            return null;
        }

        return MediaFolder::where('owner_entity_id', $entityId)->find($folderId);
    }

    public function buildFolderTree(int $entityId)
    {
        $folders = MediaFolder::where('owner_entity_id', $entityId)
            ->withCount(['children', 'mediaItems'])
            ->orderBy('sort_order')
            ->orderBy('folder_name')
            ->get();

        $byParent = $folders->groupBy('parent_id');

        $build = function ($parentId) use (&$build, $byParent) {
            return ($byParent[$parentId] ?? collect())->map(function ($folder) use (&$build) {
                $folder->children_tree = $build($folder->id)->values();
                return $folder;
            })->values();
        };

        return [
            'tree' => $build(null),
            'flat' => $folders->values(),
        ];
    }

    public function generateUniqueFolderSlug(int $entityId, string $folderName, ?int $parentId = null, ?int $ignoreId = null): string
    {
        $base = Str::slug($folderName) ?: 'folder';
        $slug = $base;
        $counter = 2;

        while ($this->folderSlugExists($entityId, $slug, $parentId, $ignoreId)) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function generateUniqueGallerySlug(int $entityId, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'gallery';
        $slug = $base;
        $counter = 2;

        while ($this->gallerySlugExists($entityId, $slug, $ignoreId)) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function determineMediaType(?string $mimeType): string
    {
        if (!$mimeType) {
            return 'other';
        }

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (
            str_starts_with($mimeType, 'audio/')
            || in_array($mimeType, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
            ], true)
        ) {
            return 'document';
        }

        return 'other';
    }

    private function extractDimensions(UploadedFile $file): array
    {
        if (!str_starts_with((string) $file->getMimeType(), 'image/')) {
            return [null, null];
        }

        $size = @getimagesize($file->getPathname());

        return [
            $size[0] ?? null,
            $size[1] ?? null,
        ];
    }

    private function resolveActor(?Request $request): array
    {
        $imsUser = $request?->attributes->get('ims_user');

        if (!$imsUser && $request?->cookie('ims_user')) {
            $imsUser = json_decode($request->cookie('ims_user'), true);
        }

        return [
            'id' => is_array($imsUser) ? ($imsUser['id'] ?? null) : null,
            'name' => is_array($imsUser)
                ? ($imsUser['name'] ?? $imsUser['full_name'] ?? $imsUser['email'] ?? null)
                : null,
        ];
    }

    private function folderSlugExists(int $entityId, string $slug, ?int $parentId = null, ?int $ignoreId = null): bool
    {
        return MediaFolder::where('owner_entity_id', $entityId)
            ->where('parent_id', $parentId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    private function gallerySlugExists(int $entityId, string $slug, ?int $ignoreId = null): bool
    {
        return \App\Models\Gallery::where('owner_entity_id', $entityId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    private function sanitizeStorageContext(string $context): string
    {
        $segments = collect(explode('/', str_replace('\\', '/', $context)))
            ->filter()
            ->map(fn ($segment) => Str::slug($segment))
            ->filter()
            ->values();

        return $segments->isNotEmpty() ? $segments->implode('/') : 'uploads';
    }

    private function buildStoragePath(int $entityId, string $context, string $fileName): string
    {
        return "media/entity-{$entityId}/{$context}/" . now()->format('Y/m') . "/{$fileName}";
    }
}
