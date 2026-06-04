<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MediaItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_entity_id',
        'folder_id',
        'storage_disk',
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
        if ($this->public_url && filter_var($this->public_url, FILTER_VALIDATE_URL)) {
            return $this->public_url;
        }

        return $this->public_url
            ? rtrim(config('app.url'), '/') . '/' . ltrim($this->public_url, '/')
            : rtrim(config('app.url'), '/') . Storage::disk($this->storage_disk)->url($this->storage_path);
    }

    public function getThumbnailFullUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path && !$this->thumbnail_url) {
            return null;
        }

        if ($this->thumbnail_url && filter_var($this->thumbnail_url, FILTER_VALIDATE_URL)) {
            return $this->thumbnail_url;
        }

        return $this->thumbnail_url
            ? rtrim(config('app.url'), '/') . '/' . ltrim($this->thumbnail_url, '/')
            : rtrim(config('app.url'), '/') . Storage::disk($this->storage_disk)->url($this->thumbnail_path);
    }
}
