<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostTaggedEntity extends Model
{
    protected $table = 'post_tagged_entities';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'post_id',
        'entity_id',
        'status',
        'approved_by',
        'is_featured_override',
    ];

    protected $casts = [
        'post_id' => 'integer',
        'entity_id' => 'integer',
        'is_featured_override' => 'boolean',
    ];

    protected $appends = [
        'effective_is_featured',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(EntityProfile::class, 'entity_id');
    }

    public function getEffectiveIsFeaturedAttribute(): bool
    {
        if ($this->is_featured_override !== null) {
            return (bool) $this->is_featured_override;
        }

        if ($this->relationLoaded('post') && $this->post) {
            return (bool) $this->post->is_featured;
        }

        return false;
    }
}
