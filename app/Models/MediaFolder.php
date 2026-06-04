<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaFolder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_entity_id',
        'parent_id',
        'folder_name',
        'slug',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'owner_entity_id' => 'integer',
        'parent_id' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $folder) {
            if ($folder->isForceDeleting()) {
                return;
            }

            $suffix = '--deleted-' . $folder->id;
            $maxLength = max(1, 180 - strlen($suffix));
            $baseSlug = mb_substr($folder->slug ?: 'folder', 0, $maxLength);
            $archivedSlug = $baseSlug . $suffix;

            if ($folder->slug === $archivedSlug) {
                return;
            }

            static::withTrashed()->whereKey($folder->id)->update([
                'slug' => $archivedSlug,
            ]);

            $folder->slug = $archivedSlug;
        });
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(EntityProfile::class, 'owner_entity_id', 'entity_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('folder_name');
    }

    public function mediaItems(): HasMany
    {
        return $this->hasMany(MediaItem::class, 'folder_id');
    }
}
