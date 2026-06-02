<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EntityStaticPage extends Model
{
    use SoftDeletes;
    protected $table = 'entity_static_pages';
    public $timestamps = true;

    protected $fillable = [
        'entity_id',
        'page_slug',
        'page_title',
        'page_excerpt',
        'page_content',
        'custom_css',
        'custom_js',
        'featured_image_uri',
        'page_category',
        'page_subcategory',
        'is_menu',
        'menu_text',
        'menu_order',
        'author',
        'page_status',
        'published_at',
        'content_last_edited_at',
        'view_count',
    ];

    protected $casts = [
        'page_category' => 'integer',
        'page_subcategory' => 'integer',
        'is_menu' => 'boolean',
        'menu_order' => 'integer',
        'published_at' => 'datetime',
        'content_last_edited_at' => 'datetime',
        'view_count' => 'integer',
        'page_status' => 'string', 
    ];

    public function entity()
    {
        return $this->belongsTo(EntityProfile::class, 'entity_id');
    }

    public function category()
    {
        return $this->belongsTo(EntityPageCategory::class, 'page_category');
    }

    public function subcategory()
    {
        return $this->belongsTo(EntityPageSubcategory::class, 'page_subcategory');
    }
}
