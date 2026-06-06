<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_entity_id',
        'public_key',
        'folder_id',
        'storage_disk',
        'storage_bucket',
        'storage_suffix_key',
        'storage_path',
        'storage_context',
        'file_name',
        'original_name',
        'public_url',
        'thumbnail_path',
        'thumbnail_url',
        'media_type',
        'mime_type',
        'file_size',
        'width',
        'height',
        'thumbnail_width',
        'thumbnail_height',
        'title',
        'alt_text',
        'caption',
        'description',
        'uploaded_by_ims_user_id',
        'uploaded_by_name',
    ];

    protected $casts = [
        'owner_entity_id' => 'integer',
        'folder_id' => 'integer',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'thumbnail_width' => 'integer',
        'thumbnail_height' => 'integer',
        'uploaded_by_ims_user_id' => 'integer',
    ];

    protected $appends = [
        'full_url',
        'thumbnail_full_url',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(EntityProfile::class, 'owner_entity_id', 'entity_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    public function galleryItems(): HasMany
    {
        return $this->hasMany(GalleryItem::class, 'media_item_id');
    }

    public function getFullUrlAttribute(): string
    {
        if ($this->usesProxyUrls()) {
            return route('media.public.show', [
                'publicKey' => $this->ensurePublicKey(),
                'filename' => $this->publicFileName(),
            ]);
        }

        return $this->directFullUrl();
    }

    public function getThumbnailFullUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path && !$this->thumbnail_url) {
            return null;
        }

        if ($this->usesProxyUrls()) {
            return route('media.public.thumbnail', [
                'publicKey' => $this->ensurePublicKey(),
                'filename' => $this->publicThumbnailFileName(),
            ]);
        }

        return $this->directThumbnailUrl();
    }

    public function directFullUrl(): string
    {
        $normalizedPublicUrl = $this->normalizeStoredUrl($this->public_url);

        if ($normalizedPublicUrl && preg_match('/^https?:\/\//i', $normalizedPublicUrl)) {
            return $normalizedPublicUrl;
        }

        return $normalizedPublicUrl
            ? rtrim(config('app.url'), '/') . '/' . ltrim($normalizedPublicUrl, '/')
            : rtrim(config('app.url'), '/') . Storage::disk($this->storage_disk)->url($this->storage_path);
    }

    public function directThumbnailUrl(): ?string
    {
        if (!$this->thumbnail_path && !$this->thumbnail_url) {
            return null;
        }

        $normalizedThumbnailUrl = $this->normalizeStoredUrl($this->thumbnail_url);

        if ($normalizedThumbnailUrl && preg_match('/^https?:\/\//i', $normalizedThumbnailUrl)) {
            return $normalizedThumbnailUrl;
        }

        return $normalizedThumbnailUrl
            ? rtrim(config('app.url'), '/') . '/' . ltrim($normalizedThumbnailUrl, '/')
            : rtrim(config('app.url'), '/') . Storage::disk($this->storage_disk)->url($this->thumbnail_path);
    }

    public function publicFileName(): string
    {
        return $this->buildPublicFileName($this->original_name ?: $this->file_name ?: 'media');
    }

    public function publicThumbnailFileName(): string
    {
        return $this->buildPublicFileName($this->file_name ?: $this->original_name ?: 'thumbnail');
    }

    public function thumbnailMimeType(): ?string
    {
        if ($this->thumbnail_path) {
            $extension = strtolower(pathinfo($this->thumbnail_path, PATHINFO_EXTENSION));
            return match ($extension) {
                'webp' => 'image/webp',
                'png' => 'image/png',
                default => 'image/jpeg',
            };
        }

        return $this->mime_type;
    }

    public function ensurePublicKey(): string
    {
        if ($this->public_key) {
            return $this->public_key;
        }

        do {
            $candidate = strtolower(Str::random(24));
        } while (static::withTrashed()->where('public_key', $candidate)->exists());

        $this->forceFill(['public_key' => $candidate])->saveQuietly();

        return (string) $this->public_key;
    }

    private function usesProxyUrls(): bool
    {
        return config('media.public_url_mode', 'direct') === 'proxy';
    }

    private function buildPublicFileName(string $source): string
    {
        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        $name = pathinfo($source, PATHINFO_FILENAME);
        $slug = Str::slug($name) ?: 'media-file';

        return $extension ? "{$slug}.{$extension}" : $slug;
    }

    private function normalizeStoredUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $escapedAppUrl = preg_quote($appUrl, '/');

        $url = preg_replace('/^' . $escapedAppUrl . '\/(?=https?:?\/?\/?)/i', '', $url) ?? $url;
        $url = preg_replace('/^(https?):?(\/\/)?/i', '$1://', $url) ?? $url;
        $url = preg_replace('/^(https?):\/(?!\/)/i', '$1://', $url) ?? $url;
        $url = preg_replace('/^' . $escapedAppUrl . '(?=https?:?\/?\/?)/i', '', $url) ?? $url;

        if (preg_match('/^(https?:\/\/[^\/]+)(https?:\/\/.+)$/i', $url, $matches)) {
            $url = $matches[2];
        }

        return $url;
    }
}
