<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntityPageCategory extends Model
{
    protected $table = 'entity_page_categories';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'entity_id',
        'category_name',
        'category_slug',
        'is_menu',
        'menu_text',
        'menu_order',
        'link_url',
    ];

    protected $casts = [
        'is_menu' => 'boolean',
        'menu_order' => 'integer',
    ];

    public function entity()
    {
        return $this->belongsTo(EntityProfile::class, 'entity_id');
    }

    public function subcategories()
    {
        return $this->hasMany(EntityPageSubcategory::class, 'category_id');
    }
}
