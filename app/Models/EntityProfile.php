<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntityProfile extends Model
{
    protected $primaryKey = 'entity_id';
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'establishment_date',
        'slug',
        'head_personnel_id',
        'head_role_assignment_id',
        'head_role_name',
        'head_info_name',
        'head_info_designation',
        'head_info_photo_url',
        'head_message',
    ];

    protected $casts = [
        'head_role_assignment_id' => 'integer',
        'establishment_date' => 'date',
    ];

    /**
     * Get the cached entity info from IMS.
     */
    public function cachedData()
    {
        return $this->hasOne(EntityCache::class, 'entity_id');
    }

    /**
     * Web settings for this entity.
     */
    public function webSettings()
    {
        return $this->hasMany(EntityWebSetting::class, 'entity_id');
    }

    /**
     * Page categories (menus).
     */
    public function pageCategories()
    {
        return $this->hasMany(EntityPageCategory::class, 'entity_id');
    }

    /**
     * Static pages.
     */
    public function staticPages()
    {
        return $this->hasMany(EntityStaticPage::class, 'entity_id');
    }

    /**
     * Posts belonging to this entity.
     */
    public function posts()
    {
        return $this->hasMany(Post::class, 'owner_entity_id');
    }

    /**
     * Snippets for this entity.
     */
    public function snippets()
    {
        return $this->hasMany(Snippet::class, 'entity_id');
    }

    public function mediaFolders()
    {
        return $this->hasMany(MediaFolder::class, 'owner_entity_id', 'entity_id');
    }

    public function mediaItems()
    {
        return $this->hasMany(MediaItem::class, 'owner_entity_id', 'entity_id');
    }

    public function galleries()
    {
        return $this->hasMany(Gallery::class, 'owner_entity_id', 'entity_id');
    }
}
