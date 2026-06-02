<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use SoftDeletes;

    protected $table = 'posts';

    public $timestamps = true;    

    protected $fillable = [
        'post_title',
        'post_excerpt',
        'category_id',
        'category',
        'post_content',
        'featured_image_uri',
        'is_featured',
        'owner_entity_id',
        'author',
        'tags',
        'post_status',
        'published_at',
        'content_last_edited_at',
        'view_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'content_last_edited_at' => 'datetime',
        'view_count' => 'integer',
        'is_featured' => 'boolean',
        'category_id' => 'integer',
        'post_status' => 'string',
        'category' => 'string',
        'tags' => 'array',
    ];

    /**
     * Relationships
     */
    
    public function postCategory(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'category_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(EntityProfile::class, 'owner_entity_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PostAttachment::class, 'post_id');
    }

    public function metadata(): HasMany
    {
        return $this->hasMany(PostMeta::class, 'post_id');
    }

    public function taggedEntities(): HasMany
    {
        return $this->hasMany(PostTaggedEntity::class, 'post_id');
    }

    /**
     * Query Scopes
     */
    
    public function scopePublished($query)
    {
        return $query->where('post_status', 'Published')
                     ->whereNotNull('published_at')
                     ->where('published_at', '<=', now());
    }

    public function scopeDraft($query)
    {
        return $query->where('post_status', 'Draft');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByCategorySlug($query, string $slug)
    {
        return $query->whereHas('postCategory', function($q) use ($slug) {
            $q->where('slug', $slug);
        });
    }

    /**
     * Scope: Filter posts by metadata key-value
     * Usage: Post::withMeta('event_start_date', '>=', '2025-01-01')
     */
    public function scopeWithMeta($query, string $key, $operator = '=', $value = null)
    {
        return $query->whereHas('metadata', function($q) use ($key, $operator, $value) {
            $q->where('meta_key', $key);
            
            if ($value !== null) {
                $q->where('meta_value', $operator, $value);
            }
        });
    }

    /**
     * Scope: Search in metadata values
     */
    public function scopeSearchMeta($query, string $searchTerm)
    {
        return $query->whereHas('metadata', function($q) use ($searchTerm) {
            $q->where('is_searchable', true)
              ->where('meta_value', 'LIKE', "%{$searchTerm}%");
        });
    }

    /**
     * Scope: Filter by date range
     */
    public function scopePublishedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('published_at', [$startDate, $endDate]);
    }

    /**
     * Helper Methods
     */
    
    /**
     * Get a specific metadata value
     */
    public function getMeta(string $key, $default = null)
    {
        $meta = $this->metadata()->where('meta_key', $key)->first();
        return $meta ? $meta->meta_value : $default;
    }

    /**
     * Set metadata value (create or update)
     */
    public function setMeta(string $key, $value, string $valueType = 'string', bool $isSearchable = true): PostMeta
    {
        return $this->metadata()->updateOrCreate(
            ['meta_key' => $key],
            [
                'meta_value' => $value,
                'value_type' => $valueType,
                'is_searchable' => $isSearchable,
            ]
        );
    }

    /**
     * Get all metadata as key-value array
     */
    public function getAllMeta(): array
    {
        return $this->metadata()
                    ->pluck('meta_value', 'meta_key')
                    ->toArray();
    }

    /**
     * Check if post has a specific metadata key
     */
    public function hasMeta(string $key): bool
    {
        return $this->metadata()->where('meta_key', $key)->exists();
    }

    /**
     * Increment view count
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * Check if post is published
     */
    public function isPublished(): bool
    {
        return $this->post_status === 'Published' 
               && $this->published_at 
               && $this->published_at <= now();
    }

    /**
     * Publish the post
     */
    public function publish(): bool
    {
        return $this->update([
            'post_status' => 'Published',
            'published_at' => $this->published_at ?? now(),
        ]);
    }

    /**
     * Make post featured
     */
    public function feature(): bool
    {
        return $this->update(['is_featured' => true]);
    }

    /**
     * Remove from featured
     */
    public function unfeature(): bool
    {
        return $this->update(['is_featured' => false]);
    }
}
