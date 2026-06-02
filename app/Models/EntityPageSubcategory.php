<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntityPageSubcategory extends Model
{
    protected $table = 'entity_page_subcategories';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'category_id',
        'subcategory_name',
        'subcategory_slug',
        'is_menu',
        'menu_text',
        'menu_order',
        'link_url',
    ];

    protected $casts = [
        'is_menu' => 'boolean',
        'menu_order' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(EntityPageCategory::class, 'category_id');
    }
}
