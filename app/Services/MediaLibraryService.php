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
    private const THUMBNAIL_MAX_DIMENSION = 480;
    private const THUMBNAIL_JPEG_QUALITY = 82;

    public function upload(
        UploadedFile $file,
        int $entityId,
        array $options = [],
        ?Request $request = null
    ): MediaItem {
        $disk = $options['disk'] ?? 'public';
        $folder = $options['folder'] ?? null;
        $storageContext = $this->sanitizeStorageContext($options['storage_context'] ?? 'uploads');
        $originalName = $this->sanitizeOriginalFileName($file->getClientOriginalName());
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $periodKey = now()->format('Ym');
        $storageBucket = $this->generateOpaqueBucket(
            sprintf('media|entity:%s|context:%s|period:%s', $entityId, $storageContext, $periodKey)
        );
        $storageSuffixKey = $this->generateUniqueStorageSuffixKey(MediaItem::class);
        $storedName = $this->buildStorageFileName($originalName, $storageSuffixKey);
        $storagePath = $this->buildStoragePath($entityId, $periodKey, $storageBucket, $storedName);
        Storage::disk($disk)->putFileAs(
            dirname($storagePath),
            $file,
            basename($storagePath)
        );

        [$width, $height] = $this->extractDimensions($file);
        $mimeType = $file->getMimeType();
        $publicUrl = Storage::disk($disk)->url($storagePath);
        $thumbnail = $this->generateThumbnailFromUploadedFile(
            $file,
            $disk,
            $storagePath,
            $mimeType
        );
        $actor = $this->resolveActor($request);

        $mediaItem = MediaItem::create([
            'owner_entity_id' => $entityId,
            'public_key' => $this->generateUniquePublicKey(),
            'folder_id' => $folder?->id,
            'storage_disk' => $disk,
            'storage_bucket' => $storageBucket,
            'storage_suffix_key' => $storageSuffixKey,
            'storage_path' => $storagePath,
            'storage_context' => $storageContext,
            'file_name' => basename($storagePath),
            'original_name' => $originalName,
            'public_url' => $publicUrl,
            'thumbnail_path' => $thumbnail['path'] ?? null,
            'thumbnail_url' => $thumbnail['url'] ?? null,
            'media_type' => $this->determineMediaType($mimeType),
            'mime_type' => $mimeType,
            'file_size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'thumbnail_width' => $thumbnail['width'] ?? null,
            'thumbnail_height' => $thumbnail['height'] ?? null,
            'title' => $options['title'] ?? $baseName,
            'alt_text' => $options['alt_text'] ?? null,
            'caption' => $options['caption'] ?? null,
            'description' => $options['description'] ?? null,
            'uploaded_by_ims_user_id' => $actor['id'],
            'uploaded_by_name' => $actor['name'],
        ]);

        return $mediaItem;
    }

    public function delete(MediaItem $mediaItem): void
    {
        if ($mediaItem->storage_path && Storage::disk($mediaItem->storage_disk)->exists($mediaItem->storage_path)) {
            Storage::disk($mediaItem->storage_disk)->delete($mediaItem->storage_path);
        }

        if ($mediaItem->thumbnail_path && Storage::disk($mediaItem->storage_disk)->exists($mediaItem->thumbnail_path)) {
            Storage::disk($mediaItem->storage_disk)->delete($mediaItem->thumbnail_path);
        }

        $mediaItem->delete();
    }

    public function ensureThumbnail(MediaItem $mediaItem): MediaItem
    {
        if (
            $mediaItem->media_type !== 'image'
            || $mediaItem->thumbnail_path
            || !extension_loaded('gd')
            || !$mediaItem->storage_path
        ) {
            return $mediaItem;
        }

        $disk = $mediaItem->storage_disk ?: 'public';

        if (!Storage::disk($disk)->exists($mediaItem->storage_path)) {
            return $mediaItem;
        }

        $sourcePath = $this->safeDiskPath($disk, $mediaItem->storage_path);
        if (!$sourcePath) {
            return $mediaItem;
        }

        $thumbnail = $this->generateThumbnailFromSourcePath(
            $sourcePath,
            $disk,
            $mediaItem->storage_path,
            $mediaItem->mime_type
        );

        if (!$thumbnail) {
            return $mediaItem;
        }

        $mediaItem->forceFill([
            'thumbnail_path' => $thumbnail['path'],
            'thumbnail_url' => $thumbnail['url'],
            'thumbnail_width' => $thumbnail['width'],
            'thumbnail_height' => $thumbnail['height'],
        ])->save();

        return $mediaItem->refresh();
    }

    public function resolveFolderForEntity(int $entityId, ?int $folderId = null): ?MediaFolder
    {
        if (!$folderId) {
            return null;
        }

        return MediaFolder::where('owner_entity_id', $entityId)->find($folderId);
    }

    public function deleteFolderContents(MediaFolder $folder): void
    {
        $folder->loadMissing(['children', 'mediaItems']);

        foreach ($folder->children as $childFolder) {
            $this->deleteFolderContents($childFolder);
            $childFolder->delete();
        }

        foreach ($folder->mediaItems as $mediaItem) {
            $this->delete($mediaItem);
        }
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
        $this->releaseDeletedFolderSlugConflicts($entityId, $base, $parentId, $ignoreId);
        $slug = $base;
        $counter = 2;

        while ($this->folderSlugExists($entityId, $slug, $parentId, $ignoreId)) {
            $this->releaseDeletedFolderSlugConflicts($entityId, $slug, $parentId, $ignoreId);
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function generateUniqueGallerySlug(int $entityId, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'gallery';
        $this->releaseDeletedGallerySlugConflicts($entityId, $base, $ignoreId);
        $slug = $base;
        $counter = 2;

        while ($this->gallerySlugExists($entityId, $slug, $ignoreId)) {
            $this->releaseDeletedGallerySlugConflicts($entityId, $slug, $ignoreId);
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

    private function releaseDeletedFolderSlugConflicts(int $entityId, string $slug, ?int $parentId = null, ?int $ignoreId = null): void
    {
        MediaFolder::withTrashed()
            ->onlyTrashed()
            ->where('owner_entity_id', $entityId)
            ->where('parent_id', $parentId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->get()
            ->each(fn (MediaFolder $folder) => $this->archiveFolderSlug($folder));
    }

    private function releaseDeletedGallerySlugConflicts(int $entityId, string $slug, ?int $ignoreId = null): void
    {
        \App\Models\Gallery::withTrashed()
            ->onlyTrashed()
            ->where('owner_entity_id', $entityId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->get()
            ->each(fn (\App\Models\Gallery $gallery) => $this->archiveGallerySlug($gallery));
    }

    private function archiveFolderSlug(MediaFolder $folder): void
    {
        $suffix = '--deleted-' . $folder->id;
        $maxLength = max(1, 180 - strlen($suffix));
        $baseSlug = mb_substr($folder->slug ?: 'folder', 0, $maxLength);
        $archivedSlug = $baseSlug . $suffix;

        if ($folder->slug === $archivedSlug) {
            return;
        }

        MediaFolder::withTrashed()
            ->whereKey($folder->id)
            ->update(['slug' => $archivedSlug]);
    }

    private function archiveGallerySlug(\App\Models\Gallery $gallery): void
    {
        $suffix = '--deleted-' . $gallery->id;
        $maxLength = max(1, 180 - strlen($suffix));
        $baseSlug = mb_substr($gallery->slug ?: 'gallery', 0, $maxLength);
        $archivedSlug = $baseSlug . $suffix;

        if ($gallery->slug === $archivedSlug) {
            return;
        }

        \App\Models\Gallery::withTrashed()
            ->whereKey($gallery->id)
            ->update(['slug' => $archivedSlug]);
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

    private function buildStoragePath(int $entityId, string $periodKey, string $bucket, string $fileName): string
    {
        return sprintf('media/e%d/%s/%s/%s', $entityId, $periodKey, $bucket, $fileName);
    }

    private function buildThumbnailPath(string $storagePath, string $extension): string
    {
        $baseName = pathinfo($storagePath, PATHINFO_FILENAME);
        $sanitizedBase = Str::slug($baseName) ?: 'media';

        return dirname($storagePath) . "/thumbnails/{$sanitizedBase}_thumb.{$extension}";
    }

    private function generateThumbnailFromUploadedFile(
        UploadedFile $file,
        string $disk,
        string $storagePath,
        ?string $mimeType
    ): ?array {
        return $this->generateThumbnailFromSourcePath(
            $file->getPathname(),
            $disk,
            $storagePath,
            $mimeType
        );
    }

    private function generateThumbnailFromSourcePath(
        string $sourcePath,
        string $disk,
        string $storagePath,
        ?string $mimeType
    ): ?array {
        if (!$this->canGenerateThumbnail($sourcePath, $mimeType)) {
            return null;
        }

        $rendered = $this->renderThumbnailBinary($sourcePath, (string) $mimeType);
        if (!$rendered) {
            return null;
        }

        $thumbnailPath = $this->buildThumbnailPath(
            $storagePath,
            $rendered['extension']
        );

        Storage::disk($disk)->put($thumbnailPath, $rendered['binary']);

        return [
            'path' => $thumbnailPath,
            'url' => Storage::disk($disk)->url($thumbnailPath),
            'width' => $rendered['width'],
            'height' => $rendered['height'],
        ];
    }

    private function canGenerateThumbnail(string $sourcePath, ?string $mimeType): bool
    {
        if (!extension_loaded('gd') || !$mimeType || !str_starts_with($mimeType, 'image/')) {
            return false;
        }

        if (in_array($mimeType, ['image/svg+xml', 'image/svg'], true)) {
            return false;
        }

        return is_file($sourcePath) && is_readable($sourcePath);
    }

    private function renderThumbnailBinary(string $sourcePath, string $mimeType): ?array
    {
        [$sourceWidth, $sourceHeight] = array_values($this->extractImageSizeFromPath($sourcePath));
        if (!$sourceWidth || !$sourceHeight) {
            return null;
        }

        $sourceImage = $this->createImageResource($sourcePath, $mimeType);
        if (!$sourceImage) {
            return null;
        }

        $scale = min(1, self::THUMBNAIL_MAX_DIMENSION / max($sourceWidth, $sourceHeight));
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        $thumbnailImage = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$thumbnailImage) {
            imagedestroy($sourceImage);
            return null;
        }

        $outputFormat = $this->determineThumbnailOutputFormat($mimeType);
        $this->prepareThumbnailCanvas($thumbnailImage, $outputFormat);

        imagecopyresampled(
            $thumbnailImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        ob_start();

        try {
            $written = match ($outputFormat) {
                'webp' => imagewebp($thumbnailImage, null, self::THUMBNAIL_JPEG_QUALITY),
                'png' => imagepng($thumbnailImage),
                default => imagejpeg($thumbnailImage, null, self::THUMBNAIL_JPEG_QUALITY),
            };

            $binary = $written ? (string) ob_get_clean() : '';
        } catch (\Throwable $e) {
            ob_end_clean();
            $binary = '';
        } finally {
            imagedestroy($sourceImage);
            imagedestroy($thumbnailImage);
        }

        if ($binary === '') {
            return null;
        }

        return [
            'binary' => $binary,
            'extension' => $outputFormat,
            'width' => $targetWidth,
            'height' => $targetHeight,
        ];
    }

    private function determineThumbnailOutputFormat(string $mimeType): string
    {
        if (function_exists('imagewebp')) {
            return 'webp';
        }

        return in_array($mimeType, ['image/png', 'image/gif', 'image/webp'], true)
            ? 'png'
            : 'jpg';
    }

    private function prepareThumbnailCanvas($image, string $outputFormat): void
    {
        if (in_array($outputFormat, ['png', 'webp'], true)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefill($image, 0, 0, $transparent);
            return;
        }

        $background = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $background);
    }

    private function createImageResource(string $sourcePath, string $mimeType): \GdImage|false
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/gif' => @imagecreatefromgif($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            'image/bmp', 'image/x-ms-bmp' => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($sourcePath) : false,
            default => false,
        };
    }

    private function extractImageSizeFromPath(string $sourcePath): array
    {
        $size = @getimagesize($sourcePath);

        return [
            $size[0] ?? null,
            $size[1] ?? null,
        ];
    }

    private function safeDiskPath(string $disk, string $path): ?string
    {
        try {
            $resolved = Storage::disk($disk)->path($path);
        } catch (\Throwable $e) {
            return null;
        }

        return is_string($resolved) ? $resolved : null;
    }

    private function generateUniquePublicKey(): string
    {
        do {
            $candidate = strtolower(Str::random(24));
        } while (MediaItem::withTrashed()->where('public_key', $candidate)->exists());

        return $candidate;
    }

    private function generateOpaqueBucket(string $logicalKey): string
    {
        return substr(hash_hmac('sha256', $logicalKey, (string) config('media.storage_hash_key')), 0, 24);
    }

    private function buildStorageFileName(string $originalName, string $storageSuffixKey): string
    {
        $baseName = trim(pathinfo($originalName, PATHINFO_FILENAME));
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeBase = $baseName !== '' ? $baseName : 'file';

        return $extension
            ? sprintf('%s--%s.%s', $safeBase, $storageSuffixKey, $extension)
            : sprintf('%s--%s', $safeBase, $storageSuffixKey);
    }

    private function sanitizeOriginalFileName(string $originalName): string
    {
        $cleaned = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]/u', '-', $originalName) ?? '';
        $cleaned = preg_replace('/\s+/u', ' ', $cleaned) ?? '';
        $cleaned = trim($cleaned, " .\t\n\r\0\x0B");

        if ($cleaned !== '') {
            return $cleaned;
        }

        return 'file';
    }

    private function generateUniqueStorageSuffixKey(string $modelClass): string
    {
        do {
            $candidate = $this->generateStorageSuffixKey();
        } while ($modelClass::withTrashed()->where('storage_suffix_key', $candidate)->exists());

        return $candidate;
    }

    private function generateStorageSuffixKey(): string
    {
        $timestampPart = $this->toBase32((int) floor(microtime(true) * 1000));
        $timestampPart = str_pad(substr($timestampPart, -6), 6, '0', STR_PAD_LEFT);

        $randomValue = random_int(0, (32 ** 6) - 1);
        $randomPart = str_pad($this->toBase32($randomValue), 6, '0', STR_PAD_LEFT);

        return strtolower($timestampPart . $randomPart);
    }

    private function toBase32(int $value): string
    {
        $alphabet = '0123456789abcdefghijklmnopqrstuv';

        if ($value <= 0) {
            return '0';
        }

        $encoded = '';

        while ($value > 0) {
            $encoded = $alphabet[$value % 32] . $encoded;
            $value = intdiv($value, 32);
        }

        return $encoded;
    }
}
