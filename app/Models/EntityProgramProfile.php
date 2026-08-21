<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityProgramProfile extends Model
{
    protected $fillable = [
        'entity_id',
        'ims_program_id',
        'slug',
        'display_title',
        'subtitle',
        'summary',
        'hero_media_item_id',
        'overview',
        'learning_outcomes',
        'admission_requirements',
        'curriculum',
        'career_opportunities',
        'fees_and_funding',
        'accreditation',
        'application_label',
        'application_url',
        'brochure_url',
        'contact_name',
        'contact_email',
        'contact_phone',
        'custom_sections',
        'status',
        'is_visible',
        'is_featured',
        'sort_order',
        'seo_title',
        'seo_description',
        'published_at',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'ims_program_id' => 'integer',
        'hero_media_item_id' => 'integer',
        'custom_sections' => 'array',
        'is_visible' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'published_at' => 'datetime',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(EntityProfile::class, 'entity_id', 'entity_id');
    }

    public function heroMediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'hero_media_item_id');
    }
}
