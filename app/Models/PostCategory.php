<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'is_system',
        'meta_schema',
        'attachment_config',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'meta_schema' => 'array',
        'attachment_config' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get all posts in this category
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'category_id');
    }

    /**
     * Scope: Get only active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Get only system categories
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope: Get only custom (non-system) categories
     */
    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Scope: Order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get required metadata fields for this category
     */
    public function getRequiredFieldsAttribute(): array
    {
        return $this->meta_schema['required_fields'] ?? [];
    }

    /**
     * Get extra (optional) metadata fields for this category
     */
    public function getExtraFieldsAttribute(): array
    {
        return $this->meta_schema['extra_fields'] ?? [];
    }

    /**
     * Get all metadata fields (required + extra)
     */
    public function getAllFieldsAttribute(): array
    {
        return array_merge($this->required_fields, $this->extra_fields);
    }

    /**
     * Check if attachments are required for this category
     */
    public function getAttachmentsRequiredAttribute(): bool
    {
        return $this->attachment_config['required'] ?? false;
    }

    /**
     * Get maximum allowed files
     */
    public function getMaxFilesAttribute(): int
    {
        return $this->attachment_config['max_files'] ?? 5;
    }

    /**
     * Get allowed attachment types
     */
    public function getAllowedTypesAttribute(): array
    {
        return $this->attachment_config['allowed_types'] ?? ['document', 'image'];
    }

    /**
     * Validate if a field key exists in the schema
     */
    public function hasField(string $key): bool
    {
        $allFields = $this->all_fields;
        return collect($allFields)->contains('key', $key);
    }

    /**
     * Get field configuration by key
     */
    public function getField(string $key): ?array
    {
        $allFields = $this->all_fields;
        return collect($allFields)->firstWhere('key', $key);
    }
}
