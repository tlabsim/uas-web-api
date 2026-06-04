<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gallery extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_entity_id',
        'title',
        'slug',
        'excerpt',
        'description',
        'cover_media_item_id',
        'gallery_status',
        'is_featured',
        'author',
        'published_at',
        'content_last_edited_at',
    ];

    protected $casts = [
        'owner_entity_id' => 'integer',
        'cover_media_item_id' => 'integer',
        'published_at' => 'datetime',
        'content_last_edited_at' => 'datetime',
        'is_featured' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $gallery) {
            if ($gallery->isForceDeleting()) {
                return;
            }

            $suffix = '--deleted-' . $gallery->id;
            $maxLength = max(1, 180 - strlen($suffix));
            $baseSlug = mb_substr($gallery->slug ?: 'gallery', 0, $maxLength);
            $archivedSlug = $baseSlug . $suffix;

            if ($gallery->slug === $archivedSlug) {
                return;
            }

            static::withTrashed()->whereKey($gallery->id)->update([
                'slug' => $archivedSlug,
            ]);

            $gallery->slug = $archivedSlug;
        });
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(EntityProfile::class, 'owner_entity_id', 'entity_id');
    }

    public function coverMediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'cover_media_item_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class, 'gallery_id')->orderBy('sort_order')->orderBy('id');
    }

    public function scopePublished($query)
    {
        return $query->where('gallery_status', 'Published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
