<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Services\MediaLibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PublicMediaController extends Controller
{
    public function __construct(private readonly MediaLibraryService $mediaLibraryService)
    {
    }

    public function show(Request $request, string $publicKey, ?string $filename = null)
    {
        return $this->deliverMedia($publicKey, false);
    }

    public function thumbnail(Request $request, string $publicKey, ?string $filename = null)
    {
        return $this->deliverMedia($publicKey, true);
    }

    private function deliverMedia(string $publicKey, bool $thumbnail): Response|RedirectResponse
    {
        $mediaItem = MediaItem::where('public_key', $publicKey)->firstOrFail();

        if ($thumbnail) {
            $mediaItem = $this->mediaLibraryService->ensureThumbnail($mediaItem);
        }

        [$path, $directUrl, $downloadName, $mimeType] = $thumbnail
            ? $this->thumbnailPayload($mediaItem)
            : $this->originalPayload($mediaItem);

        abort_unless($path, 404);

        $cacheMaxAge = max(60, (int) config('media.cache_max_age', 604800));
        $headers = [
            'Cache-Control' => "public, max-age={$cacheMaxAge}, immutable",
            'X-Robots-Tag' => 'noindex',
        ];

        if ($mimeType) {
            $headers['Content-Type'] = $mimeType;
        }

        if (config('media.proxy_delivery_mode', 'stream') === 'redirect' && $directUrl) {
            $response = redirect()->away($directUrl);
            $response->headers->add($headers);

            return $response;
        }

        abort_unless(Storage::disk($mediaItem->storage_disk)->exists($path), 404);

        return Storage::disk($mediaItem->storage_disk)->response(
            $path,
            $downloadName,
            $headers,
            'inline'
        );
    }

    private function originalPayload(MediaItem $mediaItem): array
    {
        return [
            $mediaItem->storage_path,
            $mediaItem->directFullUrl(),
            $mediaItem->publicFileName(),
            $mediaItem->mime_type,
        ];
    }

    private function thumbnailPayload(MediaItem $mediaItem): array
    {
        return [
            $mediaItem->thumbnail_path,
            $mediaItem->directThumbnailUrl(),
            $mediaItem->publicThumbnailFileName(),
            $mediaItem->thumbnailMimeType(),
        ];
    }
}
