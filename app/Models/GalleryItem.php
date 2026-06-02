<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryItem extends Model
{
    protected $fillable = [
        'gallery_id',
        'media_item_id',
        'caption_override',
        'alt_override',
        'sort_order',
    ];

    protected $casts = [
        'gallery_id' => 'integer',
        'media_item_id' => 'integer',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'effective_caption',
        'effective_alt_text',
    ];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class, 'gallery_id');
    }

    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'media_item_id');
    }

    public function getEffectiveCaptionAttribute(): ?string
    {
        return $this->caption_override ?: $this->mediaItem?->caption;
    }

    public function getEffectiveAltTextAttribute(): ?string
    {
        return $this->alt_override ?: $this->mediaItem?->alt_text;
    }
}
